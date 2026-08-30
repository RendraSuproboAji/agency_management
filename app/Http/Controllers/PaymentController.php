<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    /** Rekap pembayaran & piutang lintas project. */
    public function index(Request $request): Response
    {
        $invoices = Invoice::query()
            ->with('project.client', 'payments')
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->boolean('unsettled'), fn ($query) => $query->unsettled())
            ->orderByRaw('due_at is null, due_at asc')
            ->paginate(20)
            ->withQueryString();

        $invoices->through(fn (Invoice $invoice) => [
            ...$invoice->only(['id', 'number', 'status', 'amount']),
            'due_at' => $invoice->due_at?->format('d M Y'),
            'outstanding' => $invoice->outstanding(),
            'project_title' => $invoice->project->title,
            'project_slug' => $invoice->project->slug,
            'client_name' => $invoice->project->client->name,
        ]);

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices,
            'filters' => $request->only(['status', 'unsettled']),
            'statuses' => Invoice::STATUSES,
        ]);
    }

    public function store(Request $request, Project $project, Invoice $invoice): RedirectResponse
    {
        $this->authorizePayment($request, $project, $invoice);

        $data = $request->validate([
            // Pembayaran tidak boleh mendahului tanggal terbit invoice-nya.
            'paid_at' => ['required', 'date', 'after_or_equal:'.$invoice->issued_at->toDateString()],
            // Batas atas sisa tagihan: tanpa ini invoice bisa "lunas" oleh
            // nominal yang lebih besar dari nilainya, dan kelebihannya hilang
            // karena outstanding() menjepit di nol.
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$invoice->outstanding()],
            'method' => ['required', 'in:'.implode(',', Payment::METHODS)],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $payment = $invoice->payments()->create($data);
        $invoice->refresh()->recalculateStatus();

        ActivityLogger::log($invoice, 'payment.recorded', 'Mencatat pembayaran Rp '.number_format((float) $payment->amount, 0, ',', '.').' untuk '.$invoice->number.'.');

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
