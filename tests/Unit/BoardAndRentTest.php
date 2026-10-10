<?php

namespace Tests\Unit;

use App\Services\BoardService;
use App\Services\RentService;
use Tests\TestCase;

class BoardAndRentTest extends TestCase
{
    public function test_board_has_40_consistent_tiles(): void
    {
        $board = config('monopoly.board');
        $this->assertCount(40, $board);

        foreach ($board as $index => $tile) {
            $this->assertSame($index, $tile['id']);
        }

        $types = array_count_values(array_column($board, 'type'));
        $this->assertSame(22, $types['property']);
        $this->assertSame(4, $types['station']);
        $this->assertSame(2, $types['utility']);

        foreach ($board as $tile) {
            if ($tile['type'] === 'property') {
                $this->assertCount(6, $tile['rent']);
                $this->assertArrayHasKey($tile['group'], config('monopoly.groups'));
            }
        }
    }

    public function test_each_color_group_is_complete(): void
    {
        $board = app(BoardService::class);
        $expected = ['brown' => 2, 'lightblue' => 3, 'pink' => 3, 'orange' => 3,
            'red' => 3, 'yellow' => 3, 'green' => 3, 'blue' => 2];

        foreach ($expected as $group => $count) {
            $this->assertCount($count, $board->groupTileIds($group), $group);
        }
    }

    public function test_rent_rules(): void
    {
        $rent = app(RentService::class);
        $board = app(BoardService::class);
        $rules = config('monopoly.rules');
        $plain = ['is_mortgaged' => false, 'has_hotel' => false, 'houses' => 0];

        $singkawang = $board->tile(3);
        $this->assertSame(4, $rent->rentFor($rules, $singkawang, $plain, [3], 7));
        $this->assertSame(8, $rent->rentFor($rules, $singkawang, $plain, [1, 3], 7));
        $this->assertSame(60, $rent->rentFor($rules, $singkawang, ['houses' => 2] + $plain, [1, 3], 7));
        $this->assertSame(450, $rent->rentFor($rules, $singkawang, ['has_hotel' => true] + $plain, [1, 3], 7));
        $this->assertSame(0, $rent->rentFor($rules, $singkawang, ['is_mortgaged' => true] + $plain, [1, 3], 7));

        $station = $board->tile(5);
        $this->assertSame(25, $rent->rentFor($rules, $station, $plain, [5], 7));
        $this->assertSame(100, $rent->rentFor($rules, $station, $plain, [5, 15, 25], 7));

        $utility = $board->tile(12);
        $this->assertSame(36, $rent->rentFor($rules, $utility, $plain, [12], 9));
        $this->assertSame(90, $rent->rentFor($rules, $utility, $plain, [12, 28], 9));
    }
}