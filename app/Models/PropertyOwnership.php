<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyOwnership extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['has_hotel' => 'boolean', 'is_mortgaged' => 'boolean'];
    }

    public function owner() { return $this->belongsTo(GamePlayer::class, 'owner_player_id'); }
}