<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RejectOversizedApiPayload
{
    public function handle(Request $request, Closure $next): Response
    {
        $maxBytes = (int) config(
            'pharmacy.security.max_api_payload_bytes',
            262144,
        );

        $contentLength = $request->headers->get('Content-Length');
        if (is_string($contentLength) &&
            ctype_digit($contentLength) &&
            (int) $contentLength > $maxBytes) {
            return $this->tooLarge($maxBytes);
        }

        if (strlen($request->getContent()) > $maxBytes) {
            return $this->tooLarge($maxBytes);
        }

        return $next($request);
    }

    private function tooLarge(int $maxBytes): JsonResponse
    {
        return response()->json([
            'message' => 'The API request payload is too large.',
            'max_bytes' => $maxBytes,
        ], 413);
    }
}
