<?php

namespace App\Http\Controllers;

use App\Models\CaptureSession;
use App\Models\Client;
use App\Models\Deliverable;
use App\Models\Invoice;
use App\Models\ProcessingJob;
use App\Models\Project;
use App\Models\ServiceRequest;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $countsByStatus = Project::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $unsettled = Invoice::unsettled()->with('payments')->get();

        return Inertia::render('Dashboard', [
            'runningJobs' => ProcessingJob::running()->with('project.client')->orderBy('started_at')->get()
                ->map(fn (ProcessingJob $job) => [
                    'id' => $job->id,
                    'kind' => str_replace('_', ' ', $job->kind),
                    'machine' => $job->machine ?: 'mesin tidak dicatat',
                    'project_slug' => $job->project->slug,
                    'project_title' => $job->project->title,
                ]),
            'receivable' => $unsettled->sum(fn (Invoice $invoice) => $invoice->outstanding()),
            'dueInvoices' => Invoice::unsettled()
                ->with('project.client', 'payments')
                ->whereNotNull('due_at')
                ->orderBy('due_at')
                ->limit(5)
                ->get()
                ->map(fn (Invoice $invoice) => [
                    'id' => $invoice->id,
                    'number' => $invoice->number,
                    'due_at' => $invoice->due_at->format('d M Y'),
                    'outstanding' => $invoice->outstanding(),
                    'project_slug' => $invoice->project->slug,
                    'client_name' => $invoice->project->client->name,
                ]),
            'statuses' => Project::STATUSES,
            'countsByStatus' => $countsByStatus,
            'clientCount' => Client::count(),
            'newRequestCount' => ServiceRequest::where('status', 'new')->count(),
            'latestRequests' => ServiceRequest::where('status', 'new')->latest()->limit(5)->get()
                ->map(fn (ServiceRequest $item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'company' => $item->company,
                    'service_type' => str_replace('_', ' ', $item->service_type),
                ]),
            'activeProjectCount' => Project::whereNotIn('status', ['delivered', 'archived'])->count(),
            'upcomingDeadlines' => Project::with('client')
                ->whereNotNull('deadline')
                ->whereNotIn('status', ['delivered', 'archived'])
                ->orderBy('deadline')
                ->limit(5)
                ->get()
                ->map(fn (Project $project) => [
                    'id' => $project->id,
                    'slug' => $project->slug,
                    'title' => $project->title,
                    'client_name' => $project->client->name,
                    'deadline' => $project->deadline->format('d M Y'),
                ]),
            'upcomingSessions' => CaptureSession::with('project.client', 'crew')
                ->where('status', 'scheduled')
                ->where('scheduled_at', '>=', now()->startOfDay())
                ->orderBy('scheduled_at')
                ->limit(5)
                ->get()
                ->map(fn (CaptureSession $session) => [
                    'id' => $session->id,
                    'project_slug' => $session->project->slug,
                    'project_title' => $session->project->title,
                    'scheduled_at' => $session->scheduled_at->format('d M Y H:i'),
                    'crew_name' => $session->crew?->name,
                ]),
            'pendingDeliverables' => Deliverable::with('project.client')
                ->where('status', 'submitted')
                ->orderBy('submitted_at')
                ->limit(5)
                ->get()
                ->map(fn (Deliverable $deliverable) => [
                    'id' => $deliverable->id,
                    'title' => $deliverable->title,
                    'version' => $deliverable->version,
                    'project_slug' => $deliverable->project->slug,
                    'project_title' => $deliverable->project->title,
                    'client_name' => $deliverable->project->client->name,
                ]),
        ]);
    }
}
