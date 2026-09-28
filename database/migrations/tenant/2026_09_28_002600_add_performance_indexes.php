<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $table): void {
            $table->index(['updated_at', 'id'], 'medicines_sync_cursor_idx');
        });

        Schema::table('product_batches', function (Blueprint $table): void {
            $table->index(['updated_at', 'id'], 'product_batches_sync_cursor_idx');
            $table->index(
                ['status', 'expires_at', 'available_quantity'],
                'product_batches_alerts_idx',
            );
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->index(['updated_at', 'id'], 'customers_sync_cursor_idx');
        });
    }

    public function down(): void
    {
        Schema::table('medicines', function (Blueprint $table): void {
            $table->dropIndex('medicines_sync_cursor_idx');
        });

        Schema::table('product_batches', function (Blueprint $table): void {
            $table->dropIndex('product_batches_sync_cursor_idx');
            $table->dropIndex('product_batches_alerts_idx');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropIndex('customers_sync_cursor_idx');
        });
    }
};
