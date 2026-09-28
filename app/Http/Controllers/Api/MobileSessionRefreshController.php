<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Mobile\MobileAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileSessionRefreshController extends Controller
{
    public function __invoke(
        Request $request,
        MobileAccessService $access,
    ): JsonResponse {
        $context = $access->authenticate($request, allowExpired: true);

        return response()->json([
            'data' => $access->refresh($context),
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
