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

        $driver = (string) config('services.medicine_ocr.driver', 'custom');
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

        if (! in_array($driver, ['custom', 'openai'], true)
            || ($driver === 'openai' && ($host !== 'api.openai.com' || ($url['path'] ?? '') !== '/v1/responses'))) {
            return response()->json(['message' => 'Cloud OCR is not configured.'], 503);
        }

        $payload = [
            'image_base64' => $data['image_base64'],
            'mime_type' => $mime,
            'offline_text' => $data['offline_text'] ?? '',
        ];
        if ($driver === 'openai') {
            $payload = [
                'model' => (string) config('services.medicine_ocr.model', 'gpt-4.1-mini'),
                'store' => false,
                'max_output_tokens' => 350,
                'input' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'input_text', 'text' => 'Read this medicine label in English, Dari or Pashto. Return only a JSON object with a suggestions object containing brand_name, generic_name, strength, dosage_form, batch_number and expires_at. Use null for uncertain fields. Never invent details. Offline OCR text: '.($data['offline_text'] ?? '')],
                        ['type' => 'input_image', 'image_url' => 'data:'.$mime.';base64,'.$data['image_base64']],
                    ],
                ]],
            ];
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(20)
                ->connectTimeout(5)
                ->withoutRedirecting()
                ->post($endpoint, $payload);
        } catch (ConnectionException $exception) {
            return response()->json(['message' => 'Cloud OCR is temporarily unavailable.'], 502);
        }

        if (! $response->successful()) {
            return response()->json(['message' => 'Cloud OCR is temporarily unavailable.'], 502);
        }

        $suggestions = $response->json('suggestions');
        if ($driver === 'openai') {
            $output = $response->json('output', []);
            $text = '';
            foreach (is_array($output) ? $output : [] as $item) {
                foreach (($item['content'] ?? []) as $part) {
                    if (($part['type'] ?? '') === 'output_text' && is_string($part['text'] ?? null)) {
                        $text .= $part['text'];
                    }
                }
            }
            $decoded = json_decode($text, true);
            $suggestions = is_array($decoded) ? ($decoded['suggestions'] ?? null) : null;
        }
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
