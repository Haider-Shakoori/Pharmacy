<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ActivateOfflineLicenseRequest;
use App\Services\Licensing\OfflineInstallationActivationService;
use Illuminate\Http\JsonResponse;

class OfflineLicenseActivationController extends Controller
{
    public function __invoke(
        ActivateOfflineLicenseRequest $request,
        OfflineInstallationActivationService $activation,
    ): JsonResponse {
        $result = $activation->activate(
            $request->string('license_key')->toString(),
            $request->string('installation_id')->toString(),
            $request->string('machine_fingerprint_hash')->toString(),
            $request->input('device_name'),
            $request->input('app_version'),
        );

        return response()->json([
            'data' => $result,
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
