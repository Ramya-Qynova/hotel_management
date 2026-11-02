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
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_id');
            $table->unsignedBigInteger('booking_id');
            $table->string('refund_reference', 100)->unique()->comment('e.g. REF-20261102-0001');
            $table->decimal('amount', 10, 2);
            $table->decimal('cancellation_fee', 10, 2)->default(0.00);
            $table->enum('refund_method', ['CASH', 'UPI', 'CARD', 'BANK_TRANSFER', 'ONLINE_GATEWAY']);
            $table->enum('status', ['PENDING', 'PROCESSED', 'FAILED'])->default('PENDING');
            $table->string('reason', 255);
            $table->string('gateway_refund_id', 100)->nullable();
            $table->json('gateway_response')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('processed_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            // Composite FK strictly guaranteeing refund booking_id matches payment booking_id
            $table->foreign(['payment_id', 'booking_id'], 'fk_refunds_payment_booking')
                ->references(['id', 'booking_id'])
                ->on('payments')
                ->restrictOnDelete();

            $table->index(['payment_id', 'status'], 'idx_refunds_payment_status');
            $table->index('booking_id', 'idx_refunds_booking');
        });

        DB::statement('ALTER TABLE refunds ADD CONSTRAINT chk_refunds_amount CHECK (amount > 0.00)');
        DB::statement('ALTER TABLE refunds ADD CONSTRAINT chk_refunds_cancellation_fee CHECK (cancellation_fee >= 0.00)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};

