<?php

namespace App\Http\Controllers;

use App\Models\GamePlayer;
use App\Models\GameRoom;
use App\Models\User;
use App\Services\GameMasterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GameMasterController extends Controller
{
    public function __construct(
        private GameMasterService $gmService
    ) {
    }

    public function adjustMoney(Request $request, GameRoom $room): JsonResponse
    {
        $this->ensureOwner($request->user());
        
        $data = $request->validate([
            'player_id' => 'required|exists:game_players,id',
            'action' => 'required|in:add,subtract,set',
            'amount' => 'required|integer|min:0',
            'note' => 'nullable|string|max:255',
        ]);

        // Validasi player belongs to room
        $player = GamePlayer::findOrFail($data['player_id']);
        if ($player->game_room_id != $room->id) {
            throw new ValidationException(['player_id' => 'Pemain tidak berada di room ini.']);
        }

        $this->gmService->adjustMoney($request->user(), $room, $data['player_id'], $data['action'], $data['amount'], $data['note']);

        return response()->json(['message' => 'Saldo berhasil disesuaikan.']);
    }

    public function setDice(Request $request, GameRoom $room): JsonResponse
    {
        $this->ensureOwner($request->user());
        
        $data = $request->validate([
            'die1' => 'required|integer|min:1|max:6',
            'die2' => 'required|integer|min:1|max:6',
            'target_player_id' => 'nullable|exists:game_players,id',
            'uses' => 'required|integer|min:1|max:10',
        ]);

        $this->gmService->setDiceOverride($request->user(), $room, $data['target_player_id'], $data['die1'], $data['die2'], $data['uses']);

        return response()->json(['message' => 'Dadu telah diatur.']);
    }

    public function movePlayer(Request $request, GameRoom $room): JsonResponse
    {
        $this->ensureOwner($request->user());
        
        $data = $request->validate([
            'player_id' => 'required|exists:game_players,id',
            'tile_id' => 'required|integer|min:0|max:39',
            'trigger_effect' => 'boolean',
        ]);

        $player = GamePlayer::findOrFail($data['player_id']);
        if ($player->game_room_id != $room->id) {
            throw new ValidationException(['player_id' => 'Pemain tidak berada di room ini.']);
        }

        $this->gmService->movePlayer($request->user(), $room, $data['player_id'], $data['tile_id'], $data['trigger_effect']);

        return response()->json(['message' => 'Posisi pemain diubah.']);
    }

    public function giveProperty(Request $request, GameRoom $room): JsonResponse
    {
        $this->ensureOwner($request->user());
        
        $data = $request->validate([
            'player_id' => 'required|exists:game_players,id',
            'tile_id' => 'required|integer|min:0|max:39',
        ]);

        $this->gmService->giveProperty($request->user(), $room, $data['player_id'], $data['tile_id']);

        return response()->json(['message' => 'Properti diberikan.']);
    }

    public function forceBuilding(Request $request, GameRoom $room): JsonResponse
    {
        $this->ensureOwner($request->user());
        
        $data = $request->validate([
            'player_id' => 'required|exists:game_players,id',
            'tile_id' => 'required|integer|min:0|max:39',
            'type' => 'required|in:add_house,remove_house,add_hotel,remove_hotel',
        ]);

        $this->gmService->forceBuilding($request->user(), $room, $data['player_id'], $data['tile_id'], $data['type']);

        return response()->json(['message' => 'Bangunan diubah.']);
    }

    public function changeTurn(Request $request, GameRoom $room): JsonResponse
    {
        $this->ensureOwner($request->user());
        
        $data = $request->validate([
            'next_player_id' => 'required|exists:game_players,id',
        ]);

        $this->gmService->changeTurn($request->user(), $room, $data['next_player_id']);

        return response()->json(['message' => 'Giliran diganti.']);
    }

    public function toggleChaos(Request $request, GameRoom $room): JsonResponse
    {
        $this->ensureOwner($request->user());
        
        $enabled = $request->input('enabled', false);
        $this->gmService->toggleChaosMode($request->user(), $room, $enabled);

        return response()->json(['message' => 'Chaos mode ' . ($enabled ? 'diaktifkan' : 'dinonaktifkan') . '.']);
    }

    private function ensureOwner(User $user): void
    {
        if (!$user->is_owner) {
            abort(403, 'Akses ditolak. Hanya Owner yang dapat menggunakan panel Game Master.');
        }
    }
} 