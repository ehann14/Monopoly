<?php

namespace App\Events;

use App\Models\GameRoom;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameStateUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public GameRoom $room,
        public array $snapshot,
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("game.{$this->room->code}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'snapshot' => $this->snapshot,
            'version' => $this->room->state_version,
        ];
    }
}