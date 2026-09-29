<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('local_node_states', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->ulid('activation_id')->nullable();
            $table->uuid('device_id')->nullable();
            $table->text('lease_token');
            $table->text('lease_public_key');
            $table->timestamp('lease_issued_at');
            $table->timestamp('lease_expires_at')->index();
            $table->timestamp('last_observed_at');
            $table->string('cloud_base_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_node_states');
    }
};