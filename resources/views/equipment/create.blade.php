@extends('layouts.app')
@section('title', 'Tambah peralatan · '.config('site.name'))

@section('content')
<h1>Tambah peralatan</h1>
<form method="post" action="{{ route('equipment.store') }}">
    @csrf
    @include('equipment._form')
    <div class="form-actions">
        <button class="btn btn-primary">Simpan</button>
        <a class="btn btn-ghost" href="{{ route('equipment.index') }}">Batal</a>
    </div>
</form>
@endsection
