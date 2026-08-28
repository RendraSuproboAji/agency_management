@extends('layouts.app')
@section('title', 'Tambah pengguna · '.config('site.name'))

@section('content')
<h1>Tambah pengguna</h1>
<form method="post" action="{{ route('users.store') }}">
    @csrf
    @include('users._form')
    <div class="form-actions">
        <button class="btn btn-primary">Simpan</button>
        <a class="btn btn-ghost" href="{{ route('users.index') }}">Batal</a>
    </div>
</form>
@endsection
