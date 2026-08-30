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
     * Halaman project untuk klien. Sengaja tidak memuat catatan internal,
     * log aktivitas, maupun lampiran internal.
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
                    'can_review' => in_array($deliverable->status, ['submitted', 'revision'], true),
                ]),
                'documents' => $project->quotations
                    ->map(fn ($quotation) => [
                        'kind' => 'quotation',
                        'id' => $quotation->id,
                        'number' => $quotation->number,
                        'status' => $quotation->status,
                        'issued_at' => $quotation->issued_at->format('d M Y'),
                        'amount' => $quotation->total(),
                    ])
                    ->concat($project->invoices->map(fn ($invoice) => [
                        'kind' => 'invoice',
                        'id' => $invoice->id,
                        'number' => $invoice->number,
                        'status' => $invoice->status,
                        'issued_at' => $invoice->issued_at->format('d M Y'),
                        'amount' => (float) $invoice->amount,
                        'outstanding' => $invoice->outstanding(),
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
