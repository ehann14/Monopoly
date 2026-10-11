<?php

namespace Tests\Support;

use App\Models\GamePlayer;
use App\Models\GameRoom;
use App\Services\DiceService;

class FakeDice extends DiceService
{
    public array $queue = [];

    public function roll(?GameRoom $room = null, ?GamePlayer $player = null): array
    {
        if (!empty($this->queue)) {
            $next = array_shift($this->queue);
            
            // Support format [d1, d2] atau integer (double)
            if (is_array($next)) {
                $d1 = $next[0];
                $d2 = $next[1];
            } else {
                $d1 = $next;
                $d2 = $next;
            }
        } else {
            // Fallback random jika queue habis
            $d1 = random_int(1, 6);
            $d2 = random_int(1, 6);
        }

        return [
            'd1' => $d1,
            'd2' => $d2,
            'total' => $d1 + $d2,
            'double' => $d1 === $d2,
            'overridden' => true,
        ];
    }
}