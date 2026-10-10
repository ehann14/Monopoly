<?php

namespace App\Services;

use App\Exceptions\GameRuleException;
use App\Models\GamePlayer;
use App\Models\GameRoom;
use App\Models\PropertyOwnership;
use Illuminate\Support\Collection;

/**
 * Rumah dan hotel. Level bangunan: 0..4 = jumlah rumah, 5 = hotel.
 * Alasan penolakan dihasilkan oleh *Blocker() sehingga UI dan engine memakai aturan yang sama.
 */
class BuildingService
{
    public function __construct(
        private BoardService $board,
        private TransactionService $tx,
    ) {
    }

    /** @return array{houses_left:int,hotels_left:int} */
    public function stock(GameRoom $room): array
    {
        $rules = $room->rules ?? [];
        $houses = (int) PropertyOwnership::where('game_room_id', $room->id)->sum('houses');
        $hotels = PropertyOwnership::where('game_room_id', $room->id)->where('has_hotel', true)->count();

        return [
            'houses_left' => (int) ($rules['house_stock'] ?? 32) - $houses,
            'hotels_left' => (int) ($rules['hotel_stock'] ?? 12) - $hotels,
        ];
    }

    private function groupRows(GameRoom $room, array $tile): Collection
    {
        return PropertyOwnership::where('game_room_id', $room->id)
            ->whereIn('tile_id', $this->board->groupTileIds($tile['group']))
            ->get()
            ->keyBy('tile_id');
    }

    private function level(PropertyOwnership $row): int
    {
        return $row->has_hotel ? 5 : (int) $row->houses;
    }

    public function buildBlocker(GameRoom $room, GamePlayer $player, int $tileId, ?array $stock = null): ?string
    {
        $tile = $this->board->tile($tileId);
        if ($tile['type'] !== 'property') {
            return 'Hanya properti berwarna yang bisa dibangun.';
        }

        $rows = $this->groupRows($room, $tile);
        $own = $rows->get($tileId);
        if ($own === null || (int) $own->owner_player_id !== (int) $player->id) {
            return 'Anda bukan pemilik properti ini.';
        }
        foreach ($rows as $row) {
            if ((int) $row->owner_player_id !== (int) $player->id) {
                return 'Anda harus memiliki seluruh kelompok warna terlebih dahulu.';
            }
            if ($row->is_mortgaged) {
                return 'Ada properti di kelompok ini yang sedang digadaikan.';
            }
        }
        if ($own->has_hotel) {
            return 'Properti ini sudah berupa hotel (batas maksimum).';
        }
        $min = $rows->map(fn ($r) => $this->level($r))->min();
        if ($this->level($own) > $min) {
            return 'Bangun merata: bangun dulu di properti lain pada kelompok yang sama.';
        }

        $stock ??= $this->stock($room);
        if ((int) $own->houses >= 4) {
            if ($stock['hotels_left'] < 1) {
                return 'Stok hotel bank habis.';
            }
        } elseif ($stock['houses_left'] < 1) {
            return 'Stok rumah bank habis.';
        }

        $cost = $this->board->houseCost($tile);
        if ($player->money < $cost) {
            return "Uang tidak cukup (butuh {$cost}).";
        }

        return null;
    }

    /** @return array{kind:string,cost:int} */
    public function build(GameRoom $room, GamePlayer $player, int $tileId): array
    {
        $reason = $this->buildBlocker($room, $player, $tileId);
        if ($reason !== null) {
            throw new GameRuleException($reason);
        }

        $tile = $this->board->tile($tileId);
        $own = PropertyOwnership::where('game_room_id', $room->id)->where('tile_id', $tileId)->firstOrFail();
        $cost = $this->board->houseCost($tile);

        $this->tx->debit($player, $cost);
        if ((int) $own->houses >= 4) {
            $own->houses = 0; // 4 rumah dikembalikan ke bank, diganti 1 hotel
            $own->has_hotel = true;
            $kind = 'hotel';
        } else {
            $own->houses = (int) $own->houses + 1;
            $kind = 'house';
        }
        $own->save();

        return ['kind' => $kind, 'cost' => $cost];
    }

    public function sellBlocker(GameRoom $room, GamePlayer $player, int $tileId, ?array $stock = null): ?string
    {
        $tile = $this->board->tile($tileId);
        if ($tile['type'] !== 'property') {
            return 'Hanya properti berwarna yang punya bangunan.';
        }

        $rows = $this->groupRows($room, $tile);
        $own = $rows->get($tileId);
        if ($own === null || (int) $own->owner_player_id !== (int) $player->id) {
            return 'Anda bukan pemilik properti ini.';
        }
        if ($this->level($own) < 1) {
            return 'Tidak ada bangunan untuk dijual.';
        }
        $max = $rows->map(fn ($r) => $this->level($r))->max();
        if ($this->level($own) < $max) {
            return 'Jual merata: jual dulu dari properti dengan bangunan terbanyak.';
        }
        if ($own->has_hotel) {
            $stock ??= $this->stock($room);
            if ($stock['houses_left'] < 4) {
                return 'Stok rumah bank kurang untuk menurunkan hotel menjadi 4 rumah.';
            }
        }

        return null;
    }

    /** @return int uang yang diterima (setengah harga bangunan) */
    public function sell(GameRoom $room, GamePlayer $player, int $tileId): int
    {
        $reason = $this->sellBlocker($room, $player, $tileId);
        if ($reason !== null) {
            throw new GameRuleException($reason);
        }

        $tile = $this->board->tile($tileId);
        $own = PropertyOwnership::where('game_room_id', $room->id)->where('tile_id', $tileId)->firstOrFail();
        $refund = intdiv($this->board->houseCost($tile), 2);

        if ($own->has_hotel) {
            $own->has_hotel = false;
            $own->houses = 4;
        } else {
            $own->houses = (int) $own->houses - 1;
        }
        $own->save();
        $this->tx->credit($player, $refund);

        return $refund;
    }
}