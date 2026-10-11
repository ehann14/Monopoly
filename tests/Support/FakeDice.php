<?php

namespace Tests\Support;

use App\Models\GamePlayer;
use App\Models\GameRoom;

class FakeDice
{
    private array $sequence = [];
    private int $index = 0;

    public function setSequence(array $sequence): void
    {
        $this->sequence = $sequence;
        $this->index = 0;
    }

    public function roll(?GameRoom $room = null, ?GamePlayer $player = null): array
    {
        if (empty($this->sequence)) {
            // Default random jika tidak ada sequence
            $d1 = random_int(1, 6);
            $d2 = random_int(1, 6);
        } else {
            $value = $this->sequence[$this->index % count($this->sequence)];
            $this->index++;
            
            if (is_array($value)) {
                $d1 = $value[0];
                $d2 = $value[1];
            } else {
                $d1 = $value;
                $d2 = $value;
            }
        }

        return [
            'd1' => $d1,
            'd2' => $d2,
            'total' => $d1 + $d2,
            'double' => $d1 === $d2,
            'overridden' => !empty($this->sequence),
        ];
    }
}