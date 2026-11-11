<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Services\Mobile\MobileAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileSupplierController extends Controller
{
    public function index(Request $request, MobileAccessService $access): JsonResponse
    {
        $context = $access->authenticate($request);
        abort_unless(in_array('inventory.manage', $context->permissions, true), 403);

        return $context->tenant->run(function () use ($request): JsonResponse {
            $validated = $request->validate([
                'search' => ['sometimes', 'string', 'max:100'],
                'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            ]);
            $search = trim((string) ($validated['search'] ?? ''));
            $limit = (int) ($validated['limit'] ?? 50);

            $suppliers = Supplier::query()
                ->when($search !== '', fn ($query) => $query->where(
                    fn ($query) => $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('code', 'like', '%'.$search.'%')
                ))
                ->orderBy('name')
                ->limit($limit)
                ->get(['id', 'code', 'name', 'contact_person', 'phone', 'city', 'province', 'is_active']);

            return response()->json(['data' => $suppliers]);
        });
    }
}
