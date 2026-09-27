<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Services\Reports\PharmacyReportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request, PharmacyReportService $reports)
    {
        [$from, $to] = $reports->range($request->query('from'), $request->query('to'));

        return view('pharmacy.reports.index', [
            'from' => $from,
            'to' => $to,
            'summary' => $reports->summary($from, $to),
            'sales' => $reports->sales($from, $to),
            'returns' => $reports->returns($from, $to),
            'purchases' => $reports->purchases($from, $to),
            'movements' => $reports->movements($from, $to),
            'nearExpiry' => $reports->nearExpiry(),
            'lowStock' => $reports->lowStock(),
        ]);
    }

    public function export(Request $request, PharmacyReportService $reports): StreamedResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:sales,returns,purchases,movements'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        [$from, $to] = $reports->range($validated['from'] ?? null, $validated['to'] ?? null);
        $headers = $reports->exportHeaders($validated['type']);

        return response()->streamDownload(function () use ($reports, $validated, $from, $to, $headers): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($reports->exportRows($validated['type'], $from, $to) as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, "{$validated['type']}-{$from}-to-{$to}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
