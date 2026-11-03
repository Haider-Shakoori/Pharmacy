<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->foreignUlid('stock_location_id')
                ->nullable()
                ->after('supplier_id')
                ->constrained('stock_locations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('stock_location_id');
        });
    }
};
