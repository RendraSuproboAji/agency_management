<?php

namespace App\Http\Controllers;

use App\Models\CaptureSession;
use App\Models\Client;
use App\Models\Deliverable;
use App\Models\Invoice;
use App\Models\ProcessingJob;
use App\Models\Project;
use App\Models\ServiceRequest;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $countsByStatus = Project::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $unsettled = Invoice::unsettled()->with('payments')->get();

        return view('dashboard', [
            'runningJobs' => ProcessingJob::running()->with('project.client')->orderBy('started_at')->get(),
            'receivable' => $unsettled->sum(fn (Invoice $invoice) => $invoice->outstanding()),
            'dueInvoices' => Invoice::unsettled()
                ->with('project.client')
                ->whereNotNull('due_at')
                ->orderBy('due_at')
                ->limit(5)
                ->get(),
            'statuses' => Project::STATUSES,
            'countsByStatus' => $countsByStatus,
            'clientCount' => Client::count(),
            'newRequestCount' => ServiceRequest::where('status', 'new')->count(),
            'latestRequests' => ServiceRequest::where('status', 'new')->latest()->limit(5)->get(),
            'activeProjectCount' => Project::whereNotIn('status', ['delivered', 'archived'])->count(),
            'upcomingDeadlines' => Project::with('client')
                ->whereNotNull('deadline')
                ->whereNotIn('status', ['delivered', 'archived'])
                ->orderBy('deadline')
                ->limit(5)
                ->get(),
            'upcomingSessions' => CaptureSession::with('project.client', 'crew')
                ->where('status', 'scheduled')
                ->where('scheduled_at', '>=', now()->startOfDay())
                ->orderBy('scheduled_at')
                ->limit(5)
                ->get(),
            'pendingDeliverables' => Deliverable::with('project.client')
                ->where('status', 'submitted')
                ->orderBy('submitted_at')
                ->limit(5)
                ->get(),
        ]);
    }
}
