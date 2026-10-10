<?php

namespace App\Policies;

use App\Models\GameRoom;
use App\Models\User;

class GameRoomPolicy
{
    /** Hanya host atau pemain di room tersebut yang boleh melihat/mengakses game. */
    public function view(User $user, GameRoom $room): bool
    {
        return (int) $room->host_user_id === (int) $user->id || $room->hasMember($user);
    }
}