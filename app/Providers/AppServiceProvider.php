<?php

namespace App\Providers;

use App\Models\GameRoom;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Izin Game Master: owner sah + anggota room + Game Master Mode aktif di room tersebut.
        Gate::define('game-master', function (User $user, GameRoom $room): bool {
            return $user->isOwner()
                && $room->gm_enabled
                && $room->hasMember($user);
        });

        // Audit log privat: hanya owner, dan hanya untuk room yang ia ikuti.
        Gate::define('view-gm-audit', function (User $user, GameRoom $room): bool {
            return $user->isOwner() && $room->hasMember($user);
        });
    }
}