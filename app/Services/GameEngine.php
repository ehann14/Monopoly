<?php

namespace App\Services;

use App\Exceptions\GameRuleException;
use App\Models\GameActionRequest;
use App\Models\GamePlayer;
use App\Models\GameRoom;
use App\Models\PropertyOwnership;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Orkestrator aturan. Semua aksi pemain lewat perform(): satu transaksi DB,
 * room dikunci, idempotensi, dan pengecekan versi.
 */
class GameEngine
{
    public function __construct(
        private DiceService $dice,
        private MovementService $movement,
        private PropertyService $properties,
        private RentService $rent,
        private TransactionService $tx,
        private TurnManager $turns,
        private BankruptcyService $bankruptcy,
        private BoardService $board,
        private GameStateService $state,
    ) {
    }

    /** Game lokal (hot-seat): 2-4 pemain di satu perangkat, dikendalikan host. */
    public function createLocalGame(User $host, array $names): GameRoom
    {
        $names = array_values($names);
        $max = (int) config('monopoly.rules.max_players');
        if (count($names) < 2 || count($names) > $max) {
            throw new GameRuleException("Jumlah pemain harus 2 sampai $max.");
        }

        return DB::transaction(function () use ($host, $names) {
            $rules = config('monopoly.rules');
            $tokens = config('monopoly.tokens');

            $room = new GameRoom();
            $room->forceFill([
                'code' => GameRoom::generateCode(),
                'host_user_id' => $host->id,
                'mode' => 'local',
                'status' => 'playing',
                'rules' => $rules,
                'turn_phase' => 'await_roll',
                'turn_number' => 0,
                'state_version' => 1,
                'started_at' => now(),
            ]);
            $room->save();

            $created = [];
            foreach ($names as $seat => $name) {
                $created[] = GamePlayer::create([
                    'game_room_id' => $room->id,
                    'user_id' => $seat === 0 ? $host->id : null,
                    'name' => $name,
                    'is_ai' => false,
                    'seat' => $seat,
                    'token' => $tokens[$seat],
                    'is_ready' => true,
                    'position' => 0,
                    'money' => (int) $rules['starting_money'],
                ]);
            }

            foreach ($this->board->tiles() as $tile) {
                if ($this->board->isBuyable($tile)) {
                    PropertyOwnership::create(['game_room_id' => $room->id, 'tile_id' => $tile['id']]);
                }
            }

            $this->turns->begin($room, $created[0]);
            $room->save();
            $this->state->log($room, 'system', 'Permainan dimulai. Giliran pertama: ' . $created[0]->name . '.');

            return $room;
        });
    }

    public function perform(
        User $user,
        GameRoom $room,
        string $action,
        ?string $idempotencyKey = null,
        ?int $expectedVersion = null,
    ): void {
        DB::transaction(function () use ($user, $room, $action, $idempotencyKey, $expectedVersion) {
            $room = GameRoom::whereKey($room->id)->lockForUpdate()->firstOrFail();

            if ($idempotencyKey !== null) {
                $seen = GameActionRequest::where('game_room_id', $room->id)
                    ->where('user_id', $user->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->exists();
                if ($seen) {
                    return; // permintaan ulang: sudah diproses, tidak diproses dua kali
                }
            }

            if ($expectedVersion !== null && $expectedVersion !== (int) $room->state_version) {
                throw new GameRuleException('Status permainan sudah berubah. Tampilan dimuat ulang.');
            }
            if ($room->status !== 'playing') {
                throw new GameRuleException('Permainan tidak sedang berlangsung.');
            }

            $actor = GamePlayer::where('game_room_id', $room->id)
                ->whereKey($room->current_player_id)->lockForUpdate()->first();
            if ($actor === null || $actor->is_bankrupt || ! $room->userControls($user, $actor)) {
                throw new GameRuleException('Bukan giliran Anda.');
            }

            match ($action) {
                'roll' => $this->roll($room, $actor),
                'buy' => $this->buy($room, $actor),
                'decline' => $this->decline($room, $actor),
                'end_turn' => $this->endTurn($room, $actor),
                default => throw new GameRuleException('Aksi tidak dikenal.'),
            };

            $room->state_version = $room->state_version + 1;
            $room->save();

            if ($idempotencyKey !== null) {
                GameActionRequest::create([
                    'game_room_id' => $room->id,
                    'user_id' => $user->id,
                    'idempotency_key' => $idempotencyKey,
                    'action' => $action,
                ]);
            }
        }, 3);
    }

    private function requirePhase(GameRoom $room, string $phase): void
    {
        if ($room->turn_phase !== $phase) {
            throw new GameRuleException('Aksi ini tidak tersedia pada tahap giliran saat ini.');
        }
    }

    private function roll(GameRoom $room, GamePlayer $actor): void
    {
        $this->requirePhase($room, 'await_roll');
        $rules = $room->rules;

        $dice = $this->dice->roll(null);
        $room->last_dice = $dice;
        if ($dice['double']) {
            $room->doubles_count = $room->doubles_count + 1;
        }

        $this->state->log($room, 'dice',
            "{$actor->name} melempar dadu: {$dice['d1']} + {$dice['d2']} = {$dice['total']}"
            . ($dice['double'] ? ' (kembar)' : ''), $dice);

        if ($dice['double'] && $room->doubles_count >= (int) $rules['max_doubles']) {
            $this->state->log($room, 'rule',
                "{$actor->name} mendapat dadu kembar {$rules['max_doubles']} kali berturut-turut. "
                . 'Efek penjara belum aktif (Tahap 3), giliran berakhir tanpa bergerak.');
            $room->turn_phase = 'await_end';
            return;
        }

        $move = $this->movement->advance($rules, $actor, $dice['total']);
        $tile = $this->board->tile($move['to']);
        $this->state->log($room, 'move', "{$actor->name} bergerak ke {$tile['name']}.");
        if ($move['passed_start']) {
            $this->state->log($room, 'salary', "{$actor->name} melewati START dan menerima {$rules['salary']}.");
        }

        $this->resolveLanding($room, $actor, $move['to'], $dice['total']);
    }

    private function resolveLanding(GameRoom $room, GamePlayer $actor, int $tileId, int $diceTotal): void
    {
        $tile = $this->board->tile($tileId);

        switch ($tile['type']) {
            case 'property':
            case 'station':
            case 'utility':
                $own = $this->properties->ownership($room, $tileId);

                if ($own->owner_player_id === null) {
                    $room->turn_phase = 'await_buy';
                    $room->pending = ['type' => 'buy', 'tile_id' => $tileId, 'price' => (int) $tile['price']];
                    $this->state->log($room, 'offer', "{$tile['name']} belum dimiliki. Harga {$tile['price']}.");
                    return;
                }

                if ($own->owner_player_id === $actor->id) {
                    $this->state->log($room, 'info', "{$actor->name} singgah di properti miliknya sendiri.");
                    break;
                }

                $owner = GamePlayer::findOrFail($own->owner_player_id);
                $rent = $this->rent->rentFor(
                    $room->rules,
                    $tile,
                    $own->only(['is_mortgaged', 'has_hotel', 'houses']),
                    $this->properties->ownedTileIds($room, $owner->id),
                    $diceTotal
                );

                if ($rent <= 0) {
                    $this->state->log($room, 'info', "{$tile['name']} sedang digadaikan, tidak ada sewa.");
                    break;
                }
                if ($this->tx->canAfford($actor, $rent)) {
                    $this->tx->transfer($actor, $owner, $rent);
                    $this->state->log($room, 'rent', "{$actor->name} membayar sewa {$rent} kepada {$owner->name}.");
                    break;
                }

                $this->state->log($room, 'rent', "{$actor->name} tidak mampu membayar sewa {$rent} kepada {$owner->name}.");
                $this->goBankrupt($room, $actor, $owner);
                return;

            case 'tax':
                $amount = (int) $tile['amount'];
                if ($this->tx->canAfford($actor, $amount)) {
                    $this->tx->debit($actor, $amount);
                    $this->state->log($room, 'tax', "{$actor->name} membayar {$tile['name']} sebesar {$amount}.");
                    break;
                }
                $this->state->log($room, 'tax', "{$actor->name} tidak mampu membayar {$tile['name']} ({$amount}).");
                $this->goBankrupt($room, $actor, null);
                return;

            case 'chance':
            case 'chest':
                $this->state->log($room, 'info', "{$actor->name} singgah di {$tile['name']}. Kartu belum aktif (Tahap 3).");
                break;

            case 'go_to_jail':
                $this->state->log($room, 'info', "{$actor->name} singgah di {$tile['name']}. Efek penjara belum aktif (Tahap 3).");
                break;

            default: // start, jail (hanya berkunjung), free_parking
                $this->state->log($room, 'info', "{$actor->name} singgah di {$tile['name']}.");
        }

        $this->finishAction($room);
    }

    /** Setelah aksi selesai: dadu kembar = lempar lagi, selain itu boleh mengakhiri giliran. */
    private function finishAction(GameRoom $room): void
    {
        $room->pending = null;
        $room->turn_phase = ! empty($room->last_dice['double']) ? 'await_roll' : 'await_end';
    }

    private function buy(GameRoom $room, GamePlayer $actor): void
    {
        $this->requirePhase($room, 'await_buy');
        $tileId = (int) ($room->pending['tile_id'] ?? -1);

        $this->properties->buy($room, $actor, $tileId); // GameRuleException bila uang kurang -> semua dibatalkan
        $tile = $this->board->tile($tileId);
        $this->state->log($room, 'buy', "{$actor->name} membeli {$tile['name']} seharga {$tile['price']}.");
        $this->finishAction($room);
    }

    private function decline(GameRoom $room, GamePlayer $actor): void
    {
        $this->requirePhase($room, 'await_buy');
        $tile = $this->board->tile((int) ($room->pending['tile_id'] ?? -1));
        $this->state->log($room, 'decline', "{$actor->name} tidak membeli {$tile['name']}. (Lelang belum tersedia.)");
        $this->finishAction($room);
    }

    private function endTurn(GameRoom $room, GamePlayer $actor): void
    {
        $this->requirePhase($room, 'await_end');
        $this->advanceTurn($room, $actor);
    }

    private function advanceTurn(GameRoom $room, GamePlayer $from): void
    {
        $next = $this->turns->next($room, $from);
        if ($next === null) {
            throw new GameRuleException('Tidak ada pemain aktif berikutnya.');
        }
        $this->turns->begin($room, $next);
        $this->state->log($room, 'turn', "Giliran {$next->name}.");
    }

    private function goBankrupt(GameRoom $room, GamePlayer $actor, ?GamePlayer $creditor): void
    {
        $finished = $this->bankruptcy->declare($room, $actor, $creditor);
        $this->state->log($room, 'bankrupt', "{$actor->name} bangkrut.");

        if ($finished) {
            $winner = GamePlayer::find($room->winner_player_id);
            $this->state->log($room, 'end', 'Permainan selesai. Pemenang: ' . ($winner?->name ?? '-') . '.');
            return;
        }
        $this->advanceTurn($room, $actor);
    }
}