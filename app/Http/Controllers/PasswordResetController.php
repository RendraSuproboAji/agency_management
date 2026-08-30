<?php

namespace App\Http\Controllers;

use App\Notifications\ResetPassword;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lupa kata sandi untuk staf maupun klien.
 *
 * Satu controller melayani kedua guard karena alurnya identik; yang berbeda
 * hanya broker, halaman, dan rute tujuannya. Broker klien memakai tabel token
 * sendiri supaya staf dan klien beremail sama tidak saling menimpa.
 */
class PasswordResetController extends Controller
{
    private const BROKERS = [
        'web' => ['broker' => 'users', 'prefix' => '', 'page' => 'Auth'],
        'client' => ['broker' => 'clients', 'prefix' => 'portal.', 'page' => 'Portal'],
    ];

    public function request(string $guard): Response
    {
        return Inertia::render($this->config($guard)['page'].'/ForgotPassword', [
            'guard' => $guard,
        ]);
    }

    public function email(Request $request, string $guard): RedirectResponse
    {
        $config = $this->config($guard);

        $request->validate(['email' => ['required', 'email']]);

        $status = Password::broker($config['broker'])
            ->sendResetLink($request->only('email'), function (CanResetPassword $user, string $token) use ($guard) {
                $user->notify(new ResetPassword($token, $guard));
            });

        // Jawabannya sengaja sama untuk email yang ada maupun tidak: kalau
        // dibedakan, halaman ini menjadi alat memeriksa siapa saja klien kami.
        return back()->with('status', 'Kalau email itu terdaftar, tautan setel ulang sudah dikirim.');
    }

    public function reset(string $guard, string $token, Request $request): Response
    {
        return Inertia::render($this->config($guard)['page'].'/ResetPassword', [
            'guard' => $guard,
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function update(Request $request, string $guard): RedirectResponse
    {
        $config = $this->config($guard);

        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::broker($config['broker'])->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages([
                'email' => 'Tautan setel ulang tidak berlaku atau sudah kedaluwarsa.',
            ]);
        }

        return redirect()->route($config['prefix'].'login')
            ->with('status', 'Kata sandi diperbarui. Silakan masuk.');
    }

    /** @return array<string, string> */
    private function config(string $guard): array
    {
        abort_unless(isset(self::BROKERS[$guard]), 404);

        return self::BROKERS[$guard];
    }
}
