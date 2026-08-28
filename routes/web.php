<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CaptureSessionController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliverableController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
});

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Klien
    Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
    Route::get('/clients/create', [ClientController::class, 'create'])->name('clients.create');
    Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');
    Route::get('/clients/{client}', [ClientController::class, 'show'])->name('clients.show');
    Route::get('/clients/{client}/edit', [ClientController::class, 'edit'])->name('clients.edit');
    Route::put('/clients/{client}', [ClientController::class, 'update'])->name('clients.update');
    Route::delete('/clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');

    // Agenda pengambilan gambar lintas project
    Route::get('/sessions', [CaptureSessionController::class, 'index'])->name('sessions.index');

    // Project
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::get('/projects/{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
    Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::put('/projects/{project}/status', [ProjectController::class, 'updateStatus'])->name('projects.status');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

    // Sesi pengambilan gambar milik satu project
    Route::get('/projects/{project}/sessions/create', [CaptureSessionController::class, 'create'])->name('sessions.create');
    Route::post('/projects/{project}/sessions', [CaptureSessionController::class, 'store'])->name('sessions.store');
    Route::get('/projects/{project}/sessions/{session}/edit', [CaptureSessionController::class, 'edit'])->name('sessions.edit');
    Route::put('/projects/{project}/sessions/{session}', [CaptureSessionController::class, 'update'])->name('sessions.update');
    Route::put('/projects/{project}/sessions/{session}/complete', [CaptureSessionController::class, 'complete'])->name('sessions.complete');
    Route::delete('/projects/{project}/sessions/{session}', [CaptureSessionController::class, 'destroy'])->name('sessions.destroy');

    // Deliverable
    Route::get('/projects/{project}/deliverables/create', [DeliverableController::class, 'create'])->name('deliverables.create');
    Route::post('/projects/{project}/deliverables', [DeliverableController::class, 'store'])->name('deliverables.store');
    Route::get('/projects/{project}/deliverables/{deliverable}/edit', [DeliverableController::class, 'edit'])->name('deliverables.edit');
    Route::put('/projects/{project}/deliverables/{deliverable}', [DeliverableController::class, 'update'])->name('deliverables.update');
    Route::put('/projects/{project}/deliverables/{deliverable}/approve', [DeliverableController::class, 'approve'])->name('deliverables.approve');
    Route::put('/projects/{project}/deliverables/{deliverable}/revision', [DeliverableController::class, 'requestRevision'])->name('deliverables.revision');
    Route::delete('/projects/{project}/deliverables/{deliverable}', [DeliverableController::class, 'destroy'])->name('deliverables.destroy');

    // Kelola pengguna (admin saja)
    Route::middleware('admin')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
});
