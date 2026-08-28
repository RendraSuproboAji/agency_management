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

    public function destroy(Request $request, Project $project, Note $note): RedirectResponse
    {
        abort_unless($note->project_id === $project->id, 404);
        // Catatan hanya boleh dihapus penulisnya sendiri, atau admin.
        abort_unless($note->user_id === $request->user()->id || $request->user()->isAdmin(), 403);

        $note->delete();

        return back()->with('status', 'Catatan dihapus.');
    }
}
