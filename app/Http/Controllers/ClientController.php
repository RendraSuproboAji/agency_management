<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Support\Slug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(Request $request): View
    {
        $clients = Client::query()
            ->search($request->query('q'))
            ->status($request->query('status'))
            ->withCount('projects')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('clients.index', [
            'clients' => $clients,
            'q' => $request->query('q'),
            'status' => $request->query('status'),
        ]);
    }

    public function create(): View
    {
        return view('clients.create', ['client' => new Client(['status' => 'lead'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = Slug::uniqueFor(Client::class, $data['name']);

        $client = Client::create($data);

        return redirect()->route('clients.show', $client)->with('status', 'Klien berhasil ditambahkan.');
    }

    public function show(Client $client): View
    {
        $client->load(['projects' => fn ($query) => $query->latest()]);

        return view('clients.show', ['client' => $client]);
    }

    public function edit(Client $client): View
    {
        return view('clients.edit', ['client' => $client]);
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = Slug::uniqueFor(Client::class, $data['name'], $client->id);

        $client->update($data);

        return redirect()->route('clients.show', $client)->with('status', 'Data klien diperbarui.');
    }

    public function destroy(Request $request, Client $client): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $client->delete();

        return redirect()->route('clients.index')->with('status', 'Klien dihapus beserta project-nya.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'industry' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', 'in:'.implode(',', Client::STATUSES)],
        ]);
    }
}
