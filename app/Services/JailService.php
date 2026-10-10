<?php

namespace App\Services;

use App\Exceptions\GameRuleException;
use App\Models\GamePlayer;
use App\Models\GameRoom;

class JailService
{
    public function __construct(
        private TransactionService $tx,
        private CardService $cards,
    ) {
    }

    public function fine(GameRoom $room): int
    {
        return (int) ($room->rules['jail_fine'] ?? 50);
    }

    public function send(GameRoom $room, GamePlayer $player): void
    {
        $player->position = (int) ($room->rules['jail_position'] ?? 10);
        $player->in_jail = true;
        $player->jail_turns = 0;
        $player->save();
    }

    public function release(GamePlayer $player): void
    {
        $player->in_jail = false;
        $player->jail_turns = 0;
        $player->save();
    }

    public function payFine(GameRoom $room, GamePlayer $player): int
    {
        $fine = $this->fine($room);
        $this->tx->debit($player, $fine); // GameRuleException bila uang kurang
        $this->release($player);

        return $fine;
    }

    public function useCard(GameRoom $room, GamePlayer $player): void
    {
        if ((int) $player->jail_cards < 1) {
            throw new GameRuleException('Anda tidak punya kartu bebas penjara.');
        }
        $player->jail_cards = (int) $player->jail_cards - 1;
        $this->cards->returnJailCard($room, $player);
        $this->release($player);
    }

    /** Mencatat gagal melempar kembar. @return bool true bila ini percobaan terakhir (wajib bayar denda) */
    public function failedAttempt(GameRoom $room, GamePlayer $player): bool
    {
        $player->jail_turns = (int) $player->jail_turns + 1;
        $player->save();

        return (int) $player->jail_turns >= (int) ($room->rules['max_jail_turns'] ?? 3);
    }
}