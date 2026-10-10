<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameMasterState extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['dice_override', 'lucky', 'rule_overrides', 'chaos_mode']; // jaga-jaga bila ter-serialize

    protected function casts(): array
    {
        return [
            'dice_override' => 'array', 'lucky' => 'array',
            'rule_overrides' => 'array', 'chaos_mode' => 'boolean',
        ];
    }
}