<?php

namespace App\Services;

use App\Models\GamePlayer;

/** Pergerakan bidak. Semua perpindahan lewat sini agar gaji START konsisten. */
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

    /** Maju ke petak tertentu (efek kartu). Menerima gaji bila melewati START. */
    public function moveTo(array $rules, GamePlayer $player, int $to): array
    {
        $from = (int) $player->position;
        $passed = $to < $from;

        $player->position = $to;
        if ($passed) {
            $this->tx->credit($player, (int) $rules['salary']);
        } else {
            $player->save();
        }

        return ['from' => $from, 'to' => $to, 'passed_start' => $passed];
    }

    /** Pindah tanpa gaji (penjara, mundur). */
    public function jumpTo(GamePlayer $player, int $to): void
    {
        $player->position = $to;
        $player->save();
    }
}