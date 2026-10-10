<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameActionRequest extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['response' => 'array', 'created_at' => 'datetime'];
    }
}