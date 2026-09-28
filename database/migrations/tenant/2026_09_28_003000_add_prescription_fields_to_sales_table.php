<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('prescription_reference', 120)->nullable()->after('customer_id')->index();
            $table->string('prescriber_name', 160)->nullable()->after('prescription_reference');
            $table->date('prescription_date')->nullable()->after('prescriber_name');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['prescription_reference', 'prescriber_name', 'prescription_date']);
        });
    }
};
