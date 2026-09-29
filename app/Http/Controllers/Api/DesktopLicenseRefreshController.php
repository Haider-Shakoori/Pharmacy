<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RefreshDesktopLicenseRequest;
use App\Services\Licensing\LicenseActivationService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;

class DesktopLicenseRefreshController extends Controller
{
    public function __invoke(
        RefreshDesktopLicenseRequest $request,
        LicenseActivationService $activationService,
    ): JsonResponse {
        $leaseToken = $request->bearerToken();

        if (! is_string($leaseToken) || $leaseToken === '') {
            throw new AuthenticationException('A desktop activation lease is required.');
        }

        $result = $activationService->refreshWindowsLease(
            $leaseToken,
            $request->string('device_id')->toString(),
            $request->input('device_name'),
            $request->input('app_version'),
            $request->input('device_model'),
            $request->input('os_version'),
            $request->input('build_number'),
        );

        return response()->json([
            'data' => $result,
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
