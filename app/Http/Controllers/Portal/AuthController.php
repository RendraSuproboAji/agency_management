<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('portal.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $client = Client::where('email', $credentials['email'])->first();

        // Akses portal harus dinyalakan admin dan punya kata sandi, terpisah
        // dari status klien itu sendiri.
        if (! $client?->canUsePortal() || ! Hash::check($credentials['password'], $client->password)) {
            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi tidak cocok, atau portal belum diaktifkan.',
            ]);
        }

        Auth::guard('client')->login($client, $request->boolean('remember'));
        $request->session()->regenerate();
        $client->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('portal.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('client')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }
}
