@extends('layouts.app')
@section('title', 'Ubah invoice · '.config('site.name'))

@section('content')
<h1>Ubah {{ $invoice->number }} — {{ $project->title }}</h1>
<form method="post" action="{{ route('invoices.update', [$project, $invoice]) }}">
    @csrf @method('put')
    @include('invoices._form')
    <div class="form-actions">
        <button class="btn btn-primary">Simpan</button>
        <a class="btn btn-ghost" href="{{ route('invoices.show', [$project, $invoice]) }}">Batal</a>
    </div>
</form>
@endsection
