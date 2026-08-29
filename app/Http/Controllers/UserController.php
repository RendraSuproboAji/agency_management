<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Users/Index', [
            'users' => User::withCount('ownedProjects')->orderBy('name')->paginate(20),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Users/Form', [
            'user' => new User(['role' => 'staff']),
            'roles' => User::ROLES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'role' => ['required', 'in:'.implode(',', User::ROLES)],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $data['password'] = Hash::make($data['password']);
        User::create($data);

        return redirect()->route('users.index')->with('status', 'Pengguna ditambahkan.');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Users/Form', [
            'user' => $user->only(['id', 'name', 'email', 'role']),
            'roles' => User::ROLES,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', 'in:'.implode(',', User::ROLES)],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        // Jangan sampai admin terakhir menurunkan dirinya sendiri jadi staff.
        if ($data['role'] !== 'admin' && $user->isAdmin() && User::where('role', 'admin')->count() === 1) {
            return back()->withErrors(['role' => 'Minimal harus ada satu admin.'])->withInput();
        }

        if (filled($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()->route('users.index')->with('status', 'Pengguna diperbarui.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'Tidak bisa menghapus akun sendiri.']);
        }

        if ($user->isAdmin() && User::where('role', 'admin')->count() === 1) {
            return back()->withErrors(['user' => 'Minimal harus ada satu admin.']);
        }

        $user->delete();

        return redirect()->route('users.index')->with('status', 'Pengguna dihapus.');
    }
}
