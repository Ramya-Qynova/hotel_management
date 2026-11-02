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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->unsignedBigInteger('invoice_id')->nullable()->comment('Associated invoice once issued');
            $table->string('transaction_reference', 100)->unique()->comment('Internal ref or payment gateway transaction ID');
            $table->enum('payment_method', ['CASH', 'UPI', 'CARD', 'BANK_TRANSFER', 'ONLINE_GATEWAY']);
            $table->enum('payment_type', ['ADVANCE', 'INTERIM', 'FINAL_SETTLEMENT', 'SECURITY_DEPOSIT'])->default('ADVANCE');
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['PENDING', 'SUCCESS', 'FAILED', 'PARTIALLY_REFUNDED', 'REFUNDED'])->default('PENDING');
            $table->string('payment_gateway', 50)->nullable();
            $table->string('gateway_order_id', 100)->nullable();
            $table->string('gateway_payment_id', 100)->nullable();
            $table->string('gateway_signature', 255)->nullable();
            $table->json('gateway_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            // Composite unique key to support composite FK from refunds
            $table->unique(['id', 'booking_id'], 'uq_payments_id_booking');
            $table->index(['booking_id', 'status'], 'idx_payments_booking_status');
            $table->index('gateway_order_id', 'idx_payments_gateway_order');
        });

        DB::statement('ALTER TABLE payments ADD CONSTRAINT chk_payments_amount CHECK (amount > 0.00)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

