{{--
    Halaman error berdiri sendiri, sengaja bukan halaman Inertia: halaman 500
    justru harus tetap tampil ketika aplikasinya sedang bermasalah, dan itu
    tidak bisa dijamin kalau ia bergantung pada bundel JavaScript.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 1.5rem;
            background: #14161a; color: #e8eaee;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }
        .card { width: 100%; max-width: 28rem; text-align: center; }
        .code { font-size: 3rem; font-weight: 700; color: #f60; line-height: 1; }
        h1 { margin: .75rem 0 .5rem; font-size: 1.25rem; }
        p { margin: 0 0 1.5rem; color: #9aa2b1; line-height: 1.6; }
        a {
            display: inline-block; padding: .625rem 1rem; border: 1px solid #333842;
            border-radius: .5rem; color: #e8eaee; text-decoration: none;
        }
        a:hover { border-color: #f60; }
    </style>
</head>
<body>
    <div class="card">
        <div class="code">@yield('code')</div>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        {{-- Klien portal dikembalikan ke portalnya, bukan ke aplikasi staf. --}}
        @php($home = request()->is('portal', 'portal/*') ? url('/portal') : url('/'))
        <a href="{{ $home }}">Kembali ke halaman utama</a>
    </div>
</body>
</html>
