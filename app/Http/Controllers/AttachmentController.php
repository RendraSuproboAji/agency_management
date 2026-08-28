<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Project;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        abort_unless($project->isManageableBy($request->user()), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'category' => ['required', 'in:'.implode(',', Attachment::CATEGORIES)],
            'file' => ['required', 'file', 'max:262144'],
        ]);

        $file = $request->file('file');

        $attachment = $project->attachments()->create([
            'uploaded_by' => $request->user()->id,
            'title' => $data['title'],
            'category' => $data['category'],
            'file_path' => $file->store('attachments/'.$project->slug, 'public'),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        ActivityLogger::log($attachment, 'attachment.uploaded', 'Mengunggah lampiran "'.$attachment->title.'".');

        return back()->with('status', 'Lampiran diunggah.');
    }

    public function download(Request $request, Project $project, Attachment $attachment): StreamedResponse
    {
        abort_unless($attachment->project_id === $project->id, 404);
        abort_unless(Storage::disk('public')->exists($attachment->file_path), 404);

        return Storage::disk('public')->download($attachment->file_path, basename($attachment->file_path));
    }

    public function destroy(Request $request, Project $project, Attachment $attachment): RedirectResponse
    {
        abort_unless($attachment->project_id === $project->id, 404);
        abort_unless($project->isManageableBy($request->user()), 403);

        Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();

        ActivityLogger::log($project, 'attachment.deleted', 'Menghapus lampiran "'.$attachment->title.'".');

        return back()->with('status', 'Lampiran dihapus.');
    }
}
