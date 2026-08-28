<?php

namespace App\Http\Controllers;

use App\Models\CaptureSession;
use App\Models\Client;
use App\Models\Deliverable;
use App\Models\Project;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $countsByStatus = Project::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('dashboard', [
            'statuses' => Project::STATUSES,
            'countsByStatus' => $countsByStatus,
            'clientCount' => Client::count(),
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
