@extends('layouts.app')
@section('title', 'Tambah deliverable · '.config('site.name'))

@section('content')
<h1>Tambah deliverable — {{ $project->title }}</h1>
<form method="post" action="{{ route('deliverables.store', $project) }}" enctype="multipart/form-data">
    @csrf
    @include('deliverables._form')
    <div class="form-actions">
        <button class="btn btn-primary">Simpan</button>
        <a class="btn btn-ghost" href="{{ route('projects.show', $project) }}">Batal</a>
    </div>
</form>
@endsection
