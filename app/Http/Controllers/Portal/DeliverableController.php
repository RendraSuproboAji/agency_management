<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DeliverableController as StaffDeliverableController;
use App\Models\Client;
use App\Models\Deliverable;
use App\Models\Project;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeliverableController extends Controller
{
    public function approve(Request $request, Project $project, Deliverable $deliverable): RedirectResponse
    {
        $client = $this->authorizeDeliverable($request, $project, $deliverable);

        $deliverable->update([
            'status' => 'approved',
            'approved_at' => now(),
            'review_note' => $request->input('review_note'),
        ]);

        ActivityLogger::log(
            $deliverable,
            'deliverable.approved',
            'Klien menyetujui deliverable "'.$deliverable->title.'" v'.$deliverable->version.'.',
            actor: 'Klien — '.$client->name,
        );

        return back()->with('status', 'Terima kasih, deliverable disetujui.');
    }

    public function requestRevision(Request $request, Project $project, Deliverable $deliverable): RedirectResponse
    {
        $client = $this->authorizeDeliverable($request, $project, $deliverable);

        $data = $request->validate([
            'review_note' => ['required', 'string', 'max:2000'],
        ]);

        $deliverable->update($data + [
            'status' => 'revision',
            'approved_at' => null,
        ]);

        ActivityLogger::log(
            $deliverable,
            'deliverable.revision',
            'Klien meminta revisi deliverable "'.$deliverable->title.'" v'.$deliverable->version.'.',
            actor: 'Klien — '.$client->name,
        );

        return back()->with('status', 'Permintaan revisi terkirim.');
    }

    public function download(Request $request, Project $project, Deliverable $deliverable): StreamedResponse
    {
        $this->authorizeDeliverable($request, $project, $deliverable);

        return StaffDeliverableController::stream($deliverable);
    }

    private function authorizeDeliverable(Request $request, Project $project, Deliverable $deliverable): Client
    {
        $client = $request->user('client');

        abort_unless($project->client_id === $client->id, 404);
        abort_unless($deliverable->project_id === $project->id, 404);
        // Klien hanya menilai yang sudah diserahkan ke mereka.
        abort_unless(in_array($deliverable->status, ['submitted', 'revision', 'approved'], true), 403);

        return $client;
    }
}
