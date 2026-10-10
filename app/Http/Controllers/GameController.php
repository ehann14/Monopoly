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

        try {
            $engine->perform($request->user(), $room, $action, $key, $version);
        } catch (GameRuleException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($state->snapshot($room, $request->user()));
    }
}