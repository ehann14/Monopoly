<?php

namespace App\Services;

use App\Models\GamePlayer;
use App\Models\GameRoom;
use App\Models\PropertyOwnership;
use Illuminate\Support\Collection;

/**
 * Pengambil keputusan AI. Hanya MEMBACA informasi yang juga tersedia bagi pemain biasa
 * (fase giliran, saldo, kepemilikan properti, stok bank). Tidak menyentuh dadu maupun urutan dek,
 * dan tidak mengubah database: hasilnya hanya nama aksi yang kemudian dijalankan GameEngine
 * melalui jalur yang sama dengan pemain manusia.
 */
class AIPlayerService
{
    public function __construct(
        private BoardService $board,
        private BuildingService $buildings,
        private MortgageService $mortgage,
    ) {
    }

    /** @return array{action:string,params:array} */
    public function decide(GameRoom $room, GamePlayer $ai): array
    {
        $hard = ($ai->ai_difficulty ?? $room->ai_difficulty) === 'hard';

        return match ($room->turn_phase) {
            'await_debt' => $this->raiseFunds($room, $ai),
            'await_buy' => $this->decideBuy($room, $ai, $hard),
            'await_roll' => $this->decideBeforeRoll($room, $ai, $hard),
            'await_end' => ($hard ? $this->hardManage($room, $ai) : null) ?? $this->act('end_turn'),
            default => $this->act($this->fallback($room, $ai)),
        };
    }

    /** Aksi paling aman bila keputusan AI ditolak engine. */
    public function fallback(GameRoom $room, GamePlayer $ai): string
    {
        return match ($room->turn_phase) {
            'await_buy' => 'decline',
            'await_end' => 'end_turn',
            'await_debt' => (int) $ai->money >= (int) ($room->pending['amount'] ?? PHP_INT_MAX)
                ? 'pay_debt' : 'declare_bankruptcy',
            default => 'roll',
        };
    }

    private function act(string $action, ?int $tile = null): array
    {
        return ['action' => $action, 'params' => $tile !== null ? ['tile_id' => $tile] : []];
    }

    private function rows(GameRoom $room): Collection
    {
        return PropertyOwnership::where('game_room_id', $room->id)->get()->keyBy('tile_id');
    }

    private function ownerOf(Collection $rows, int $tileId): ?int
    {
        $row = $rows->get($tileId);

        return $row !== null && $row->owner_player_id !== null ? (int) $row->owner_player_id : null;
    }

    // ------------------------------------------------------------------ utang

    private function raiseFunds(GameRoom $room, GamePlayer $ai): array
    {
        $amount = (int) ($room->pending['amount'] ?? 0);
        if ((int) $ai->money >= $amount) {
            return $this->act('pay_debt');
        }

        $mine = $this->rows($room)->filter(fn ($r) => (int) $r->owner_player_id === (int) $ai->id);

        // 1. Jual bangunan (mulai dari level tertinggi yang boleh dijual).
        $best = null;
        foreach ($mine as $row) {
            if (! $row->has_hotel && (int) $row->houses < 1) {
                continue;
            }
            if ($this->buildings->sellBlocker($room, $ai, (int) $row->tile_id) !== null) {
                continue;
            }
            $level = $row->has_hotel ? 5 : (int) $row->houses;
            if ($best === null || $level > $best['level']) {
                $best = ['tile' => (int) $row->tile_id, 'level' => $level];
            }
        }
        if ($best !== null) {
            return $this->act('sell_building', $best['tile']);
        }

        // 2. Gadaikan properti: yang bukan bagian kelompok lengkap dan paling murah dulu.
        $candidates = [];
        foreach ($mine as $row) {
            $tileId = (int) $row->tile_id;
            if ($row->is_mortgaged || $this->mortgage->mortgageBlocker($room, $ai, $tileId) !== null) {
                continue;
            }
            $tile = $this->board->tile($tileId);
            $candidates[] = [
                'tile' => $tileId,
                'full' => $this->inFullGroup($mine, $tile) ? 1 : 0,
                'price' => (int) $tile['price'],
            ];
        }
        if ($candidates !== []) {
            usort($candidates, fn ($a, $b) => [$a['full'], $a['price'], $a['tile']] <=> [$b['full'], $b['price'], $b['tile']]);

            return $this->act('mortgage', $candidates[0]['tile']);
        }

        return $this->act('declare_bankruptcy');
    }

    private function inFullGroup(Collection $mine, array $tile): bool
    {
        if ($tile['type'] !== 'property') {
            return false;
        }
        foreach ($this->board->groupTileIds($tile['group']) as $id) {
            if (! $mine->has($id)) {
                return false;
            }
        }

        return true;
    }

    // ------------------------------------------------------------------ beli

    private function decideBuy(GameRoom $room, GamePlayer $ai, bool $hard): array
    {
        $tileId = (int) ($room->pending['tile_id'] ?? -1);
        $price = (int) ($room->pending['price'] ?? 0);
        $money = (int) $ai->money;

        if ($money < $price) {
            return $this->act('decline');
        }
        $after = $money - $price;

        if (! $hard) {
            // Easy: hemat. Hanya membeli bila sisa uang masih aman.
            return $this->act($after >= 400 ? 'buy' : 'decline');
        }

        $tile = $this->board->tile($tileId);
        $rows = $this->rows($room);
        $need = 150; // cadangan uang minimum sesudah membeli

        if ($tile['type'] === 'property') {
            $others = array_values(array_diff($this->board->groupTileIds($tile['group']), [$tileId]));
            $owners = array_map(fn ($id) => $this->ownerOf($rows, $id), $others);
            $mine = count(array_filter($owners, fn ($o) => $o === (int) $ai->id));
            $set = array_values(array_unique($owners, SORT_REGULAR));

            if ($mine === count($others)) {
                $need = 0;      // melengkapi satu kelompok warna
            } elseif (count($set) === 1 && $set[0] !== null && $set[0] !== (int) $ai->id) {
                $need = 50;     // memblokir lawan yang hampir lengkap
            } elseif ($mine > 0) {
                $need = 100;
            } elseif ($price > 300) {
                $need = 250;
            }
        } elseif ($tile['type'] === 'station') {
            $need = 100;
        } else { // utility
            $need = 250;
        }

        return $this->act($after >= $need ? 'buy' : 'decline');
    }

    // ------------------------------------------------------------------ sebelum lempar dadu

    private function decideBeforeRoll(GameRoom $room, GamePlayer $ai, bool $hard): array
    {
        if ($ai->in_jail) {
            return $this->decideJail($room, $ai, $hard);
        }

        return ($hard ? $this->hardManage($room, $ai) : null) ?? $this->act('roll');
    }

    private function decideJail(GameRoom $room, GamePlayer $ai, bool $hard): array
    {
        if ((int) $ai->jail_cards > 0) {
            return $this->act('use_jail_card');
        }

        if ($hard) {
            $fine = (int) ($room->rules['jail_fine'] ?? 50);
            $unowned = PropertyOwnership::where('game_room_id', $room->id)->whereNull('owner_player_id')->count();
            // Awal permainan masih banyak properti kosong: keluar cepat. Akhir permainan: penjara justru aman.
            if ($unowned >= 8 && (int) $ai->money >= $fine + 300) {
                return $this->act('pay_jail');
            }
        }

        return $this->act('roll');
    }

    /** Hard: tebus gadai bila uang longgar, lalu bangun rumah/hotel dengan kenaikan sewa terbaik. */
    private function hardManage(GameRoom $room, GamePlayer $ai): ?array
    {
        $money = (int) $ai->money;
        $mine = $this->rows($room)->filter(fn ($r) => (int) $r->owner_player_id === (int) $ai->id);

        $unmortgage = null;
        foreach ($mine as $row) {
            if (! $row->is_mortgaged) {
                continue;
            }
            $tileId = (int) $row->tile_id;
            $tile = $this->board->tile($tileId);
            if ($money - $this->mortgage->unmortgageCost($room, $tile) < 400) {
                continue;
            }
            if ($this->mortgage->unmortgageBlocker($room, $ai, $tileId) !== null) {
                continue;
            }
            if ($unmortgage === null || $tile['price'] > $unmortgage['price']) {
                $unmortgage = ['tile' => $tileId, 'price' => (int) $tile['price']];
            }
        }
        if ($unmortgage !== null) {
            return $this->act('unmortgage', $unmortgage['tile']);
        }

        $stock = $this->buildings->stock($room);
        $best = null;
        foreach ($mine as $row) {
            $tileId = (int) $row->tile_id;
            $tile = $this->board->tile($tileId);
            if ($tile['type'] !== 'property' || $row->is_mortgaged || $row->has_hotel) {
                continue;
            }
            $cost = $this->board->houseCost($tile);
            if ($money - $cost < 250) {
                continue;
            }
            if ($this->buildings->buildBlocker($room, $ai, $tileId, $stock) !== null) {
                continue;
            }
            $level = (int) $row->houses;
            $gain = ($tile['rent'][$level + 1] - $tile['rent'][$level]) / max(1, $cost);
            if ($best === null || $gain > $best['gain']) {
                $best = ['tile' => $tileId, 'gain' => $gain];
            }
        }

        return $best !== null ? $this->act('build', $best['tile']) : null;
    }
}