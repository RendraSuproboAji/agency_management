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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

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

    public function index(): Response
    {
        return Inertia::render('Archive/Index', [
            'groups' => [
                $this->group('clients', 'Klien', Client::onlyTrashed()->latest('deleted_at')->get(),
                    fn ($item) => [$item->name, null]),
                $this->group('projects', 'Project', Project::onlyTrashed()->with('client')->latest('deleted_at')->get(),
                    fn ($item) => [$item->title, $item->client?->name]),
                $this->group('quotations', 'Penawaran', Quotation::onlyTrashed()->with('project')->latest('deleted_at')->get(),
                    fn ($item) => [$item->number, $item->project?->title]),
                $this->group('invoices', 'Invoice', Invoice::onlyTrashed()->with('project')->latest('deleted_at')->get(),
                    fn ($item) => [$item->number, $item->project?->title]),
                $this->group('deliverables', 'Deliverable', Deliverable::onlyTrashed()->with('project')->latest('deleted_at')->get(),
                    fn ($item) => [$item->title.' v'.$item->version, $item->project?->title]),
                $this->group('equipment', 'Peralatan', Equipment::onlyTrashed()->latest('deleted_at')->get(),
                    fn ($item) => [$item->name, $item->code]),
            ],
        ]);
    }

    /**
     * @param  Collection<int, mixed>  $items
     * @return array<string, mixed>
     */
    private function group(string $type, string $label, $items, callable $describe): array
    {
        return [
            'type' => $type,
            'label' => $label,
            'items' => $items->map(function ($item) use ($describe) {
                [$title, $meta] = $describe($item);

                return [
                    'id' => $item->id,
                    'label' => $title,
                    'meta' => $meta,
                    'deleted_at' => $item->deleted_at->format('d M Y H:i'),
                ];
            })->values(),
        ];
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
        $this->deleteFiles($model);

        $model->forceDelete();

        return back()->with('status', 'Data dihapus permanen.');
    }

    /**
     * Kumpulkan seluruh berkas milik satu record lalu hapus. Untuk project,
     * turunannya ikut terhapus oleh foreign key, jadi berkas deliverable dan
     * lampirannya harus dibuang di sini — kalau tidak, keduanya jadi yatim.
     */
    private function deleteFiles(mixed $model): void
    {
        // Deliverable dibagikan ke klien lewat disk publik; lampiran internal
        // hidup di disk privat. Keduanya harus dibersihkan dari disknya sendiri.
        [$public, $private] = match (true) {
            $model instanceof Deliverable => [[$model->file_path], []],
            $model instanceof Project => [
                $model->deliverables()->withTrashed()->pluck('file_path')->all(),
                $model->attachments()->pluck('file_path')->all(),
            ],
            default => [[], []],
        };

        if ($paths = array_filter($public)) {
            Storage::disk('public')->delete($paths);
        }

        if ($paths = array_filter($private)) {
            Storage::disk('local')->delete($paths);
        }
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
