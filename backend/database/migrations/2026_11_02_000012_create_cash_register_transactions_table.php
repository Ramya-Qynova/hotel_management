<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cash_register_transactions', function (Blueprint $table) {
            $table->id();
            $table->date('register_date');
            $table->enum('transaction_type', ['OPENING_BALANCE', 'CASH_IN', 'CASH_OUT', 'CLOSING_BALANCE']);
            $table->enum('category', [
                'OPENING',
                'ROOM_PAYMENT',
                'SERVICE_PAYMENT',
                'REFUND',
                'EXPENSE',
                'BANK_DEPOSIT',
                'CLOSING_AUDIT',
                'ADJUSTMENT',
            ]);
            $table->decimal('amount', 10, 2);
            $table->decimal('balance_after', 10, 2);
            $table->foreignId('payment_id')->nullable()->unique()->constrained('payments')->restrictOnDelete();
            $table->foreignId('refund_id')->nullable()->unique()->constrained('refunds')->restrictOnDelete();
            $table->unsignedBigInteger('expense_id')->nullable()->unique()->comment('Deferred FK attached when expenses table exists');
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->string('notes', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('register_date', 'idx_crt_date');
        });

        DB::statement('ALTER TABLE cash_register_transactions ADD CONSTRAINT chk_crt_amount CHECK (amount >= 0.00)');
        DB::statement('ALTER TABLE cash_register_transactions ADD CONSTRAINT chk_crt_single_source CHECK ((payment_id IS NOT NULL AND refund_id IS NULL AND expense_id IS NULL) OR (payment_id IS NULL AND refund_id IS NOT NULL AND expense_id IS NULL) OR (payment_id IS NULL AND refund_id IS NULL AND expense_id IS NOT NULL) OR (payment_id IS NULL AND refund_id IS NULL AND expense_id IS NULL))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_register_transactions');
    }
};

