<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateOwner extends Command
{
    protected $signature = 'monopoly:create-owner {--username= : Username owner} {--email= : Email (opsional)}';
    protected $description = 'Membuat satu-satunya akun OWNER / Game Master (password diminta secara aman).';

    public function handle(): int
    {
        if (User::where('owner_flag', 1)->exists()) {
            $this->error('Akun owner sudah ada. Hanya satu owner yang diizinkan.');
            return self::FAILURE;
        }

        $username = $this->option('username') ?: $this->ask('Username owner');
        $email = $this->option('email') ?: $this->ask('Email (kosongkan jika tidak perlu)');
        $password = $this->secret('Password (min. 12 karakter, tidak akan ditampilkan)');
        $confirm = $this->secret('Ulangi password');

        $validator = Validator::make(
            ['username' => $username, 'email' => $email ?: null, 'password' => $password, 'password_confirmation' => $confirm],
            [
                'username' => ['required', 'alpha_dash', 'min:3', 'max:30', 'unique:users,username'],
                'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:12', 'confirmed'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }
            return self::FAILURE;
        }

        // forceFill: role & owner_flag memang tidak ada di $fillable.
        $user = (new User())->forceFill([
            'name' => $username,
            'username' => $username,
            'email' => $email ?: null,
            'password' => Hash::make($password),
            'role' => 'owner',
            'owner_flag' => 1,
        ]);
        $user->save();

        $this->info("Akun owner '{$username}' berhasil dibuat.");
        return self::SUCCESS;
    }
}