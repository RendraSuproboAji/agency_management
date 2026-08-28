@extends('layouts.app')
@section('title', 'Ubah klien · '.config('site.name'))

@section('content')
<h1>Ubah klien</h1>
<form method="post" action="{{ route('clients.update', $client) }}">
    @csrf @method('put')
    @include('clients._form')
    <div class="form-actions">
        <button class="btn btn-primary">Simpan</button>
        <a class="btn btn-ghost" href="{{ route('clients.show', $client) }}">Batal</a>
    </div>
</form>
@endsection
