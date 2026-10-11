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

    private function ensureOwner(User $user): void
    {
        // Cek apakah user memiliki role owner melalui relasi atau field khusus
        if (!$user->hasRole('owner') && !$user->is_admin) {
            // Fallback: cek email atau ID khusus jika tidak ada role system
            $ownerEmails = config('monopoly.owner_emails', ['admin123@gmail.com']);
            if (!in_array($user->email, $ownerEmails)) {
                abort(403, 'Akses ditolak. Hanya Owner yang dapat menggunakan panel Game Master.');
            }
        }
    }

    public function enableGmMode(Request $request, GameRoom $room): JsonResponse
    {
        $this->ensureOwner($request->user());
        
        $this->gmService->enableGmMode($request->user(), $room);

        return response()->json(['message' => 'Game Master Mode diaktifkan.']);
    }

    public function adjustMoney(Request $request, GameRoom $room): JsonResponse
    {
        $this->ensureOwner($request->user());
        
        $validated = $request->validate([
            'player_id' => ['required', 'integer', 'exists:game_players,id'],
            'action' => ['required', 'string', 'in:add,subtract,set'],
            'amount' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $player = GamePlayer::findOrFail($validated['player_id']);
        if ($player->game_room_id != $room->id) {
            throw ValidationException::withMessages(['player_id' => 'Pemain tidak berada di room ini.']);
        }

        $this->gmService->adjustMoney(
            $request->user(), 
            $room, 
            $validated['player_id'], 
            $validated['action'], 
            $validated['amount'], 
            $validated['note'] ?? null
        );

        return response()->json(['message' => 'Saldo berhasil disesuaikan.']);
    }

    public function setDice(Request $request, GameRoom $room): JsonResponse
    {
        $this->ensureOwner($request->user());
        
        $validated = $request->validate([
            'die1' => ['required', 'integer', 'min:1', 'max:6'],
            'die2' => ['required', 'integer', 'min:1', 'max:6'],
            'target_player_id' => ['nullable', 'integer', 'exists:game_players,id'],
            'uses' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        $this->gmService->setDiceOverride(
            $request->user(), 
            $room, 
            $validated['target_player_id'] ?? null, 
            $validated['die1'], 
            $validated['die2'], 
            $validated['uses']
        );

        return response()->json(['message' => 'Dadu telah diatur.']);
    }

    public function movePlayer(Request $request, GameRoom $room): JsonResponse
    {
        $this->ensureOwner($request->user());
        
        $validated = $request->validate([
            'player_id' => ['required', 'integer', 'exists:game_players,id'],
            'tile_id' => ['required', 'integer', 'min:0', 'max:39'],
            'trigger_effect' => ['sometimes', 'boolean'],
        ]);

        $player = GamePlayer::findOrFail($validated['player_id']);
        if ($player->game_room_id != $room->id) {
            throw ValidationException::withMessages(['player_id' => 'Pemain tidak berada di room ini.']);
        }

        $this->gmService->movePlayer(
            $request->user(), 
            $room, 
            $validated['player_id'], 
            $validated['tile_id'], 
            $validated['trigger_effect'] ?? false
        );

        return response()->json(['message' => 'Posisi pemain diubah.']);
    }

    public function giveProperty(Request $request, GameRoom $room): JsonResponse
    {
        $this->ensureOwner($request->user());
        
        $validated = $request->validate([
            'player_id' => ['required', 'integer', 'exists:game_players,id'],
            'tile_id' => ['required', 'integer', 'min:0', 'max:39'],
        ]);

        $this->gmService->giveProperty($request->user(), $room, $validated['player_id'], $validated['tile_id']);

        return response()->json(['message' => 'Properti diberikan.']);
    }

    public function forceBuilding(Request $request, GameRoom $room): JsonResponse
    {
        $this->ensureOwner($request->user());
        
        $validated = $request->validate([
            'player_id' => ['required', 'integer', 'exists:game_players,id'],
            'tile_id' => ['required', 'integer', 'min:0', 'max:39'],
            'type' => ['required', 'string', 'in:add_house,remove_house,add_hotel,remove_hotel'],
        ]);

        $this->gmService->forceBuilding(
            $request->user(), 
            $room, 
            $validated['player_id'], 
            $validated['tile_id'], 
            $validated['type']
        );

        return response()->json(['message' => 'Bangunan diubah.']);
    }

    public function changeTurn(Request $request, GameRoom $room): JsonResponse
    {
        $this->ensureOwner($request->user());
        
        $validated = $request->validate([
            'next_player_id' => ['required', 'integer', 'exists:game_players,id'],
        ]);

        $this->gmService->changeTurn($request->user(), $room, $validated['next_player_id']);

        return response()->json(['message' => 'Giliran diganti.']);
    }

    public function toggleChaos(Request $request, GameRoom $room): JsonResponse
    {
        $this->ensureOwner($request->user());
        
        // Handle boolean properly from JSON
        $enabled = filter_var($request->input('enabled'), FILTER_VALIDATE_BOOLEAN);
        
        $this->gmService->toggleChaosMode($request->user(), $room, $enabled);

        return response()->json(['message' => 'Chaos mode ' . ($enabled ? 'diaktifkan' : 'dinonaktifkan') . '.']);
    }
}