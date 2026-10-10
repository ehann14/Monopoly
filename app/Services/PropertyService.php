<?php

namespace App\Services;

use App\Exceptions\GameRuleException;
use App\Models\GamePlayer;
use App\Models\GameRoom;
use App\Models\PropertyOwnership;

class PropertyService
{
    public function __construct(
        private BoardService $board,
        private TransactionService $tx,
    ) {
    }

    public function ownership(GameRoom $room, int $tileId): PropertyOwnership
    {
        return PropertyOwnership::where('game_room_id', $room->id)
            ->where('tile_id', $tileId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /** @return int[] */
    public function ownedTileIds(GameRoom $room, int $playerId): array
    {
        return PropertyOwnership::where('game_room_id', $room->id)
            ->where('owner_player_id', $playerId)
            ->pluck('tile_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function buy(GameRoom $room, GamePlayer $player, int $tileId): void
    {
        $tile = $this->board->tile($tileId);
        if (! $this->board->isBuyable($tile)) {
            throw new GameRuleException('Petak ini tidak dapat dibeli.');
        }

        $ownership = $this->ownership($room, $tileId);
        if ($ownership->owner_player_id !== null) {
            throw new GameRuleException('Properti ini sudah dimiliki.');
        }

        $this->tx->debit($player, (int) $tile['price']);
        $ownership->owner_player_id = $player->id;
        $ownership->save();
    }
}