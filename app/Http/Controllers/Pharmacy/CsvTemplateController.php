<?php

namespace App\Http\Controllers\Pharmacy;

use Illuminate\Http\Response;

class CsvTemplateController
{
    public function medicines(): Response
    {
        return $this->download(
            'medicine-template.csv',
            ['medicine_code', 'brand_name', 'generic_name', 'strength', 'dosage_form', 'category', 'manufacturer', 'manufacturer_country', 'barcode', 'purchase_unit', 'sale_unit', 'units_per_purchase_unit', 'reorder_level', 'prescription_required', 'batch_tracking_required', 'expiry_tracking_required', 'is_active', 'notes']
        );
    }

    public function inventory(): Response
    {
        return $this->download(
            'inventory-template.csv',
            ['medicine_code', 'batch_number', 'expiry_date', 'manufactured_date', 'quantity', 'purchase_cost', 'sale_price', 'notes']
        );
    }

    private function download(string $filename, array $columns): Response
    {
        return response("\xEF\xBB\xBF".implode(',', $columns)."\r\n", 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
