<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameCardDeck extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['order' => 'array', 'held' => 'array'];
    }
}