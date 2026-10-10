<?php

namespace App\Services;

use App\Models\GameLog;
use App\Models\GamePlayer;
use App\Models\GameRoom;
use App\Models\PropertyOwnership;
use App\Models\User;

class GameStateService
{
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
        $players = GamePlayer::where('game_room_id', $room->id)->orderBy('seat')->get();
        $current = $players->firstWhere('id', $room->current_player_id);

        $properties = [];
        foreach (PropertyOwnership::where('game_room_id', $room->id)->get() as $o) {
            $properties[$o->tile_id] = [
                'owner' => $o->owner_player_id,
                'houses' => (int) $o->houses,
                'hotel' => (bool) $o->has_hotel,
                'mortgaged' => (bool) $o->is_mortgaged,
            ];
        }

        $logs = GameLog::where('game_room_id', $room->id)
            ->orderByDesc('id')->limit(60)->get()->reverse()->values()
            ->map(fn ($l) => ['id' => $l->id, 'type' => $l->type, 'message' => $l->message])
            ->all();

        $canAct = $viewer !== null
            && $room->status === 'playing'
            && $current !== null
            && ! $current->is_bankrupt
            && $room->userControls($viewer, $current);

        $actions = [];
        if ($canAct) {
            $actions = match ($room->turn_phase) {
                'await_roll' => ['roll'],
                'await_buy' => ['buy', 'decline'],
                'await_end' => ['end_turn'],
                default => [],
            };
        }

        $canBuy = $canAct
            && $room->turn_phase === 'await_buy'
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
            'phase' => $room->turn_phase,
            'turn_number' => (int) $room->turn_number,
            'current_player_id' => $room->current_player_id,
            'last_dice' => $room->last_dice,
            'pending' => $room->pending,
            'winner_id' => $room->winner_player_id,
            'players' => $players->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'token' => $p->token,
                'seat' => (int) $p->seat,
                'position' => (int) $p->position,
                'money' => (int) $p->money,
                'is_bankrupt' => (bool) $p->is_bankrupt,
                'in_jail' => (bool) $p->in_jail,
            ])->all(),
            'properties' => $properties,
            'logs' => $logs,
            'can_act' => $canAct,
            'actions' => $actions,
            'can_buy' => $canBuy,
        ];
    }
}