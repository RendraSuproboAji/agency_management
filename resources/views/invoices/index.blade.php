@extends('layouts.app')
@section('title', 'Tagihan · '.config('site.name'))

@section('content')
<h1>Tagihan</h1>

<form class="filters" method="get">
    <select name="status">
        <option value="">Semua status</option>
        @foreach (\App\Models\Invoice::STATUSES as $option)
            <option value="{{ $option }}" @selected(($filters['status'] ?? null) === $option)>{{ $option }}</option>
        @endforeach
    </select>
    <label class="inline"><input type="checkbox" name="unsettled" value="1" @checked($filters['unsettled'] ?? false)> Belum lunas</label>
    <button class="btn">Filter</button>
</form>

<table class="table">
    <thead><tr><th>Nomor</th><th>Project</th><th>Klien</th><th>Jatuh tempo</th><th>Nilai</th><th>Sisa</th><th>Status</th></tr></thead>
    <tbody>
    @forelse ($invoices as $invoice)
        <tr>
            <td><a href="{{ route('invoices.show', [$invoice->project, $invoice]) }}">{{ $invoice->number }}</a></td>
            <td>{{ $invoice->project->title }}</td>
            <td>{{ $invoice->project->client->name }}</td>
            <td>{{ $invoice->due_at?->format('d M Y') ?: '—' }}</td>
            <td>@include('partials.money', ['amount' => $invoice->amount])</td>
            <td>@include('partials.money', ['amount' => $invoice->outstanding()])</td>
            <td>@include('partials.status-badge', ['status' => $invoice->status])</td>
        </tr>
    @empty
        <tr><td colspan="7" class="muted">Belum ada tagihan.</td></tr>
    @endforelse
    </tbody>
</table>

{{ $invoices->links() }}
@endsection
