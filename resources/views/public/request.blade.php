{{-- Sengaja tetap Blade: halaman publik tanpa login, tidak perlu memuat
     bundel React hanya untuk satu formulir. --}}
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ajukan request · {{ config('site.name') }}</title>
<meta name="description" content="{{ config('site.description') }}">
@vite('resources/css/app.css')
</head>
<body class="h-full">
<main class="mx-auto max-w-2xl p-6">
    <h1 class="text-2xl font-semibold">Ajukan request</h1>
    <p class="mb-4 text-sm text-muted">
        Ceritakan kebutuhan rekonstruksi 3D Anda — kami akan menghubungi untuk survei dan penawaran.
    </p>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-ok px-3 py-2 text-sm text-ok">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-danger px-3 py-2 text-sm text-danger">
            <ul class="list-disc pl-4">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    @php $input = 'mt-1 w-full rounded-lg border border-line bg-raised px-2 py-2 text-sm text-ink'; @endphp

    <form method="post" action="{{ route('public.request.store') }}" class="rounded-lg border border-line bg-surface p-5">
        @csrf
        <div class="gap-x-4 sm:grid sm:grid-cols-2">
            <label class="mb-3 block text-xs text-muted">Nama Anda *
                <input type="text" name="name" value="{{ old('name') }}" required class="{{ $input }}">
            </label>
            <label class="mb-3 block text-xs text-muted">Perusahaan
                <input type="text" name="company" value="{{ old('company') }}" class="{{ $input }}">
            </label>
            <label class="mb-3 block text-xs text-muted">Email *
                <input type="email" name="email" value="{{ old('email') }}" required class="{{ $input }}">
            </label>
            <label class="mb-3 block text-xs text-muted">Telepon / WhatsApp
                <input type="text" name="phone" value="{{ old('phone') }}" class="{{ $input }}">
            </label>
            <label class="mb-3 block text-xs text-muted">Jenis layanan *
                <select name="service_type" required class="{{ $input }}">
                    @foreach (\App\Models\Project::SERVICE_TYPES as $option)
                        <option value="{{ $option }}" @selected(old('service_type') === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </label>
            <label class="mb-3 block text-xs text-muted">Perkiraan luas area (m²)
                <input type="number" min="0" name="area_sqm" value="{{ old('area_sqm') }}" class="{{ $input }}">
            </label>
            <label class="mb-3 block text-xs text-muted sm:col-span-2">Lokasi
                <input type="text" name="site_location" value="{{ old('site_location') }}" class="{{ $input }}">
            </label>
            <label class="mb-3 block text-xs text-muted sm:col-span-2">Kebutuhan Anda
                <textarea name="message" rows="5" class="{{ $input }}"
                          placeholder="Mis. showroom dua lantai, butuh virtual tour untuk website.">{{ old('message') }}</textarea>
            </label>
        </div>

        {{-- Honeypot: disembunyikan dari manusia, diisi oleh bot. --}}
        <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
            <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>

        <button type="submit" class="rounded-lg border border-accent bg-accent px-3 py-2 text-sm font-semibold text-accent-ink">
            Kirim request
        </button>
    </form>
</main>
</body>
</html>
