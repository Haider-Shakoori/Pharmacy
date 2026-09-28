<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('parent_id')->nullable()->constrained('ledger_accounts')->nullOnDelete();
            $table->string('code', 32)->unique();
            $table->string('name', 160);
            $table->string('type', 32)->index();
            $table->string('normal_balance', 8);
            $table->string('system_key', 64)->nullable()->unique();
            $table->char('currency', 3)->default('AFN');
            $table->boolean('is_system')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('journal_number', 80)->unique();
            $table->date('business_date')->index();
            $table->timestamp('occurred_at')->index();
            $table->string('status', 24)->default('posted')->index();
            $table->char('currency', 3)->default('AFN');
            $table->string('source_type', 120)->index();
            $table->string('source_id', 64)->index();
            $table->string('source_event', 80)->nullable()->index();
            $table->string('source_number', 120)->nullable();
            $table->string('idempotency_key', 191)->unique();
            $table->string('reference', 160)->nullable();
            $table->string('description', 500);
            $table->decimal('total_debit', 20, 4);
            $table->decimal('total_credit', 20, 4);
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('reversal_of_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->timestamp('posted_at')->index();
            $table->timestamps();

            $table->index(['source_type', 'source_id', 'source_event']);
            $table->index(['business_date', 'status']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('ledger_account_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('stock_location_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('debit', 20, 4)->default(0);
            $table->decimal('credit', 20, 4)->default(0);
            $table->string('counterparty_type', 80)->nullable();
            $table->string('counterparty_id', 64)->nullable();
            $table->string('memo', 500)->nullable();
            $table->timestamps();

            $table->index(['ledger_account_id', 'journal_entry_id']);
            $table->index(['counterparty_type', 'counterparty_id']);
            $table->index(['stock_location_id', 'ledger_account_id']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('expense_number', 80)->unique();
            $table->foreignUlid('expense_account_id')->constrained('ledger_accounts')->restrictOnDelete();
            $table->foreignUlid('payment_account_id')->constrained('ledger_accounts')->restrictOnDelete();
            $table->foreignUlid('stock_location_id')->nullable()->constrained()->nullOnDelete();
            $table->date('business_date')->index();
            $table->char('currency', 3)->default('AFN');
            $table->decimal('amount', 20, 4);
            $table->string('payee', 180)->nullable();
            $table->string('reference', 160)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 24)->default('posted')->index();
            $table->string('idempotency_key', 191)->unique();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at')->index();
            $table->timestamp('reversed_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('accounting_adjustments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('adjustment_number', 80)->unique();
            $table->date('business_date')->index();
            $table->char('currency', 3)->default('AFN');
            $table->foreignUlid('debit_account_id')->constrained('ledger_accounts')->restrictOnDelete();
            $table->foreignUlid('credit_account_id')->constrained('ledger_accounts')->restrictOnDelete();
            $table->foreignUlid('stock_location_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 20, 4);
            $table->string('reference', 160)->nullable();
            $table->text('reason');
            $table->string('status', 24)->default('posted')->index();
            $table->string('idempotency_key', 191)->unique();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at')->index();
            $table->timestamp('reversed_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_adjustments');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('ledger_accounts');
    }
};
