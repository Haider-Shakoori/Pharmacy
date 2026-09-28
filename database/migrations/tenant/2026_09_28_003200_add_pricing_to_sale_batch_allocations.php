<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_batch_allocations', function (Blueprint $table) {
            $table->decimal('unit_price', 20, 4)->nullable()->after('unit_cost');
            $table->decimal('line_total', 20, 4)->nullable()->after('unit_price');
        });

        DB::table('sale_batch_allocations')
            ->join('sale_lines', 'sale_lines.id', '=', 'sale_batch_allocations.sale_line_id')
            ->select('sale_batch_allocations.id', 'sale_batch_allocations.quantity', 'sale_lines.unit_price')
            ->orderBy('sale_batch_allocations.id')
            ->get()
            ->each(function ($allocation): void {
                DB::table('sale_batch_allocations')->where('id', $allocation->id)->update([
                    'unit_price' => $allocation->unit_price,
                    'line_total' => (float) $allocation->quantity * (float) $allocation->unit_price,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('sale_batch_allocations', function (Blueprint $table) {
            $table->dropColumn(['unit_price', 'line_total']);
        });
    }
};
