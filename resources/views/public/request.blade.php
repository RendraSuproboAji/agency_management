@extends('layouts.app')
@section('title', 'Ajukan request · '.config('site.name'))

@section('content')
<div class="login-card request-card">
    <h1>Ajukan request</h1>
    <p class="muted">
        Ceritakan kebutuhan rekonstruksi 3D Anda — kami akan menghubungi untuk
        survei dan penawaran.
    </p>

    <form method="post" action="{{ route('public.request.store') }}">
        @csrf
        <div class="form-grid">
            <label>Nama Anda *
                <input type="text" name="name" value="{{ old('name') }}" required>
            </label>
            <label>Perusahaan
                <input type="text" name="company" value="{{ old('company') }}">
            </label>
            <label>Email *
                <input type="email" name="email" value="{{ old('email') }}" required>
            </label>
            <label>Telepon / WhatsApp
                <input type="text" name="phone" value="{{ old('phone') }}">
            </label>
            <label>Jenis layanan *
                <select name="service_type" required>
                    @foreach (\App\Models\Project::SERVICE_TYPES as $option)
                        <option value="{{ $option }}" @selected(old('service_type') === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </label>
            <label>Perkiraan luas area (m²)
                <input type="number" min="0" name="area_sqm" value="{{ old('area_sqm') }}">
            </label>
            <label class="span-2">Lokasi
                <input type="text" name="site_location" value="{{ old('site_location') }}">
            </label>
            <label class="span-2">Kebutuhan Anda
                <textarea name="message" rows="5" placeholder="Mis. showroom dua lantai, butuh virtual tour untuk website.">{{ old('message') }}</textarea>
            </label>
        </div>

        {{-- Honeypot: disembunyikan dari manusia, diisi oleh bot. --}}
        <div class="hp" aria-hidden="true">
            <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>

        <button class="btn btn-primary" type="submit">Kirim request</button>
    </form>
</div>
@endsection
