<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', config('site.name'))</title>
<link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
<body>
@auth('client')
<header class="topbar">
    <a class="brand" href="{{ route('portal.dashboard') }}">
        <span class="brand-mark">3D</span>
        <span>
            <strong>{{ config('site.name') }}</strong>
            <small>Portal klien</small>
        </span>
    </a>
    <div class="topbar-user">
        <span class="muted">{{ auth('client')->user()->name }}</span>
        <form method="post" action="{{ route('portal.logout') }}">@csrf<button class="btn btn-ghost">Keluar</button></form>
    </div>
</header>

<main class="content">
    @include('partials.flash')
    @yield('content')
</main>
@else
<main class="content content-plain">
    @include('partials.flash')
    @yield('content')
</main>
@endauth
<script src="{{ asset('js/app.js') }}" type="module"></script>
</body>
</html>
