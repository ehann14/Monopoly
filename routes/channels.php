<?php

use App\Models\GameRoom;
use App\Models\GamePlayer;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Presence channel untuk lobby game
 * Semua anggota room bisa join
 */
Broadcast::channel('game.{code}', function ($user, $code) {
    $room = GameRoom::where('code', $code)->first();
    
    if (!$room) {
        return false;
    }

    // Izinkan jika user adalah member room
    if ($room->hasMember($user)) {
        $player = GamePlayer::where('game_room_id', $room->id)
            ->where('user_id', $user->id)
            ->first();

        return $player ? [
            'id' => $player->id,
            'name' => $player->name,
            'seat' => $player->seat,
            'token' => $player->token,
        ] : false;
    }

    // Izinkan guest untuk melihat lobby (tapi data terbatas)
    return [
        'id' => null,
        'name' => 'Guest',
        'seat' => null,
        'token' => null,
    ];
});

/**
 * Private channel untuk update state game
 * Hanya member room yang bisa subscribe
 */
Broadcast::channel('game.{code}.state', function ($user, $code) {
    $room = GameRoom::where('code', $code)->first();
    
    if (!$room) {
        return false;
    }

    return $room->hasMember($user);
});