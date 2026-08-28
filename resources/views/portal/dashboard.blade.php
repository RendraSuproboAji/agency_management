@extends('layouts.portal')
@section('title', 'Project saya · '.config('site.name'))

@section('content')
<h1>Project Anda</h1>

<table class="table">
    <thead><tr><th>Project</th><th>Layanan</th><th>Status</th><th>Deadline</th><th>Sisa tagihan</th></tr></thead>
    <tbody>
    @forelse ($projects as $project)
        <tr>
            <td><a href="{{ route('portal.projects.show', $project) }}">{{ $project->title }}</a></td>
            <td>{{ str_replace('_', ' ', $project->service_type) }}</td>
            <td>@include('partials.status-badge', ['status' => $project->status])</td>
            <td>{{ $project->deadline?->format('d M Y') ?: '—' }}</td>
            <td>@include('partials.money', ['amount' => $project->invoices->sum(fn ($invoice) => $invoice->outstanding())])</td>
        </tr>
    @empty
        <tr><td colspan="5" class="muted">Belum ada project.</td></tr>
    @endforelse
    </tbody>
</table>
@endsection
