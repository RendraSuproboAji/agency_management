@extends('layouts.print')
@section('title', $invoice->number.' · '.config('site.name'))

@section('document')
<h2 class="doc-title">Invoice</h2>
<p class="doc-meta">
    Nomor {{ $invoice->number }} · Tanggal {{ $invoice->issued_at->format('d F Y') }}
    @if ($invoice->due_at) · Jatuh tempo {{ $invoice->due_at->format('d F Y') }} @endif
    @if ($invoice->quotation) · Penawaran {{ $invoice->quotation->number }} @endif
</p>

<div class="parties">
    <div>
        <h3>Ditagihkan kepada</h3>
        <p>
            <strong>{{ $project->client->name }}</strong><br>
            @if ($project->client->contact_name) u.p. {{ $project->client->contact_name }}<br> @endif
            @if ($project->client->address) {{ $project->client->address }}<br> @endif
            {{ $project->client->email }}
        </p>
    </div>
    <div>
        <h3>Perihal</h3>
        <p>
            <strong>{{ $project->title }}</strong><br>
            {{ str_replace('_', ' ', $project->service_type) }}
        </p>
    </div>
</div>

<table>
    <thead><tr><th>Keterangan</th><th class="right">Jumlah</th></tr></thead>
    <tbody>
        <tr>
            <td>{{ $project->title }}</td>
            <td class="right">@include('partials.money', ['amount' => $invoice->amount])</td>
        </tr>
        @foreach ($invoice->payments as $payment)
            <tr>
                <td>Pembayaran diterima {{ $payment->paid_at->format('d F Y') }}
                    @if ($payment->note) — {{ $payment->note }} @endif
                </td>
                <td class="right">− @include('partials.money', ['amount' => $payment->amount])</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr class="total-row">
            <td class="right"><strong>Sisa tagihan</strong></td>
            <td class="right"><strong>@include('partials.money', ['amount' => $invoice->outstanding()])</strong></td>
        </tr>
    </tfoot>
</table>

@if ($invoice->notes)
    <div class="panel-note">
        <h3>Catatan</h3>
        <p class="notes">{{ $invoice->notes }}</p>
    </div>
@endif

@include('partials.bank-details')

<div class="signature">
    Hormat kami,
    <div class="line">{{ config('site.name') }}</div>
</div>
@endsection
