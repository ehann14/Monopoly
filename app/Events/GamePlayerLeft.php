<?php

namespace App\Events;

use App\Models\GamePlayer;
use App\Models\GameRoom;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GamePlayerLeft implements ShouldBroadcastNow
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
            'player_id' => $this->player->id,
            'player_name' => $this->player->name,
        ];
    }
}