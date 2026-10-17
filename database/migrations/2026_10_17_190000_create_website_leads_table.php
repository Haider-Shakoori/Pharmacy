<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_leads', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('source', 50)->default('darmaltoon.com');
            $table->unsignedBigInteger('source_id');
            $table->string('kind', 16);
            $table->string('language', 8)->default('en');
            $table->string('contact_name', 100);
            $table->string('phone', 40);
            $table->string('email', 200)->nullable();
            $table->string('pharmacy', 160)->nullable();
            $table->text('message')->nullable();
            $table->json('details')->nullable();
            $table->string('status', 32)->default('new')->index();
            $table->boolean('is_test')->default(false);
            $table->text('internal_notes')->nullable();
            $table->timestamp('follow_up_at')->nullable();
            $table->foreignUlid('reviewed_by')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->timestamp('submitted_at')->index();
            $table->timestamps();
            $table->unique(['source', 'source_id']);
            $table->index(['is_test', 'status', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_leads');
    }
};
