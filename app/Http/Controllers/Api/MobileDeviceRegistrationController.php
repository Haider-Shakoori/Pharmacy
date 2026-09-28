<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RegisterMobileDeviceRequest;
use App\Services\Mobile\MobileDeviceRegistrationService;
use Illuminate\Http\JsonResponse;

class MobileDeviceRegistrationController extends Controller
{
    public function __invoke(
        RegisterMobileDeviceRequest $request,
        MobileDeviceRegistrationService $registration,
    ): JsonResponse {
        $result = $registration->register(
            $request->string('license_key')->toString(),
            $request->string('device_id')->toString(),
            $request->string('email')->toString(),
            $request->string('password')->toString(),
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
