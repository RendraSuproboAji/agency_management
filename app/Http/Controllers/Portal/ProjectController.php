<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $client = $this->client($request);

        return view('portal.dashboard', [
            'client' => $client,
            'projects' => $client->projects()->with('invoices.payments')->latest()->get(),
        ]);
    }

    /**
     * Halaman project untuk klien. Sengaja tidak memuat catatan internal,
     * log aktivitas, maupun lampiran internal.
     */
    public function show(Request $request, Project $project): View
    {
        abort_unless($project->client_id === $this->client($request)->id, 404);

        $project->load([
            'captureSessions' => fn ($query) => $query->orderBy('scheduled_at'),
            'deliverables' => fn ($query) => $query->orderByDesc('created_at'),
            'invoices' => fn ($query) => $query->whereNot('status', 'draft')->with('payments')->orderByDesc('issued_at'),
            'quotations' => fn ($query) => $query->whereNot('status', 'draft')->with('items')->orderByDesc('issued_at'),
        ]);

        return view('portal.project', [
            'project' => $project,
            'statuses' => Project::STATUSES,
        ]);
    }

    private function client(Request $request): Client
    {
        return $request->user('client');
    }
}
