@extends('layouts.app')
@section('title', 'Project baru · '.config('site.name'))

@section('content')
<h1>Project baru</h1>
<form method="post" action="{{ route('projects.store') }}">
    @csrf
    @include('projects._form')
    <div class="form-actions">
        <button class="btn btn-primary">Simpan</button>
        <a class="btn btn-ghost" href="{{ route('projects.index') }}">Batal</a>
    </div>
</form>
@endsection
