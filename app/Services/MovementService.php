<?php

namespace App\Services;

use App\Models\GamePlayer;

/** Pergerakan bidak. Semua perpindahan maju lewat sini agar gaji START konsisten. */
class MovementService
{
    public function __construct(
        private TransactionService $tx,
        private BoardService $board,
    ) {
    }

    /** @return array{from:int,to:int,passed_start:bool} */
    public function advance(array $rules, GamePlayer $player, int $steps): array
    {
        $size = $this->board->size();
        $from = (int) $player->position;
        $raw = $from + $steps;
        $to = $raw % $size;
        $passed = $raw >= $size;

        $player->position = $to;
        if ($passed) {
            $this->tx->credit($player, (int) $rules['salary']); // sekaligus menyimpan posisi
        } else {
            $player->save();
        }

        return ['from' => $from, 'to' => $to, 'passed_start' => $passed];
    }
}