<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Desktop\DesktopUpdateManifestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class DesktopUpdateManifestController extends Controller
{
    public function __invoke(
        Request $request,
        DesktopUpdateManifestService $updates,
    ): JsonResponse {
        $validated = $request->validate([
            'channel' => ['nullable', 'string', 'in:stable'],
            'current' => ['nullable', 'string', 'max:40'],
            'mode' => ['required', 'string', 'max:40'],
            'server_version' => ['nullable', 'string', 'max:40'],
        ]);

        try {
            $manifest = $updates->manifest(
                $validated['channel'] ?? 'stable',
                $validated['mode'],
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        if ($manifest === null) {
            return response()->json([
                'message' => 'No signed production update is currently published for this desktop mode.',
            ], 503);
        }

        return response()
            ->json($manifest)
            ->header('Cache-Control', 'private, max-age=300');
    }
}