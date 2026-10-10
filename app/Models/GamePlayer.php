<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GamePlayer extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_ai' => 'boolean', 'is_ready' => 'boolean', 'is_connected' => 'boolean',
            'in_jail' => 'boolean', 'is_bankrupt' => 'boolean', 'last_seen_at' => 'datetime',
        ];
    }

    public function room() { return $this->belongsTo(GameRoom::class, 'game_room_id'); }
    public function user() { return $this->belongsTo(User::class); }
}