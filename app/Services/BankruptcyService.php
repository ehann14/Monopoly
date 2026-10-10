<?php

namespace App\Services;

use App\Models\GamePlayer;
use App\Models\GameRoom;
use App\Models\PropertyOwnership;

/**
 * Kebangkrutan (Tahap 3).
 * - Kreditor pemain: menerima semua uang dan properti. Status gadai dipertahankan,
 *   bangunan dikembalikan ke bank.
 * - Kreditor bank: properti kembali ke bank (tanpa gadai, tanpa bangunan).
 * - Banyak penerima (kartu "bayar tiap pemain"): sisa uang dibagi rata, properti ke bank.
 */
class BankruptcyService
{
    public function __construct(
        private TransactionService $tx,
        private CardService $cards,
        private BoardService $board,
    ) {
    }

    /** Uang tunai + nilai gadai properti + setengah harga bangunan. */
    public function liquidationValue(GameRoom $room, GamePlayer $player): int
    {
        $value = (int) $player->money;

        $rows = PropertyOwnership::where('game_room_id', $room->id)
            ->where('owner_player_id', $player->id)->get();
        foreach ($rows as $row) {
            $tile = $this->board->tile((int) $row->tile_id);
            if (! $row->is_mortgaged) {
                $value += $this->board->mortgageValue($tile);
            }
            if ($tile['type'] === 'property') {
                $level = $row->has_hotel ? 5 : (int) $row->houses;
                $value += $level * intdiv($this->board->houseCost($tile), 2);
            }
        }

        return $value;
    }

    /**
     * @param int[] $payeeIds penerima bila utang kepada banyak pemain
     * @return bool true bila permainan berakhir karena tersisa satu pemain
     */
    public function declare(GameRoom $room, GamePlayer $player, ?GamePlayer $creditor, array $payeeIds = []): bool
    {
        if ($player->money > 0) {
            if ($creditor !== null) {
                $this->tx->credit($creditor, (int) $player->money);
            } elseif ($payeeIds !== []) {
                $share = intdiv((int) $player->money, count($payeeIds));
                if ($share > 0) {
                    foreach ($payeeIds as $pid) {
                        $this->tx->credit(GamePlayer::findOrFail($pid), $share);
                    }
                }
            }
        }

        $player->money = 0;
        $player->is_bankrupt = true;
        $player->in_jail = false;
        $player->jail_turns = 0;
        $player->jail_cards = 0;
        $player->bankrupt_order = GamePlayer::where('game_room_id', $room->id)
            ->where('is_bankrupt', true)->count() + 1;
        $player->save();

        $this->cards->releaseAll($room, $player);

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