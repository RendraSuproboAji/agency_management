<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Deliverable;
use App\Models\Equipment;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quotation;
use App\Support\ActivityLogger;
use App\Support\Archive;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ArchiveController extends Controller
{
    /** Jenis yang bisa dipulihkan, dipetakan ke modelnya. */
    private const TYPES = [
        'clients' => Client::class,
        'projects' => Project::class,
        'quotations' => Quotation::class,
        'invoices' => Invoice::class,
        'deliverables' => Deliverable::class,
        'equipment' => Equipment::class,
    ];

    public function index(): View
    {
        return view('archive.index', [
            'clients' => Client::onlyTrashed()->latest('deleted_at')->get(),
            'projects' => Project::onlyTrashed()->with('client')->latest('deleted_at')->get(),
            'quotations' => Quotation::onlyTrashed()->with('project')->latest('deleted_at')->get(),
            'invoices' => Invoice::onlyTrashed()->with('project')->latest('deleted_at')->get(),
            'deliverables' => Deliverable::onlyTrashed()->with('project')->latest('deleted_at')->get(),
            'equipment' => Equipment::onlyTrashed()->latest('deleted_at')->get(),
        ]);
    }

    public function restore(string $type, int $id): RedirectResponse
    {
        $model = $this->resolve($type, $id);

        match (true) {
            $model instanceof Client => Archive::restoreClient($model),
            $model instanceof Project => Archive::restoreProject($model),
            default => $model->restore(),
        };

        ActivityLogger::log($model, 'archive.restored', 'Memulihkan '.$this->label($type).' dari arsip.');

        return back()->with('status', 'Data dipulihkan dari arsip.');
    }

    public function forceDelete(string $type, int $id): RedirectResponse
    {
        $model = $this->resolve($type, $id);

        // Hapus permanen ikut membuang berkas fisiknya — tidak ada jalan pulang.
        if ($model instanceof Deliverable && $model->file_path) {
            Storage::disk('public')->delete($model->file_path);
        }

        if ($model instanceof Project) {
            foreach ($model->attachments()->get() as $attachment) {
                Storage::disk('public')->delete($attachment->file_path);
            }
        }

        $model->forceDelete();

        return back()->with('status', 'Data dihapus permanen.');
    }

    private function resolve(string $type, int $id): mixed
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        return self::TYPES[$type]::onlyTrashed()->findOrFail($id);
    }

    private function label(string $type): string
    {
        return match ($type) {
            'clients' => 'klien',
            'projects' => 'project',
            'quotations' => 'penawaran',
            'invoices' => 'invoice',
            'deliverables' => 'deliverable',
            'equipment' => 'peralatan',
        };
    }
}
