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
 * Orkestrator aturan. Semua aksi pemain (dan AI) lewat applyAction(): satu transaksi DB,
 * room dikunci, idempotensi, dan pengecekan versi.
 *
 * Fase giliran: await_roll | await_buy | await_debt | await_end
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
        private BuildingService $buildings,
        private MortgageService $mortgage,
        private JailService $jail,
        private CardService $cards,
        private AIPlayerService $ai,
    ) {
    }

    // ------------------------------------------------------------------ membuat game

    /** Game lokal (hot-seat): 2-4 pemain di satu perangkat, dikendalikan host. */
    public function createLocalGame(User $host, array $names): GameRoom
    {
        $names = array_values($names);
        $max = (int) config('monopoly.rules.max_players');
        if (count($names) < 2 || count($names) > $max) {
            throw new GameRuleException("Jumlah pemain harus 2 sampai $max.");
        }

        $seats = [];
        foreach ($names as $seat => $name) {
            $seats[] = ['name' => $name, 'is_ai' => false, 'user_id' => $seat === 0 ? $host->id : null];
        }

        return $this->createGame($host, 'local', $seats, null);
    }

    /** Game melawan komputer: 1 pemain manusia (seat 0) + 1-3 AI. */
    public function createAiGame(User $host, array $aiNames, string $difficulty): GameRoom
    {
        $aiNames = array_values($aiNames);
        $max = (int) config('monopoly.rules.max_players');
        if (count($aiNames) < 1 || count($aiNames) > $max - 1) {
            throw new GameRuleException('Jumlah lawan komputer harus 1 sampai ' . ($max - 1) . '.');
        }
        if (! in_array($difficulty, ['easy', 'hard'], true)) {
            throw new GameRuleException('Tingkat kesulitan tidak valid.');
        }

        $seats = [['name' => mb_substr($host->name, 0, 40), 'is_ai' => false, 'user_id' => $host->id]];
        foreach ($aiNames as $name) {
            $seats[] = ['name' => mb_substr($name, 0, 40), 'is_ai' => true, 'user_id' => null];
        }

        return $this->createGame($host, 'ai', $seats, $difficulty);
    }

    private function createGame(User $host, string $mode, array $seats, ?string $difficulty): GameRoom
    {
        return DB::transaction(function () use ($host, $mode, $seats, $difficulty) {
            $rules = config('monopoly.rules');
            $tokens = config('monopoly.tokens');

            $room = new GameRoom();
            $room->forceFill([
                'code' => GameRoom::generateCode(),
                'host_user_id' => $host->id,
                'mode' => $mode,
                'status' => 'playing',
                'ai_difficulty' => $difficulty,
                'rules' => $rules,
                'turn_phase' => 'await_roll',
                'turn_number' => 0,
                'state_version' => 1,
                'started_at' => now(),
            ]);
            $room->save();

            $created = [];
            foreach ($seats as $seat => $s) {
                $created[] = GamePlayer::create([
                    'game_room_id' => $room->id,
                    'user_id' => $s['user_id'],
                    'name' => $s['name'],
                    'is_ai' => $s['is_ai'],
                    'ai_difficulty' => $s['is_ai'] ? $difficulty : null,
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

            $this->cards->ensureDecks($room);

            $this->turns->begin($room, $created[0]);
            $room->save();
            $this->state->log($room, 'system', 'Permainan dimulai. Giliran pertama: ' . $created[0]->name . '.');

            return $room;
        });
    }

    // ------------------------------------------------------------------ pintu masuk aksi

    public function perform(
        User $user,
        GameRoom $room,
        string $action,
        ?string $idempotencyKey = null,
        ?int $expectedVersion = null,
        array $params = [],
    ): void {
        DB::transaction(function () use ($user, $room, $action, $idempotencyKey, $expectedVersion, $params) {
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

            $this->applyAction($room, $actor, $action, $params);

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

    /**
     * Menjalankan SATU aksi pemain AI yang sedang giliran. Keputusan datang dari AIPlayerService,
     * eksekusinya lewat applyAction() yang sama dengan pemain manusia (aturan yang sama).
     * Bila keputusan AI ditolak aturan, perubahannya dibatalkan dan aksi aman dijalankan.
     */
    public function aiStep(GameRoom $room, ?int $expectedVersion = null): void
    {
        DB::transaction(function () use ($room, $expectedVersion) {
            $room = GameRoom::whereKey($room->id)->lockForUpdate()->firstOrFail();

            if ($expectedVersion !== null && $expectedVersion !== (int) $room->state_version) {
                throw new GameRuleException('Status permainan sudah berubah. Tampilan dimuat ulang.');
            }
            if ($room->status !== 'playing') {
                throw new GameRuleException('Permainan tidak sedang berlangsung.');
            }

            $actor = GamePlayer::where('game_room_id', $room->id)
                ->whereKey($room->current_player_id)->lockForUpdate()->first();
            if ($actor === null || ! $actor->is_ai || $actor->is_bankrupt) {
                throw new GameRuleException('Bukan giliran komputer.');
            }

            $choice = $this->ai->decide($room, $actor);

            try {
                DB::transaction(fn () => $this->applyAction($room, $actor, $choice['action'], $choice['params']));
            } catch (GameRuleException $e) {
                $room->refresh();
                $actor->refresh();
                $this->applyAction($room, $actor, $this->ai->fallback($room, $actor), []);
            }

            $room->state_version = $room->state_version + 1;
            $room->save();
        }, 3);
    }

    private function applyAction(GameRoom $room, GamePlayer $actor, string $action, array $params): void
    {
        match ($action) {
            'roll' => $this->roll($room, $actor),
            'buy' => $this->buy($room, $actor),
            'decline' => $this->decline($room, $actor),
            'end_turn' => $this->endTurn($room, $actor),
            'build' => $this->build($room, $actor, $this->tileParam($params)),
            'sell_building' => $this->sellBuilding($room, $actor, $this->tileParam($params)),
            'mortgage' => $this->mortgageTile($room, $actor, $this->tileParam($params)),
            'unmortgage' => $this->unmortgageTile($room, $actor, $this->tileParam($params)),
            'pay_jail' => $this->payJail($room, $actor),
            'use_jail_card' => $this->useJailCard($room, $actor),
            'pay_debt' => $this->payDebt($room, $actor),
            'declare_bankruptcy' => $this->declareBankruptcy($room, $actor),
            default => throw new GameRuleException('Aksi tidak dikenal.'),
        };
    }

    // ------------------------------------------------------------------ util

    private function requirePhase(GameRoom $room, string $phase): void
    {
        if ($room->turn_phase !== $phase) {
            throw new GameRuleException('Aksi ini tidak tersedia pada tahap giliran saat ini.');
        }
    }

    private function requirePhaseIn(GameRoom $room, array $phases): void
    {
        if (! in_array($room->turn_phase, $phases, true)) {
            throw new GameRuleException('Aksi ini tidak tersedia pada tahap giliran saat ini.');
        }
    }

    private function tileParam(array $params): int
    {
        $id = $params['tile_id'] ?? null;
        if (! is_int($id) || $id < 0 || $id >= $this->board->size()) {
            throw new GameRuleException('Petak tidak valid.');
        }

        return $id;
    }

    /** Setelah aksi selesai: dadu kembar (belum di penjara) = lempar lagi, selain itu boleh mengakhiri giliran. */
    private function finishAction(GameRoom $room, GamePlayer $actor): void
    {
        $room->pending = null;
        $extra = ! empty($room->last_dice['double'])
            && (int) $room->doubles_count > 0
            && ! $actor->in_jail
            && ! $actor->is_bankrupt;
        $room->turn_phase = $extra ? 'await_roll' : 'await_end';
    }

    // ------------------------------------------------------------------ dadu & gerak

    private function roll(GameRoom $room, GamePlayer $actor): void
    {
        $this->requirePhase($room, 'await_roll');

        if ($actor->in_jail) {
            $this->rollInJail($room, $actor);
            return;
        }

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
                "{$actor->name} mendapat dadu kembar {$rules['max_doubles']} kali berturut-turut.");
            $this->sendToJail($room, $actor);
            return;
        }

        $this->moveAndResolve($room, $actor, $dice['total']);
    }

    private function rollInJail(GameRoom $room, GamePlayer $actor): void
    {
        $dice = $this->dice->roll(null);
        $room->last_dice = $dice;
        $this->state->log($room, 'dice',
            "{$actor->name} melempar dadu di penjara: {$dice['d1']} + {$dice['d2']} = {$dice['total']}"
            . ($dice['double'] ? ' (kembar)' : ''), $dice);

        if ($dice['double']) {
            $this->jail->release($actor);
            $room->doubles_count = 0; // keluar lewat kembar tidak memberi giliran tambahan
            $this->state->log($room, 'jail', "{$actor->name} bebas dari penjara.");
            $this->moveAndResolve($room, $actor, $dice['total']);
            return;
        }

        if (! $this->jail->failedAttempt($room, $actor)) {
            $this->state->log($room, 'jail',
                "{$actor->name} gagal keluar penjara (percobaan {$actor->jail_turns}).");
            $room->pending = null;
            $room->turn_phase = 'await_end';
            return;
        }

        $fine = $this->jail->fine($room);
        $this->state->log($room, 'jail', "{$actor->name} gagal 3 kali dan wajib membayar denda penjara {$fine}.");
        $continuation = ['kind' => 'jail_move', 'steps' => $dice['total']];
        if ($this->charge($room, $actor, $fine, null, 'denda penjara',
            "{$actor->name} membayar denda penjara {$fine}.", $continuation)) {
            $this->resume($room, $actor, $continuation);
        }
    }

    private function moveAndResolve(GameRoom $room, GamePlayer $actor, int $steps): void
    {
        $rules = $room->rules;
        $move = $this->movement->advance($rules, $actor, $steps);
        $tile = $this->board->tile($move['to']);
        $this->state->log($room, 'move', "{$actor->name} bergerak ke {$tile['name']}.");
        if ($move['passed_start']) {
            $this->state->log($room, 'salary', "{$actor->name} melewati START dan menerima {$rules['salary']}.");
        }

        $this->resolveLanding($room, $actor, $move['to'], $steps);
    }

    private function sendToJail(GameRoom $room, GamePlayer $actor): void
    {
        $this->jail->send($room, $actor);
        $this->state->log($room, 'jail', "{$actor->name} masuk penjara.");
        $room->pending = null;
        $room->turn_phase = 'await_end';
    }

    // ------------------------------------------------------------------ petak

    /** @param array $opts rent_mult (int) | utility_x10 (bool) untuk efek kartu */
    private function resolveLanding(GameRoom $room, GamePlayer $actor, int $tileId, int $diceTotal, array $opts = []): void
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

                if ((int) $own->owner_player_id === (int) $actor->id) {
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
                if (! empty($opts['rent_mult'])) {
                    $rent *= (int) $opts['rent_mult'];
                }
                if (! empty($opts['utility_x10']) && $tile['type'] === 'utility') {
                    $rent = $diceTotal * 10;
                }

                if (! $this->charge($room, $actor, $rent, $owner, "sewa {$tile['name']}",
                    "{$actor->name} membayar sewa {$rent} kepada {$owner->name}.")) {
                    return;
                }
                break;

            case 'tax':
                $amount = (int) $tile['amount'];
                if (! $this->charge($room, $actor, $amount, null, $tile['name'],
                    "{$actor->name} membayar {$tile['name']} sebesar {$amount}.")) {
                    return;
                }
                break;

            case 'chance':
                $this->drawCard($room, $actor, 'chance', $diceTotal);
                return;

            case 'chest':
                $this->drawCard($room, $actor, 'chest', $diceTotal);
                return;

            case 'go_to_jail':
                $this->state->log($room, 'info', "{$actor->name} singgah di {$tile['name']}.");
                $this->sendToJail($room, $actor);
                return;

            default: // start, jail (hanya berkunjung), free_parking
                $this->state->log($room, 'info', "{$actor->name} singgah di {$tile['name']}.");
        }

        $this->finishAction($room, $actor);
    }

    // ------------------------------------------------------------------ kartu

    private function drawCard(GameRoom $room, GamePlayer $actor, string $deck, int $diceTotal): void
    {
        $card = $this->cards->draw($room, $deck);
        $deckName = $deck === 'chance' ? 'Kesempatan' : 'Dana Umum';
        $this->state->log($room, 'card',
            "{$actor->name} mengambil kartu {$deckName}: {$card['title']}. {$card['text']}",
            ['deck' => $deck, 'title' => $card['title'], 'text' => $card['text'], 'player' => $actor->name]);

        if ($this->applyCard($room, $actor, $deck, $card, $diceTotal)) {
            $this->finishAction($room, $actor);
        }
    }

    /** @return bool true bila giliran boleh diselesaikan; false bila state sudah ditangani (pindah/penjara/utang) */
    private function applyCard(GameRoom $room, GamePlayer $actor, string $deck, array $card, int $diceTotal): bool
    {
        $e = $card['effect'];

        switch ($e['type']) {
            case 'money':
                $amount = (int) $e['amount'];
                if ($amount >= 0) {
                    $this->tx->credit($actor, $amount);
                    $this->state->log($room, 'card_effect', "{$actor->name} menerima {$amount}.");
                    return true;
                }
                $pay = -$amount;
                return $this->charge($room, $actor, $pay, null, $card['title'],
                    "{$actor->name} membayar {$pay} ({$card['title']}).");

            case 'move_to':
                $to = (int) $e['tile'];
                $move = $this->movement->moveTo($room->rules, $actor, $to);
                $this->logCardMove($room, $actor, $to, $move['passed_start']);
                $this->resolveLanding($room, $actor, $to, $diceTotal);
                return false;

            case 'move_back':
                $size = $this->board->size();
                $to = (((int) $actor->position - (int) $e['steps']) % $size + $size) % $size;
                $this->movement->jumpTo($actor, $to);
                $this->logCardMove($room, $actor, $to, false);
                $this->resolveLanding($room, $actor, $to, $diceTotal);
                return false;

            case 'nearest':
                $to = $this->cards->nearest($e['kind'], (int) $actor->position);
                $move = $this->movement->moveTo($room->rules, $actor, $to);
                $this->logCardMove($room, $actor, $to, $move['passed_start']);
                $opts = $e['kind'] === 'station' ? ['rent_mult' => 2] : ['utility_x10' => true];
                $this->resolveLanding($room, $actor, $to, $diceTotal, $opts);
                return false;

            case 'go_to_jail':
                $this->sendToJail($room, $actor);
                return false;

            case 'jail_card':
                $actor->jail_cards = (int) $actor->jail_cards + 1;
                $actor->save();
                $this->cards->hold($room, $deck, $card['id'], $actor);
                $this->state->log($room, 'card_effect', "{$actor->name} menyimpan kartu bebas penjara.");
                return true;

            case 'pay_each':
                $others = $this->otherActivePlayers($room, $actor);
                if ($others->isEmpty()) {
                    return true;
                }
                $per = (int) $e['amount'];
                $total = $per * $others->count();
                return $this->charge($room, $actor, $total, null, $card['title'],
                    "{$actor->name} membayar {$per} kepada setiap pemain lain.",
                    ['kind' => 'finish'], $others->pluck('id')->map(fn ($id) => (int) $id)->all(), $per);

            case 'collect_each':
                $per = (int) $e['amount'];
                $total = 0;
                foreach ($this->otherActivePlayers($room, $actor) as $other) {
                    $pay = min((int) $other->money, $per);
                    if ($pay > 0) {
                        $this->tx->transfer($other, $actor, $pay);
                        $total += $pay;
                    }
                }
                $this->state->log($room, 'card_effect', "{$actor->name} menerima total {$total} dari pemain lain.");
                return true;

            case 'repairs':
                $rows = PropertyOwnership::where('game_room_id', $room->id)
                    ->where('owner_player_id', $actor->id)->get();
                $houses = (int) $rows->sum('houses');
                $hotels = $rows->where('has_hotel', true)->count();
                $cost = $houses * (int) $e['house'] + $hotels * (int) $e['hotel'];
                if ($cost <= 0) {
                    $this->state->log($room, 'card_effect', "{$actor->name} tidak punya bangunan, tidak ada biaya.");
                    return true;
                }
                return $this->charge($room, $actor, $cost, null, $card['title'],
                    "{$actor->name} membayar biaya perbaikan {$cost} ({$houses} rumah, {$hotels} hotel).");
        }

        throw new GameRuleException('Efek kartu tidak dikenal.');
    }

    private function logCardMove(GameRoom $room, GamePlayer $actor, int $to, bool $passedStart): void
    {
        $tile = $this->board->tile($to);
        $this->state->log($room, 'move', "{$actor->name} dipindahkan ke {$tile['name']}.");
        if ($passedStart) {
            $this->state->log($room, 'salary',
                "{$actor->name} melewati START dan menerima {$room->rules['salary']}.");
        }
    }

    private function otherActivePlayers(GameRoom $room, GamePlayer $actor)
    {
        return GamePlayer::where('game_room_id', $room->id)
            ->where('is_bankrupt', false)
            ->where('id', '!=', $actor->id)
            ->orderBy('seat')->get();
    }

    // ------------------------------------------------------------------ utang & kebangkrutan

    /**
     * Membayar kewajiban. @return bool true bila lunas sekarang; false bila masuk fase utang atau bangkrut
     * (pemanggil harus berhenti dan tidak mengubah fase lagi).
     *
     * @param int[] $payeeIds penerima bila kewajiban dibayar ke banyak pemain ($per per orang)
     */
    private function charge(
        GameRoom $room,
        GamePlayer $actor,
        int $amount,
        ?GamePlayer $creditor,
        string $reason,
        string $paidMessage,
        array $continuation = ['kind' => 'finish'],
        array $payeeIds = [],
        int $per = 0,
    ): bool {
        if ($this->tx->canAfford($actor, $amount)) {
            $this->settle($actor, $amount, $creditor, $payeeIds, $per);
            $this->state->log($room, 'pay', $paidMessage);
            return true;
        }

        if ($this->bankruptcy->liquidationValue($room, $actor) >= $amount) {
            $room->turn_phase = 'await_debt';
            $room->pending = [
                'type' => 'debt',
                'amount' => $amount,
                'reason' => $reason,
                'creditor_id' => $creditor?->id,
                'payee_ids' => $payeeIds,
                'per' => $per,
                'paid_message' => $paidMessage,
                'continuation' => $continuation,
            ];
            $this->state->log($room, 'debt',
                "{$actor->name} harus membayar {$amount} ({$reason}) tetapi uangnya kurang. "
                . 'Jual bangunan atau gadaikan properti, lalu bayar.');
            return false;
        }

        $this->state->log($room, 'debt',
            "{$actor->name} tidak mampu membayar {$amount} ({$reason}) walau seluruh asetnya dijual.");
        $this->goBankrupt($room, $actor, $creditor, $payeeIds);
        return false;
    }

    private function settle(GamePlayer $actor, int $amount, ?GamePlayer $creditor, array $payeeIds, int $per): void
    {
        if ($payeeIds !== []) {
            foreach ($payeeIds as $pid) {
                $this->tx->transfer($actor, GamePlayer::findOrFail($pid), $per);
            }
        } elseif ($creditor !== null) {
            $this->tx->transfer($actor, $creditor, $amount);
        } else {
            $this->tx->debit($actor, $amount);
        }
    }

    /** Melanjutkan giliran setelah kewajiban lunas. */
    private function resume(GameRoom $room, GamePlayer $actor, array $continuation): void
    {
        $room->pending = null;

        if (($continuation['kind'] ?? 'finish') === 'jail_move') {
            $this->jail->release($actor);
            $room->doubles_count = 0;
            $this->state->log($room, 'jail', "{$actor->name} bebas dari penjara.");
            $this->moveAndResolve($room, $actor, (int) $continuation['steps']);
            return;
        }

        $this->finishAction($room, $actor);
    }

    private function payDebt(GameRoom $room, GamePlayer $actor): void
    {
        $this->requirePhase($room, 'await_debt');
        $debt = $room->pending ?? [];
        $amount = (int) ($debt['amount'] ?? 0);

        if (! $this->tx->canAfford($actor, $amount)) {
            throw new GameRuleException('Uang belum cukup. Jual bangunan atau gadaikan properti dulu.');
        }

        $creditor = ! empty($debt['creditor_id']) ? GamePlayer::find($debt['creditor_id']) : null;
        $this->settle($actor, $amount, $creditor, (array) ($debt['payee_ids'] ?? []), (int) ($debt['per'] ?? 0));
        $this->state->log($room, 'pay', $debt['paid_message'] ?? "{$actor->name} melunasi utang {$amount}.");

        $this->resume($room, $actor, (array) ($debt['continuation'] ?? ['kind' => 'finish']));
    }

    private function declareBankruptcy(GameRoom $room, GamePlayer $actor): void
    {
        $this->requirePhase($room, 'await_debt');
        $debt = $room->pending ?? [];
        $creditor = ! empty($debt['creditor_id']) ? GamePlayer::find($debt['creditor_id']) : null;

        $this->state->log($room, 'bankrupt', "{$actor->name} menyatakan bangkrut.");
        $this->goBankrupt($room, $actor, $creditor, (array) ($debt['payee_ids'] ?? []));
    }

    private function goBankrupt(GameRoom $room, GamePlayer $actor, ?GamePlayer $creditor, array $payeeIds = []): void
    {
        $finished = $this->bankruptcy->declare($room, $actor, $creditor, $payeeIds);
        $this->state->log($room, 'bankrupt', "{$actor->name} bangkrut.");

        if ($finished) {
            $room->pending = null;
            $winner = GamePlayer::find($room->winner_player_id);
            $this->state->log($room, 'end', 'Permainan selesai. Pemenang: ' . ($winner?->name ?? '-') . '.');
            return;
        }
        $this->advanceTurn($room, $actor);
    }

    // ------------------------------------------------------------------ aksi pemain

    private function buy(GameRoom $room, GamePlayer $actor): void
    {
        $this->requirePhase($room, 'await_buy');
        $tileId = (int) ($room->pending['tile_id'] ?? -1);

        $this->properties->buy($room, $actor, $tileId); // GameRuleException bila uang kurang -> semua dibatalkan
        $tile = $this->board->tile($tileId);
        $this->state->log($room, 'buy', "{$actor->name} membeli {$tile['name']} seharga {$tile['price']}.");
        $this->finishAction($room, $actor);
    }

    private function decline(GameRoom $room, GamePlayer $actor): void
    {
        $this->requirePhase($room, 'await_buy');
        $tile = $this->board->tile((int) ($room->pending['tile_id'] ?? -1));
        $this->state->log($room, 'decline', "{$actor->name} tidak membeli {$tile['name']}. (Lelang belum tersedia.)");
        $this->finishAction($room, $actor);
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

    private function build(GameRoom $room, GamePlayer $actor, int $tileId): void
    {
        $this->requirePhaseIn($room, ['await_roll', 'await_end']);
        $res = $this->buildings->build($room, $actor, $tileId);
        $tile = $this->board->tile($tileId);
        $what = $res['kind'] === 'hotel' ? 'hotel' : 'rumah';
        $this->state->log($room, 'build', "{$actor->name} membangun {$what} di {$tile['name']} seharga {$res['cost']}.");
    }

    private function sellBuilding(GameRoom $room, GamePlayer $actor, int $tileId): void
    {
        $this->requirePhaseIn($room, ['await_roll', 'await_buy', 'await_end', 'await_debt']);
        $refund = $this->buildings->sell($room, $actor, $tileId);
        $tile = $this->board->tile($tileId);
        $this->state->log($room, 'sell', "{$actor->name} menjual bangunan di {$tile['name']} dan menerima {$refund}.");
    }

    private function mortgageTile(GameRoom $room, GamePlayer $actor, int $tileId): void
    {
        $this->requirePhaseIn($room, ['await_roll', 'await_buy', 'await_end', 'await_debt']);
        $value = $this->mortgage->mortgage($room, $actor, $tileId);
        $tile = $this->board->tile($tileId);
        $this->state->log($room, 'mortgage', "{$actor->name} menggadaikan {$tile['name']} dan menerima {$value}.");
    }

    private function unmortgageTile(GameRoom $room, GamePlayer $actor, int $tileId): void
    {
        $this->requirePhaseIn($room, ['await_roll', 'await_end']);
        $cost = $this->mortgage->unmortgage($room, $actor, $tileId);
        $tile = $this->board->tile($tileId);
        $this->state->log($room, 'unmortgage', "{$actor->name} menebus {$tile['name']} seharga {$cost}.");
    }

    private function payJail(GameRoom $room, GamePlayer $actor): void
    {
        $this->requirePhase($room, 'await_roll');
        if (! $actor->in_jail) {
            throw new GameRuleException('Anda tidak sedang di penjara.');
        }
        $fine = $this->jail->payFine($room, $actor);
        $this->state->log($room, 'jail', "{$actor->name} membayar denda {$fine} dan keluar dari penjara.");
    }

    private function useJailCard(GameRoom $room, GamePlayer $actor): void
    {
        $this->requirePhase($room, 'await_roll');
        if (! $actor->in_jail) {
            throw new GameRuleException('Anda tidak sedang di penjara.');
        }
        $this->jail->useCard($room, $actor);
        $this->state->log($room, 'jail', "{$actor->name} memakai kartu bebas penjara.");
    }
}