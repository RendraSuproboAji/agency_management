<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', config('site.name'))</title>
<link rel="stylesheet" href="{{ asset('css/print.css') }}">
</head>
<body>
<div class="sheet">
    <header class="letterhead">
        <div>
            <h1>{{ config('site.name') }}</h1>
            <p class="tagline">{{ config('site.tagline') }}</p>
        </div>
        <address>
            @if (config('site.company.address')) {{ config('site.company.address') }}<br> @endif
            @if (config('site.company.phone')) {{ config('site.company.phone') }}<br> @endif
            {{ config('site.company.email') }}
        </address>
    </header>

    @yield('document')

    <p class="no-print actions">
        <button onclick="window.print()">Cetak / simpan sebagai PDF</button>
        <a href="{{ $backUrl }}">Kembali</a>
    </p>
</div>
</body>
</html>
