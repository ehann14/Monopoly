<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GameRoom extends Model
{
    protected $guarded = ['id', 'state_version', 'status', 'host_user_id'];

    protected function casts(): array
    {
        return [
            'rules' => 'array',
            'last_dice' => 'array',
            'pending' => 'array',
            'gm_enabled' => 'boolean',
            'gm_announced' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function host() { return $this->belongsTo(User::class, 'host_user_id'); }
    public function players(): HasMany { return $this->hasMany(GamePlayer::class)->orderBy('seat'); }
    public function properties(): HasMany { return $this->hasMany(PropertyOwnership::class); }
    public function logs(): HasMany { return $this->hasMany(GameLog::class); }
    public function gmState(): HasOne { return $this->hasOne(GameMasterState::class); }

    public function hasMember(User $user): bool
    {
        return $this->players()->where('user_id', $user->id)->exists();
    }

    public static function generateCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // tanpa karakter ambigu
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (static::where('code', $code)->exists());

        return $code;
    }
}