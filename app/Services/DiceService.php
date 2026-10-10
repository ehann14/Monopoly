<?php

namespace App\Services;

use App\Models\GameMasterState;
use App\Models\GamePlayer;
use App\Models\GameRoom;

class DiceService
{
    /**
     * @param GameRoom|null $room Jika ada, cek override GM
     * @param GamePlayer|null $player Pemain yang sedang melempar
     */
    public function roll(?GameRoom $room = null, ?GamePlayer $player = null): array
    {
        // Cek apakah ada override dari Game Master
        if ($room && $player) {
            $gmState = GameMasterState::where('game_room_id', $room->id)->first();
            
            if ($gmState && !empty($gmState->dice_override)) {
                $override = $gmState->dice_override;
                
                // Cek apakah override ditujukan untuk pemain ini atau global (null target)
                if ($override['target_player_id'] == null || $override['target_player_id'] == $player->id) {
                    if ($override['remaining_uses'] > 0) {
                        $d1 = (int) $override['die1'];
                        $d2 = (int) $override['die2'];
                        
                        // Kurangi sisa penggunaan
                        $override['remaining_uses']--;
                        if ($override['remaining_uses'] <= 0) {
                            $gmState->dice_override = null;
                        } else {
                            $gmState->dice_override = $override;
                        }
                        $gmState->save();
                        
                        return [
                            'd1' => $d1,
                            'd2' => $d2,
                            'total' => $d1 + $d2,
                            'double' => $d1 === $d2,
                            'overridden' => true,
                        ];
                    }
                }
            }
        }

        // Normal random roll
        $d1 = random_int(1, 6);
        $d2 = random_int(1, 6);

        return [
            'd1' => $d1,
            'd2' => $d2,
            'total' => $d1 + $d2,
            'double' => $d1 === $d2,
            'overridden' => false,
        ];
    }
}