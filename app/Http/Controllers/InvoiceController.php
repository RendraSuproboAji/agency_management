<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Project;
use App\Support\DocumentNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function create(Request $request, Project $project): View
    {
        abort_unless($project->isManageableBy($request->user()), 403);

        // Datang dari tombol "Buat invoice dari penawaran ini": salin nilainya.
        $quotation = $request->query('quotation')
            ? $project->quotations()->with('items')->findOrFail($request->query('quotation'))
            : null;

        return view('invoices.create', [
            'project' => $project,
            'quotation' => $quotation,
            'invoice' => new Invoice([
                'quotation_id' => $quotation?->id,
                'issued_at' => now()->toDateString(),
                'due_at' => now()->addDays(14)->toDateString(),
                'amount' => $quotation?->total(),
                'status' => 'draft',
            ]),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        abort_unless($project->isManageableBy($request->user()), 403);

        $data = $this->validated($request, $project);

        $invoice = $project->invoices()->create($data + [
            'number' => DocumentNumber::next(Invoice::class, 'INV'),
        ]);

        return redirect()->route('invoices.show', [$project, $invoice])
            ->with('status', 'Invoice '.$invoice->number.' dibuat.');
    }

    public function show(Project $project, Invoice $invoice): View
    {
        abort_unless($invoice->project_id === $project->id, 404);

        return view('invoices.show', [
            'project' => $project,
            'invoice' => $invoice->load(['payments' => fn ($query) => $query->orderBy('paid_at'), 'quotation']),
        ]);
    }

    public function edit(Request $request, Project $project, Invoice $invoice): View
    {
        $this->authorizeInvoice($request, $project, $invoice);

        return view('invoices.edit', [
            'project' => $project,
            'invoice' => $invoice,
            'quotation' => $invoice->quotation,
        ]);
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
                Rule::exists('quotations', 'id')->where('project_id', $project->id),
            ],
            'issued_at' => ['required', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:'.implode(',', Invoice::STATUSES)],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
