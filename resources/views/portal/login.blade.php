@extends('layouts.portal')
@section('title', 'Portal klien · '.config('site.name'))

@section('content')
<div class="login-card">
    <h1>Portal klien</h1>
    <p class="muted">Pantau progres project dan setujui hasil pekerjaan Anda.</p>

    <form method="post" action="{{ route('portal.login') }}">
        @csrf
        <label>Email
            <input type="email" name="email" value="{{ old('email') }}" required autofocus>
        </label>
        <label>Kata sandi
            <input type="password" name="password" required>
        </label>
        <label class="inline">
            <input type="checkbox" name="remember" value="1"> Ingat saya
        </label>
        <button class="btn btn-primary" type="submit">Masuk</button>
    </form>
</div>
@endsection
