<?php

namespace App\Http\Controllers;

use App\Exceptions\GameRuleException;
use App\Models\GameRoom;
use App\Services\GameEngine;
use App\Services\GameStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GameController extends Controller
{
    public function menu(Request $request): View
    {
        $rooms = GameRoom::where('host_user_id', $request->user()->id)
            ->whereIn('status', ['playing', 'paused'])
            ->latest()->limit(10)->get();

        return view('menu', ['rooms' => $rooms]);
    }

    public function store(Request $request, GameEngine $engine): RedirectResponse
    {
        // Buang kolom nama yang dikosongkan sebelum validasi.
        $names = array_values(array_filter(
            array_map(fn ($v) => is_string($v) ? trim($v) : '', (array) $request->input('players', [])),
            fn ($v) => $v !== ''
        ));
        $request->merge(['players' => $names]);

        $data = $request->validate([
            'players' => ['required', 'array', 'min:2', 'max:4'],
            'players.*' => ['required', 'string', 'min:1', 'max:20', 'distinct:ignore_case'],
        ], [
            'players.min' => 'Minimal 2 pemain.',
            'players.*.distinct' => 'Nama pemain tidak boleh sama.',
        ]);

        $room = $engine->createLocalGame($request->user(), $data['players']);

        return redirect()->route('game.show', $room->code);
    }

    public function storeAi(Request $request, GameEngine $engine): RedirectResponse
    {
        $names = array_values(array_filter(
            array_map(fn ($v) => is_string($v) ? trim($v) : '', (array) $request->input('ai_names', [])),
            fn ($v) => $v !== ''
        ));
        $request->merge(['ai_names' => $names]);

        $data = $request->validate([
            'difficulty' => ['required', 'in:easy,hard'],
            'ai_names' => ['required', 'array', 'min:1', 'max:3'],
            'ai_names.*' => ['required', 'string', 'min:1', 'max:20', 'distinct:ignore_case'],
        ], [
            'difficulty.required' => 'Pilih tingkat kesulitan.',
            'difficulty.in' => 'Tingkat kesulitan tidak valid.',
            'ai_names.required' => 'Isi minimal satu nama komputer.',
            'ai_names.min' => 'Isi minimal satu nama komputer.',
            'ai_names.max' => 'Maksimal 3 lawan komputer.',
            'ai_names.*.distinct' => 'Nama komputer tidak boleh sama.',
        ]);

        $human = mb_strtolower(mb_substr($request->user()->name, 0, 40));
        foreach ($data['ai_names'] as $name) {
            if (mb_strtolower($name) === $human) {
                throw ValidationException::withMessages([
                    'ai_names' => 'Nama komputer tidak boleh sama dengan nama Anda.',
                ]);
            }
        }

        $room = $engine->createAiGame($request->user(), $data['ai_names'], $data['difficulty']);

        return redirect()->route('game.show', $room->code);
    }

    public function show(Request $request, GameRoom $room, GameStateService $state): View
    {
        Gate::authorize('view', $room);

        return view('game', [
            'room' => $room,
            'clientData' => [
                'tiles' => config('monopoly.board'),
                'groups' => config('monopoly.groups'),
                'stationRent' => config('monopoly.station_rent'),
                'utilityMultiplier' => config('monopoly.utility_multiplier'),
                'snapshot' => $state->snapshot($room, $request->user()),
                'urls' => [
                    'state' => route('game.state', $room->code),
                    'action' => route('game.action', [$room->code, '__ACTION__']),
                    'aiStep' => route('game.ai.step', $room->code),
                    'menu' => route('menu'),
                ],
            ],
        ]);
    }

    public function state(Request $request, GameRoom $room, GameStateService $state): JsonResponse
    {
        Gate::authorize('view', $room);

        return response()->json($state->snapshot($room, $request->user()));
    }

    public function action(Request $request, GameRoom $room, string $action, GameEngine $engine, GameStateService $state): JsonResponse
    {
        Gate::authorize('view', $room);

        $key = $request->header('Idempotency-Key');
        $key = is_string($key) && $key !== '' ? substr($key, 0, 64) : null;
        $version = $request->input('expected_version');
        $version = is_numeric($version) ? (int) $version : null;

        // Hanya tile_id yang diteruskan ke engine; nilainya divalidasi ulang di sana.
        $tile = $request->input('tile_id');
        $params = ['tile_id' => is_numeric($tile) ? (int) $tile : null];

        try {
            $engine->perform($request->user(), $room, $action, $key, $version, $params);
        } catch (GameRuleException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($state->snapshot($room, $request->user()));
    }

    /** Menjalankan satu langkah giliran komputer. Boleh dipicu anggota room mana pun. */
    public function aiStep(Request $request, GameRoom $room, GameEngine $engine, GameStateService $state): JsonResponse
    {
        Gate::authorize('view', $room);

        $version = $request->input('expected_version');
        $version = is_numeric($version) ? (int) $version : null;

        try {
            $engine->aiStep($room, $version);
        } catch (GameRuleException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($state->snapshot($room, $request->user()));
    }
}