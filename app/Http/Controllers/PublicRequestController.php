<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ServiceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicRequestController extends Controller
{
    public function create(): View
    {
        return view('public.request');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'company' => ['nullable', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'service_type' => ['required', 'in:'.implode(',', Project::SERVICE_TYPES)],
            'site_location' => ['nullable', 'string', 'max:255'],
            'area_sqm' => ['nullable', 'integer', 'min:0'],
            'message' => ['nullable', 'string', 'max:5000'],
            // Honeypot: bot mengisi kolom tersembunyi ini, manusia tidak.
            'website' => ['prohibited'],
        ]);

        unset($data['website']);

        ServiceRequest::create($data);

        return redirect()->route('public.request.create')
            ->with('status', 'Terima kasih. Request Anda sudah kami terima dan akan segera dihubungi.');
    }
}
