<?php

namespace App\Services;

/** Perhitungan sewa murni (tanpa akses database) sehingga mudah diuji. */
class RentService
{
    public function __construct(private BoardService $board)
    {
    }

    /**
     * @param array $ownership    ['is_mortgaged'=>bool,'has_hotel'=>bool,'houses'=>int]
     * @param int[] $ownedTileIds semua petak milik pemilik (apa pun status hipoteknya)
     */
    public function rentFor(array $rules, array $tile, array $ownership, array $ownedTileIds, int $diceTotal): int
    {
        if (! empty($ownership['is_mortgaged'])) {
            return 0;
        }

        switch ($tile['type']) {
            case 'property':
                $rent = $tile['rent'];
                if (! empty($ownership['has_hotel'])) {
                    return $rent[5];
                }
                $houses = (int) ($ownership['houses'] ?? 0);
                if ($houses > 0) {
                    return $rent[min(4, $houses)];
                }
                if (($rules['double_rent_full_set'] ?? true) && $this->ownsFullGroup($tile['group'], $ownedTileIds)) {
                    return $rent[0] * 2;
                }
                return $rent[0];

            case 'station':
                $count = $this->countOfType('station', $ownedTileIds);
                return (int) config("monopoly.station_rent.$count", 0);

            case 'utility':
                $count = $this->countOfType('utility', $ownedTileIds);
                return $diceTotal * (int) config("monopoly.utility_multiplier.$count", 0);
        }

        return 0;
    }

    public function ownsFullGroup(string $group, array $ownedTileIds): bool
    {
        $ids = $this->board->groupTileIds($group);

        return $ids !== [] && array_diff($ids, $ownedTileIds) === [];
    }

    private function countOfType(string $type, array $ownedTileIds): int
    {
        return count(array_intersect($this->board->tileIdsOfType($type), $ownedTileIds));
    }
}