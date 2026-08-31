<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Quotation;
use App\Models\ServiceRequest;
use App\Support\ActivityLogger;
use App\Support\DocumentNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class QuotationController extends Controller
{
    public function create(Request $request, Project $project): Response
    {
        abort_unless($project->isManageableBy($request->user()), 403);

        return Inertia::render('Quotations/Form', [
            'project' => $project->only(['slug', 'title']),
            'quotation' => [
                'issued_at' => now()->toDateString(),
                'valid_until' => now()->addDays(14)->toDateString(),
                'status' => 'draft',
                'tax_percent' => 11,
                'items' => [],
            ],
            'statuses' => Quotation::STATUSES,
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        abort_unless($project->isManageableBy($request->user()), 403);

        $data = $this->validated($request);

        $quotation = DocumentNumber::assign(Quotation::class, 'QUO', function (string $number) use ($project, $data) {
            $quotation = $project->quotations()->create($data + ['number' => $number]);

            $this->syncItems($quotation, $data['items']);

            return $quotation;
        });

        return redirect()->route('quotations.show', [$project, $quotation])
            ->with('status', 'Penawaran '.$quotation->number.' dibuat.');
    }

    public function show(Request $request, Project $project, Quotation $quotation): Response
    {
        abort_unless($quotation->project_id === $project->id, 404);

        $quotation->load('items', 'invoices');

        return Inertia::render('Quotations/Show', [
            'project' => $project->only(['slug', 'title']),
            'quotation' => [
                ...$quotation->only(['id', 'number', 'status', 'notes', 'tax_percent']),
                'issued_at' => $quotation->issued_at->format('d M Y'),
                'valid_until' => $quotation->valid_until?->format('d M Y'),
                'subtotal' => $quotation->subtotal(),
                'tax_amount' => $quotation->taxAmount(),
                'total' => $quotation->total(),
                'items' => $quotation->items->map(fn ($item) => [
                    ...$item->only(['id', 'description', 'qty', 'unit', 'unit_price']),
                    'line_total' => $item->lineTotal(),
                ]),
                'invoices' => $quotation->invoices->map(fn ($invoice) => $invoice->only(['id', 'number', 'status', 'amount'])),
            ],
            'canManage' => $project->isManageableBy($request->user()),
        ]);
    }

    public function print(Project $project, Quotation $quotation): View
    {
        abort_unless($quotation->project_id === $project->id, 404);

        return view('quotations.print', [
            'project' => $project->load('client'),
            'quotation' => $quotation->load('items'),
            'backUrl' => route('quotations.show', [$project, $quotation]),
        ]);
    }

    public function edit(Request $request, Project $project, Quotation $quotation): Response
    {
        $this->authorizeQuotation($request, $project, $quotation);

        $quotation->load('items');

        return Inertia::render('Quotations/Form', [
            'project' => $project->only(['slug', 'title']),
            'quotation' => [
                ...$quotation->only(['id', 'number', 'status', 'notes', 'tax_percent']),
                'issued_at' => $quotation->issued_at->format('Y-m-d'),
                'valid_until' => $quotation->valid_until?->format('Y-m-d'),
                'items' => $quotation->items->map(fn ($item) => $item->only(['description', 'qty', 'unit', 'unit_price'])),
            ],
            'statuses' => Quotation::STATUSES,
        ]);
    }

    public function update(Request $request, Project $project, Quotation $quotation): RedirectResponse
    {
        $this->authorizeQuotation($request, $project, $quotation);

        $data = $this->validated($request);

        DB::transaction(function () use ($quotation, $data) {
            $quotation->update($data);
            $quotation->items()->delete();
            $this->syncItems($quotation, $data['items']);
        });

        return redirect()->route('quotations.show', [$project, $quotation])
            ->with('status', 'Penawaran diperbarui.');
    }

    public function accept(Request $request, Project $project, Quotation $quotation): RedirectResponse
    {
        $this->authorizeQuotation($request, $project, $quotation);

        $quotation->update(['status' => 'accepted']);

        ActivityLogger::log($quotation, 'quotation.accepted', 'Penawaran '.$quotation->number.' disetujui klien.');

        return back()->with('status', 'Penawaran ditandai disetujui klien.');
    }

    public function destroy(Request $request, Project $project, Quotation $quotation): RedirectResponse
    {
        abort_unless($quotation->project_id === $project->id, 404);
        abort_unless($request->user()->isAdmin(), 403);

        $quotation->delete();

        return redirect()->route('projects.show', $project)->with('status', 'Penawaran dihapus.');
    }

    private function authorizeQuotation(Request $request, Project $project, Quotation $quotation): void
    {
        abort_unless($quotation->project_id === $project->id, 404);
        abort_unless($project->isManageableBy($request->user()), 403);
    }

    /** @param  array<int, array<string, mixed>>  $items */
    private function syncItems(Quotation $quotation, array $items): void
    {
        foreach ($items as $item) {
            $quotation->items()->create($item);
        }
    }

    // --- Penawaran untuk calon klien -------------------------------------
    //
    // Penawaran boleh ditujukan ke permintaan yang masuk lewat form publik,
    // tanpa perlu membuat data klien dan project lebih dulu. Metode-metode
    // berikut memakai ulang validasi, penomoran, dan penyusunan item yang
    // sama dengan penawaran project.

    public function createForRequest(ServiceRequest $serviceRequest): Response
    {
        return Inertia::render('Quotations/Form', [
            'serviceRequest' => $this->requestSummary($serviceRequest),
            'quotation' => [
                'issued_at' => now()->toDateString(),
                'valid_until' => now()->addDays(14)->toDateString(),
                'tax_percent' => 11,
                'status' => 'draft',
                'items' => [],
            ],
            'statuses' => Quotation::STATUSES,
        ]);
    }

    public function storeForRequest(Request $request, ServiceRequest $serviceRequest): RedirectResponse
    {
        $data = $this->validated($request);

        $quotation = DocumentNumber::assign(Quotation::class, 'QUO', function (string $number) use ($serviceRequest, $data) {
            $quotation = $serviceRequest->quotations()->create($data + ['number' => $number]);

            $this->syncItems($quotation, $data['items']);

            return $quotation;
        });

        return redirect()->route('requests.show', $serviceRequest)
            ->with('status', 'Penawaran '.$quotation->number.' dibuat untuk calon klien.');
    }

    public function showForRequest(ServiceRequest $serviceRequest, Quotation $quotation): Response
    {
        $this->authorizeProspectQuotation($serviceRequest, $quotation);

        $quotation->load('items');

        return Inertia::render('Quotations/Show', [
            'serviceRequest' => $this->requestSummary($serviceRequest),
            'quotation' => $this->quotationPayload($quotation),
            'canManage' => true,
        ]);
    }

    public function editForRequest(ServiceRequest $serviceRequest, Quotation $quotation): Response
    {
        $this->authorizeProspectQuotation($serviceRequest, $quotation);

        $quotation->load('items');

        return Inertia::render('Quotations/Form', [
            'serviceRequest' => $this->requestSummary($serviceRequest),
            'quotation' => [
                ...$quotation->only(['id', 'number', 'status', 'notes', 'tax_percent']),
                'issued_at' => $quotation->issued_at->format('Y-m-d'),
                'valid_until' => $quotation->valid_until?->format('Y-m-d'),
                'items' => $quotation->items->map(fn ($item) => $item->only(['description', 'qty', 'unit', 'unit_price'])),
            ],
            'statuses' => Quotation::STATUSES,
        ]);
    }

    public function updateForRequest(Request $request, ServiceRequest $serviceRequest, Quotation $quotation): RedirectResponse
    {
        $this->authorizeProspectQuotation($serviceRequest, $quotation);

        $data = $this->validated($request);

        DB::transaction(function () use ($quotation, $data) {
            $quotation->update($data);
            $quotation->items()->delete();
            $this->syncItems($quotation, $data['items']);
        });

        return redirect()->route('requests.quotations.show', [$serviceRequest, $quotation])
            ->with('status', 'Penawaran diperbarui.');
    }

    public function acceptForRequest(ServiceRequest $serviceRequest, Quotation $quotation): RedirectResponse
    {
        $this->authorizeProspectQuotation($serviceRequest, $quotation);

        $quotation->update(['status' => 'accepted']);

        ActivityLogger::log($quotation, 'quotation.accepted', 'Penawaran '.$quotation->number.' disetujui calon klien.');

        // Sengaja tidak ikut mengonversi. Menyetujui dan menjadikan klien
        // adalah dua keputusan berbeda — yang kedua menentukan nama klien,
        // judul project, dan PIC-nya.
        return back()->with('status', 'Penawaran ditandai disetujui. Konversi request ini untuk mulai mengerjakan dan menagih.');
    }

    private function authorizeProspectQuotation(ServiceRequest $serviceRequest, Quotation $quotation): void
    {
        abort_unless($quotation->service_request_id === $serviceRequest->id, 404);
    }

    /** @return array<string, mixed> */
    private function quotationPayload(Quotation $quotation): array
    {
        return [
            ...$quotation->only(['id', 'number', 'status', 'notes', 'tax_percent']),
            'issued_at' => $quotation->issued_at->format('d M Y'),
            'valid_until' => $quotation->valid_until?->format('d M Y'),
            'subtotal' => $quotation->subtotal(),
            'tax_amount' => $quotation->taxAmount(),
            'total' => $quotation->total(),
            'items' => $quotation->items->map(fn ($item) => [
                ...$item->only(['id', 'description', 'qty', 'unit', 'unit_price']),
                'line_total' => $item->lineTotal(),
            ]),
            'invoices' => [],
        ];
    }

    public function printForRequest(ServiceRequest $serviceRequest, Quotation $quotation): View
    {
        abort_unless($quotation->service_request_id === $serviceRequest->id, 404);

        return view('quotations.print', [
            'quotation' => $quotation->load('items', 'serviceRequest'),
            'backUrl' => route('requests.show', $serviceRequest),
        ]);
    }

    public function destroyForRequest(ServiceRequest $serviceRequest, Quotation $quotation): RedirectResponse
    {
        abort_unless($quotation->service_request_id === $serviceRequest->id, 404);

        $quotation->delete();

        return back()->with('status', 'Penawaran diarsipkan.');
    }

    /** @return array<string, mixed> */
    private function requestSummary(ServiceRequest $serviceRequest): array
    {
        return [
            'id' => $serviceRequest->id,
            'name' => $serviceRequest->company ?: $serviceRequest->name,
            'contact_name' => $serviceRequest->name,
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'issued_at' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'tax_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'status' => ['required', 'in:'.implode(',', Quotation::STATUSES)],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.qty' => ['required', 'numeric', 'min:0'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);
    }
}
