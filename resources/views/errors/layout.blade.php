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
    {{-- Halaman ini berdiri sendiri tanpa bundel JS, jadi temanya diambil
         langsung dari setelan perangkat dan dari pilihan yang tersimpan. --}}
    <script>
        try {
            var t = localStorage.getItem('tema');
            if (t === 'light' || t === 'dark') document.documentElement.dataset.theme = t;
        } catch (e) {}
    </script>
    <style>
        :root {
            color-scheme: light;
            --ground: #f5f6f8; --ink: #1b1e24; --muted: #5b6270;
            --line: #d5d9e0; --accent: #c24f00;
        }

        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) {
                color-scheme: dark;
                --ground: #14161a; --ink: #e8eaee; --muted: #9aa2b1;
                --line: #333842; --accent: #f60;
            }
        }

        :root[data-theme="dark"] {
            color-scheme: dark;
            --ground: #14161a; --ink: #e8eaee; --muted: #9aa2b1;
            --line: #333842; --accent: #f60;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 1.5rem;
            background: var(--ground); color: var(--ink);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }
        .card { width: 100%; max-width: 28rem; text-align: center; }
        .code { font-size: 3rem; font-weight: 700; color: var(--accent); line-height: 1; }
        h1 { margin: .75rem 0 .5rem; font-size: 1.25rem; }
        p { margin: 0 0 1.5rem; color: var(--muted); line-height: 1.6; }
        a {
            display: inline-block; padding: .625rem 1rem; border: 1px solid var(--line);
            border-radius: .5rem; color: var(--ink); text-decoration: none;
        }
        a:hover { border-color: var(--accent); }
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
