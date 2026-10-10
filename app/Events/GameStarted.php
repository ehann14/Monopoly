<?php

namespace App\Events;

use App\Models\GameRoom;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameStarted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public GameRoom $room,
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
            'room_code' => $this->room->code,
            'started_at' => $this->room->started_at->toIso8601String(),
        ];
    }
}