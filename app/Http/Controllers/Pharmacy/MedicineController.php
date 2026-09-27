<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pharmacy\StoreMedicineRequest;
use App\Http\Requests\Pharmacy\UpdateMedicineRequest;
use App\Models\Manufacturer;
use App\Models\Medicine;
use App\Models\MedicineCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MedicineController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $category = (string) $request->query('category');
        $status = (string) $request->query('status');

        $medicines = Medicine::query()
            ->with(['category:id,name', 'manufacturer:id,name'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('brand_name', 'like', "%{$search}%")
                        ->orWhere('generic_name', 'like', "%{$search}%")
                        ->orWhere('medicine_code', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%")
                        ->orWhere('strength', 'like', "%{$search}%");
                });
            })
            ->when($category !== '', fn ($query) => $query->where('medicine_category_id', $category))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('brand_name')
            ->paginate(config('pharmacy.performance.default_page_size'))
            ->withQueryString();

        return view('pharmacy.medicines.index', [
            'medicines' => $medicines,
            'categories' => MedicineCategory::query()->orderBy('name')->get(['id', 'name']),
            'search' => $search,
            'category' => $category,
            'status' => $status,
        ]);
    }

    public function create(): View
    {
        return view('pharmacy.medicines.create', $this->referenceData());
    }

    public function store(StoreMedicineRequest $request): RedirectResponse
    {
        $medicine = Medicine::query()->create($request->validated());

        return redirect()
            ->route('pharmacy.medicines.edit', $medicine)
            ->with('success', 'Medicine created.');
    }

    public function edit(Medicine $medicine): View
    {
        return view('pharmacy.medicines.edit', [
            'medicine' => $medicine,
            ...$this->referenceData(),
        ]);
    }

    public function update(UpdateMedicineRequest $request, Medicine $medicine): RedirectResponse
    {
        $medicine->update($request->validated());

        return back()->with('success', 'Medicine updated.');
    }

    private function referenceData(): array
    {
        return [
            'categories' => MedicineCategory::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'manufacturers' => Manufacturer::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }
}
