<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Mobile\MobileAccessService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MobileMedicineCloudOcrController extends Controller
{
    public function __invoke(Request $request, MobileAccessService $access): JsonResponse
    {
        $context = $access->authenticate($request);
        abort_unless(in_array('medicines.manage', $context->permissions, true), 403);

        $data = $request->validate([
            'consent' => ['required', 'accepted'],
            'image_base64' => ['required', 'string', 'max:11185000'],
            'offline_text' => ['nullable', 'string', 'max:12000'],
        ]);

        $image = base64_decode($data['image_base64'], true);
        if ($image === false || strlen($image) < 1 || strlen($image) > 8 * 1024 * 1024) {
            return response()->json(['message' => 'Invalid image size or encoding.'], 422);
        }
        $imageInfo = @getimagesizefromstring($image);
        $mime = $imageInfo['mime'] ?? null;
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return response()->json(['message' => 'Unsupported image format.'], 422);
        }

        $endpoint = (string) config('services.medicine_ocr.endpoint', '');
        $token = (string) config('services.medicine_ocr.token', '');
        $url = parse_url($endpoint);
        $allowedHosts = config('services.medicine_ocr.allowed_hosts', []);
        $host = strtolower((string) ($url['host'] ?? ''));
        $trustedHosts = array_map('strtolower', is_array($allowedHosts) ? $allowedHosts : []);
        // Require a separately configured trusted HTTPS provider; no redirect
        // following and no provider credentials are sent to the mobile app.
        if ($token === '' || ! is_array($url) || ($url['scheme'] ?? '') !== 'https'
            || empty($url['host']) || isset($url['user']) || isset($url['pass'])
            || isset($url['fragment']) || isset($url['port'])
            || filter_var($host, FILTER_VALIDATE_IP) !== false
            || ! in_array($host, $trustedHosts, true)) {
            return response()->json(['message' => 'Cloud OCR is not configured.'], 503);
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(20)
                ->connectTimeout(5)
                ->withoutRedirecting()
                ->post($endpoint, [
                    'image_base64' => $data['image_base64'],
                    'mime_type' => $mime,
                    'offline_text' => $data['offline_text'] ?? '',
                ]);
        } catch (ConnectionException $exception) {
            return response()->json(['message' => 'Cloud OCR is temporarily unavailable.'], 502);
        }

        if (! $response->successful()) {
            return response()->json(['message' => 'Cloud OCR is temporarily unavailable.'], 502);
        }

        $suggestions = $response->json('suggestions');
        if (! is_array($suggestions)) {
            return response()->json(['message' => 'Invalid OCR provider response.'], 502);
        }
        $allowed = ['brand_name', 'generic_name', 'strength', 'dosage_form', 'batch_number', 'expires_at'];
        $filtered = [];
        foreach ($allowed as $field) {
            $value = $suggestions[$field] ?? null;
            if (is_string($value) && mb_strlen($value) <= 180) {
                $filtered[$field] = trim($value);
            }
        }

        // Never create or modify medicine records from OCR suggestions.
        return response()->json(['suggestions' => $filtered]);
    }
}
