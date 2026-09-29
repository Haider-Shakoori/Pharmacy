<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RegisterTrialDeviceRequest;
use App\Models\Business;
use App\Models\Tenant;
use App\Services\Mobile\MobileDeviceRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TrialDeviceRegistrationController extends Controller
{
    public function __invoke(
        RegisterTrialDeviceRequest $request,
        MobileDeviceRegistrationService $registration,
    ): JsonResponse {
        $business = Business::query()
            ->with('tenant')
            ->where('slug', Str::lower($request->string('pharmacy_code')->toString()))
            ->whereRaw('LOWER(owner_email) = ?', [
                Str::lower($request->string('email')->toString()),
            ])
            ->first();

        $tenant = $business?->tenant;

        if (! $tenant instanceof Tenant) {
            throw ValidationException::withMessages([
                'email' => 'The provided pharmacy credentials are invalid.',
            ]);
        }

        $result = $registration->registerTrial(
            $tenant,
            $request->string('device_id')->toString(),
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->input('device_name'),
            $request->input('app_version'),
            $request->input('device_model'),
            $request->input('os_version'),
            $request->input('build_number'),
            $request->input('platform', 'android'),
        );

        return response()->json([
            'data' => $result,
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
