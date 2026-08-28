@extends('layouts.app')
@section('title', 'Pengguna · '.config('site.name'))

@section('content')
<div class="page-head">
    <h1>Pengguna</h1>
    <a class="btn btn-primary" href="{{ route('users.create') }}">Tambah pengguna</a>
</div>

<table class="table">
    <thead><tr><th>Nama</th><th>Email</th><th>Peran</th><th>Project</th><th></th></tr></thead>
    <tbody>
    @foreach ($users as $user)
        <tr>
            <td>{{ $user->name }}</td>
            <td>{{ $user->email }}</td>
            <td>@include('partials.status-badge', ['status' => $user->role])</td>
            <td>{{ $user->owned_projects_count }}</td>
            <td class="row-actions">
                <a href="{{ route('users.edit', $user) }}">Ubah</a>
                <form method="post" action="{{ route('users.destroy', $user) }}" data-confirm="Hapus pengguna ini?">
                    @csrf @method('delete')
                    <button class="btn btn-mini btn-danger">Hapus</button>
                </form>
            </td>
        </tr>
    @endforeach
    </tbody>
</table>

{{ $users->links() }}
@endsection
