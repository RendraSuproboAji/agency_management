<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
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
        abort_unless($project->isManageableBy($request->user()), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'category' => ['required', 'in:'.implode(',', Attachment::CATEGORIES)],
            'file' => UploadRules::file(true),
        ]);

        $file = $request->file('file');

        $attachment = $project->attachments()->create([
            'uploaded_by' => $request->user()->id,
            'title' => $data['title'],
            'category' => $data['category'],
            // Disk privat: kontrak, denah, dan foto survei tidak boleh bisa
            // diambil lewat URL langsung. Route download di bawah yang menegakkan
            // otorisasinya.
            'file_path' => $file->store('attachments/'.$project->slug, 'local'),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        ActivityLogger::log($attachment, 'attachment.uploaded', 'Mengunggah lampiran "'.$attachment->title.'".');

        return back()->with('status', 'Lampiran diunggah.');
    }

    public function download(Request $request, Project $project, Attachment $attachment): StreamedResponse
    {
        abort_unless($attachment->project_id === $project->id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404);

        // Nama tersimpan berupa hash acak; yang diunduh pengguna harus memakai
        // judul lampirannya supaya berkasnya bisa dikenali di folder unduhan.
        $extension = pathinfo($attachment->file_path, PATHINFO_EXTENSION);
        $name = Str::slug($attachment->title).($extension ? '.'.$extension : '');

        return Storage::disk('local')->download($attachment->file_path, $name);
    }

    public function destroy(Request $request, Project $project, Attachment $attachment): RedirectResponse
    {
        abort_unless($attachment->project_id === $project->id, 404);
        abort_unless($project->isManageableBy($request->user()), 403);

        Storage::disk('local')->delete($attachment->file_path);
        $attachment->delete();

        ActivityLogger::log($project, 'attachment.deleted', 'Menghapus lampiran "'.$attachment->title.'".');

        return back()->with('status', 'Lampiran dihapus.');
    }
}
