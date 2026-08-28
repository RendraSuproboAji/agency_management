<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    /** Rekap pembayaran & piutang lintas project. */
    public function index(Request $request): View
    {
        $invoices = Invoice::query()
            ->with('project.client')
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->boolean('unsettled'), fn ($query) => $query->unsettled())
            ->orderByRaw('due_at is null, due_at asc')
            ->paginate(20)
            ->withQueryString();

        return view('invoices.index', [
            'invoices' => $invoices,
            'filters' => $request->only(['status', 'unsettled']),
        ]);
    }

    public function store(Request $request, Project $project, Invoice $invoice): RedirectResponse
    {
        $this->authorizePayment($request, $project, $invoice);

        $data = $request->validate([
            'paid_at' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:'.implode(',', Payment::METHODS)],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $invoice->payments()->create($data);
        $invoice->refresh()->recalculateStatus();

        return back()->with('status', 'Pembayaran dicatat.');
    }

    public function destroy(Request $request, Project $project, Invoice $invoice, Payment $payment): RedirectResponse
    {
        $this->authorizePayment($request, $project, $invoice);
        abort_unless($payment->invoice_id === $invoice->id, 404);

        $payment->delete();
        $invoice->refresh()->recalculateStatus();

        return back()->with('status', 'Pembayaran dihapus.');
    }

    private function authorizePayment(Request $request, Project $project, Invoice $invoice): void
    {
        abort_unless($invoice->project_id === $project->id, 404);
        abort_unless($project->isManageableBy($request->user()), 403);
    }
}
