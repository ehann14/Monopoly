<?php

namespace App\Services;

use InvalidArgumentException;

class BoardService
{
    public function tiles(): array
    {
        return config('monopoly.board');
    }

    public function tile(int $id): array
    {
        $tile = config("monopoly.board.$id");
        if ($tile === null) {
            throw new InvalidArgumentException("Petak $id tidak ada.");
        }
        return $tile;
    }

    public function isBuyable(array $tile): bool
    {
        return in_array($tile['type'], ['property', 'station', 'utility'], true);
    }

    public function mortgageValue(array $tile): int
    {
        return intdiv($tile['price'], 2);
    }

    public function unmortgageCost(array $tile, ?float $interest = null): int
    {
        $interest ??= config('monopoly.rules.unmortgage_interest');
        return (int) ceil($this->mortgageValue($tile) * (1 + $interest));
    }

    public function houseCost(array $tile): int
    {
        return config("monopoly.groups.{$tile['group']}.house_cost");
    }

    /** @return int[] id petak dalam satu kelompok warna */
    public function groupTileIds(string $group): array
    {
        return array_values(array_column(
            array_filter($this->tiles(), fn ($t) => ($t['group'] ?? null) === $group),
            'id'
        ));
    }
}