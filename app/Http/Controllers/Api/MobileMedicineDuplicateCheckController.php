<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Medicine;
use App\Services\Mobile\MobileAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileMedicineDuplicateCheckController extends Controller
{
    public function __invoke(Request $request, MobileAccessService $access): JsonResponse
    {
        $context = $access->authenticate($request);

        abort_unless(in_array('medicines.manage', $context->permissions, true), 403);

        $validated = $request->validate([
            'brand_name' => ['required', 'string', 'max:180'],
            'barcode' => ['nullable', 'string', 'max:120'],
        ]);

        $brand = trim($validated['brand_name']);
        $barcode = trim((string) ($validated['barcode'] ?? ''));

        if ($brand === '') {
            return response()->json(['message' => 'Brand name is required.'], 422);
        }

        $matches = $context->tenant->run(function () use ($brand, $barcode) {
            return Medicine::query()
                ->where(function ($query) use ($brand, $barcode): void {
                    $query->whereRaw('LOWER(TRIM(brand_name)) = ?', [mb_strtolower($brand)]);

                    if ($barcode !== '') {
                        $query->orWhere('barcode', $barcode);
                    }
                })
                ->orderBy('brand_name')
                ->limit(25)
                ->get(['id', 'brand_name', 'barcode', 'medicine_code'])
                ->map(fn (Medicine $medicine): array => [
                    'id' => $medicine->id,
                    'brand_name' => $medicine->brand_name,
                    'barcode' => $medicine->barcode,
                    'medicine_code' => $medicine->medicine_code,
                ])
                ->all();
        });

        return response()->json([
            'data' => [
                'possible_duplicates' => $matches,
                'registration_allowed' => false,
                'reason' => 'Registration requires a separate authorized server-side create operation.',
            ],
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
