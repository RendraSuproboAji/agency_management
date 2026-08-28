@extends('layouts.app')
@section('title', 'Invoice baru · '.config('site.name'))

@section('content')
<h1>Invoice baru — {{ $project->title }}</h1>
@if ($quotation)
    <p class="muted">Nilai disalin dari penawaran {{ $quotation->number }}.</p>
@endif
<form method="post" action="{{ route('invoices.store', $project) }}">
    @csrf
    @include('invoices._form')
    <div class="form-actions">
        <button class="btn btn-primary">Simpan</button>
        <a class="btn btn-ghost" href="{{ route('projects.show', $project) }}">Batal</a>
    </div>
</form>
@endsection
