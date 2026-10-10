<?php

namespace App\Services;

use App\Exceptions\GameRuleException;
use App\Models\GamePlayer;
use App\Models\GameRoom;
use App\Models\PropertyOwnership;

class MortgageService
{
    public function __construct(
        private BoardService $board,
        private TransactionService $tx,
    ) {
    }

    private function row(GameRoom $room, int $tileId): ?PropertyOwnership
    {
        return PropertyOwnership::where('game_room_id', $room->id)->where('tile_id', $tileId)->first();
    }

    /** Biaya tebus = nilai gadai + bunga (dibulatkan, tanpa pecahan desimal). */
    public function unmortgageCost(GameRoom $room, array $tile): int
    {
        $value = $this->board->mortgageValue($tile);
        $interest = (float) ($room->rules['unmortgage_interest'] ?? 0.10);

        return $value + (int) round($value * $interest);
    }

    public function mortgageBlocker(GameRoom $room, GamePlayer $player, int $tileId): ?string
    {
        $tile = $this->board->tile($tileId);
        if (! $this->board->isBuyable($tile)) {
            return 'Petak ini tidak bisa digadaikan.';
        }
        $own = $this->row($room, $tileId);
        if ($own === null || (int) $own->owner_player_id !== (int) $player->id) {
            return 'Anda bukan pemilik properti ini.';
        }
        if ($own->is_mortgaged) {
            return 'Properti ini sudah digadaikan.';
        }
        if ($tile['type'] === 'property') {
            $built = PropertyOwnership::where('game_room_id', $room->id)
                ->whereIn('tile_id', $this->board->groupTileIds($tile['group']))
                ->where(fn ($q) => $q->where('houses', '>', 0)->orWhere('has_hotel', true))
                ->exists();
            if ($built) {
                return 'Jual semua bangunan di kelompok warna ini sebelum menggadaikan.';
            }
        }

        return null;
    }

    public function mortgage(GameRoom $room, GamePlayer $player, int $tileId): int
    {
        $reason = $this->mortgageBlocker($room, $player, $tileId);
        if ($reason !== null) {
            throw new GameRuleException($reason);
        }

        $own = $this->row($room, $tileId);
        $value = $this->board->mortgageValue($this->board->tile($tileId));
        $own->is_mortgaged = true;
        $own->save();
        $this->tx->credit($player, $value);

        return $value;
    }

    public function unmortgageBlocker(GameRoom $room, GamePlayer $player, int $tileId): ?string
    {
        $tile = $this->board->tile($tileId);
        if (! $this->board->isBuyable($tile)) {
            return 'Petak ini tidak bisa ditebus.';
        }
        $own = $this->row($room, $tileId);
        if ($own === null || (int) $own->owner_player_id !== (int) $player->id) {
            return 'Anda bukan pemilik properti ini.';
        }
        if (! $own->is_mortgaged) {
            return 'Properti ini tidak sedang digadaikan.';
        }
        $cost = $this->unmortgageCost($room, $tile);
        if ($player->money < $cost) {
            return "Uang tidak cukup (butuh {$cost}).";
        }

        return null;
    }

    public function unmortgage(GameRoom $room, GamePlayer $player, int $tileId): int
    {
        $reason = $this->unmortgageBlocker($room, $player, $tileId);
        if ($reason !== null) {
            throw new GameRuleException($reason);
        }

        $own = $this->row($room, $tileId);
        $cost = $this->unmortgageCost($room, $this->board->tile($tileId));
        $this->tx->debit($player, $cost);
        $own->is_mortgaged = false;
        $own->save();

        return $cost;
    }
}