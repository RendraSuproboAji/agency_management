@extends('layouts.app')
@section('title', 'Masuk · '.config('site.name'))

@section('content')
<div class="login-card">
    <h1>{{ config('site.name') }}</h1>
    <p class="muted">{{ config('site.tagline') }}</p>

    <form method="post" action="{{ route('login') }}">
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
