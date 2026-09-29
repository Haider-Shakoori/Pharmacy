<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ActivateLicenseRequest;
use App\Services\Licensing\LicenseActivationService;
use Illuminate\Http\JsonResponse;

class LicenseActivationController extends Controller
{
    public function __invoke(
        ActivateLicenseRequest $request,
        LicenseActivationService $activationService,
    ): JsonResponse {
        $result = $activationService->activate(
            $request->string('license_key')->toString(),
            $request->string('device_id')->toString(),
            $request->input('device_name'),
            $request->input('app_version'),
            null,
            null,
            null,
            $request->input('platform', 'android'),
        );

        return response()->json([
            'data' => $result,
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
