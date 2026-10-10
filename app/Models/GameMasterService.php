<?php

namespace App\Services;

use App\Exceptions\GameRuleException;
use App\Models\GameMasterAuditLog;
use App\Models\GameMasterState;
use App\Models\GamePlayer;
use App\Models\GameRoom;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class GameMasterService
{
    public function __construct(
        private GameStateService $state,
        private TransactionService $tx,
        private MovementService $movement,
        private BoardService $board,
        private BuildingService $buildings,
        private MortgageService $mortgage,
        private JailService $jail,
        private TurnManager $turns,
    ) {
    }

    /**
     * Mengatur uang pemain (Tambah/Kurang/Set)
     */
    public function adjustMoney(User $owner, GameRoom $room, int $playerId, string $action, int $amount, ?string $note = null): void
    {
        $player = GamePlayer::where('game_room_id', $room->id)->findOrFail($playerId);
        $before = $player->money;

        DB::transaction(function () use ($player, $action, $amount) {
            if ($action === 'add') {
                $this->tx->credit($player, $amount);
            } elseif ($action === 'subtract') {
                $this->tx->debit($player, $amount);
            } elseif ($action === 'set') {
                $diff = $amount - $player->money;
                if ($diff > 0) $this->tx->credit($player, $diff);
                elseif ($diff < 0) $this->tx->debit($player, abs($diff));
            }
        });

        $this->logAudit($owner, $room, 'adjust_money', $player, [
            'action' => $action,
            'amount' => $amount,
            'before' => $before,
            'after' => $player->money,
            'note' => $note,
        ]);
    }

    /**
     * Mengatur hasil dadu berikutnya
     */
    public function setDiceOverride(User $owner, GameRoom $room, ?int $targetPlayerId, int $die1, int $die2, int $uses = 1): void
    {
        $gmState = $this->getOrCreateGmState($room);
        
        $target = $targetPlayerId ? GamePlayer::find($targetPlayerId) : null;
        
        $gmState->dice_override = [
            'target_player_id' => $targetPlayerId,
            'die1' => $die1,
            'die2' => $die2,
            'remaining_uses' => $uses,
        ];
        $gmState->save();

        $this->logAudit($owner, $room, 'set_dice', $target, [
            'die1' => $die1,
            'die2' => $die2,
            'uses' => $uses,
            'target_name' => $target?->name,
        ]);
    }

    /**
     * Memindahkan posisi bidak
     */
    public function movePlayer(User $owner, GameRoom $room, int $playerId, int $tileId, bool $triggerEffect = true): void
    {
        $player = GamePlayer::where('game_room_id', $room->id)->findOrFail($playerId);
        $oldPos = $player->position;
        
        $this->movement->jumpTo($player, $tileId);
        
        if ($triggerEffect) {
            // Logika sederhana: jika trigger effect, kita asumsikan ini seperti mendarat normal
            // Namun karena ini GM, kita mungkin tidak ingin menjalankan seluruh resolveLanding yang kompleks
            // Untuk keamanan, kita hanya update posisi. Efek bisa ditambahkan manual jika perlu.
            $this->state->log($room, 'gm_move', "GM memindahkan {$player->name} ke petak {$tileId}.");
        } else {
            $this->state->log($room, 'gm_move', "GM memindahkan {$player->name} ke petak {$tileId} tanpa efek.", ['secret' => true]);
        }

        $this->logAudit($owner, $room, 'move_player', $player, [
            'from' => $oldPos,
            'to' => $tileId,
            'trigger_effect' => $triggerEffect,
        ]);
    }

    /**
     * Memberikan properti
     */
    public function giveProperty(User $owner, GameRoom $room, int $playerId, int $tileId): void
    {
        $player = GamePlayer::where('game_room_id', $room->id)->findOrFail($playerId);
        $ownership = \App\Models\PropertyOwnership::where('game_room_id', $room->id)
            ->where('tile_id', $tileId)
            ->firstOrFail();
            
        $oldOwner = $ownership->owner_player_id;
        $ownership->owner_player_id = $player->id;
        $ownership->is_mortgaged = false; // Reset mortgage saat dipaksa pindah oleh GM
        $ownership->save();

        $tile = $this->board->tile($tileId);
        $this->state->log($room, 'gm_property', "GM memberikan {$tile['name']} kepada {$player->name}.");

        $this->logAudit($owner, $room, 'give_property', $player, [
            'tile_id' => $tileId,
            'tile_name' => $tile['name'],
            'previous_owner' => $oldOwner,
        ]);
    }

    /**
     * Membangun/Menghapus bangunan secara paksa
     */
    public function forceBuilding(User $owner, GameRoom $room, int $playerId, int $tileId, string $type): void
    {
        $player = GamePlayer::where('game_room_id', $room->id)->findOrFail($playerId);
        $ownership = \App\Models\PropertyOwnership::where('game_room_id', $room->id)
            ->where('tile_id', $tileId)
            ->firstOrFail();

        if ($type === 'add_house') {
            $ownership->houses = min(4, $ownership->houses + 1);
        } elseif ($type === 'remove_house') {
            $ownership->houses = max(0, $ownership->houses - 1);
        } elseif ($type === 'add_hotel') {
            $ownership->has_hotel = true;
            $ownership->houses = 0;
        } elseif ($type === 'remove_hotel') {
            $ownership->has_hotel = false;
        }
        $ownership->save();

        $tile = $this->board->tile($tileId);
        $this->state->log($room, 'gm_building', "GM mengubah bangunan di {$tile['name']} milik {$player->name}.");

        $this->logAudit($owner, $room, 'force_building', $player, [
            'tile_id' => $tileId,
            'type' => $type,
        ]);
    }

    /**
     * Mengubah giliran
     */
    public function changeTurn(User $owner, GameRoom $room, int $nextPlayerId): void
    {
        $nextPlayer = GamePlayer::find($nextPlayerId);
        $currentPlayer = GamePlayer::find($room->current_player_id);
        
        $this->turns->begin($room, $nextPlayer);
        
        $this->state->log($room, 'gm_turn', "GM mengganti giliran dari {$currentPlayer->name} ke {$nextPlayer->name}.");

        $this->logAudit($owner, $room, 'change_turn', $nextPlayer, [
            'from_player_id' => $currentPlayer->id,
            'to_player_id' => $nextPlayerId,
        ]);
    }

    /**
     * Toggle Chaos Mode
     */
    public function toggleChaosMode(User $owner, GameRoom $room, bool $enabled): void
    {
        $gmState = $this->getOrCreateGmState($room);
        $gmState->chaos_mode = $enabled;
        $gmState->save();

        $this->state->log($room, 'gm_event', "GM " . ($enabled ? 'mengaktifkan' : 'menonaktifkan') . " Chaos Mode.");
        
        $this->logAudit($owner, $room, 'toggle_chaos', null, ['enabled' => $enabled]);
    }

    private function getOrCreateGmState(GameRoom $room): GameMasterState
    {
        return GameMasterState::firstOrCreate(
            ['game_room_id' => $room->id],
            ['dice_override' => null, 'lucky' => null, 'rule_overrides' => null, 'chaos_mode' => false]
        );
    }

    private function logAudit(User $owner, GameRoom $room, string $action, ?GamePlayer $target, array $data): void
    {
        GameMasterAuditLog::create([
            'game_room_id' => $room->id,
            'user_id' => $owner->id,
            'action' => $action,
            'target_player_id' => $target?->id,
            'is_secret' => true, // Default secret untuk audit log
            'before' => null,
            'after' => $data,
            'note' => $data['note'] ?? null,
        ]);
    }
}