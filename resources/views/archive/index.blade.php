@extends('layouts.app')
@section('title', 'Arsip · '.config('site.name'))

@php
    $groups = [
        ['type' => 'clients', 'label' => 'Klien', 'items' => $clients],
        ['type' => 'projects', 'label' => 'Project', 'items' => $projects],
        ['type' => 'quotations', 'label' => 'Penawaran', 'items' => $quotations],
        ['type' => 'invoices', 'label' => 'Invoice', 'items' => $invoices],
        ['type' => 'deliverables', 'label' => 'Deliverable', 'items' => $deliverables],
        ['type' => 'equipment', 'label' => 'Peralatan', 'items' => $equipment],
    ];
@endphp

@section('content')
<h1>Arsip</h1>
<p class="muted">
    Data yang diarsipkan tidak lagi muncul di daftar maupun angka dashboard,
    tetapi masih bisa dipulihkan. Mengarsipkan klien ikut mengarsipkan seluruh
    project, penawaran, invoice, dan pembayarannya; memulihkannya mengembalikan
    yang diarsipkan bersamaan saja.
</p>

@foreach ($groups as $group)
    <section class="panel">
        <h2>{{ $group['label'] }} <span class="muted">({{ $group['items']->count() }})</span></h2>

        <table class="table">
            <thead><tr><th>Data</th><th>Diarsipkan</th><th></th></tr></thead>
            <tbody>
            @forelse ($group['items'] as $item)
                <tr>
                    <td>
                        @switch($group['type'])
                            @case('clients') {{ $item->name }} @break
                            @case('projects') {{ $item->title }} <small class="muted">{{ $item->client?->name }}</small> @break
                            @case('quotations') @case('invoices') {{ $item->number }} <small class="muted">{{ $item->project?->title }}</small> @break
                            @case('deliverables') {{ $item->title }} v{{ $item->version }} <small class="muted">{{ $item->project?->title }}</small> @break
                            @default {{ $item->name }} <small class="muted">{{ $item->code }}</small>
                        @endswitch
                    </td>
                    <td>{{ $item->deleted_at->format('d M Y H:i') }}</td>
                    <td class="row-actions">
                        <form method="post" action="{{ route('archive.restore', [$group['type'], $item->id]) }}">
                            @csrf @method('put')
                            <button class="btn btn-mini">Pulihkan</button>
                        </form>
                        <form method="post" action="{{ route('archive.force-delete', [$group['type'], $item->id]) }}"
                              data-confirm="Hapus permanen? Data dan berkasnya tidak bisa dikembalikan.">
                            @csrf @method('delete')
                            <button class="btn btn-mini btn-danger">Hapus permanen</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="muted">Tidak ada.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endforeach
@endsection
