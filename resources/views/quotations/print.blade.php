@extends('layouts.print')
@section('title', $quotation->number.' · '.config('site.name'))

@section('document')
<h2 class="doc-title">Penawaran</h2>
<p class="doc-meta">
    Nomor {{ $quotation->number }} · Tanggal {{ $quotation->issued_at->format('d F Y') }}
    @if ($quotation->valid_until) · Berlaku sampai {{ $quotation->valid_until->format('d F Y') }} @endif
</p>

<div class="parties">
    <div>
        <h3>Kepada</h3>
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
            {{ str_replace('_', ' ', $project->service_type) }}<br>
            @if ($project->site_location) {{ $project->site_location }} @endif
            @if ($project->area_sqm) · {{ $project->area_sqm }} m² @endif
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
