@extends('layouts.app')
@section('title', 'Dashboard · '.config('site.name'))

@section('content')
<h1>Dashboard</h1>

<div class="stat-row">
    <div class="stat"><span class="stat-value">{{ $clientCount }}</span><span class="stat-label">Klien</span></div>
    <div class="stat"><span class="stat-value">{{ $activeProjectCount }}</span><span class="stat-label">Project berjalan</span></div>
    <div class="stat"><span class="stat-value">{{ $upcomingSessions->count() }}</span><span class="stat-label">Sesi terjadwal</span></div>
    <div class="stat"><span class="stat-value">{{ $pendingDeliverables->count() }}</span><span class="stat-label">Menunggu approval</span></div>
</div>

<section class="panel">
    <h2>Pipeline produksi</h2>
    <div class="pipeline">
        @foreach ($statuses as $status)
            <a class="pipeline-step" href="{{ route('projects.index', ['status' => $status]) }}">
                <span class="pipeline-count">{{ $countsByStatus[$status] ?? 0 }}</span>
                <span class="pipeline-label">{{ $status }}</span>
            </a>
        @endforeach
    </div>
</section>

<div class="grid-2">
    <section class="panel">
        <h2>Deadline terdekat</h2>
        @forelse ($upcomingDeadlines as $project)
            <div class="list-row">
                <a href="{{ route('projects.show', $project) }}">{{ $project->title }}</a>
                <span class="muted">{{ $project->client->name }} · {{ $project->deadline->format('d M Y') }}</span>
            </div>
        @empty
            <p class="muted">Belum ada deadline.</p>
        @endforelse
    </section>

    <section class="panel">
        <h2>Sesi pengambilan gambar</h2>
        @forelse ($upcomingSessions as $session)
            <div class="list-row">
                <a href="{{ route('projects.show', $session->project) }}">{{ $session->project->title }}</a>
                <span class="muted">
                    {{ $session->scheduled_at->format('d M Y H:i') }}
                    @if ($session->crew) · {{ $session->crew->name }} @endif
                </span>
            </div>
        @empty
            <p class="muted">Tidak ada sesi terjadwal.</p>
        @endforelse
    </section>
</div>

<section class="panel">
    <h2>Deliverable menunggu approval</h2>
    @forelse ($pendingDeliverables as $deliverable)
        <div class="list-row">
            <a href="{{ route('projects.show', $deliverable->project) }}">{{ $deliverable->title }} (v{{ $deliverable->version }})</a>
            <span class="muted">{{ $deliverable->project->client->name }} · {{ $deliverable->project->title }}</span>
        </div>
    @empty
        <p class="muted">Tidak ada yang menunggu.</p>
    @endforelse
</section>
@endsection
