<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameMasterAuditLog extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'is_secret' => 'boolean', 'created_at' => 'datetime'];
    }
}