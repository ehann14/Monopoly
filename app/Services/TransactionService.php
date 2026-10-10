<?php

namespace App\Services;

use App\Exceptions\GameRuleException;
use App\Models\GamePlayer;
use InvalidArgumentException;

/** Satu-satunya tempat saldo pemain berubah. */
class TransactionService
{
    public function canAfford(GamePlayer $player, int $amount): bool
    {
        return $player->money >= $amount;
    }

    public function credit(GamePlayer $player, int $amount): void
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Jumlah kredit tidak boleh negatif.');
        }
        $player->money += $amount;
        $player->save();
    }

    public function debit(GamePlayer $player, int $amount): void
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Jumlah debit tidak boleh negatif.');
        }
        if (! $this->canAfford($player, $amount)) {
            throw new GameRuleException('Uang tidak cukup.');
        }
        $player->money -= $amount;
        $player->save();
    }

    public function transfer(GamePlayer $from, GamePlayer $to, int $amount): void
    {
        $this->debit($from, $amount);
        $this->credit($to, $amount);
    }
}