<?php

namespace App\Services;

use App\Exceptions\GameRuleException;
use App\Models\GameCardDeck;
use App\Models\GamePlayer;
use App\Models\GameRoom;

/** Urutan dek per game (diacak server). Efek kartu dijalankan oleh GameEngine. */
class CardService
{
    private const DECKS = ['chance', 'chest'];

    public function __construct(private BoardService $board)
    {
    }

    public function find(string $deck, string $cardId): array
    {
        foreach (config("monopoly_cards.$deck", []) as $card) {
            if ($card['id'] === $cardId) {
                return $card;
            }
        }
        throw new GameRuleException('Kartu tidak ditemukan.');
    }

    /** Membuat dek yang belum ada (juga untuk game lama yang dibuat sebelum Tahap 3). */
    public function ensureDecks(GameRoom $room): void
    {
        foreach (self::DECKS as $deck) {
            $exists = GameCardDeck::where('game_room_id', $room->id)->where('deck', $deck)->exists();
            if ($exists) {
                continue;
            }
            GameCardDeck::create([
                'game_room_id' => $room->id,
                'deck' => $deck,
                'order' => $this->shuffled(array_column(config("monopoly_cards.$deck", []), 'id')),
                'pointer' => 0,
                'held' => [],
            ]);
        }
    }

    public function draw(GameRoom $room, string $deck): array
    {
        $this->ensureDecks($room);
        $row = GameCardDeck::where('game_room_id', $room->id)->where('deck', $deck)
            ->lockForUpdate()->firstOrFail();

        $order = $row->order;
        $count = count($order);
        $heldIds = array_column($row->held ?? [], 'card_id');

        for ($i = 0; $i < $count; $i++) {
            $id = $order[((int) $row->pointer + $i) % $count];
            if (in_array($id, $heldIds, true)) {
                continue; // kartu bebas penjara yang sedang dipegang pemain
            }
            $row->pointer = ((int) $row->pointer + $i + 1) % $count;
            $row->save();

            return $this->find($deck, $id);
        }

        throw new GameRuleException('Dek kartu kosong.');
    }

    public function hold(GameRoom $room, string $deck, string $cardId, GamePlayer $player): void
    {
        $row = GameCardDeck::where('game_room_id', $room->id)->where('deck', $deck)
            ->lockForUpdate()->firstOrFail();
        $held = $row->held ?? [];
        $held[] = ['card_id' => $cardId, 'player_id' => (int) $player->id];
        $row->held = array_values($held);
        $row->save();
    }

    /** Mengembalikan satu kartu bebas penjara milik pemain ke dek. */
    public function returnJailCard(GameRoom $room, GamePlayer $player): void
    {
        foreach (GameCardDeck::where('game_room_id', $room->id)->get() as $deck) {
            $held = $deck->held ?? [];
            foreach ($held as $i => $h) {
                if ((int) $h['player_id'] === (int) $player->id) {
                    unset($held[$i]);
                    $deck->held = array_values($held);
                    $deck->save();

                    return;
                }
            }
        }
    }

    /** Dipakai saat bangkrut: semua kartu yang dipegang kembali ke dek. */
    public function releaseAll(GameRoom $room, GamePlayer $player): void
    {
        foreach (GameCardDeck::where('game_room_id', $room->id)->get() as $deck) {
            $held = $deck->held ?? [];
            $kept = array_values(array_filter($held, fn ($h) => (int) $h['player_id'] !== (int) $player->id));
            if (count($kept) !== count($held)) {
                $deck->held = $kept;
                $deck->save();
            }
        }
    }

    /** Petak stasiun/utilitas terdekat di depan posisi $from (berputar). */
    public function nearest(string $type, int $from): int
    {
        $ids = $this->board->tileIdsOfType($type);
        sort($ids);
        foreach ($ids as $id) {
            if ($id > $from) {
                return $id;
            }
        }

        return $ids[0];
    }

    private function shuffled(array $ids): array
    {
        for ($i = count($ids) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        }

        return array_values($ids);
    }
}