@extends('layouts.app')
@section('title', 'Ubah pengguna · '.config('site.name'))

@section('content')
<h1>Ubah pengguna</h1>
<form method="post" action="{{ route('users.update', $user) }}">
    @csrf @method('put')
    @include('users._form')
    <div class="form-actions">
        <button class="btn btn-primary">Simpan</button>
        <a class="btn btn-ghost" href="{{ route('users.index') }}">Batal</a>
    </div>
</form>
@endsection
