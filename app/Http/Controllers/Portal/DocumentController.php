<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quotation;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Klien mencetak penawaran dan invoice-nya sendiri.
 *
 * View cetaknya sama persis dengan yang dipakai staf — keduanya menerima
 * $backUrl, jadi cukup mengarahkannya kembali ke portal. Dokumen berstatus
 * draft disembunyikan, sama seperti daftar dokumen di halaman project portal.
 */
class DocumentController extends Controller
{
    public function quotation(Request $request, Project $project, Quotation $quotation): View
    {
        $this->authorizeDocument($request, $project, $quotation->project_id, $quotation->status);

        return view('quotations.print', [
            'project' => $project->load('client'),
            'quotation' => $quotation->load('items'),
            'backUrl' => route('portal.projects.show', $project),
        ]);
    }

    public function invoice(Request $request, Project $project, Invoice $invoice): View
    {
        $this->authorizeDocument($request, $project, $invoice->project_id, $invoice->status);

        return view('invoices.print', [
            'project' => $project->load('client'),
            'invoice' => $invoice->load(['payments' => fn ($query) => $query->orderBy('paid_at'), 'quotation']),
            'backUrl' => route('portal.projects.show', $project),
        ]);
    }

    private function authorizeDocument(Request $request, Project $project, int $ownerId, string $status): void
    {
        abort_unless($project->client_id === $request->user('client')->id, 404);
        abort_unless($ownerId === $project->id, 404);
        abort_if($status === 'draft', 404);
    }
}
