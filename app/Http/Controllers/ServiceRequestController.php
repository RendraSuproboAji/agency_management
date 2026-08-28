<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Project;
use App\Models\ServiceRequest;
use App\Support\Slug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ServiceRequestController extends Controller
{
    public function index(Request $request): View
    {
        $requests = ServiceRequest::query()
            ->with('convertedProject')
            ->search($request->query('q'))
            ->status($request->query('status'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('requests.index', [
            'requests' => $requests,
            'filters' => $request->only(['q', 'status']),
        ]);
    }

    public function show(ServiceRequest $serviceRequest): View
    {
        return view('requests.show', [
            'serviceRequest' => $serviceRequest->load('convertedProject'),
            'clients' => Client::orderBy('name')->get(),
        ]);
    }

    public function updateStatus(Request $request, ServiceRequest $serviceRequest): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', ServiceRequest::STATUSES)],
        ]);

        // Status "converted" hanya boleh lahir dari aksi konversi.
        abort_if($data['status'] === 'converted' && ! $serviceRequest->converted_project_id, 422);

        $serviceRequest->update($data);

        return back()->with('status', 'Status request diperbarui.');
    }

    /** Ubah request jadi Klien (baru atau yang sudah ada) + Project berstatus lead. */
    public function convert(Request $request, ServiceRequest $serviceRequest): RedirectResponse
    {
        if ($serviceRequest->converted_project_id) {
            return back()->withErrors(['request' => 'Request ini sudah pernah dikonversi.']);
        }

        $data = $request->validate([
            'client_id' => ['nullable', 'exists:clients,id'],
            'title' => ['required', 'string', 'max:150'],
        ]);

        $project = DB::transaction(function () use ($data, $serviceRequest, $request) {
            $client = ($data['client_id'] ?? null)
                ? Client::findOrFail($data['client_id'])
                : Client::create([
                    'name' => $serviceRequest->company ?: $serviceRequest->name,
                    'slug' => Slug::uniqueFor(Client::class, $serviceRequest->company ?: $serviceRequest->name),
                    'contact_name' => $serviceRequest->name,
                    'email' => $serviceRequest->email,
                    'phone' => $serviceRequest->phone,
                    'address' => $serviceRequest->site_location,
                    'status' => 'lead',
                ]);

            $project = Project::create([
                'client_id' => $client->id,
                'owner_id' => $request->user()->id,
                'title' => $data['title'],
                'slug' => Slug::uniqueFor(Project::class, $data['title']),
                'brief' => $serviceRequest->message,
                'service_type' => $serviceRequest->service_type,
                'status' => 'lead',
                'site_location' => $serviceRequest->site_location,
                'area_sqm' => $serviceRequest->area_sqm,
            ]);

            $serviceRequest->update([
                'status' => 'converted',
                'converted_project_id' => $project->id,
            ]);

            return $project;
        });

        return redirect()->route('projects.show', $project)
            ->with('status', 'Request dikonversi menjadi project.');
    }

    public function destroy(ServiceRequest $serviceRequest): RedirectResponse
    {
        $serviceRequest->delete();

        return redirect()->route('requests.index')->with('status', 'Request dihapus.');
    }
}
