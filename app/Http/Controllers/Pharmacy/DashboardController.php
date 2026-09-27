<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('pharmacy.dashboard', [
            'stats' => [
                ['label' => __('pharmacy.stats.today_sales'), 'value' => 'AFN 0'],
                ['label' => __('pharmacy.stats.low_stock'), 'value' => '0'],
                ['label' => __('pharmacy.stats.expiring'), 'value' => '0'],
                ['label' => __('pharmacy.stats.pending_sync'), 'value' => '0'],
            ],
        ]);
    }
}
