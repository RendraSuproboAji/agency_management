@extends('layouts.app')
@section('title', 'Peralatan · '.config('site.name'))

@section('content')
<div class="page-head">
    <h1>Peralatan</h1>
    <a class="btn btn-primary" href="{{ route('equipment.create') }}">Tambah peralatan</a>
</div>

<form class="filters" method="get">
    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari nama, kode, no. seri…">
    <select name="category">
        <option value="">Semua kategori</option>
        @foreach (\App\Models\Equipment::CATEGORIES as $option)
            <option value="{{ $option }}" @selected(($filters['category'] ?? null) === $option)>{{ $option }}</option>
        @endforeach
    </select>
    <select name="status">
        <option value="">Semua status</option>
        @foreach (\App\Models\Equipment::STATUSES as $option)
            <option value="{{ $option }}" @selected(($filters['status'] ?? null) === $option)>{{ $option }}</option>
        @endforeach
    </select>
    <button class="btn">Filter</button>
</form>

<table class="table">
    <thead><tr><th>Nama</th><th>Kode</th><th>Kategori</th><th>No. seri</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @forelse ($equipment as $item)
        <tr>
            <td>{{ $item->name }}</td>
            <td>{{ $item->code }}</td>
            <td>{{ $item->category }}</td>
            <td>{{ $item->serial_number ?: '—' }}</td>
            <td>@include('partials.status-badge', ['status' => $item->status])</td>
            <td class="row-actions">
                <a href="{{ route('equipment.edit', $item) }}">Ubah</a>
                @if (auth()->user()->isAdmin())
                    <form method="post" action="{{ route('equipment.destroy', $item) }}" data-confirm="Hapus peralatan ini?">
                        @csrf @method('delete')
                        <button class="btn btn-mini btn-danger">Hapus</button>
                    </form>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="6" class="muted">Belum ada peralatan.</td></tr>
    @endforelse
    </tbody>
</table>

{{ $equipment->links() }}
@endsection
