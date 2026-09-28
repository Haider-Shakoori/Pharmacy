<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Mobile\MobileAccessService;
use App\Services\Mobile\MobileSyncPullService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileSyncPullController extends Controller
{
    public function __invoke(
        Request $request,
        string $stream,
        MobileAccessService $access,
        MobileSyncPullService $sync,
    ): JsonResponse {
        $validated = $request->validate([
            'cursor' => ['nullable', 'string', 'max:1000'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $context = $access->authenticate($request);
        $page = $sync->pull(
            $context,
            $stream,
            $validated['cursor'] ?? null,
            (int) ($validated['limit'] ?? 200),
        );

        return response()->json([
            'data' => $page,
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
