<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameMasterState extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'dice_override' => 'array',
            'lucky' => 'array',
            'rule_overrides' => 'array',
            'chaos_mode' => 'boolean',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(GameRoom::class, 'game_room_id');
    }
}