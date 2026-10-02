<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublicTrialRequest;
use App\Models\Business;
use App\Models\TrialRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PublicTrialRequestController extends Controller
{
    public function create(): View
    {
        return view('public.trial-request');
    }

    public function store(PublicTrialRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if (Business::query()->where('owner_email', $data['owner_email'])->exists()) {
            throw ValidationException::withMessages([
                'owner_email' => 'This email already belongs to an existing pharmacy account.',
            ]);
        }

        if (TrialRequest::query()
            ->where('owner_email', $data['owner_email'])
            ->whereIn('status', ['pending', 'approved'])
            ->exists()) {
            throw ValidationException::withMessages([
                'owner_email' => 'A trial request for this email is already pending or approved.',
            ]);
        }

        TrialRequest::query()->create([
            'pharmacy_name' => $data['pharmacy_name'],
            'requested_slug' => $this->availableSlug($data['pharmacy_name']),
            'owner_name' => $data['owner_name'],
            'owner_email' => $data['owner_email'],
            'phone_whatsapp' => $data['phone_whatsapp'],
            'location' => $data['location'],
            'preferred_locale' => $data['preferred_locale'],
            'notes' => $data['notes'] ?? null,
            'owner_password_ciphertext' => Crypt::encryptString($data['password']),
            'status' => 'pending',
        ]);

        return redirect()
            ->route('trial.request')
            ->with('success', 'Your 7-day trial request has been submitted for approval. We will contact you after review.');
    }

    private function availableSlug(string $pharmacyName): string
    {
        $base = Str::slug($pharmacyName);
        $base = $base !== '' ? Str::limit($base, 63, '') : 'pharmacy';
        $candidate = $base;
        $suffix = 2;
        $reserved = array_map(
            static fn (mixed $slug): string => Str::lower((string) $slug),
            config('pharmacy.reserved_subdomains', []),
        );

        while (
            in_array(Str::lower($candidate), $reserved, true)
            || Business::query()->where('slug', $candidate)->exists()
            || TrialRequest::query()->where('requested_slug', $candidate)->exists()
        ) {
            $candidate = Str::limit($base, 58, '').'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
