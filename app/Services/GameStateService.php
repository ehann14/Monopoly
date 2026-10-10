<?php

namespace App\Services;

use App\Events\GameStateChanged;
use App\Models\GameLog;
use App\Models\GamePlayer;
use App\Models\GameRoom;
use App\Models\PropertyOwnership;
use App\Models\User;

class GameStateService
{
    /**
     * Membuat snapshot publik state game untuk dikirim ke frontend.
     */
    public function snapshot(GameRoom $room, User $user): array
    {
        $players = $room->players()->orderBy('seat')->get();
        $properties = PropertyOwnership::where('game_room_id', $room->id)->get();
        $logs = GameLog::where('game_room_id', $room->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->reverse()
            ->values();

        return [
            'room' => [
                'code' => $room->code,
                'status' => $room->status,
                'mode' => $room->mode,
                'turn_phase' => $room->turn_phase,
                'current_player_id' => $room->current_player_id,
                'turn_number' => $room->turn_number,
                'doubles_count' => $room->doubles_count,
                'last_dice' => $room->last_dice,
                'pending' => $room->pending,
                'winner_player_id' => $room->winner_player_id,
                'gm_enabled' => $room->gm_enabled,
            ],
            'players' => $players->map(fn (GamePlayer $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'token' => $p->token,
                'money' => $p->money,
                'position' => $p->position,
                'is_ai' => $p->is_ai,
                'in_jail' => $p->in_jail,
                'jail_turns' => $p->jail_turns,
                'jail_cards' => $p->jail_cards,
                'is_bankrupt' => $p->is_bankrupt,
                'is_connected' => $p->is_connected,
                'user_id' => $p->user_id,
            ])->toArray(),
            'properties' => $properties->map(fn (PropertyOwnership $po) => [
                'tile_id' => $po->tile_id,
                'owner_player_id' => $po->owner_player_id,
                'houses' => $po->houses,
                'has_hotel' => $po->has_hotel,
                'is_mortgaged' => $po->is_mortgaged,
            ])->toArray(),
            'logs' => $logs->map(fn (GameLog $l) => [
                'type' => $l->type,
                'message' => $l->message,
            ])->toArray(),
            'version' => $room->state_version,
        ];
    }

    /**
     * Menulis log publik permainan.
     */
    public function log(GameRoom $room, string $type, string $message, ?array $payload = null): void
    {
        GameLog::create([
            'game_room_id' => $room->id,
            'state_version' => $room->state_version,
            'type' => $type,
            'message' => $message,
            'payload' => $payload,
        ]);
    }

    /**
     * Broadcast state terbaru ke semua pemain di room.
     * DIPANGGIL SETELAH TRANSAKSI BERHASIL DI-COMMIT.
     */
    public function broadcastState(GameRoom $room): void
    {
        // Kita butuh user dummy untuk snapshot karena snapshot bersifat publik
        // Ambil host sebagai referensi, tapi snapshot tidak mengandung data privat
        $host = $room->host;
        if (!$host) {
            return;
        }

        $snapshot = $this->snapshot($room, $host);

        event(new GameStateChanged($room, $snapshot));
    }
}