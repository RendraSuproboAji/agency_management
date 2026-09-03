<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Quotation;
use App\Notifications\QuotationAccepted;
use App\Support\ActivityLogger;
use App\Support\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class QuotationController extends Controller
{
    public function accept(Request $request, Project $project, Quotation $quotation): RedirectResponse
    {
        $client = $request->user('client');

        abort_unless($project->client_id === $client->id, 404);
        abort_unless($quotation->project_id === $project->id, 404);
        // Draft belum dikirim ke siapa pun; portal memang tidak menampilkannya,
        // dan rutenya tidak boleh jadi pintu belakang.
        abort_if($quotation->status === 'draft', 403);

        if ($quotation->status === 'accepted') {
            return back()->withErrors([
                'quotation' => 'Penawaran ini sudah disetujui pada '
                    .$quotation->accepted_at?->format('d M Y').'. Hubungi tim kami bila ada yang perlu diubah.',
            ]);
        }

        if ($quotation->isExpired()) {
            return back()->withErrors([
                'quotation' => 'Masa berlaku penawaran ini sudah habis pada '
                    .$quotation->valid_until->format('d M Y').'. Kami akan mengirimkan penawaran terbaru.',
            ]);
        }

        $quotation->update([
            'status' => 'accepted',
            'accepted_at' => now(),
            'accepted_by' => $client->name,
        ]);

        ActivityLogger::log(
            $quotation,
            'quotation.accepted',
            'Klien menyetujui penawaran '.$quotation->number.' lewat portal.',
            actor: 'Klien — '.$client->name,
        );

        Notifier::send($project->owner, new QuotationAccepted($quotation->load('project')));

        return back()->with('status', 'Terima kasih, penawaran disetujui. Tim kami akan menghubungi Anda.');
    }
}
