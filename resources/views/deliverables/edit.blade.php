@extends('layouts.app')
@section('title', 'Ubah deliverable · '.config('site.name'))

@section('content')
<h1>Ubah deliverable — {{ $project->title }}</h1>
<form method="post" action="{{ route('deliverables.update', [$project, $deliverable]) }}" enctype="multipart/form-data">
    @csrf @method('put')
    @include('deliverables._form')
    <div class="form-actions">
        <button class="btn btn-primary">Simpan</button>
        <a class="btn btn-ghost" href="{{ route('projects.show', $project) }}">Batal</a>
    </div>
</form>
@endsection
