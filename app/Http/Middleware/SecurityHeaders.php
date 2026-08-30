<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header dasar untuk halaman aplikasi.
 *
 * Berkas di public/storage sudah dikeraskan di sisi web server, tetapi
 * halamannya sendiri belum: tanpa X-Frame-Options aplikasi ini bisa
 * disematkan di situs lain dan tombol seperti "Hapus permanen" diklik
 * lewat lapisan transparan.
 */
class SecurityHeaders
{
    private const HEADERS = [
        'X-Frame-Options' => 'DENY',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'same-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADERS as $header => $value) {
            $response->headers->set($header, $value, false);
        }

        return $response;
    }
}
