<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pharmacy\StoreMedicineReferenceRequest;
use App\Models\Manufacturer;
use App\Models\MedicineCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MedicineReferenceController extends Controller
{
    public function index(): View
    {
        return view('pharmacy.medicines.references', [
            'categories' => MedicineCategory::query()->withCount('medicines')->orderBy('name')->get(),
            'manufacturers' => Manufacturer::query()->withCount('medicines')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreMedicineReferenceRequest $request, string $type): RedirectResponse
    {
        $validated = $request->validated();

        if ($type === 'category') {
            MedicineCategory::query()->create([
                'name' => $validated['name'],
                'is_active' => true,
            ]);
        } else {
            Manufacturer::query()->create([
                'name' => $validated['name'],
                'country' => $validated['country'] ?? null,
                'is_active' => true,
            ]);
        }

        return back()->with('success', ucfirst($type).' created.');
    }
}
