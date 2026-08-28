@extends('layouts.portal')
@section('title', $project->title.' · '.config('site.name'))

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $project->title }}</h1>
        <p class="muted">
            {{ str_replace('_', ' ', $project->service_type) }}
            @include('partials.status-badge', ['status' => $project->status])
        </p>
    </div>
    <a class="btn btn-ghost" href="{{ route('portal.dashboard') }}">Kembali</a>
</div>

<section class="panel">
    <h2>Tahap pengerjaan</h2>
    <div class="pipeline">
        @foreach ($statuses as $status)
            <div @class(['pipeline-step', 'pipeline-current' => $project->status === $status])>
                <span class="pipeline-label">{{ $status }}</span>
            </div>
        @endforeach
    </div>

    <dl class="detail">
        <div><dt>Deadline</dt><dd>{{ $project->deadline?->format('d M Y') ?: '—' }}</dd></div>
        <div><dt>Lokasi</dt><dd>{{ $project->site_location ?: '—' }}</dd></div>
        <div><dt>Virtual tour</dt><dd>
            @if ($project->gallery_url)
                <a href="{{ $project->gallery_url }}" target="_blank" rel="noopener">Buka tur</a>
            @else — @endif
        </dd></div>
    </dl>
</section>

<section class="panel">
    <h2>Jadwal pengambilan gambar</h2>
    <table class="table">
        <thead><tr><th>Jadwal</th><th>Lokasi</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($project->captureSessions as $session)
            <tr>
                <td>{{ $session->scheduled_at->format('d M Y H:i') }}</td>
                <td>{{ $session->location ?: '—' }}</td>
                <td>@include('partials.status-badge', ['status' => $session->status])</td>
            </tr>
        @empty
            <tr><td colspan="3" class="muted">Belum ada jadwal.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>

<section class="panel">
    <h2>Hasil pekerjaan</h2>
    @forelse ($project->deliverables as $deliverable)
        <div class="deliverable">
            <div>
                <strong>{{ $deliverable->title }}</strong> <span class="muted">v{{ $deliverable->version }} · {{ $deliverable->type }}</span>
                @include('partials.status-badge', ['status' => $deliverable->status])
                @if ($deliverable->url())
                    <a href="{{ $deliverable->url() }}" target="_blank" rel="noopener">Buka</a>
                @endif
                @if ($deliverable->review_note)
                    <p class="notes">{{ $deliverable->review_note }}</p>
                @endif
            </div>
            @if (in_array($deliverable->status, ['submitted', 'revision'], true))
                <div class="row-actions">
                    <form method="post" action="{{ route('portal.deliverables.approve', [$project, $deliverable]) }}">
                        @csrf @method('put')
                        <button class="btn btn-mini">Setujui</button>
                    </form>
                    <form method="post" action="{{ route('portal.deliverables.revision', [$project, $deliverable]) }}">
                        @csrf @method('put')
                        <input type="text" name="review_note" placeholder="Apa yang perlu diperbaiki?" required>
                        <button class="btn btn-mini">Minta revisi</button>
                    </form>
                </div>
            @endif
        </div>
    @empty
        <p class="muted">Belum ada hasil yang diserahkan.</p>
    @endforelse
</section>

<section class="panel">
    <h2>Penawaran & tagihan</h2>
    <table class="table">
        <thead><tr><th>Dokumen</th><th>Tanggal</th><th>Nilai</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @foreach ($project->quotations as $quotation)
            <tr>
                <td>{{ $quotation->number }} <span class="muted">penawaran</span></td>
                <td>{{ $quotation->issued_at->format('d M Y') }}</td>
                <td>@include('partials.money', ['amount' => $quotation->total()])</td>
                <td>@include('partials.status-badge', ['status' => $quotation->status])</td>
                <td></td>
            </tr>
        @endforeach
        @foreach ($project->invoices as $invoice)
            <tr>
                <td>{{ $invoice->number }} <span class="muted">invoice</span></td>
                <td>{{ $invoice->issued_at->format('d M Y') }}</td>
                <td>@include('partials.money', ['amount' => $invoice->amount])<br>
                    <small class="muted">sisa @include('partials.money', ['amount' => $invoice->outstanding()])</small></td>
                <td>@include('partials.status-badge', ['status' => $invoice->status])</td>
                <td></td>
            </tr>
        @endforeach
        @if ($project->quotations->isEmpty() && $project->invoices->isEmpty())
            <tr><td colspan="5" class="muted">Belum ada dokumen.</td></tr>
        @endif
        </tbody>
    </table>
</section>
@endsection
