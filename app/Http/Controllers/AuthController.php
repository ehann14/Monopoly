<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'max:200'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['username' => 'Username atau password salah.'])
                ->onlyInput('username');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('menu'));
    }

    public function register(Request $request): RedirectResponse
    {
        // Hanya field ini yang dibaca. Field "role" dari request diabaikan sepenuhnya.
        $data = $request->validate([
            'name' => ['required', 'string', 'max:40'],
            'username' => ['required', 'alpha_dash', 'min:3', 'max:30', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8', 'max:200', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'password' => $data['password'], // di-hash otomatis oleh cast 'hashed'
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('menu');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}