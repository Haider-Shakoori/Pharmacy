<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WebsiteLead;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WebsiteLeadImportController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        // Both systems must explicitly configure the same high-entropy secret.
        // Do not allow an empty secret, public ingestion, or browser CORS access.
        $expected = (string) config('pharmacy.marketing_leads.import_token', '');
        $actual = (string) $request->bearerToken();

        if (strlen($expected) < 32 || ! hash_equals($expected, $actual)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $data = $request->validate([
            'leads' => ['required', 'array', 'min:1', 'max:50'],
            'leads.*.id' => ['required', 'integer', 'min:1'],
            'leads.*.created_at' => ['required', 'integer', 'min:1'],
            'leads.*.kind' => ['required', Rule::in(['demo', 'contact', 'trial'])],
            'leads.*.language' => ['required', Rule::in(['en', 'fa', 'ps'])],
            'leads.*.name' => ['required', 'string', 'min:2', 'max:100'],
            'leads.*.phone' => ['required', 'string', 'min:6', 'max:40'],
            'leads.*.email' => ['nullable', 'email', 'max:200'],
            'leads.*.pharmacy' => ['nullable', 'string', 'max:160'],
            'leads.*.message' => ['nullable', 'string', 'max:1200'],
            'leads.*.details' => ['nullable', 'array', 'max:12'],
            'leads.*.details.*' => ['nullable', 'string', 'max:150'],
        ]);

        foreach ($data['leads'] as $index => $item) {
            if ($item['kind'] === 'trial' && (empty($item['email']) || empty($item['pharmacy']))) {
                throw ValidationException::withMessages([
                    "leads.{$index}.email" => 'Trial applications require a valid contact email and pharmacy name.',
                ]);
            }
        }

        $imported = 0;
        $skipped = 0;

        DB::connection('central')->transaction(function () use ($data, &$imported, &$skipped): void {
            foreach ($data['leads'] as $item) {
                $lead = WebsiteLead::query()->firstOrCreate(
                    ['source' => 'darmaltoon.com', 'source_id' => $item['id']],
                    [
                        'kind' => $item['kind'],
                        'language' => $item['language'],
                        'contact_name' => Str::limit(trim($item['name']), 100, ''),
                        'phone' => trim($item['phone']),
                        'email' => $item['email'] ?? null,
                        'pharmacy' => $item['pharmacy'] ?? null,
                        'message' => $item['message'] ?? null,
                        'details' => $item['details'] ?? [],
                        'status' => 'new',
                        'is_test' => false,
                        'submitted_at' => CarbonImmutable::createFromTimestampUTC($item['created_at']),
                    ],
                );

                // Replayed website submissions never erase status, test flag,
                // follow-up assignments or notes changed by a staff member.
                $lead->wasRecentlyCreated ? $imported++ : $skipped++;
            }
        });

        return response()->json([
            'ok' => true,
            'imported' => $imported,
            'already_present' => $skipped,
        ]);
    }
}
