@extends('layouts.app')
@section('title', $client->name.' · '.config('site.name'))

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $client->name }}</h1>
        <p class="muted">@include('partials.status-badge', ['status' => $client->status]) {{ $client->industry }}</p>
    </div>
    <div class="page-actions">
        <a class="btn" href="{{ route('clients.edit', $client) }}">Ubah</a>
        <a class="btn btn-primary" href="{{ route('projects.create', ['client_id' => $client->id]) }}">Project baru</a>
        @if (auth()->user()->isAdmin())
            <form method="post" action="{{ route('clients.destroy', $client) }}" data-confirm="Hapus klien beserta seluruh project-nya?">
                @csrf @method('delete')
                <button class="btn btn-danger">Hapus</button>
            </form>
        @endif
    </div>
</div>

<section class="panel">
    <dl class="detail">
        <div><dt>Narahubung</dt><dd>{{ $client->contact_name ?: '—' }}</dd></div>
        <div><dt>Email</dt><dd>{{ $client->email ?: '—' }}</dd></div>
        <div><dt>Telepon</dt><dd>{{ $client->phone ?: '—' }}</dd></div>
        <div><dt>Alamat</dt><dd>{{ $client->address ?: '—' }}</dd></div>
    </dl>
    @if ($client->notes)
        <p class="notes">{{ $client->notes }}</p>
    @endif
</section>

<section class="panel">
    <h2>Project</h2>
    <table class="table">
        <thead><tr><th>Judul</th><th>Layanan</th><th>Status</th><th>Deadline</th></tr></thead>
        <tbody>
        @forelse ($client->projects as $project)
            <tr>
                <td><a href="{{ route('projects.show', $project) }}">{{ $project->title }}</a></td>
                <td>{{ $project->service_type }}</td>
                <td>@include('partials.status-badge', ['status' => $project->status])</td>
                <td>{{ $project->deadline?->format('d M Y') ?: '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="muted">Belum ada project untuk klien ini.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
@endsection
