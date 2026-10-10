<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    // 'role' dan 'owner_flag' SENGAJA tidak ada di sini: tidak bisa di-mass-assign dari request.
    protected $fillable = ['name', 'username', 'email', 'password'];

    protected $hidden = ['password', 'remember_token', 'role', 'owner_flag'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner' && (int) $this->owner_flag === 1;
    }
}