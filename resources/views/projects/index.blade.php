@extends('layouts.app')
@section('title', 'Project · '.config('site.name'))

@section('content')
<div class="page-head">
    <h1>Project</h1>
    <a class="btn btn-primary" href="{{ route('projects.create') }}">Project baru</a>
</div>

<form class="filters" method="get">
    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari judul, lokasi, klien…">
    <select name="status">
        <option value="">Semua status</option>
        @foreach (\App\Models\Project::STATUSES as $option)
            <option value="{{ $option }}" @selected(($filters['status'] ?? null) === $option)>{{ $option }}</option>
        @endforeach
    </select>
    <select name="client">
        <option value="">Semua klien</option>
        @foreach ($clients as $client)
            <option value="{{ $client->slug }}" @selected(($filters['client'] ?? null) === $client->slug)>{{ $client->name }}</option>
        @endforeach
    </select>
    <label class="inline"><input type="checkbox" name="mine" value="1" @checked($filters['mine'] ?? false)> Punya saya</label>
    <button class="btn">Filter</button>
</form>

<table class="table">
    <thead><tr><th>Judul</th><th>Klien</th><th>Layanan</th><th>PIC</th><th>Status</th><th>Deadline</th></tr></thead>
    <tbody>
    @forelse ($projects as $project)
        <tr>
            <td><a href="{{ route('projects.show', $project) }}">{{ $project->title }}</a></td>
            <td><a href="{{ route('clients.show', $project->client) }}">{{ $project->client->name }}</a></td>
            <td>{{ $project->service_type }}</td>
            <td>{{ $project->owner?->name ?: '—' }}</td>
            <td>@include('partials.status-badge', ['status' => $project->status])</td>
            <td>{{ $project->deadline?->format('d M Y') ?: '—' }}</td>
        </tr>
    @empty
        <tr><td colspan="6" class="muted">Belum ada project.</td></tr>
    @endforelse
    </tbody>
</table>

{{ $projects->links() }}
@endsection
