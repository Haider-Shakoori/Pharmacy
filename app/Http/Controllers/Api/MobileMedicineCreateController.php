<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Medicine;
use App\Services\Mobile\MobileAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MobileMedicineCreateController extends Controller
{
    public function __invoke(Request $request, MobileAccessService $access): JsonResponse
    {
        $context = $access->authenticate($request);
        abort_unless(in_array('medicines.manage', $context->permissions, true), 403);

        return $context->tenant->run(function () use ($request): JsonResponse {
            $data = Validator::make($request->all(), [
                'pharmacist_approved' => ['required', 'accepted'],
                'medicine_code' => ['required', 'string', 'max:80', Rule::unique('medicines', 'medicine_code')],
                'brand_name' => ['required', 'string', 'max:180'],
                'barcode' => ['nullable', 'string', 'max:120', Rule::unique('medicines', 'barcode')],
                'purchase_unit' => ['required', 'string', 'max:50'],
                'sale_unit' => ['required', 'string', 'max:50'],
                'units_per_purchase_unit' => ['required', 'numeric', 'gt:0'],
                'reorder_level' => ['required', 'numeric', 'min:0'],
                'prescription_required' => ['required', 'boolean'],
                'batch_tracking_required' => ['required', 'boolean'],
                'expiry_tracking_required' => ['required', 'boolean'],
                'is_active' => ['required', 'boolean'],
                'generic_name' => ['nullable', 'string', 'max:180'],
                'strength' => ['nullable', 'string', 'max:100'],
                'dosage_form' => ['nullable', 'string', 'max:80'],
            ])->validate();

            $brand = trim($data['brand_name']);
            if ($brand === '' || Medicine::query()->whereRaw('LOWER(TRIM(brand_name)) = ?', [mb_strtolower($brand)])->exists()) {
                throw ValidationException::withMessages(['brand_name' => 'Brand name is empty or already exists.']);
            }

            unset($data['pharmacist_approved']);
            $data['brand_name'] = $brand;
            $medicine = Medicine::query()->create($data);

            return response()->json(['data' => ['id' => $medicine->id]], 201);
        });
    }
}
