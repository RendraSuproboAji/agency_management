<?php

use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CaptureSessionController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliverableController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Portal\AuthController as PortalAuthController;
use App\Http\Controllers\Portal\DeliverableController as PortalDeliverableController;
use App\Http\Controllers\Portal\DocumentController as PortalDocumentController;
use App\Http\Controllers\Portal\ProjectController as PortalProjectController;
use App\Http\Controllers\ProcessingJobController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectSceneController;
use App\Http\Controllers\PublicRequestController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\ServiceRequestController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Form request klien — publik, tanpa login.
Route::get('/request', [PublicRequestController::class, 'create'])->name('public.request.create');
Route::post('/request', [PublicRequestController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('public.request.store');

Route::middleware('guest:web')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

    // Lupa kata sandi staf. Throttle sama ketatnya dengan login: halaman ini
    // mengirim email atas nama siapa pun yang menebak alamat.
    Route::get('/forgot-password', fn () => app(PasswordResetController::class)->request('web'))->name('password.request');
    Route::post('/forgot-password', fn (Request $request) => app(PasswordResetController::class)->email($request, 'web'))
        ->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', fn (string $token, Request $request) => app(PasswordResetController::class)->reset('web', $token, $request))
        ->name('password.reset');
    Route::post('/reset-password', fn (Request $request) => app(PasswordResetController::class)->update($request, 'web'))
        ->middleware('throttle:6,1')->name('password.update');
});

Route::middleware('auth:web')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Klien
    // Profil sendiri: mengganti kata sandi tidak lagi harus lewat admin.
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
    Route::get('/clients/create', [ClientController::class, 'create'])->name('clients.create');
    Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');
    Route::get('/clients/{client}', [ClientController::class, 'show'])->name('clients.show');
    Route::get('/clients/{client}/edit', [ClientController::class, 'edit'])->name('clients.edit');
    Route::put('/clients/{client}', [ClientController::class, 'update'])->name('clients.update');
    Route::delete('/clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');

    // Request masuk dari form publik
    Route::get('/requests', [ServiceRequestController::class, 'index'])->name('requests.index');
    Route::get('/requests/{serviceRequest}', [ServiceRequestController::class, 'show'])->name('requests.show');
    // Penawaran untuk calon klien: menempel pada permintaan yang masuk,
    // tanpa perlu membuat data klien dan project lebih dulu.
    Route::get('/requests/{serviceRequest}/quotations/create', [QuotationController::class, 'createForRequest'])->name('requests.quotations.create');
    Route::post('/requests/{serviceRequest}/quotations', [QuotationController::class, 'storeForRequest'])->name('requests.quotations.store');
    Route::get('/requests/{serviceRequest}/quotations/{quotation}/print', [QuotationController::class, 'printForRequest'])->name('requests.quotations.print');
    Route::delete('/requests/{serviceRequest}/quotations/{quotation}', [QuotationController::class, 'destroyForRequest'])->name('requests.quotations.destroy');

    Route::put('/requests/{serviceRequest}/status', [ServiceRequestController::class, 'updateStatus'])->name('requests.status');
    Route::post('/requests/{serviceRequest}/convert', [ServiceRequestController::class, 'convert'])->name('requests.convert');
    Route::delete('/requests/{serviceRequest}', [ServiceRequestController::class, 'destroy'])
        ->middleware('admin')
        ->name('requests.destroy');

    // Inventaris peralatan
    Route::get('/equipment', [EquipmentController::class, 'index'])->name('equipment.index');
    Route::get('/equipment/create', [EquipmentController::class, 'create'])->name('equipment.create');
    Route::post('/equipment', [EquipmentController::class, 'store'])->name('equipment.store');
    Route::get('/equipment/{equipment}/edit', [EquipmentController::class, 'edit'])->name('equipment.edit');
    Route::put('/equipment/{equipment}', [EquipmentController::class, 'update'])->name('equipment.update');
    Route::delete('/equipment/{equipment}', [EquipmentController::class, 'destroy'])->name('equipment.destroy');

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
    // Scene per project (mis. lobi, ruang pamer) — deliverable dan sesi bisa
    // ditautkan ke salah satunya.
    Route::post('/projects/{project}/scenes', [ProjectSceneController::class, 'store'])->name('scenes.store');
    Route::put('/projects/{project}/scenes/{scene}', [ProjectSceneController::class, 'update'])->name('scenes.update');
    Route::delete('/projects/{project}/scenes/{scene}', [ProjectSceneController::class, 'destroy'])->name('scenes.destroy');

    Route::get('/projects/{project}/sessions/create', [CaptureSessionController::class, 'create'])->name('sessions.create');
    Route::post('/projects/{project}/sessions', [CaptureSessionController::class, 'store'])->name('sessions.store');
    Route::get('/projects/{project}/sessions/{session}/edit', [CaptureSessionController::class, 'edit'])->name('sessions.edit');
    Route::put('/projects/{project}/sessions/{session}', [CaptureSessionController::class, 'update'])->name('sessions.update');
    Route::put('/projects/{project}/sessions/{session}/complete', [CaptureSessionController::class, 'complete'])->name('sessions.complete');
    Route::delete('/projects/{project}/sessions/{session}', [CaptureSessionController::class, 'destroy'])->name('sessions.destroy');

    // Deliverable
    Route::get('/projects/{project}/deliverables/create', [DeliverableController::class, 'create'])->name('deliverables.create');
    Route::post('/projects/{project}/deliverables', [DeliverableController::class, 'store'])->name('deliverables.store');
    Route::get('/projects/{project}/deliverables/{deliverable}/download', [DeliverableController::class, 'download'])->name('deliverables.download');
    Route::get('/projects/{project}/deliverables/{deliverable}/edit', [DeliverableController::class, 'edit'])->name('deliverables.edit');
    Route::put('/projects/{project}/deliverables/{deliverable}', [DeliverableController::class, 'update'])->name('deliverables.update');
    Route::put('/projects/{project}/deliverables/{deliverable}/approve', [DeliverableController::class, 'approve'])->name('deliverables.approve');
    Route::put('/projects/{project}/deliverables/{deliverable}/revision', [DeliverableController::class, 'requestRevision'])->name('deliverables.revision');
    Route::delete('/projects/{project}/deliverables/{deliverable}', [DeliverableController::class, 'destroy'])->name('deliverables.destroy');

    // Job processing (photogrammetry / splat training)
    Route::post('/projects/{project}/jobs', [ProcessingJobController::class, 'store'])->name('jobs.store');
    Route::put('/projects/{project}/jobs/{job}', [ProcessingJobController::class, 'update'])->name('jobs.update');
    Route::put('/projects/{project}/jobs/{job}/start', [ProcessingJobController::class, 'start'])->name('jobs.start');
    Route::put('/projects/{project}/jobs/{job}/finish', [ProcessingJobController::class, 'finish'])->name('jobs.finish');
    Route::delete('/projects/{project}/jobs/{job}', [ProcessingJobController::class, 'destroy'])->name('jobs.destroy');

    // Lampiran & catatan internal
    Route::post('/projects/{project}/attachments', [AttachmentController::class, 'store'])->name('attachments.store');
    Route::get('/projects/{project}/attachments/{attachment}', [AttachmentController::class, 'download'])->name('attachments.download');
    Route::delete('/projects/{project}/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');
    Route::post('/projects/{project}/notes', [NoteController::class, 'store'])->name('notes.store');
    Route::delete('/projects/{project}/notes/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');

    // Penawaran
    Route::get('/projects/{project}/quotations/create', [QuotationController::class, 'create'])->name('quotations.create');
    Route::post('/projects/{project}/quotations', [QuotationController::class, 'store'])->name('quotations.store');
    Route::get('/projects/{project}/quotations/{quotation}', [QuotationController::class, 'show'])->name('quotations.show');
    Route::get('/projects/{project}/quotations/{quotation}/print', [QuotationController::class, 'print'])->name('quotations.print');
    Route::get('/projects/{project}/quotations/{quotation}/edit', [QuotationController::class, 'edit'])->name('quotations.edit');
    Route::put('/projects/{project}/quotations/{quotation}', [QuotationController::class, 'update'])->name('quotations.update');
    Route::put('/projects/{project}/quotations/{quotation}/accept', [QuotationController::class, 'accept'])->name('quotations.accept');
    Route::delete('/projects/{project}/quotations/{quotation}', [QuotationController::class, 'destroy'])->name('quotations.destroy');

    // Invoice & pembayaran
    Route::get('/invoices', [PaymentController::class, 'index'])->name('invoices.index');
    Route::get('/projects/{project}/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
    Route::post('/projects/{project}/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('/projects/{project}/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('/projects/{project}/invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print');
    Route::get('/projects/{project}/invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
    Route::put('/projects/{project}/invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
    Route::delete('/projects/{project}/invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
    Route::post('/projects/{project}/invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::delete('/projects/{project}/invoices/{invoice}/payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');

    // Kelola pengguna & arsip (admin saja)
    Route::middleware('admin')->group(function () {
        Route::get('/archive', [ArchiveController::class, 'index'])->name('archive.index');
        Route::put('/archive/{type}/{id}/restore', [ArchiveController::class, 'restore'])->name('archive.restore');
        Route::delete('/archive/{type}/{id}', [ArchiveController::class, 'forceDelete'])->name('archive.force-delete');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
});

/*
|--------------------------------------------------------------------------
| Portal klien
|--------------------------------------------------------------------------
| Guard terpisah ("client"): akses baca ke project miliknya sendiri, plus
| menyetujui atau meminta revisi deliverable.
*/
Route::prefix('portal')->name('portal.')->group(function () {
    Route::middleware('guest:client')->group(function () {
        Route::get('/login', [PortalAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [PortalAuthController::class, 'login'])->middleware('throttle:6,1');

        Route::get('/forgot-password', fn () => app(PasswordResetController::class)->request('client'))->name('password.request');
        Route::post('/forgot-password', fn (Request $request) => app(PasswordResetController::class)->email($request, 'client'))
            ->middleware('throttle:6,1')->name('password.email');
        Route::get('/reset-password/{token}', fn (string $token, Request $request) => app(PasswordResetController::class)->reset('client', $token, $request))
            ->name('password.reset');
        Route::post('/reset-password', fn (Request $request) => app(PasswordResetController::class)->update($request, 'client'))
            ->middleware('throttle:6,1')->name('password.update');
    });

    Route::middleware('auth:client')->group(function () {
        Route::get('/', [PortalProjectController::class, 'index'])->name('dashboard');
        Route::post('/logout', [PortalAuthController::class, 'logout'])->name('logout');
        Route::get('/projects/{project}', [PortalProjectController::class, 'show'])->name('projects.show');
        Route::get('/projects/{project}/quotations/{quotation}/print', [PortalDocumentController::class, 'quotation'])->name('quotations.print');
        Route::get('/projects/{project}/invoices/{invoice}/print', [PortalDocumentController::class, 'invoice'])->name('invoices.print');
        Route::get('/projects/{project}/deliverables/{deliverable}/download', [PortalDeliverableController::class, 'download'])->name('deliverables.download');
        Route::put('/projects/{project}/deliverables/{deliverable}/approve', [PortalDeliverableController::class, 'approve'])->name('deliverables.approve');
        Route::put('/projects/{project}/deliverables/{deliverable}/revision', [PortalDeliverableController::class, 'requestRevision'])->name('deliverables.revision');
    });
});
