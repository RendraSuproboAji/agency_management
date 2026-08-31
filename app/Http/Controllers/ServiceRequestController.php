<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Project;
use App\Models\ServiceRequest;
use App\Support\ActivityLogger;
use App\Support\Slug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ServiceRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $requests = ServiceRequest::query()
            ->with('convertedProject')
            ->search($request->query('q'))
            ->status($request->query('status'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $requests->through(fn (ServiceRequest $item) => [
            ...$item->only(['id', 'name', 'company', 'email', 'status', 'site_location']),
            'service_type' => str_replace('_', ' ', $item->service_type),
            'created_at' => $item->created_at->format('d M Y'),
        ]);

        return Inertia::render('Requests/Index', [
            'requests' => $requests,
            'filters' => $request->only(['q', 'status']),
            'statuses' => ServiceRequest::STATUSES,
        ]);
    }

    public function show(ServiceRequest $serviceRequest): Response
    {
        $serviceRequest->load('convertedProject', 'quotations.items');

        return Inertia::render('Requests/Show', [
            'serviceRequest' => [
                ...$serviceRequest->only(['id', 'name', 'company', 'email', 'phone', 'status', 'site_location', 'area_sqm', 'message']),
                'service_type' => str_replace('_', ' ', $serviceRequest->service_type),
                'created_at' => $serviceRequest->created_at->format('d M Y H:i'),
                'converted_project' => $serviceRequest->convertedProject?->only(['slug', 'title']),
                'quotations' => $serviceRequest->quotations->map(fn ($quotation) => [
                    ...$quotation->only(['id', 'number', 'status']),
                    'issued_at' => $quotation->issued_at->format('d M Y'),
                    'total' => $quotation->total(),
                    'print_url' => route('requests.quotations.print', [$serviceRequest, $quotation]),
                ]),
            ],
            'clients' => Client::orderBy('name')->get(['id', 'name']),
            'statuses' => ServiceRequest::STATUSES,
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
            // convert() memakai Client::findOrFail yang menyaring arsip; tanpa
            // whereNull di sini validasi lolos lalu konversinya 404.
            'client_id' => ['nullable', Rule::exists('clients', 'id')->whereNull('deleted_at')],
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

            // Penawaran yang sudah dikirim ke calon klien ikut pindah ke
            // project baru, supaya riwayatnya utuh dan tidak ada penawaran
            // yang menggantung tanpa induk.
            $serviceRequest->quotations()->update([
                'project_id' => $project->id,
                'service_request_id' => null,
            ]);

            $serviceRequest->update([
                'status' => 'converted',
                'converted_project_id' => $project->id,
            ]);

            return $project;
        });

        ActivityLogger::log($project, 'request.converted', 'Mengonversi request dari '.$serviceRequest->name.' menjadi project.');

        return redirect()->route('projects.show', $project)
            ->with('status', 'Request dikonversi menjadi project.');
    }

    public function destroy(ServiceRequest $serviceRequest): RedirectResponse
    {
        $serviceRequest->delete();

        return redirect()->route('requests.index')->with('status', 'Request dihapus.');
    }
}
