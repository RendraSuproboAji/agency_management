<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EquipmentController extends Controller
{
    public function index(Request $request): View
    {
        $equipment = Equipment::query()
            ->search($request->query('q'))
            ->when($request->query('category'), fn ($query, $category) => $query->where('category', $category))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderBy('category')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('equipment.index', [
            'equipment' => $equipment,
            'filters' => $request->only(['q', 'category', 'status']),
        ]);
    }

    public function create(): View
    {
        return view('equipment.create', [
            'item' => new Equipment(['category' => 'camera', 'status' => 'available']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Equipment::create($this->validated($request));

        return redirect()->route('equipment.index')->with('status', 'Peralatan ditambahkan.');
    }

    public function edit(Equipment $equipment): View
    {
        return view('equipment.edit', ['item' => $equipment]);
    }

    public function update(Request $request, Equipment $equipment): RedirectResponse
    {
        $equipment->update($this->validated($request, $equipment));

        return redirect()->route('equipment.index')->with('status', 'Peralatan diperbarui.');
    }

    public function destroy(Request $request, Equipment $equipment): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $equipment->delete();

        return redirect()->route('equipment.index')->with('status', 'Peralatan dihapus.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Equipment $equipment = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:50', Rule::unique('equipment', 'code')->ignore($equipment?->id)],
            'category' => ['required', 'in:'.implode(',', Equipment::CATEGORIES)],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'in:'.implode(',', Equipment::STATUSES)],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
