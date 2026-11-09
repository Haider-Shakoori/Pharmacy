<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\StockLocation;
use App\Services\Mobile\MobileAccessService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MobileStockLocationController extends Controller
{
    public function index(Request $request, MobileAccessService $access): JsonResponse
    {
        $context = $access->authenticate($request);
        abort_unless(
            in_array('inventory.manage', $context->permissions, true) ||
            in_array('pos.sell', $context->permissions, true),
            403
        );

        return $context->tenant->run(fn (): JsonResponse => response()->json([
            'data' => StockLocation::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'branch_id', 'code', 'name', 'kind', 'is_default']),
        ]));
    }

    public function branches(Request $request, MobileAccessService $access): JsonResponse
    {
        $context = $access->authenticate($request);
        abort_unless(in_array('inventory.manage', $context->permissions, true), 403);

        return $context->tenant->run(fn (): JsonResponse => response()->json([
            'data' => Branch::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
        ]));
    }

    public function store(Request $request, MobileAccessService $access): JsonResponse
    {
        $context = $access->authenticate($request);
        abort_unless(in_array('inventory.manage', $context->permissions, true), 403);

        return $context->tenant->run(function () use ($request): JsonResponse {
            $input = $request->all();
            foreach (['code', 'name'] as $field) {
                if (is_string($input[$field] ?? null)) {
                    $input[$field] = trim($input[$field]);
                }
            }
            $data = Validator::make($input, [
                'branch_id' => ['required', 'ulid', Rule::exists('branches', 'id')->where('is_active', true)],
                'code' => ['required', 'string', 'max:60', Rule::unique('stock_locations', 'code')->where('branch_id', $request->input('branch_id'))],
                'name' => ['required', 'string', 'max:160'],
                'kind' => ['required', Rule::in(['store', 'warehouse', 'shelf', 'quarantine', 'other'])],
            ])->validate();

            try {
                $location = StockLocation::query()->create([
                    ...$data,
                    'is_default' => false,
                    'is_active' => true,
                ]);
            } catch (UniqueConstraintViolationException $exception) {
                throw ValidationException::withMessages([
                    'code' => 'A stock location with this code already exists in the branch.',
                ]);
            }

            return response()->json(['data' => [
                'id' => $location->id,
                'branch_id' => $location->branch_id,
                'code' => $location->code,
                'name' => $location->name,
                'kind' => $location->kind,
            ]], 201);
        });
    }
}
