@extends('layouts.app')
@section('title', 'Penawaran baru · '.config('site.name'))

@section('content')
<h1>Penawaran baru — {{ $project->title }}</h1>
<form method="post" action="{{ route('quotations.store', $project) }}">
    @csrf
    @include('quotations._form')
    <div class="form-actions">
        <button class="btn btn-primary">Simpan</button>
        <a class="btn btn-ghost" href="{{ route('projects.show', $project) }}">Batal</a>
    </div>
</form>
@endsection
