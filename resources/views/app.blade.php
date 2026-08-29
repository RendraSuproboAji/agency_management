<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="{{ config('site.description') }}">
@inertiaHead
@viteReactRefresh
@vite(['resources/css/app.css', 'resources/js/app.jsx'])
</head>
<body class="h-full">
@inertia
</body>
</html>
