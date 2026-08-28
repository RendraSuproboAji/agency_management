@extends('layouts.app')
@section('title', $invoice->number.' · '.config('site.name'))

@php $canManage = $project->isManageableBy(auth()->user()); @endphp

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $invoice->number }}</h1>
        <p class="muted">
            <a href="{{ route('projects.show', $project) }}">{{ $project->title }}</a>
            · terbit {{ $invoice->issued_at->format('d M Y') }}
            @if ($invoice->due_at) · jatuh tempo {{ $invoice->due_at->format('d M Y') }} @endif
            @include('partials.status-badge', ['status' => $invoice->status])
        </p>
    </div>
    <div class="page-actions">
        <a class="btn" href="{{ route('invoices.print', [$project, $invoice]) }}" target="_blank" rel="noopener">Cetak</a>
        @if ($canManage)
            <a class="btn" href="{{ route('invoices.edit', [$project, $invoice]) }}">Ubah</a>
        @endif
        @if (auth()->user()->isAdmin())
            <form method="post" action="{{ route('invoices.destroy', [$project, $invoice]) }}" data-confirm="Hapus invoice ini?">
                @csrf @method('delete')
                <button class="btn btn-danger">Hapus</button>
            </form>
        @endif
    </div>
</div>

<section class="panel">
    <dl class="detail">
        <div><dt>Nilai tagihan</dt><dd>@include('partials.money', ['amount' => $invoice->amount])</dd></div>
        <div><dt>Sudah dibayar</dt><dd>@include('partials.money', ['amount' => $invoice->paidAmount()])</dd></div>
        <div><dt>Sisa</dt><dd><strong>@include('partials.money', ['amount' => $invoice->outstanding()])</strong></dd></div>
    </dl>
    @if ($invoice->quotation)
        <p class="muted">Dari penawaran
            <a href="{{ route('quotations.show', [$project, $invoice->quotation]) }}">{{ $invoice->quotation->number }}</a>.
        </p>
    @endif
    @if ($invoice->notes)
        <p class="notes">{{ $invoice->notes }}</p>
    @endif
</section>

<section class="panel">
    <h2>Pembayaran</h2>
    <table class="table">
        <thead><tr><th>Tanggal</th><th>Jumlah</th><th>Metode</th><th>Referensi</th><th></th></tr></thead>
        <tbody>
        @forelse ($invoice->payments as $payment)
            <tr>
                <td>{{ $payment->paid_at->format('d M Y') }}</td>
                <td>@include('partials.money', ['amount' => $payment->amount])</td>
                <td>{{ $payment->method }}</td>
                <td>{{ $payment->reference ?: '—' }}<br><small class="muted">{{ $payment->note }}</small></td>
                <td class="row-actions">
                    @if ($canManage)
                        <form method="post" action="{{ route('payments.destroy', [$project, $invoice, $payment]) }}" data-confirm="Hapus pembayaran ini?">
                            @csrf @method('delete')
                            <button class="btn btn-mini btn-danger">Hapus</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">Belum ada pembayaran.</td></tr>
        @endforelse
        </tbody>
    </table>

    @if ($canManage)
        <h3>Catat pembayaran</h3>
        <form method="post" action="{{ route('payments.store', [$project, $invoice]) }}">
            @csrf
            <div class="form-grid">
                <label>Tanggal bayar *
                    <input type="date" name="paid_at" value="{{ old('paid_at', now()->toDateString()) }}" required>
                </label>
                <label>Jumlah (Rp) *
                    <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount', $invoice->outstanding() ?: null) }}" required>
                </label>
                <label>Metode *
                    <select name="method" required>
                        @foreach (\App\Models\Payment::METHODS as $option)
                            <option value="{{ $option }}" @selected(old('method') === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Referensi
                    <input type="text" name="reference" value="{{ old('reference') }}" placeholder="No. transaksi / bukti transfer">
                </label>
                <label class="span-2">Catatan
                    <input type="text" name="note" value="{{ old('note') }}" placeholder="Mis. DP 50%">
                </label>
            </div>
            <button class="btn btn-primary">Catat pembayaran</button>
        </form>
    @endif
</section>
@endsection
