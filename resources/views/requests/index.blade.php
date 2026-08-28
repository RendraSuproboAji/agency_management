@extends('layouts.app')
@section('title', 'Request masuk · '.config('site.name'))

@section('content')
<div class="page-head">
    <h1>Request masuk</h1>
    <a class="btn" href="{{ route('public.request.create') }}" target="_blank" rel="noopener">Lihat form publik</a>
</div>

<form class="filters" method="get">
    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari nama, perusahaan, email…">
    <select name="status">
        <option value="">Semua status</option>
        @foreach (\App\Models\ServiceRequest::STATUSES as $option)
            <option value="{{ $option }}" @selected(($filters['status'] ?? null) === $option)>{{ $option }}</option>
        @endforeach
    </select>
    <button class="btn">Filter</button>
</form>

<table class="table">
    <thead><tr><th>Masuk</th><th>Pengirim</th><th>Layanan</th><th>Lokasi</th><th>Status</th></tr></thead>
    <tbody>
    @forelse ($requests as $item)
        <tr>
            <td>{{ $item->created_at->format('d M Y') }}</td>
            <td><a href="{{ route('requests.show', $item) }}">{{ $item->company ?: $item->name }}</a><br>
                <small class="muted">{{ $item->name }} · {{ $item->email }}</small></td>
            <td>{{ $item->service_type }}</td>
            <td>{{ $item->site_location ?: '—' }}</td>
            <td>@include('partials.status-badge', ['status' => $item->status])</td>
        </tr>
    @empty
        <tr><td colspan="5" class="muted">Belum ada request masuk.</td></tr>
    @endforelse
    </tbody>
</table>

{{ $requests->links() }}
@endsection
