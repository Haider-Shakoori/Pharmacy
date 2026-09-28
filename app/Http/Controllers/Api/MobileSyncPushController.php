<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Mobile\MobileAccessService;
use App\Services\Mobile\MobileSyncPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileSyncPushController extends Controller
{
    public function __invoke(
        Request $request,
        MobileAccessService $access,
        MobileSyncPushService $sync,
    ): JsonResponse {
        $validated = $request->validate([
            'events' => ['required', 'array', 'min:1', 'max:25'],
            'events.*.idempotency_key' => ['required', 'string', 'max:191'],
            'events.*.event_type' => ['required', 'string', 'max:100'],
            'events.*.payload' => ['required', 'array'],
        ]);

        $context = $access->authenticate($request);

        return response()->json([
            'data' => [
                'results' => $sync->push($context, $validated['events']),
            ],
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
