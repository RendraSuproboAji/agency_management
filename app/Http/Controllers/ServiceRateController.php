<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ServiceRate;
use App\Models\ServiceRequest;
use App\Support\QuotationEstimator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ServiceRateController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Rates/Index', [
            'rates' => ServiceRate::orderBy('service_type')->orderBy('unit')->get()
                ->map(fn (ServiceRate $rate) => [
                    ...$rate->only(['id', 'service_type', 'unit', 'label', 'unit_price', 'min_charge', 'active']),
                    'unit_label' => ServiceRate::UNIT_LABELS[$rate->unit] ?? $rate->unit,
                ]),
        ] + $this->formOptions());
    }

    public function create(): Response
    {
        return Inertia::render('Rates/Form', [
            'rate' => new ServiceRate(['service_type' => 'gaussian_splatting', 'unit' => 'sqm', 'active' => true]),
        ] + $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        ServiceRate::create($this->validated($request));

        return redirect()->route('rates.index')->with('status', 'Tarif ditambahkan.');
    }

    public function edit(ServiceRate $rate): Response
    {
        return Inertia::render('Rates/Form', [
            'rate' => $rate->only(['id', 'service_type', 'unit', 'label', 'unit_price', 'min_charge', 'active']),
        ] + $this->formOptions());
    }

    public function update(Request $request, ServiceRate $rate): RedirectResponse
    {
        $rate->update($this->validated($request, $rate));

        return redirect()->route('rates.index')->with('status', 'Tarif diperbarui.');
    }

    public function destroy(ServiceRate $rate): RedirectResponse
    {
        $rate->delete();

        return back()->with('status', 'Tarif dihapus.');
    }

    /**
     * Usulan baris penawaran untuk satu project.
     *
     * Dipanggil dari form penawaran lewat fetch, bukan lewat kunjungan Inertia:
     * form itu menyimpan baris yang sedang diketik di state-nya sendiri, dan
     * pemuatan ulang halaman akan menghapusnya.
     */
    public function estimateForProject(Request $request, Project $project): JsonResponse
    {
        abort_unless($project->isManageableBy($request->user()), 403);

        return response()->json([
            'items' => QuotationEstimator::suggest(
                $project->service_type,
                $project->area_sqm,
                $project->scenes()->count(),
                $this->multiplier($request),
            ),
        ]);
    }

    /**
     * Usulan untuk calon klien: permintaan yang masuk juga membawa
     * service_type dan area_sqm, jadi menawar ke mereka tidak perlu menunggu
     * project dibuat lebih dulu. Belum ada scene, jadi barisnya hanya luas.
     */
    public function estimateForRequest(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        return response()->json([
            'items' => QuotationEstimator::suggest(
                $serviceRequest->service_type,
                $serviceRequest->area_sqm,
                0,
                $this->multiplier($request),
            ),
        ]);
    }

    private function multiplier(Request $request): float
    {
        $value = (string) $request->query('multiplier', '1');

        // Hanya pengali yang dikenal; angka bebas dari URL tidak boleh menjadi
        // dasar harga yang dikirim ke klien.
        return array_key_exists($value, QuotationEstimator::MULTIPLIERS) ? (float) $value : 1.0;
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?ServiceRate $rate = null): array
    {
        $unique = Rule::unique('service_rates')->where(
            fn ($query) => $query->where('service_type', $request->input('service_type')),
        );

        return $request->validate([
            'service_type' => ['required', 'in:'.implode(',', Project::SERVICE_TYPES)],
            'unit' => ['required', 'in:'.implode(',', ServiceRate::UNITS), $rate ? $unique->ignore($rate) : $unique],
            'label' => ['required', 'string', 'max:150'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'min_charge' => ['nullable', 'numeric', 'min:0'],
            'active' => ['required', 'boolean'],
        ]);
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'serviceTypes' => Project::SERVICE_TYPES,
            'units' => ServiceRate::UNITS,
            'unitLabels' => ServiceRate::UNIT_LABELS,
        ];
    }
}
