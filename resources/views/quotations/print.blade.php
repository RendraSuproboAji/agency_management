@extends('layouts.print')
@section('title', $quotation->number.' · '.config('site.name'))

@section('document')
<h2 class="doc-title">Penawaran</h2>
<p class="doc-meta">
    Nomor {{ $quotation->number }} · Tanggal {{ $quotation->issued_at->format('d F Y') }}
    @if ($quotation->valid_until) · Berlaku sampai {{ $quotation->valid_until->format('d F Y') }} @endif
</p>

{{-- Penerima diambil dari satu sumber: penawaran bisa ditujukan ke klien
     yang sudah ada maupun ke calon klien yang baru mengirim permintaan. --}}
@php($to = $quotation->recipient())

<div class="parties">
    <div>
        <h3>Kepada</h3>
        <p>
            <strong>{{ $to['name'] }}</strong><br>
            @if ($to['contact_name']) u.p. {{ $to['contact_name'] }}<br> @endif
            @if ($to['address']) {{ $to['address'] }}<br> @endif
            {{ $to['email'] }}
        </p>
    </div>
    <div>
        <h3>Perihal</h3>
        <p>
            <strong>{{ $to['subject'] }}</strong><br>
            {{ $to['service_type'] }}<br>
            @if ($to['site_location']) {{ $to['site_location'] }} @endif
            @if ($to['area_sqm']) · {{ $to['area_sqm'] }} m² @endif
        </p>
    </div>
</div>

<table>
    <thead>
        <tr><th>Deskripsi</th><th>Qty</th><th class="right">Harga satuan</th><th class="right">Jumlah</th></tr>
    </thead>
    <tbody>
        @foreach ($quotation->items as $item)
            <tr>
                <td>{{ $item->description }}</td>
                <td>{{ rtrim(rtrim(number_format((float) $item->qty, 2, ',', '.'), '0'), ',') }} {{ $item->unit }}</td>
                <td class="right">@include('partials.money', ['amount' => $item->unit_price])</td>
                <td class="right">@include('partials.money', ['amount' => $item->lineTotal()])</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr><td colspan="3" class="right">Subtotal</td><td class="right">@include('partials.money', ['amount' => $quotation->subtotal()])</td></tr>
        <tr><td colspan="3" class="right">Pajak {{ rtrim(rtrim((string) $quotation->tax_percent, '0'), '.') }}%</td><td class="right">@include('partials.money', ['amount' => $quotation->taxAmount()])</td></tr>
        <tr class="total-row"><td colspan="3" class="right"><strong>Total</strong></td><td class="right"><strong>@include('partials.money', ['amount' => $quotation->total()])</strong></td></tr>
    </tfoot>
</table>

@if ($quotation->notes)
    <div class="panel-note">
        <h3>Catatan</h3>
        <p class="notes">{{ $quotation->notes }}</p>
    </div>
@endif

@include('partials.bank-details')

<div class="signature">
    Hormat kami,
    <div class="line">{{ config('site.name') }}</div>
</div>
@endsection
