<?php

namespace App\Events;

use App\Models\GamePlayer;
use App\Models\GameRoom;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GamePlayerJoined implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public GameRoom $room,
        public GamePlayer $player,
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel("game.{$this->room->code}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'player' => [
                'id' => $this->player->id,
                'name' => $this->player->name,
                'seat' => $this->player->seat,
                'token' => $this->player->token,
                'is_ready' => $this->player->is_ready,
            ],
        ];
    }
}