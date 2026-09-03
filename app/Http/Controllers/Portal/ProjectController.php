<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $client = $this->client($request);

        return Inertia::render('Portal/Dashboard', [
            'projects' => $client->projects()->with('invoices.payments')->latest()->get()
                ->map(fn (Project $project) => [
                    ...$project->only(['id', 'slug', 'title', 'status']),
                    'service_type' => str_replace('_', ' ', $project->service_type),
                    'deadline' => $project->deadline?->format('d M Y'),
                    'outstanding' => $project->invoices->sum(fn ($invoice) => $invoice->outstanding()),
                ]),
        ]);
    }

    /**
     * Halaman project untuk klien. Sengaja tidak memuat catatan internal
     * maupun log aktivitas. Catatan hanya ikut bila ditandai dibagikan, dan
     * lampiran hanya yang klien itu sendiri kirimkan — kontrak dan foto survei
     * internal tetap tidak terlihat.
     */
    public function show(Request $request, Project $project): Response
    {
        abort_unless($project->client_id === $this->client($request)->id, 404);

        $project->load([
            'captureSessions' => fn ($query) => $query->orderBy('scheduled_at'),
            'scenes',
            'deliverables' => fn ($query) => $query->orderByDesc('created_at'),
            'invoices' => fn ($query) => $query->whereNot('status', 'draft')->with('payments')->orderByDesc('issued_at'),
            'quotations' => fn ($query) => $query->whereNot('status', 'draft')->with('items')->orderByDesc('issued_at'),
            'notes' => fn ($query) => $query->where('shared_with_client', true)
                ->with('author', 'client')->oldest(),
            'attachments' => fn ($query) => $query->whereNotNull('uploaded_by_client_id')
                ->with('uploaderClient')->latest(),
        ]);

        return Inertia::render('Portal/Project', [
            'project' => [
                ...$project->only(['id', 'slug', 'title', 'status', 'site_location', 'gallery_url']),
                'service_type' => str_replace('_', ' ', $project->service_type),
                'deadline' => $project->deadline?->format('d M Y'),
                'capture_sessions' => $project->captureSessions->map(fn ($session) => [
                    ...$session->only(['id', 'status', 'location']),
                    'scheduled_at' => $session->scheduled_at->format('d M Y H:i'),
                ]),
                'deliverables' => $project->deliverables->map(fn ($deliverable) => [
                    ...$deliverable->only(['id', 'title', 'type', 'version', 'status', 'review_note']),
                    'scene' => $project->scenes->firstWhere('id', $deliverable->scene_id)?->name,
                    'url' => $deliverable->url(),
                    'download_url' => $deliverable->hasFile()
                        ? route('portal.deliverables.download', [$project, $deliverable])
                        : null,
                    'can_review' => in_array($deliverable->status, ['submitted', 'revision'], true),
                ]),
                'messages' => $project->notes->map(fn ($note) => [
                    ...$note->only(['id', 'body']),
                    'author' => $note->authorName(),
                    'from_client' => $note->client_id !== null,
                    'created_at' => $note->created_at->format('d M Y H:i'),
                ]),
                'files' => $project->attachments->map(fn ($attachment) => [
                    ...$attachment->only(['id', 'title']),
                    'size' => $attachment->humanSize(),
                    'created_at' => $attachment->created_at->format('d M Y'),
                    'download_url' => route('portal.attachments.download', [$project, $attachment]),
                ]),
                'documents' => $project->quotations
                    ->map(fn ($quotation) => [
                        'kind' => 'quotation',
                        'id' => $quotation->id,
                        'number' => $quotation->number,
                        'status' => $quotation->status,
                        'issued_at' => $quotation->issued_at->format('d M Y'),
                        'amount' => $quotation->total(),
                        'print_url' => route('portal.quotations.print', [$project, $quotation]),
                        'accept_url' => route('portal.quotations.accept', [$project, $quotation]),
                        'can_accept' => $quotation->status !== 'accepted' && ! $quotation->isExpired(),
                        'is_expired' => $quotation->isExpired(),
                        'accepted_by' => $quotation->accepted_by,
                        'accepted_at' => $quotation->accepted_at?->format('d M Y'),
                        'payments' => [],
                    ])
                    ->concat($project->invoices->map(fn ($invoice) => [
                        'kind' => 'invoice',
                        'id' => $invoice->id,
                        'number' => $invoice->number,
                        'status' => $invoice->status,
                        'issued_at' => $invoice->issued_at->format('d M Y'),
                        'amount' => (float) $invoice->amount,
                        'outstanding' => $invoice->outstanding(),
                        'days_overdue' => $invoice->daysOverdue(),
                        'print_url' => route('portal.invoices.print', [$project, $invoice]),
                        // Klien berhak melihat pembayarannya sendiri sudah
                        // tercatat; datanya memang sudah dimuat sejak awal.
                        'payments' => $invoice->payments
                            ->sortBy('paid_at')
                            ->map(fn ($payment) => [
                                'id' => $payment->id,
                                'paid_at' => $payment->paid_at->format('d M Y'),
                                'amount' => (float) $payment->amount,
                                'method' => $payment->method,
                            ])
                            ->values(),
                    ]))
                    ->values(),
            ],
            'statuses' => Project::STATUSES,
        ]);
    }

    private function client(Request $request): Client
    {
        return $request->user('client');
    }
}
