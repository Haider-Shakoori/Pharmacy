<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('supplier_payments',fn(Blueprint $t)=>$t->foreignUlid('stock_location_id')->nullable()->constrained()->restrictOnDelete()); }
 public function down(): void { Schema::table('supplier_payments',fn(Blueprint $t)=>$t->dropConstrainedForeignId('stock_location_id')); }
};
