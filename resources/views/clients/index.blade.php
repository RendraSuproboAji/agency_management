@extends('layouts.app')
@section('title', 'Klien · '.config('site.name'))

@section('content')
<div class="page-head">
    <h1>Klien</h1>
    <a class="btn btn-primary" href="{{ route('clients.create') }}">Tambah klien</a>
</div>

<form class="filters" method="get">
    <input type="search" name="q" value="{{ $q }}" placeholder="Cari nama, kontak, email…">
    <select name="status">
        <option value="">Semua status</option>
        @foreach (\App\Models\Client::STATUSES as $option)
            <option value="{{ $option }}" @selected($status === $option)>{{ $option }}</option>
        @endforeach
    </select>
    <button class="btn">Filter</button>
</form>

<table class="table">
    <thead><tr><th>Nama</th><th>Kontak</th><th>Industri</th><th>Project</th><th>Status</th></tr></thead>
    <tbody>
    @forelse ($clients as $client)
        <tr>
            <td><a href="{{ route('clients.show', $client) }}">{{ $client->name }}</a></td>
            <td>{{ $client->contact_name ?: '—' }}<br><small class="muted">{{ $client->email }}</small></td>
            <td>{{ $client->industry ?: '—' }}</td>
            <td>{{ $client->projects_count }}</td>
            <td>@include('partials.status-badge', ['status' => $client->status])</td>
        </tr>
    @empty
        <tr><td colspan="5" class="muted">Belum ada klien.</td></tr>
    @endforelse
    </tbody>
</table>

{{ $clients->links() }}
@endsection
