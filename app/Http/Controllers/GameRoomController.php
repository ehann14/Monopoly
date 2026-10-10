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
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'max_players' => ['required', 'integer', 'min:2', 'max:4'],
            'is_public' => ['sometimes', 'boolean'],
        ]);

        $room = GameRoom::create([
            'code' => GameRoom::generateCode(),
            'name' => $data['name'] ?? null,
            'host_user_id' => $request->user()->id,
            'mode' => 'multiplayer',
            'status' => 'waiting',
            'max_players' => $data['max_players'],
            'is_public' => $data['is_public'] ?? true,
            'rules' => config('monopoly.rules'),
            'turn_phase' => 'await_roll',
            'state_version' => 0,
        ]);

        $player = GamePlayer::create([
            'game_room_id' => $room->id,
            'user_id' => $request->user()->id,
            'name' => mb_substr($request->user()->name, 0, 40),
            'is_ai' => false,
            'seat' => 0,
            'token' => config('monopoly.tokens')[0],
            'is_ready' => false,
            'position' => 0,
            'money' => (int) config('monopoly.rules.starting_money'),
        ]);

        // Broadcast player joined
        event(new GamePlayerJoined($room, $player));

        return redirect()->route('lobby.show', $room->code);
    }

    public function join(Request $request, string $code): RedirectResponse
    {
        $room = GameRoom::where('code', $code)->firstOrFail();

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

            event(new GamePlayerJoined($room, $existingPlayer));

            return redirect()->route('lobby.show', $room->code);
        }

        $nextSeat = $room->players()->max('seat') + 1;
        $tokens = config('monopoly.tokens');

        $player = GamePlayer::create([
            'game_room_id' => $room->id,
            'user_id' => $request->user()->id,
            'name' => mb_substr($request->user()->name, 0, 40),
            'is_ai' => false,
            'seat' => $nextSeat,
            'token' => $tokens[$nextSeat] ?? 'gray',
            'is_ready' => false,
            'position' => 0,
            'money' => (int) config('monopoly.rules.starting_money'),
        ]);

        // Broadcast player joined
        event(new GamePlayerJoined($room, $player));

        return redirect()->route('lobby.show', $room->code);
    }

    public function show(Request $request, GameRoom $room): View
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

        // Broadcast ready status change
        event(new GamePlayerReady($room, $player));

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

        if (!$this->canStartGame($room, $request->user())) {
            throw ValidationException::withMessages([
                'start' => 'Minimal 2 pemain manusia harus siap untuk memulai.',
            ]);
        }

        // Inisialisasi game melalui engine
        $engine->initializeMultiplayerGame($room);

        // Broadcast game started
        event(new GameStarted($room));

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

        // Broadcast sebelum hapus
        event(new GamePlayerLeft($room, $player));

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