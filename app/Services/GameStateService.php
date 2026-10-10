<?php

namespace App\Services;

use App\Models\GameLog;
use App\Models\GamePlayer;
use App\Models\GameRoom;
use App\Models\PropertyOwnership;
use App\Models\User;

class GameStateService
{
    public function __construct(
        private BoardService $board,
        private BuildingService $buildings,
        private MortgageService $mortgage,
    ) {
    }

    /** Menulis log PUBLIK. Aksi rahasia Game Master tidak boleh lewat sini. */
    public function log(GameRoom $room, string $type, string $message, ?array $payload = null): void
    {
        GameLog::create([
            'game_room_id' => $room->id,
            'state_version' => $room->state_version + 1,
            'type' => $type,
            'message' => mb_substr($message, 0, 500),
            'payload' => $payload,
        ]);
    }

    /** State publik yang aman dikirim ke browser. */
    public function snapshot(GameRoom $room, ?User $viewer = null): array
    {
        $room = $room->fresh();
        $rules = $room->rules ?? [];
        $players = GamePlayer::where('game_room_id', $room->id)->orderBy('seat')->get();
        $current = $players->firstWhere('id', $room->current_player_id);

        $properties = [];
        foreach (PropertyOwnership::where('game_room_id', $room->id)->get() as $o) {
            $properties[$o->tile_id] = [
                'owner' => $o->owner_player_id !== null ? (int) $o->owner_player_id : null,
                'houses' => (int) $o->houses,
                'hotel' => (bool) $o->has_hotel,
                'mortgaged' => (bool) $o->is_mortgaged,
            ];
        }

        $logs = GameLog::where('game_room_id', $room->id)
            ->orderByDesc('id')->limit(60)->get()->reverse()->values()
            ->map(fn ($l) => ['id' => $l->id, 'type' => $l->type, 'message' => $l->message]
                + ($l->type === 'card' ? ['card' => $l->payload] : []))
            ->all();

        $canAct = $viewer !== null
            && $room->status === 'playing'
            && $current !== null
            && ! $current->is_bankrupt
            && $room->userControls($viewer, $current);

        $phase = $room->turn_phase;
        $stock = $this->buildings->stock($room);
        $jailFine = (int) ($rules['jail_fine'] ?? 50);

        $actions = [];
        if ($canAct) {
            $actions = match ($phase) {
                'await_roll' => ['roll'],
                'await_buy' => ['buy', 'decline'],
                'await_end' => ['end_turn'],
                'await_debt' => ['declare_bankruptcy'],
                default => [],
            };
            if ($phase === 'await_roll' && $current->in_jail) {
                if ($current->money >= $jailFine) {
                    $actions[] = 'pay_jail';
                }
                if ((int) $current->jail_cards > 0) {
                    $actions[] = 'use_jail_card';
                }
            }
            if ($phase === 'await_debt'
                && isset($room->pending['amount'])
                && $current->money >= (int) $room->pending['amount']) {
                array_unshift($actions, 'pay_debt');
            }
        }

        $canBuy = $canAct
            && $phase === 'await_buy'
            && isset($room->pending['price'])
            && $current->money >= (int) $room->pending['price'];

        return [
            'room' => [
                'code' => $room->code,
                'mode' => $room->mode,
                'status' => $room->status,
                'gm_enabled' => (bool) $room->gm_enabled, // indikator publik Game Master Mode
            ],
            'version' => (int) $room->state_version,
            'phase' => $phase,
            'turn_number' => (int) $room->turn_number,
            'current_player_id' => $room->current_player_id,
            'last_dice' => $room->last_dice,
            'pending' => $room->pending,
            'winner_id' => $room->winner_player_id,
            'rules' => [
                'jail_fine' => $jailFine,
                'max_jail_turns' => (int) ($rules['max_jail_turns'] ?? 3),
                'salary' => (int) ($rules['salary'] ?? 200),
            ],
            'bank' => $stock,
            'players' => $players->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'token' => $p->token,
                'seat' => (int) $p->seat,
                'position' => (int) $p->position,
                'money' => (int) $p->money,
                'is_bankrupt' => (bool) $p->is_bankrupt,
                'in_jail' => (bool) $p->in_jail,
                'jail_turns' => (int) $p->jail_turns,
                'jail_cards' => (int) $p->jail_cards,
            ])->all(),
            'properties' => $properties,
            'logs' => $logs,
            'can_act' => $canAct,
            'actions' => $actions,
            'can_buy' => $canBuy,
            'manage' => $canAct ? $this->manage($room, $current, $properties, $stock) : [],
        ];
    }

    /** Aksi kelola properti untuk pemain yang sedang giliran, lengkap dengan alasan bila tidak bisa. */
    private function manage(GameRoom $room, GamePlayer $current, array $properties, array $stock): array
    {
        $phase = $room->turn_phase;
        if (! in_array($phase, ['await_roll', 'await_buy', 'await_end', 'await_debt'], true)) {
            return [];
        }
        $calmPhase = in_array($phase, ['await_roll', 'await_end'], true);
        $calmReason = 'Hanya bisa dilakukan sebelum melempar dadu atau setelah aksi giliran selesai.';

        $manage = [];
        foreach ($properties as $tileId => $pr) {
            if ($pr['owner'] !== (int) $current->id) {
                continue;
            }
            $tileId = (int) $tileId;
            $tile = $this->board->tile($tileId);
            $entry = ['build' => null, 'sell' => null, 'mortgage' => null, 'unmortgage' => null];

            if ($tile['type'] === 'property') {
                $cost = $this->board->houseCost($tile);
                if (! $pr['mortgaged']) {
                    $reason = $calmPhase ? $this->buildings->buildBlocker($room, $current, $tileId, $stock) : $calmReason;
                    $entry['build'] = ['ok' => $reason === null, 'reason' => $reason, 'amount' => $cost];
                }
                if ($pr['hotel'] || $pr['houses'] > 0) {
                    $reason = $this->buildings->sellBlocker($room, $current, $tileId, $stock);
                    $entry['sell'] = ['ok' => $reason === null, 'reason' => $reason, 'amount' => intdiv($cost, 2)];
                }
            }

            if ($pr['mortgaged']) {
                $reason = $calmPhase ? $this->mortgage->unmortgageBlocker($room, $current, $tileId) : $calmReason;
                $entry['unmortgage'] = [
                    'ok' => $reason === null, 'reason' => $reason,
                    'amount' => $this->mortgage->unmortgageCost($room, $tile),
                ];
            } else {
                $reason = $this->mortgage->mortgageBlocker($room, $current, $tileId);
                $entry['mortgage'] = [
                    'ok' => $reason === null, 'reason' => $reason,
                    'amount' => $this->board->mortgageValue($tile),
                ];
            }

            $manage[$tileId] = $entry;
        }

        return $manage;
    }
}