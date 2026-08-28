@extends('layouts.app')
@section('title', 'Sesi capture · '.config('site.name'))

@section('content')
<h1>Agenda pengambilan gambar</h1>

<form class="filters" method="get">
    <select name="status">
        <option value="">Semua status</option>
        @foreach (\App\Models\CaptureSession::STATUSES as $option)
            <option value="{{ $option }}" @selected(($filters['status'] ?? null) === $option)>{{ $option }}</option>
        @endforeach
    </select>
    <label class="inline"><input type="checkbox" name="mine" value="1" @checked($filters['mine'] ?? false)> Sesi saya</label>
    <button class="btn">Filter</button>
</form>

<table class="table">
    <thead><tr><th>Jadwal</th><th>Project</th><th>Klien</th><th>Kru</th><th>Peralatan</th><th>Status</th></tr></thead>
    <tbody>
    @forelse ($sessions as $session)
        <tr>
            <td>{{ $session->scheduled_at->format('d M Y H:i') }}</td>
            <td><a href="{{ route('projects.show', $session->project) }}">{{ $session->project->title }}</a></td>
            <td>{{ $session->project->client->name }}</td>
            <td>{{ $session->crew?->name ?: '—' }}</td>
            <td>{{ $session->equipment->pluck('name')->join(', ') ?: '—' }}</td>
            <td>@include('partials.status-badge', ['status' => $session->status])</td>
        </tr>
    @empty
        <tr><td colspan="6" class="muted">Belum ada sesi.</td></tr>
    @endforelse
    </tbody>
</table>

{{ $sessions->links() }}
@endsection
