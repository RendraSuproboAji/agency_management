<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Support\ActivityLogger;
use App\Support\Archive;
use App\Support\Slug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(Request $request): Response
    {
        $clients = Client::query()
            ->search($request->query('q'))
            ->status($request->query('status'))
            ->withCount('projects')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Clients/Index', [
            'clients' => $clients,
            'filters' => $request->only(['q', 'status']),
            'statuses' => Client::STATUSES,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Clients/Form', [
            'client' => new Client(['status' => 'lead']),
            'statuses' => Client::STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = Slug::uniqueFor(Client::class, $data['name']);
        $data = $this->withPortalCredentials($request, $data);

        $client = Client::create($data);

        return redirect()->route('clients.show', $client)->with('status', 'Klien berhasil ditambahkan.');
    }

    public function show(Client $client): Response
    {
        $client->load(['projects' => fn ($query) => $query->latest()]);

        return Inertia::render('Clients/Show', [
            'client' => [
                ...$client->only(['id', 'slug', 'name', 'status', 'contact_name', 'email', 'phone', 'industry', 'address', 'notes', 'portal_enabled']),
                'projects' => $client->projects->map(fn ($project) => [
                    ...$project->only(['id', 'slug', 'title', 'status']),
                    'service_type' => str_replace('_', ' ', $project->service_type),
                    'deadline' => $project->deadline?->format('d M Y'),
                ]),
            ],
        ]);
    }

    public function edit(Client $client): Response
    {
        return Inertia::render('Clients/Form', [
            'client' => $client,
            'statuses' => Client::STATUSES,
        ]);
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $data = $this->validated($request, $client);
        $data['slug'] = Slug::uniqueFor(Client::class, $data['name'], $client->id);
        $data = $this->withPortalCredentials($request, $data, $client);

        $client->update($data);

        return redirect()->route('clients.show', $client)->with('status', 'Data klien diperbarui.');
    }

    public function destroy(Request $request, Client $client): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        Archive::archiveClient($client);

        ActivityLogger::log($client, 'client.archived', 'Mengarsipkan klien "'.$client->name.'" beserta project-nya.');

        return redirect()->route('clients.index')
            ->with('status', 'Klien diarsipkan beserta project-nya. Bisa dipulihkan dari halaman Arsip.');
    }

    /**
     * Kredensial portal hanya boleh disentuh admin.
     *
     * Menyalakan portal dan menyetel kata sandinya sama dengan mencetak kunci
     * ke seluruh data satu klien; tanpa gerbang ini staf mana pun bisa
     * membuatkan dirinya akun portal milik klien lain, tanpa jejak yang jelas.
     * Kolomnya sengaja tidak ada di form staf, jadi permintaan yang membawanya
     * ditolak alih-alih diabaikan diam-diam.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withPortalCredentials(Request $request, array $data, ?Client $client = null): array
    {
        $wantsPortalChange = filled($data['password'] ?? null)
            || $request->boolean('portal_enabled') !== (bool) ($client?->portal_enabled ?? false);

        if ($wantsPortalChange) {
            abort_unless($request->user()->isAdmin(), 403);
        }

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        if ($request->user()->isAdmin()) {
            $data['portal_enabled'] = $request->boolean('portal_enabled');
        } else {
            unset($data['portal_enabled']);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Client $client = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            // Email adalah identitas masuk portal, jadi harus unik — dua
            // klien beremail sama membuat yang kedua tidak pernah bisa masuk.
            'email' => [
                'nullable', 'email', 'max:150',
                Rule::unique('clients', 'email')->ignore($client?->id)->whereNull('deleted_at'),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'industry' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', 'in:'.implode(',', Client::STATUSES)],
            'password' => ['nullable', 'string', Password::defaults()],
        ]);
    }
}
