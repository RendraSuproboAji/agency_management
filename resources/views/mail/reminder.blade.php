<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8"><title>{{ $heading }}</title></head>
<body style="font-family: system-ui, sans-serif; color: #1c1f25; line-height: 1.5;">
    <h1 style="font-size: 18px;">{{ $heading }}</h1>

    @foreach ($lines as $line)
        <p style="margin: 4px 0;">{{ $line }}</p>
    @endforeach

    @if ($url)
        <p style="margin-top: 16px;">
            <a href="{{ $url }}" style="color: #f60;">{{ $urlLabel ?? 'Buka' }}</a>
        </p>
    @endif

    <p style="margin-top: 24px; color: #6b7280; font-size: 12px;">
        Email otomatis dari {{ config('app.name') }}. Tidak perlu dibalas.
    </p>
</body>
</html>
