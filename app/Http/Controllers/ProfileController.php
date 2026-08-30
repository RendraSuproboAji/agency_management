<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Profil sendiri, untuk staf maupun klien.
 *
 * Sebelumnya mengganti kata sandi selalu lewat admin — admin mengetikkan kata
 * sandi baru dan menyampaikannya entah lewat apa. Di sini pemiliknya sendiri
 * yang menggantinya, dan harus membuktikan tahu kata sandi lamanya supaya sesi
 * yang tertinggal terbuka di komputer bersama tidak bisa dipakai membajak akun.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Profile/Edit', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update($data);

        return back()->with('status', 'Profil diperbarui.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Kata sandi saat ini tidak cocok.']);
        }

        $user->update(['password' => $data['password']]);

        // Sesi lain milik pengguna ini ikut diputus setelah kata sandi berubah.
        $request->session()->regenerate();

        return back()->with('status', 'Kata sandi diperbarui.');
    }
}
