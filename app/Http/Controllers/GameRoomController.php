<?php

namespace App\Http\Controllers;

use App\Models\GamePlayer;
use App\Models\GameRoom;
use App\Services\GameStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GameRoomController extends Controller
{
    /**
     * Halaman lobby multiplayer - daftar room yang tersedia
     */
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

    /**
     * Buat room multiplayer baru
     */
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

        // Host otomatis bergabung sebagai pemain pertama
        GamePlayer::create([
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

        return redirect()->route('lobby.show', $room->code);
    }

    /**
     * Gabung ke room menggunakan kode
     */
    public function join(Request $request, string $code): RedirectResponse
    {
        $room = GameRoom::where('code', $code)->firstOrFail();

        // Validasi room
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

        // Cek apakah user sudah ada di room ini
        $existingPlayer = GamePlayer::where('game_room_id', $room->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($existingPlayer) {
            // Reconnect: update status koneksi
            $existingPlayer->update([
                'is_connected' => true,
                'last_seen_at' => now(),
                'disconnected_at' => null,
                'reconnected_at' => now(),
            ]);

            return redirect()->route('lobby.show', $room->code);
        }

        // Cek apakah user sudah jadi AI di room lain dengan kode sama (cegah duplikat)
        $duplicateCheck = GamePlayer::where('game_room_id', $room->id)
            ->whereNull('user_id')
            ->exists();

        // Buat pemain baru
        $nextSeat = $room->players()->max('seat') + 1;
        $tokens = config('monopoly.tokens');

        GamePlayer::create([
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

        return redirect()->route('lobby.show', $room->code);
    }

    /**
     * Tampilkan detail lobby room
     */
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

    /**
     * Toggle ready status pemain
     */
    public function toggleReady(Request $request, GameRoom $room): JsonResponse
    {
        Gate::authorize('view', $room);

        $player = GamePlayer::where('game_room_id', $room->id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $player->is_ready = !$player->is_ready;
        $player->save();

        return response()->json([
            'success' => true,
            'is_ready' => $player->is_ready,
            'message' => $player->is_ready ? 'Anda siap bermain!' : 'Anda batal siap.',
        ]);
    }

    /**
     * Mulai permainan (hanya host)
     */
    public function start(Request $request, GameRoom $room): RedirectResponse
    {
        Gate::authorize('view', $room);

        // Hanya host yang bisa mulai
        if ((int) $room->host_user_id !== (int) $request->user()->id) {
            abort(403, 'Hanya host yang dapat memulai permainan.');
        }

        if (!$this->canStartGame($room, $request->user())) {
            throw ValidationException::withMessages([
                'start' => 'Minimal 2 pemain manusia harus siap untuk memulai.',
            ]);
        }

        // Inisialisasi properti dan kartu
        \App\Models\PropertyOwnership::where('game_room_id', $room->id)->delete();
        \App\Models\GameCardDeck::where('game_room_id', $room->id)->delete();

        $boardService = app(\App\Services\BoardService::class);
        foreach ($boardService->tiles() as $tile) {
            if ($boardService->isBuyable($tile)) {
                \App\Models\PropertyOwnership::create([
                    'game_room_id' => $room->id,
                    'tile_id' => $tile['id'],
                ]);
            }
        }

        $cardService = app(\App\Services\CardService::class);
        $cardService->ensureDecks($room);

        // Update status room
        $room->status = 'playing';
        $room->started_at = now();
        $room->state_version = 1;

        // Set pemain pertama (seat 0)
        $firstPlayer = $room->players()->orderBy('seat')->first();
        $room->current_player_id = $firstPlayer->id;
        $room->turn_phase = 'await_roll';
        $room->turn_number = 0;
        $room->save();

        // Log awal permainan
        $stateService = app(\App\Services\GameStateService::class);
        $stateService->log($room, 'system', "Permainan dimulai! {$firstPlayer->name} jalan pertama.");

        return redirect()->route('game.show', $room->code);
    }

    /**
     * Keluar dari room (sebelum permainan dimulai)
     */
    public function leave(Request $request, GameRoom $room): RedirectResponse
    {
        Gate::authorize('view', $room);

        if ($room->status !== 'waiting') {
            abort(403, 'Tidak dapat keluar setelah permainan dimulai.');
        }

        $player = GamePlayer::where('game_room_id', $room->id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        // Jika host keluar, transfer host atau hapus room
        if ((int) $room->host_user_id === (int) $request->user()->id) {
            $otherPlayers = $room->players()
                ->where('id', '!=', $player->id)
                ->orderBy('seat')
                ->first();

            if ($otherPlayers) {
                // Transfer host ke pemain berikutnya
                $room->host_user_id = $otherPlayers->user_id;
                $room->save();
            } else {
                // Tidak ada pemain lain, hapus room
                $room->delete();
                return redirect()->route('lobby.index');
            }
        }

        $player->delete();

        return redirect()->route('lobby.index');
    }

    /**
     * Cek apakah room bisa dimulai
     */
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