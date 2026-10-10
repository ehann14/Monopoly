<?php

namespace Tests\Support;

use App\Services\DiceService;

/** Dadu terkontrol untuk test: isi $queue dengan pasangan [d1, d2]. Kosong = acak. */
class FakeDice extends DiceService
{
    public array $queue = [];

    public function roll(?array $forced = null): array
    {
        return parent::roll(array_shift($this->queue));
    }
}