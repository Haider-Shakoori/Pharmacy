<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Desktop\DesktopAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DesktopSyncStatusController extends Controller
{
    public function __invoke(
        Request $request,
        DesktopAccessService $access,
    ): JsonResponse {
        $context = $access->authenticate($request);
        $enabled = (bool) ($context->tenant->business?->desktop_cloud_sync_enabled ?? true);

        return response()->json([
            'data' => [
                'enabled' => $enabled,
                'managed_by' => 'platform',
                'message' => $enabled
                    ? 'Live server connection and synchronization are enabled by the platform.'
                    : 'Live server connection and synchronization are disabled by the platform. Local work remains available and pending changes stay queued.',
            ],
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
