<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RefreshDesktopSessionRequest;
use App\Services\Desktop\DesktopAccessService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;

class DesktopSessionRefreshController extends Controller
{
    public function __invoke(
        RefreshDesktopSessionRequest $request,
        DesktopAccessService $access,
    ): JsonResponse {
        $accessToken = $request->bearerToken();

        if (! is_string($accessToken) || $accessToken === '') {
            throw new AuthenticationException('A desktop user session is required.');
        }

        $result = $access->refresh(
            $accessToken,
            $request->string('device_id')->toString(),
        );

        return response()->json([
            'data' => $result,
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
