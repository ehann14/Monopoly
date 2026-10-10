<?php

namespace App\Services;

use App\Exceptions\GameRuleException;

class DiceService
{
    /**
     * Lempar dua dadu di server. $forced = [d1, d2] disiapkan untuk Game Master (Tahap 6);
     * hasilnya tetap diproses engine seperti lemparan biasa.
     */
    public function roll(?array $forced = null): array
    {
        if ($forced !== null) {
            $forced = array_values($forced);
            if (count($forced) !== 2) {
                throw new GameRuleException('Dadu harus berisi dua nilai.');
            }
            [$d1, $d2] = $forced;
            foreach ([$d1, $d2] as $d) {
                if (! is_int($d) || $d < 1 || $d > 6) {
                    throw new GameRuleException('Nilai dadu harus 1 sampai 6.');
                }
            }
        } else {
            $d1 = random_int(1, 6);
            $d2 = random_int(1, 6);
        }

        return ['d1' => $d1, 'd2' => $d2, 'total' => $d1 + $d2, 'double' => $d1 === $d2];
    }
}