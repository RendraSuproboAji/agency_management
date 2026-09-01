<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Client;
use App\Models\Project;
use App\Support\ActivityLogger;
use App\Support\UploadRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $client = $this->authorizeProject($request, $project);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'file' => UploadRules::file(true),
        ]);

        $file = $request->file('file');

        $attachment = $project->attachments()->create([
            'uploaded_by_client_id' => $client->id,
            'title' => $data['title'],
            // Kategorinya dipaksa, tidak diambil dari permintaan: berkas dari
            // klien tidak boleh mendarat berlabel "contract" dan menyamar
            // sebagai dokumen yang agensi sendiri terbitkan.
            'category' => 'reference',
            'file_path' => $file->store('attachments/'.$project->slug, 'local'),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        ActivityLogger::log(
            $attachment,
            'attachment.uploaded',
            'Klien mengunggah berkas "'.$attachment->title.'".',
            actor: 'Klien — '.$client->name,
        );

        return back()->with('status', 'Berkas terkirim.');
    }

    public function download(Request $request, Project $project, Attachment $attachment): StreamedResponse
    {
        $this->authorizeProject($request, $project);

        abort_unless($attachment->project_id === $project->id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404);

        $extension = pathinfo($attachment->file_path, PATHINFO_EXTENSION);
        $name = Str::slug($attachment->title).($extension ? '.'.$extension : '');

        return Storage::disk('local')->download($attachment->file_path, $name);
    }

    private function authorizeProject(Request $request, Project $project): Client
    {
        $client = $request->user('client');

        abort_unless($project->client_id === $client->id, 404);

        return $client;
    }
}
