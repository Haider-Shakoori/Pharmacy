<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\Subscriptions\PlatformSubscriptionMonitoringService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class SubscriptionMonitoringController extends Controller
{
    public function __invoke(
        Request $request,
        PlatformSubscriptionMonitoringService $monitoring,
    ): View {
        $search = trim((string) $request->query('search'));
        $state = trim((string) $request->query('state', 'attention'));
        $snapshot = $monitoring->snapshot();
        $rows = $snapshot['rows'];

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $rows = $rows->filter(function (array $row) use ($needle): bool {
                return str_contains(mb_strtolower($row['pharmacy_name']), $needle)
                    || str_contains(mb_strtolower($row['slug']), $needle)
                    || str_contains(mb_strtolower((string) ($row['plan_name'] ?? '')), $needle);
            })->values();
        }

        if ($state === 'attention') {
            $rows = $rows->where('needs_attention', true)->values();
        } elseif ($state === 'operational') {
            $rows = $rows->where('operational', true)->values();
        } elseif ($state === 'non_operational') {
            $rows = $rows->where('operational', false)->values();
        }

        $perPage = (int) config('pharmacy.performance.default_page_size');
        $page = max(1, (int) $request->query('page', 1));
        $paginator = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );

        return view('platform.monitoring.index', [
            'metrics' => $snapshot['metrics'],
            'rows' => $paginator,
            'search' => $search,
            'state' => $state,
        ]);
    }
}
