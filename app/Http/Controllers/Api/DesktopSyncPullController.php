<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Desktop\DesktopAccessService;
use App\Services\Mobile\MobileAccessContext;
use App\Services\Mobile\MobileSyncPullService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DesktopSyncPullController extends Controller
{
    public function __invoke(
        Request $request,
        string $stream,
        DesktopAccessService $access,
        MobileSyncPullService $sync,
    ): JsonResponse {
        $maxPageSize = (int) config('pharmacy.performance.sync_max_page_size', 250);
        $defaultPageSize = (int) config('pharmacy.performance.sync_page_size', 100);

        $validated = $request->validate([
            'cursor' => ['nullable', 'string', 'max:1000'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.$maxPageSize],
        ]);

        $desktopContext = $access->authenticate($request);

        if (! (bool) ($desktopContext->tenant->business?->desktop_cloud_sync_enabled ?? true)) {
            return response()->json([
                'message' => 'Live server synchronization is disabled by the platform.',
                'code' => 'desktop_sync_disabled',
            ], 409);
        }

        $context = new MobileAccessContext(
            $desktopContext->tenant,
            $desktopContext->activation,
            $desktopContext->userId,
            $desktopContext->user,
            $desktopContext->permissions,
        );

        $page = $sync->pull(
            $context,
            $stream,
            $validated['cursor'] ?? null,
            (int) ($validated['limit'] ?? $defaultPageSize),
        );

        return response()->json([
            'data' => $page,
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
