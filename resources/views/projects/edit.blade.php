@extends('layouts.app')
@section('title', 'Ubah project · '.config('site.name'))

@section('content')
<h1>Ubah project</h1>
<form method="post" action="{{ route('projects.update', $project) }}">
    @csrf @method('put')
    @include('projects._form')
    <div class="form-actions">
        <button class="btn btn-primary">Simpan</button>
        <a class="btn btn-ghost" href="{{ route('projects.show', $project) }}">Batal</a>
    </div>
</form>
@endsection
