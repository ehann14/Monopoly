<?php

namespace App\Services;

use App\Events\GameStateChanged;
use App\Models\GameLog;
use App\Models\GamePlayer;
use App\Models\GameRoom;
use App\Models\PropertyOwnership;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class GameStateService
{
    public function snapshot(GameRoom $room, User $user): array
    {
        $players = $room->players()->orderBy('seat')->get();
        $properties = PropertyOwnership::where('game_room_id', $room->id)->get();
        $logs = GameLog::where('game_room_id', $room->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->reverse()
            ->values();

        $currentPlayer = $players->firstWhere('id', $room->current_player_id);
        $currentUserPlayer = $players->firstWhere('user_id', $user->id);

        // Tentukan aksi yang tersedia untuk pemain saat ini
        $actions = [];
        $canAct = false;
        
        if ($currentPlayer && !$currentPlayer->is_ai) {
            $isMyTurn = $currentUserPlayer && $currentUserPlayer->id === $currentPlayer->id;
            $isLocalHost = $room->mode === 'local' && $room->host_user_id === $user->id;
            
            if ($isMyTurn || $isLocalHost) {
                $canAct = true;
                
                if ($room->turn_phase === 'await_roll') {
                    $actions[] = 'roll';
                    if ($currentPlayer->in_jail) {
                        $actions[] = 'pay_jail';
                        if ($currentPlayer->jail_cards > 0) {
                            $actions[] = 'use_jail_card';
                        }
                    }
                } elseif ($room->turn_phase === 'await_buy') {
                    $actions[] = 'buy';
                    $actions[] = 'decline';
                } elseif ($room->turn_phase === 'await_end') {
                    $actions[] = 'end_turn';
                    $actions[] = 'build';
                    $actions[] = 'sell_building';
                    $actions[] = 'mortgage';
                    $actions[] = 'unmortgage';
                } elseif ($room->turn_phase === 'await_debt') {
                    $actions[] = 'pay_debt';
                    $actions[] = 'declare_bankruptcy';
                    $actions[] = 'sell_building';
                    $actions[] = 'mortgage';
                }
            }
        }

        // Cek apakah AI harus jalan
        $aiTurn = $currentPlayer && $currentPlayer->is_ai && !$currentPlayer->is_bankrupt && $room->status === 'playing';

        return [
            'room' => [
                'code' => $room->code,
                'status' => $room->status,
                'mode' => $room->mode,
                'turn_phase' => $room->turn_phase,
                'current_player_id' => $room->current_player_id,
                'turn_number' => $room->turn_number,
                'doubles_count' => $room->doubles_count,
                'last_dice' => $room->last_dice,
                'pending' => $room->pending,
                'winner_player_id' => $room->winner_player_id,
                'gm_enabled' => $room->gm_enabled,
            ],
            'players' => $players->map(fn (GamePlayer $p) => [
                'id' => $p->id,
                'name' => e($p->name), // Escape untuk mencegah XSS
                'token' => $p->token,
                'money' => $p->money,
                'position' => $p->position,
                'is_ai' => $p->is_ai,
                'in_jail' => $p->in_jail,
                'jail_turns' => $p->jail_turns,
                'jail_cards' => $p->jail_cards,
                'is_bankrupt' => $p->is_bankrupt,
                'is_connected' => $p->is_connected,
                'user_id' => $p->user_id,
            ])->toArray(),
            'properties' => $properties->map(fn (PropertyOwnership $po) => [
                'tile_id' => $po->tile_id,
                'owner_player_id' => $po->owner_player_id,
                'houses' => $po->houses,
                'has_hotel' => $po->has_hotel,
                'is_mortgaged' => $po->is_mortgaged,
            ])->toArray(),
            'logs' => $logs->map(fn (GameLog $l) => [
                'type' => $l->type,
                'message' => e($l->message), // Escape untuk mencegah XSS
            ])->toArray(),
            'version' => $room->state_version,
            'phase' => $room->turn_phase,
            'actions' => $actions,
            'can_act' => $canAct,
            'manage' => $currentUserPlayer && ($room->host_user_id === $user->id || $room->mode === 'local'),
            'ai_turn' => $aiTurn,
        ];
    }

    public function log(GameRoom $room, string $type, string $message, ?array $payload = null): void
    {
        GameLog::create([
            'game_room_id' => $room->id,
            'state_version' => $room->state_version,
            'type' => $type,
            'message' => $message,
            'payload' => $payload,
        ]);
    }

    public function broadcastState(GameRoom $room): void
    {
        try {
            // Ambil host sebagai referensi untuk membuat snapshot publik
            $host = $room->load('host')->host;
            if (!$host) {
                return;
            }

            $snapshot = $this->snapshot($room, $host);
            
            // Broadcast hanya versi dan event, bukan data sensitif
            event(new GameStateChanged($room, $snapshot));
        } catch (\Exception $e) {
            Log::warning('Broadcast failed: ' . $e->getMessage());
        }
    }
}