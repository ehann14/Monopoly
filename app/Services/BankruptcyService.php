<?php

namespace App\Services;

use App\Models\GamePlayer;
use App\Models\GameRoom;
use App\Models\PropertyOwnership;

/**
 * VERSI SEDERHANA (Tahap 2): pemain yang tidak mampu membayar langsung bangkrut.
 * Tahap 3 menambahkan opsi menjual bangunan / hipotek sebelum bangkrut.
 */
class BankruptcyService
{
    public function __construct(private TransactionService $tx)
    {
    }

    /** @return bool true bila permainan berakhir karena tersisa satu pemain */
    public function declare(GameRoom $room, GamePlayer $player, ?GamePlayer $creditor): bool
    {
        if ($creditor !== null && $player->money > 0) {
            $this->tx->credit($creditor, (int) $player->money);
        }

        $player->money = 0;
        $player->is_bankrupt = true;
        $player->bankrupt_order = GamePlayer::where('game_room_id', $room->id)
            ->where('is_bankrupt', true)->count() + 1;
        $player->save();

        $assets = PropertyOwnership::where('game_room_id', $room->id)
            ->where('owner_player_id', $player->id);

        if ($creditor !== null) {
            $assets->update(['owner_player_id' => $creditor->id, 'houses' => 0, 'has_hotel' => false]);
        } else {
            $assets->update(['owner_player_id' => null, 'houses' => 0, 'has_hotel' => false, 'is_mortgaged' => false]);
        }

        $active = GamePlayer::where('game_room_id', $room->id)->where('is_bankrupt', false)->get();
        if ($active->count() <= 1) {
            $room->status = 'finished';
            $room->winner_player_id = $active->first()?->id;
            $room->finished_at = now();
            return true;
        }

        return false;
    }
}