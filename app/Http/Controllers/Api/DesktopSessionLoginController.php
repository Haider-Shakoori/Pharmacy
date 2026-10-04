<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginDesktopSessionRequest;
use App\Services\Desktop\DesktopAccessService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;

class DesktopSessionLoginController extends Controller
{
    public function __invoke(
        LoginDesktopSessionRequest $request,
        DesktopAccessService $access,
    ): JsonResponse {
        $leaseToken = $request->bearerToken();

        if (! is_string($leaseToken) || $leaseToken === '') {
            throw new AuthenticationException('A desktop activation lease is required.');
        }

        $result = $access->login(
            $leaseToken,
            $request->string('device_id')->toString(),
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json([
            'data' => $result,
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
