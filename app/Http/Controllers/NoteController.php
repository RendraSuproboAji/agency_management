<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        abort_unless($project->isManageableBy($request->user()), 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $project->notes()->create($data + ['user_id' => $request->user()->id]);

        return back()->with('status', 'Catatan ditambahkan.');
    }

    /**
     * Buka atau tutup satu catatan untuk klien.
     *
     * Penandanya eksplisit dan bisa dicabut: portal hanya menampilkan catatan
     * yang ditandai, jadi tidak ada catatan internal yang bocor karena
     * kelalaian menebak dari siapa penulisnya.
     */
    public function share(Request $request, Project $project, Note $note): RedirectResponse
    {
        abort_unless($note->project_id === $project->id, 404);
        abort_unless($project->isManageableBy($request->user()), 403);
        // Pesan klien memang selalu terbagi; tidak ada yang bisa ditutup.
        abort_if($note->client_id !== null, 403);

        $note->update(['shared_with_client' => ! $note->shared_with_client]);

        return back()->with('status', $note->shared_with_client
            ? 'Catatan dibagikan ke klien.'
            : 'Catatan tidak lagi terlihat klien.');
    }

    public function destroy(Request $request, Project $project, Note $note): RedirectResponse
    {
        abort_unless($note->project_id === $project->id, 404);
        // Catatan hanya boleh dihapus penulisnya sendiri, atau admin.
        // Pesan klien tidak punya penulis staf, jadi hanya admin yang bisa
        // menghapusnya — bukan siapa pun yang kebetulan membuka halamannya.
        abort_unless($note->user_id === $request->user()->id || $request->user()->isAdmin(), 403);

        $note->delete();

        return back()->with('status', 'Catatan dihapus.');
    }
}
