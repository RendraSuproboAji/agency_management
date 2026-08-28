<div class="form-grid">
    <label>Nama *
        <input type="text" name="name" value="{{ old('name', $user->name) }}" required>
    </label>
    <label>Email *
        <input type="email" name="email" value="{{ old('email', $user->email) }}" required>
    </label>
    <label>Peran *
        <select name="role" required>
            @foreach (\App\Models\User::ROLES as $option)
                <option value="{{ $option }}" @selected(old('role', $user->role) === $option)>{{ $option }}</option>
            @endforeach
        </select>
    </label>
    <label>Kata sandi {{ $user->exists ? '(kosongkan bila tidak diubah)' : '*' }}
        <input type="password" name="password" @required(! $user->exists) minlength="8">
    </label>
</div>
