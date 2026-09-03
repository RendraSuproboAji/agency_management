<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Support\DocumentNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function create(Request $request, Project $project): Response
    {
        abort_unless($project->isManageableBy($request->user()), 403);

        // Datang dari tombol "Buat invoice dari penawaran ini": salin nilainya.
        $quotation = $request->query('quotation')
            ? $project->quotations()->with('items')->findOrFail($request->query('quotation'))
            : null;

        return Inertia::render('Invoices/Form', [
            'project' => $project->only(['slug', 'title']),
            'fromQuotation' => $quotation?->number,
            'invoice' => [
                'quotation_id' => $quotation?->id,
                'issued_at' => now()->toDateString(),
                'due_at' => now()->addDays(14)->toDateString(),
                'amount' => $quotation?->total(),
                'status' => 'draft',
            ],
        ] + $this->formOptions($project));
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        abort_unless($project->isManageableBy($request->user()), 403);

        $data = $this->validated($request, $project);

        $invoice = DocumentNumber::assign(
            Invoice::class,
            'INV',
            fn (string $number) => $project->invoices()->create($data + ['number' => $number]),
        );

        return redirect()->route('invoices.show', [$project, $invoice])
            ->with('status', 'Invoice '.$invoice->number.' dibuat.');
    }

    public function show(Request $request, Project $project, Invoice $invoice): Response
    {
        abort_unless($invoice->project_id === $project->id, 404);

        $invoice->load(['payments' => fn ($query) => $query->orderBy('paid_at'), 'quotation']);

        return Inertia::render('Invoices/Show', [
            'project' => $project->only(['slug', 'title']),
            'invoice' => [
                ...$invoice->only(['id', 'number', 'status', 'notes', 'amount']),
                'issued_at' => $invoice->issued_at->format('d M Y'),
                'due_at' => $invoice->due_at?->format('d M Y'),
                'paid' => $invoice->paidAmount(),
                'outstanding' => $invoice->outstanding(),
                'quotation' => $invoice->quotation?->only(['id', 'number']),
                'payments' => $invoice->payments->map(fn ($payment) => [
                    ...$payment->only(['id', 'amount', 'method', 'reference', 'note']),
                    'paid_at' => $payment->paid_at->format('d M Y'),
                ]),
            ],
            'canManage' => $project->isManageableBy($request->user()),
            'methods' => Payment::METHODS,
        ]);
    }

    public function print(Project $project, Invoice $invoice): View
    {
        abort_unless($invoice->project_id === $project->id, 404);

        return view('invoices.print', [
            'project' => $project->load('client'),
            'invoice' => $invoice->load(['payments' => fn ($query) => $query->orderBy('paid_at'), 'quotation']),
            'backUrl' => route('invoices.show', [$project, $invoice]),
        ]);
    }

    public function edit(Request $request, Project $project, Invoice $invoice): Response
    {
        $this->authorizeInvoice($request, $project, $invoice);

        return Inertia::render('Invoices/Form', [
            'project' => $project->only(['slug', 'title']),
            'fromQuotation' => null,
            'invoice' => [
                ...$invoice->only(['id', 'number', 'quotation_id', 'amount', 'status', 'notes']),
                'issued_at' => $invoice->issued_at->format('Y-m-d'),
                'due_at' => $invoice->due_at?->format('Y-m-d'),
            ],
        ] + $this->formOptions($project));
    }

    public function update(Request $request, Project $project, Invoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($request, $project, $invoice);

        $invoice->update($this->validated($request, $project));
        $invoice->recalculateStatus();

        return redirect()->route('invoices.show', [$project, $invoice])
            ->with('status', 'Invoice diperbarui.');
    }

    public function destroy(Request $request, Project $project, Invoice $invoice): RedirectResponse
    {
        abort_unless($invoice->project_id === $project->id, 404);
        abort_unless($request->user()->isAdmin(), 403);

        $invoice->delete();

        return redirect()->route('projects.show', $project)->with('status', 'Invoice dihapus.');
    }

    /** @return array<string, mixed> */
    private function formOptions(Project $project): array
    {
        return [
            'quotations' => $project->quotations()->orderByDesc('issued_at')->get(['id', 'number']),
            'statuses' => Invoice::STATUSES,
        ];
    }

    private function authorizeInvoice(Request $request, Project $project, Invoice $invoice): void
    {
        abort_unless($invoice->project_id === $project->id, 404);
        abort_unless($project->isManageableBy($request->user()), 403);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, Project $project): array
    {
        return $request->validate([
            'quotation_id' => [
                'nullable',
                Rule::exists('quotations', 'id')->where('project_id', $project->id)->whereNull('deleted_at'),
            ],
            'issued_at' => ['required', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:'.implode(',', Invoice::STATUSES)],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
