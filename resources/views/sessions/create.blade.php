@extends('layouts.app')
@section('title', 'Jadwalkan sesi · '.config('site.name'))

@section('content')
<h1>Jadwalkan sesi — {{ $project->title }}</h1>
<form method="post" action="{{ route('sessions.store', $project) }}">
    @csrf
    @include('sessions._form')
    <div class="form-actions">
        <button class="btn btn-primary">Simpan</button>
        <a class="btn btn-ghost" href="{{ route('projects.show', $project) }}">Batal</a>
    </div>
</form>
@endsection
