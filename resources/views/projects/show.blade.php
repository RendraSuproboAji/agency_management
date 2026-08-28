@extends('layouts.app')
@section('title', $project->title.' · '.config('site.name'))

@php $canManage = $project->isManageableBy(auth()->user()); @endphp

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $project->title }}</h1>
        <p class="muted">
            <a href="{{ route('clients.show', $project->client) }}">{{ $project->client->name }}</a>
            · {{ $project->service_type }}
            @include('partials.status-badge', ['status' => $project->status])
        </p>
    </div>
    <div class="page-actions">
        @if ($canManage)
            <a class="btn" href="{{ route('projects.edit', $project) }}">Ubah</a>
        @endif
        @if (auth()->user()->isAdmin())
            <form method="post" action="{{ route('projects.destroy', $project) }}" data-confirm="Hapus project ini?">
                @csrf @method('delete')
                <button class="btn btn-danger">Hapus</button>
            </form>
        @endif
    </div>
</div>

<section class="panel">
    <dl class="detail">
        <div><dt>PIC</dt><dd>{{ $project->owner?->name ?: '—' }}</dd></div>
        <div><dt>Deadline</dt><dd>{{ $project->deadline?->format('d M Y') ?: '—' }}</dd></div>
        <div><dt>Budget</dt><dd>{{ $project->budget ? 'Rp '.number_format((float) $project->budget, 0, ',', '.') : '—' }}</dd></div>
        <div><dt>Lokasi</dt><dd>{{ $project->site_location ?: '—' }}</dd></div>
        <div><dt>Luas area</dt><dd>{{ $project->area_sqm ? $project->area_sqm.' m²' : '—' }}</dd></div>
        <div><dt>Virtual tour</dt><dd>
            @if ($project->gallery_url)
                <a href="{{ $project->gallery_url }}" target="_blank" rel="noopener">Buka tur</a>
            @else — @endif
        </dd></div>
    </dl>

    @if ($project->brief)
        <h3>Brief / request klien</h3>
        <p class="notes">{{ $project->brief }}</p>
    @endif

    @if ($canManage)
        <form class="inline-form" method="post" action="{{ route('projects.status', $project) }}">
            @csrf @method('put')
            <label class="inline">Pindah status
                <select name="status">
                    @foreach (\App\Models\Project::STATUSES as $option)
                        <option value="{{ $option }}" @selected($project->status === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </label>
            <button class="btn">Simpan status</button>
        </form>
    @endif
</section>

<section class="panel">
    <div class="page-head">
        <h2>Penawaran & tagihan</h2>
        @if ($canManage)
            <div class="page-actions">
                <a class="btn" href="{{ route('quotations.create', $project) }}">Penawaran baru</a>
                <a class="btn" href="{{ route('invoices.create', $project) }}">Invoice baru</a>
            </div>
        @endif
    </div>

    <dl class="detail">
        <div><dt>Nilai ditagihkan</dt><dd>@include('partials.money', ['amount' => $billed])</dd></div>
        <div><dt>Sudah dibayar</dt><dd>@include('partials.money', ['amount' => $paid])</dd></div>
        <div><dt>Sisa tagihan</dt><dd><strong>@include('partials.money', ['amount' => $billed - $paid])</strong></dd></div>
    </dl>

    <table class="table">
        <thead><tr><th>Dokumen</th><th>Tanggal</th><th>Nilai</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($project->quotations as $quotation)
            <tr>
                <td><a href="{{ route('quotations.show', [$project, $quotation]) }}">{{ $quotation->number }}</a> <span class="muted">penawaran</span></td>
                <td>{{ $quotation->issued_at->format('d M Y') }}</td>
                <td>@include('partials.money', ['amount' => $quotation->total()])</td>
                <td>@include('partials.status-badge', ['status' => $quotation->status])</td>
            </tr>
        @empty
        @endforelse
        @forelse ($project->invoices as $invoice)
            <tr>
                <td><a href="{{ route('invoices.show', [$project, $invoice]) }}">{{ $invoice->number }}</a> <span class="muted">invoice</span></td>
                <td>{{ $invoice->issued_at->format('d M Y') }}</td>
                <td>@include('partials.money', ['amount' => $invoice->amount])<br><small class="muted">sisa @include('partials.money', ['amount' => $invoice->outstanding()])</small></td>
                <td>@include('partials.status-badge', ['status' => $invoice->status])</td>
            </tr>
        @empty
        @endforelse
        @if ($project->quotations->isEmpty() && $project->invoices->isEmpty())
            <tr><td colspan="4" class="muted">Belum ada penawaran maupun tagihan.</td></tr>
        @endif
        </tbody>
    </table>
</section>

<section class="panel">
    <div class="page-head">
        <h2>Sesi pengambilan gambar</h2>
        @if ($canManage)
            <a class="btn" href="{{ route('sessions.create', $project) }}">Jadwalkan sesi</a>
        @endif
    </div>

    <table class="table">
        <thead><tr><th>Jadwal</th><th>Kru</th><th>Lokasi</th><th>Peralatan</th><th>Jumlah shot</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($project->captureSessions as $session)
            <tr>
                <td>{{ $session->scheduled_at->format('d M Y H:i') }}</td>
                <td>{{ $session->crew?->name ?: '—' }}</td>
                <td>{{ $session->location ?: '—' }}</td>
                <td>{{ $session->equipment->pluck('name')->join(', ') ?: '—' }}</td>
                <td>{{ $session->shot_count ?? '—' }}</td>
                <td>@include('partials.status-badge', ['status' => $session->status])</td>
                <td class="row-actions">
                    @if ($canManage)
                        <a href="{{ route('sessions.edit', [$project, $session]) }}">Ubah</a>
                        @if ($session->status === 'scheduled')
                            <form method="post" action="{{ route('sessions.complete', [$project, $session]) }}">
                                @csrf @method('put')
                                <input type="number" name="shot_count" min="0" placeholder="shot" class="mini">
                                <button class="btn btn-mini">Selesai</button>
                            </form>
                        @endif
                        <form method="post" action="{{ route('sessions.destroy', [$project, $session]) }}" data-confirm="Hapus sesi ini?">
                            @csrf @method('delete')
                            <button class="btn btn-mini btn-danger">Hapus</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">Belum ada sesi terjadwal.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>

<section class="panel">
    <div class="page-head">
        <h2>Deliverable</h2>
        @if ($canManage)
            <a class="btn" href="{{ route('deliverables.create', $project) }}">Tambah deliverable</a>
        @endif
    </div>

    @forelse ($project->deliverables as $deliverable)
        <div class="deliverable">
            <div>
                <strong>{{ $deliverable->title }}</strong> <span class="muted">v{{ $deliverable->version }} · {{ $deliverable->type }}</span>
                @include('partials.status-badge', ['status' => $deliverable->status])
                @if ($deliverable->url())
                    <a href="{{ $deliverable->url() }}" target="_blank" rel="noopener">Buka aset</a>
                @endif
                @if ($deliverable->review_note)
                    <p class="notes">{{ $deliverable->review_note }}</p>
                @endif
            </div>
            @if ($canManage)
                <div class="row-actions">
                    <a href="{{ route('deliverables.edit', [$project, $deliverable]) }}">Ubah</a>
                    @if ($deliverable->status !== 'approved')
                        <form method="post" action="{{ route('deliverables.approve', [$project, $deliverable]) }}">
                            @csrf @method('put')
                            <button class="btn btn-mini">Setujui</button>
                        </form>
                    @endif
                    <form method="post" action="{{ route('deliverables.revision', [$project, $deliverable]) }}">
                        @csrf @method('put')
                        <input type="text" name="review_note" placeholder="Catatan revisi" required>
                        <button class="btn btn-mini">Minta revisi</button>
                    </form>
                    <form method="post" action="{{ route('deliverables.destroy', [$project, $deliverable]) }}" data-confirm="Hapus deliverable ini?">
                        @csrf @method('delete')
                        <button class="btn btn-mini btn-danger">Hapus</button>
                    </form>
                </div>
            @endif
        </div>
    @empty
        <p class="muted">Belum ada deliverable.</p>
    @endforelse
</section>
<section class="panel">
    <div class="page-head">
        <h2>Lampiran</h2>
    </div>

    <table class="table">
        <thead><tr><th>Judul</th><th>Kategori</th><th>Ukuran</th><th>Diunggah</th><th></th></tr></thead>
        <tbody>
        @forelse ($project->attachments as $attachment)
            <tr>
                <td><a href="{{ route('attachments.download', [$project, $attachment]) }}">{{ $attachment->title }}</a></td>
                <td>{{ $attachment->category }}</td>
                <td>{{ $attachment->humanSize() }}</td>
                <td>{{ $attachment->created_at->format('d M Y') }}<br><small class="muted">{{ $attachment->uploader?->name }}</small></td>
                <td class="row-actions">
                    @if ($canManage)
                        <form method="post" action="{{ route('attachments.destroy', [$project, $attachment]) }}" data-confirm="Hapus lampiran ini?">
                            @csrf @method('delete')
                            <button class="btn btn-mini btn-danger">Hapus</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">Belum ada lampiran.</td></tr>
        @endforelse
        </tbody>
    </table>

    @if ($canManage)
        <form method="post" action="{{ route('attachments.store', $project) }}" enctype="multipart/form-data">
            @csrf
            <div class="form-grid">
                <label>Judul *
                    <input type="text" name="title" value="{{ old('title') }}" required placeholder="Mis. Kontrak kerja sama">
                </label>
                <label>Kategori *
                    <select name="category" required>
                        @foreach (\App\Models\Attachment::CATEGORIES as $option)
                            <option value="{{ $option }}" @selected(old('category') === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="span-2">Berkas *
                    <input type="file" name="file" required>
                </label>
            </div>
            <button class="btn">Unggah lampiran</button>
        </form>
    @endif
</section>

<section class="panel">
    <h2>Catatan internal</h2>

    @forelse ($project->notes as $note)
        <div class="note">
            <p class="notes">{{ $note->body }}</p>
            <p class="muted">
                {{ $note->author?->name ?: 'Pengguna terhapus' }} · {{ $note->created_at->diffForHumans() }}
                @if ($note->user_id === auth()->id() || auth()->user()->isAdmin())
                    <form method="post" action="{{ route('notes.destroy', [$project, $note]) }}" data-confirm="Hapus catatan ini?">
                        @csrf @method('delete')
                        <button class="btn btn-mini btn-danger">Hapus</button>
                    </form>
                @endif
            </p>
        </div>
    @empty
        <p class="muted">Belum ada catatan.</p>
    @endforelse

    @if ($canManage)
        <form method="post" action="{{ route('notes.store', $project) }}">
            @csrf
            <label>Tulis catatan
                <textarea name="body" rows="3" required placeholder="Hasil rapat, kendala di lapangan, kesepakatan dengan klien…">{{ old('body') }}</textarea>
            </label>
            <button class="btn">Simpan catatan</button>
        </form>
    @endif
</section>

<section class="panel">
    <h2>Riwayat aktivitas</h2>
    @forelse ($project->activities as $activity)
        <div class="list-row">
            <span>{{ $activity->description }}</span>
            <span class="muted">{{ $activity->actorName() }} · {{ $activity->created_at->format('d M Y H:i') }}</span>
        </div>
    @empty
        <p class="muted">Belum ada aktivitas tercatat.</p>
    @endforelse
</section>
@endsection
