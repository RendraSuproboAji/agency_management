@extends('layouts.app')
@section('title', $quotation->number.' · '.config('site.name'))

@php $canManage = $project->isManageableBy(auth()->user()); @endphp

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $quotation->number }}</h1>
        <p class="muted">
            <a href="{{ route('projects.show', $project) }}">{{ $project->title }}</a>
            · terbit {{ $quotation->issued_at->format('d M Y') }}
            @if ($quotation->valid_until) · berlaku s.d. {{ $quotation->valid_until->format('d M Y') }} @endif
            @include('partials.status-badge', ['status' => $quotation->status])
        </p>
    </div>
    <div class="page-actions">
        <a class="btn" href="{{ route('quotations.print', [$project, $quotation]) }}" target="_blank" rel="noopener">Cetak</a>
        @if ($canManage)
            <a class="btn" href="{{ route('quotations.edit', [$project, $quotation]) }}">Ubah</a>
            @if ($quotation->status !== 'accepted')
                <form method="post" action="{{ route('quotations.accept', [$project, $quotation]) }}">
                    @csrf @method('put')
                    <button class="btn btn-primary">Tandai disetujui</button>
                </form>
            @else
                <a class="btn btn-primary" href="{{ route('invoices.create', [$project, 'quotation' => $quotation->id]) }}">Buat invoice</a>
            @endif
        @endif
        @if (auth()->user()->isAdmin())
            <form method="post" action="{{ route('quotations.destroy', [$project, $quotation]) }}" data-confirm="Hapus penawaran ini?">
                @csrf @method('delete')
                <button class="btn btn-danger">Hapus</button>
            </form>
        @endif
    </div>
</div>

<section class="panel">
    <table class="table">
        <thead><tr><th>Deskripsi</th><th>Qty</th><th>Harga satuan</th><th class="right">Jumlah</th></tr></thead>
        <tbody>
        @foreach ($quotation->items as $item)
            <tr>
                <td>{{ $item->description }}</td>
                <td>{{ rtrim(rtrim(number_format((float) $item->qty, 2, ',', '.'), '0'), ',') }} {{ $item->unit }}</td>
                <td>@include('partials.money', ['amount' => $item->unit_price])</td>
                <td class="right">@include('partials.money', ['amount' => $item->lineTotal()])</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
            <tr><td colspan="3" class="right">Subtotal</td><td class="right">@include('partials.money', ['amount' => $quotation->subtotal()])</td></tr>
            <tr><td colspan="3" class="right">Pajak {{ rtrim(rtrim((string) $quotation->tax_percent, '0'), '.') }}%</td><td class="right">@include('partials.money', ['amount' => $quotation->taxAmount()])</td></tr>
            <tr><td colspan="3" class="right"><strong>Total</strong></td><td class="right"><strong>@include('partials.money', ['amount' => $quotation->total()])</strong></td></tr>
        </tfoot>
    </table>

    @if ($quotation->notes)
        <h3>Catatan</h3>
        <p class="notes">{{ $quotation->notes }}</p>
    @endif
</section>

@if ($quotation->invoices->isNotEmpty())
<section class="panel">
    <h2>Invoice dari penawaran ini</h2>
    @foreach ($quotation->invoices as $invoice)
        <div class="list-row">
            <a href="{{ route('invoices.show', [$project, $invoice]) }}">{{ $invoice->number }}</a>
            <span class="muted">@include('partials.money', ['amount' => $invoice->amount]) · {{ $invoice->status }}</span>
        </div>
    @endforeach
</section>
@endif
@endsection
