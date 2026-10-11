<?php

namespace App\Http\Controllers;

use App\Events\GamePlayerJoined;
use App\Events\GamePlayerLeft;
use App\Events\GamePlayerReady;
use App\Events\GameStarted;
use App\Models\GamePlayer;
use App\Models\GameRoom;
use App\Services\GameEngine;
use App\Services\GameStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GameRoomController extends Controller
{
    public function index(Request $request): View
    {
        $rooms = GameRoom::where('status', 'waiting')
            ->where('mode', 'multiplayer')
            ->where('is_public', true)
            ->with(['host', 'players'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('lobby', [
            'rooms' => $rooms,
            'userRooms' => GameRoom::where('host_user_id', $request->user()->id)
                ->whereIn('status', ['waiting', 'playing', 'paused'])
                ->latest()
                ->limit(5)
                ->get(),
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'max_players' => ['required', 'integer', 'min:2', 'max:4'],
            'is_public' => ['sometimes', 'boolean'],
        ]);

        // Buat room tanpa mass-assignment pada guarded fields
        $room = new GameRoom();
        $room->code = GameRoom::generateCode();
        $room->name = $validated['name'] ?? null;
        $room->host_user_id = $request->user()->id;
        $room->mode = 'multiplayer';
        $room->status = 'waiting';
        $room->max_players = $validated['max_players'];
        $room->is_public = $validated['is_public'] ?? true;
        $room->rules = config('monopoly.rules');
        $room->turn_phase = 'await_roll';
        $room->state_version = 0;
        $room->save();

        $player = new GamePlayer();
        $player->game_room_id = $room->id;
        $player->user_id = $request->user()->id;
        $player->name = mb_substr($request->user()->name, 0, 40);
        $player->is_ai = false;
        $player->seat = 0;
        $player->token = config('monopoly.tokens')[0];
        $player->is_ready = false;
        $player->position = 0;
        $player->money = (int) config('monopoly.rules.starting_money');
        $player->save();

        try {
            event(new GamePlayerJoined($room, $player));
        } catch (\Exception $e) {
            Log::warning('Failed to broadcast player joined: ' . $e->getMessage());
        }

        return redirect()->route('lobby.show', $room->code);
    }

    public function join(Request $request, string $code): RedirectResponse
    {
        $room = GameRoom::where('code', strtoupper($code))->firstOrFail();

        if ($room->status !== 'waiting') {
            throw ValidationException::withMessages([
                'code' => 'Room sudah dimulai atau tidak aktif.',
            ]);
        }

        if ($room->players()->count() >= $room->max_players) {
            throw ValidationException::withMessages([
                'code' => 'Room sudah penuh.',
            ]);
        }

        $existingPlayer = GamePlayer::where('game_room_id', $room->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($existingPlayer) {
            $existingPlayer->update([
                'is_connected' => true,
                'last_seen_at' => now(),
                'disconnected_at' => null,
                'reconnected_at' => now(),
            ]);

            try {
                event(new GamePlayerJoined($room, $existingPlayer));
            } catch (\Exception $e) {
                Log::warning('Failed to broadcast rejoin: ' . $e->getMessage());
            }

            return redirect()->route('lobby.show', $room->code);
        }

        $nextSeat = $room->players()->max('seat') + 1;
        $tokens = config('monopoly.tokens');

        $player = new GamePlayer();
        $player->game_room_id = $room->id;
        $player->user_id = $request->user()->id;
        $player->name = mb_substr($request->user()->name, 0, 40);
        $player->is_ai = false;
        $player->seat = $nextSeat;
        $player->token = $tokens[$nextSeat] ?? 'gray';
        $player->is_ready = false;
        $player->position = 0;
        $player->money = (int) config('monopoly.rules.starting_money');
        $player->save();

        try {
            event(new GamePlayerJoined($room, $player));
        } catch (\Exception $e) {
            Log::warning('Failed to broadcast player joined: ' . $e->getMessage());
        }

        return redirect()->route('lobby.show', $room->code);
    }

    public function show(Request $request, GameRoom $room)
    {
        Gate::authorize('view', $room);

        if ($room->status === 'playing') {
            return redirect()->route('game.show', $room->code);
        }

        $players = $room->players()->orderBy('seat')->get();
        $currentUserPlayer = $players->firstWhere('user_id', $request->user()->id);

        return view('lobby-room', [
            'room' => $room,
            'players' => $players,
            'currentUserPlayer' => $currentUserPlayer,
            'canStart' => $this->canStartGame($room, $request->user()),
        ]);
    }

    public function toggleReady(Request $request, GameRoom $room): JsonResponse
    {
        Gate::authorize('view', $room);

        $player = GamePlayer::where('game_room_id', $room->id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $player->is_ready = !$player->is_ready;
        $player->save();

        try {
            event(new GamePlayerReady($room, $player));
        } catch (\Exception $e) {
            Log::warning('Failed to broadcast ready status: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'is_ready' => $player->is_ready,
            'message' => $player->is_ready ? 'Anda siap bermain!' : 'Anda batal siap.',
        ]);
    }

    public function start(Request $request, GameRoom $room, GameEngine $engine): RedirectResponse
    {
        Gate::authorize('view', $room);

        if ((int) $room->host_user_id !== (int) $request->user()->id) {
            abort(403, 'Hanya host yang dapat memulai permainan.');
        }

        // Cegah reset game yang sudah berjalan
        if ($room->status === 'playing') {
            return redirect()->route('game.show', $room->code);
        }

        if (!$this->canStartGame($room, $request->user())) {
            throw ValidationException::withMessages([
                'start' => 'Minimal 2 pemain manusia harus siap untuk memulai.',
            ]);
        }

        $engine->initializeMultiplayerGame($room);

        try {
            event(new GameStarted($room));
        } catch (\Exception $e) {
            Log::warning('Failed to broadcast game started: ' . $e->getMessage());
        }

        return redirect()->route('game.show', $room->code);
    }

    public function leave(Request $request, GameRoom $room): RedirectResponse
    {
        Gate::authorize('view', $room);

        if ($room->status !== 'waiting') {
            abort(403, 'Tidak dapat keluar setelah permainan dimulai.');
        }

        $player = GamePlayer::where('game_room_id', $room->id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        try {
            event(new GamePlayerLeft($room, $player));
        } catch (\Exception $e) {
            Log::warning('Failed to broadcast player left: ' . $e->getMessage());
        }

        if ((int) $room->host_user_id === (int) $request->user()->id) {
            $otherPlayers = $room->players()
                ->where('id', '!=', $player->id)
                ->orderBy('seat')
                ->first();

            if ($otherPlayers) {
                $room->host_user_id = $otherPlayers->user_id;
                $room->save();
            } else {
                $room->delete();
                return redirect()->route('lobby.index');
            }
        }

        $player->delete();

        return redirect()->route('lobby.index');
    }

    private function canStartGame(GameRoom $room, $user): bool
    {
        if ((int) $room->host_user_id !== (int) $user->id) {
            return false;
        }

        $humanPlayers = $room->players()
            ->where('is_ai', false)
            ->where('is_ready', true)
            ->count();

        return $humanPlayers >= 2;
    }
}