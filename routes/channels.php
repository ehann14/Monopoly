<?php

use App\Models\GameRoom;
use App\Models\GamePlayer;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Presence channel untuk lobby game
 */
Broadcast::channel('game.{code}', function ($user, $code) {
    $room = GameRoom::where('code', $code)->first();

    if (!$room) {
        return false;
    }

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

    return false;
});