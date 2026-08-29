<?php

namespace App\Http\Middleware;

use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /**
     * Data yang tersedia di seluruh halaman.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user('web');

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role,
                    'is_admin' => $user->isAdmin(),
                ] : null,
                'client' => $request->user('client')?->only(['id', 'name']),
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
            // Lencana sidebar: dulu dikueri di dalam layout Blade, sekarang
            // dihitung sekali di sini.
            'newRequestCount' => fn () => $user
                ? ServiceRequest::where('status', 'new')->count()
                : 0,
        ]);
    }
}
