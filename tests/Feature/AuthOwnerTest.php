<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthOwnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_page_loads_with_get(): void
    {
        $this->get('/register')->assertOk();
    }

    public function test_public_registration_cannot_become_owner(): void
    {
        $this->post('/register', [
            'name' => 'Penyusup',
            'username' => 'penyusup',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'owner',
            'owner_flag' => 1,
        ])->assertRedirect(route('menu'));

        $user = User::where('username', 'penyusup')->firstOrFail();
        $this->assertSame('player', $user->role);
        $this->assertNull($user->owner_flag);
        $this->assertFalse($user->isOwner());
    }

    public function test_only_one_owner_can_be_created(): void
    {
        $this->artisan('monopoly:create-owner')
            ->expectsQuestion('Username owner', 'bossowner')
            ->expectsQuestion('Email (kosongkan jika tidak perlu)', '')
            ->expectsQuestion('Password (min. 12 karakter, tidak akan ditampilkan)', 'passwordkuat123')
            ->expectsQuestion('Ulangi password', 'passwordkuat123')
            ->assertExitCode(0);

        $this->assertTrue(User::where('username', 'bossowner')->firstOrFail()->isOwner());

        $this->artisan('monopoly:create-owner')->assertExitCode(1);
        $this->assertSame(1, User::where('owner_flag', 1)->count());
    }
}