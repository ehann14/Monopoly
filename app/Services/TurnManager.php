<?php

namespace App\Services;

use App\Models\GamePlayer;
use App\Models\GameRoom;

class TurnManager
{
    /** Pemain aktif berikutnya (melewati yang bangkrut). */
    public function next(GameRoom $room, GamePlayer $current): ?GamePlayer
    {
        $players = GamePlayer::where('game_room_id', $room->id)->orderBy('seat')->get()->values();
        $count = $players->count();
        $index = $players->search(fn ($p) => $p->id === $current->id);
        if ($index === false) {
            return null;
        }

        for ($i = 1; $i <= $count; $i++) {
            $candidate = $players[($index + $i) % $count];
            if (! $candidate->is_bankrupt) {
                return $candidate;
            }
        }

        return null;
    }

    public function begin(GameRoom $room, GamePlayer $player): void
    {
        $room->current_player_id = $player->id;
        $room->turn_phase = 'await_roll';
        $room->doubles_count = 0;
        $room->pending = null;
        $room->turn_number = $room->turn_number + 1;
    }
}