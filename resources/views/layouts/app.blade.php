<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', config('site.name'))</title>
<meta name="description" content="{{ config('site.description') }}">
<link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
<body>
@auth
<header class="topbar">
    <button class="nav-toggle" type="button" data-drawer-toggle aria-label="Buka menu">☰</button>
    <a class="brand" href="{{ route('dashboard') }}">
        <span class="brand-mark">3D</span>
        <span>
            <strong>{{ config('site.name') }}</strong>
            <small>{{ config('site.tagline') }}</small>
        </span>
    </a>
    <div class="topbar-user">
        <span class="muted">{{ auth()->user()->name }} · {{ auth()->user()->role }}</span>
        <form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-ghost">Keluar</button></form>
    </div>
</header>

<div class="shell">
    <nav class="sidebar" data-drawer>
        @php
            $current = request()->route()?->getName();
            $newRequestCount = \App\Models\ServiceRequest::where('status', 'new')->count();
        @endphp
        <a href="{{ route('dashboard') }}" @class(['active' => $current === 'dashboard'])>Dashboard</a>
        <a href="{{ route('requests.index') }}" @class(['active' => str_starts_with((string) $current, 'requests.')])>
            Request
            @if ($newRequestCount)
                <span class="pill">{{ $newRequestCount }}</span>
            @endif
        </a>
        <a href="{{ route('clients.index') }}" @class(['active' => str_starts_with((string) $current, 'clients.')])>Klien</a>
        <a href="{{ route('projects.index') }}" @class(['active' => str_starts_with((string) $current, 'projects.')])>Project</a>
        <a href="{{ route('equipment.index') }}" @class(['active' => str_starts_with((string) $current, 'equipment.')])>Peralatan</a>
        <a href="{{ route('invoices.index') }}" @class(['active' => str_starts_with((string) $current, 'invoices.')])>Tagihan</a>
        <a href="{{ route('sessions.index') }}" @class(['active' => str_starts_with((string) $current, 'sessions.')])>Sesi Capture</a>
        @if (auth()->user()->isAdmin())
            <a href="{{ route('users.index') }}" @class(['active' => str_starts_with((string) $current, 'users.')])>Pengguna</a>
        @endif
    </nav>

    <main class="content">
        @include('partials.flash')
        @yield('content')
    </main>
</div>
@else
<main class="content content-plain">
    @include('partials.flash')
    @yield('content')
</main>
@endauth
<script src="{{ asset('js/app.js') }}" type="module"></script>
</body>
</html>
