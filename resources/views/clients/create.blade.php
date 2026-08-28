@extends('layouts.app')
@section('title', 'Tambah klien · '.config('site.name'))

@section('content')
<h1>Tambah klien</h1>
<form method="post" action="{{ route('clients.store') }}">
    @csrf
    @include('clients._form')
    <div class="form-actions">
        <button class="btn btn-primary">Simpan</button>
        <a class="btn btn-ghost" href="{{ route('clients.index') }}">Batal</a>
    </div>
</form>
@endsection
