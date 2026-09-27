<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicine_categories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('manufacturers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('country', 100)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('medicines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('medicine_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('manufacturer_id')->nullable()->constrained()->nullOnDelete();

            $table->string('medicine_code', 80);
            $table->string('barcode', 120)->nullable();
            $table->string('brand_name', 180);
            $table->string('generic_name', 180)->nullable();
            $table->string('strength', 100)->nullable();
            $table->string('dosage_form', 80)->nullable();

            $table->string('purchase_unit', 50)->default('pack');
            $table->string('sale_unit', 50)->default('unit');
            $table->decimal('units_per_purchase_unit', 12, 4)->default(1);
            $table->decimal('reorder_level', 14, 4)->default(0);

            $table->boolean('prescription_required')->default(false);
            $table->boolean('batch_tracking_required')->default(true);
            $table->boolean('expiry_tracking_required')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'medicine_code']);
            $table->unique(['tenant_id', 'barcode']);
            $table->index(['tenant_id', 'brand_name']);
            $table->index(['tenant_id', 'generic_name']);
            $table->index(['tenant_id', 'medicine_category_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicines');
        Schema::dropIfExists('manufacturers');
        Schema::dropIfExists('medicine_categories');
    }
};
