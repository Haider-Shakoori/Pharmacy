<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Subscriptions\DarmaltoonCommercialBillingService;
use Illuminate\Http\JsonResponse;

class DarmaltoonLaunchOfferController extends Controller
{
    public function __invoke(DarmaltoonCommercialBillingService $billing): JsonResponse
    {
        $snapshot = $billing->snapshot();

        // Aggregate only: no names, payment references or pharmacy identities.
        // Availability is indicative; a place is allocated only on confirmed payment.
        return response()->json([
            'founding_limit' => \App\Support\Commercial\DarmaltoonLaunchPricing::FOUNDING_LIMIT,
            'founding_remaining' => $snapshot['remaining'],
            'founding_confirmed' => $snapshot['confirmed_founders'],
            'first_year_price_afn' => $snapshot['first_year_price'],
            'renewal_price_afn' => $snapshot['renewal_price'],
            'regular_first_year_price_afn' => $snapshot['regular_first_year_price'],
            'reservation' => false,
        ])->header('Cache-Control', 'public, max-age=30')
            ->header('Access-Control-Allow-Origin', 'https://darmaltoon.com');
    }
}
