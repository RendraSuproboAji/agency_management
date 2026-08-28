@extends('layouts.app')
@section('title', 'Ubah sesi · '.config('site.name'))

@section('content')
<h1>Ubah sesi — {{ $project->title }}</h1>
<form method="post" action="{{ route('sessions.update', [$project, $session]) }}">
    @csrf @method('put')
    @include('sessions._form')
    <div class="form-actions">
        <button class="btn btn-primary">Simpan</button>
        <a class="btn btn-ghost" href="{{ route('projects.show', $project) }}">Batal</a>
    </div>
</form>
@endsection
