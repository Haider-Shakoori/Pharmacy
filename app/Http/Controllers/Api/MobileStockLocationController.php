<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ProductBatch;
use App\Models\StockLocation;
use App\Services\Inventory\StockTransferService;
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

    public function batches(Request $request, MobileAccessService $access): JsonResponse
    {
        $context = $access->authenticate($request);
        abort_unless(in_array('inventory.manage', $context->permissions, true), 403);

        return $context->tenant->run(function () use ($request): JsonResponse {
            $data = Validator::make($request->query(), [
                'stock_location_id' => [
                    'required',
                    'ulid',
                    Rule::exists('stock_locations', 'id')->where('is_active', true),
                ],
                'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
                'after_id' => ['sometimes', 'ulid'],
            ])->validate();

            $limit = (int) ($data['limit'] ?? 100);
            $query = ProductBatch::query()
                ->where('stock_location_id', $data['stock_location_id'])
                ->where('available_quantity', '>', 0)
                ->when(isset($data['after_id']), fn ($query) => $query->where('id', '>', $data['after_id']))
                ->orderBy('id');

            $batches = $query->limit($limit + 1)->get([
                'id', 'medicine_id', 'branch_id', 'stock_location_id',
                'batch_number', 'batch_key', 'status',
                'expires_at', 'available_quantity',
            ]);
            $hasMore = $batches->count() > $limit;
            $page = $batches->take($limit)->values();

            return response()->json([
                'data' => $page,
                'next_after_id' => $hasMore ? $page->last()->id : null,
            ]);
        });
    }

    public function transfer(
        Request $request,
        MobileAccessService $access,
        StockTransferService $transfers,
    ): JsonResponse {
        $context = $access->authenticate($request);
        abort_unless(in_array('inventory.manage', $context->permissions, true), 403);

        return $context->tenant->run(function () use ($request, $context, $transfers): JsonResponse {
            $data = Validator::make($request->all(), [
                'batch_id' => ['required', 'ulid'],
                'destination_id' => ['required', 'ulid'],
                'quantity' => ['required', 'regex:/^\\d{1,12}(?:\\.\\d{1,4})?$/'],
                'request_id' => ['required', 'uuid'],
            ])->validate();

            return response()->json([
                'data' => $transfers->transfer(
                    $data['batch_id'],
                    $data['destination_id'],
                    (string) $data['quantity'],
                    $data['request_id'],
                    $context->userId,
                ),
            ]);
        });
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
