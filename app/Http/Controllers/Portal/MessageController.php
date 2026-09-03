<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Project;
use App\Notifications\ClientMessagePosted;
use App\Support\ActivityLogger;
use App\Support\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $client = $this->authorizeProject($request, $project);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $note = $project->notes()->create([
            'client_id' => $client->id,
            'body' => $data['body'],
            // Pesan dari klien memang untuk dibaca bersama; yang perlu dijaga
            // adalah arah sebaliknya, catatan staf yang belum ditandai.
            'shared_with_client' => true,
        ]);

        ActivityLogger::log(
            $note,
            'message.posted',
            'Klien menulis pesan di project "'.$project->title.'".',
            actor: 'Klien — '.$client->name,
        );

        Notifier::send($project->owner, new ClientMessagePosted($note->load('project')));

        return back()->with('status', 'Pesan terkirim. Tim kami akan membalas di sini.');
    }

    private function authorizeProject(Request $request, Project $project): Client
    {
        $client = $request->user('client');

        abort_unless($project->client_id === $client->id, 404);

        return $client;
    }
}
