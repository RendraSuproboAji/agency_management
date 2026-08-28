@extends('layouts.app')
@section('title', 'Ubah penawaran · '.config('site.name'))

@section('content')
<h1>Ubah {{ $quotation->number }} — {{ $project->title }}</h1>
<form method="post" action="{{ route('quotations.update', [$project, $quotation]) }}">
    @csrf @method('put')
    @include('quotations._form')
    <div class="form-actions">
        <button class="btn btn-primary">Simpan</button>
        <a class="btn btn-ghost" href="{{ route('quotations.show', [$project, $quotation]) }}">Batal</a>
    </div>
</form>
@endsection
