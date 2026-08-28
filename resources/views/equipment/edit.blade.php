@extends('layouts.app')
@section('title', 'Ubah peralatan · '.config('site.name'))

@section('content')
<h1>Ubah peralatan</h1>
<form method="post" action="{{ route('equipment.update', $item) }}">
    @csrf @method('put')
    @include('equipment._form')
    <div class="form-actions">
        <button class="btn btn-primary">Simpan</button>
        <a class="btn btn-ghost" href="{{ route('equipment.index') }}">Batal</a>
    </div>
</form>
@endsection
