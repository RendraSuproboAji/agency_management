<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="{{ config('site.description') }}">
<meta name="color-scheme" content="light dark">
{{-- Disetel sebelum halaman digambar supaya tidak berkedip terang lalu gelap.
     Sengaja inline dan sekecil ini: menunggu bundel JS berarti kedipnya sudah
     telanjur terlihat. --}}
<script>
    try {
        var t = localStorage.getItem('tema');
        if (t === 'light' || t === 'dark') document.documentElement.dataset.theme = t;
    } catch (e) {}
</script>
@inertiaHead
@viteReactRefresh
@vite(['resources/css/app.css', 'resources/js/app.jsx'])
</head>
<body class="h-full">
@inertia
</body>
</html>
