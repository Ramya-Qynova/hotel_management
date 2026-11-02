<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('booking_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->enum('from_status', [
                'PENDING_PAYMENT',
                'CONFIRMED',
                'CHECKED_IN',
                'CHECKED_OUT',
                'CANCELLED',
                'EXPIRED',
                'NO_SHOW',
            ])->nullable();
            $table->enum('to_status', [
                'PENDING_PAYMENT',
                'CONFIRMED',
                'CHECKED_IN',
                'CHECKED_OUT',
                'CANCELLED',
                'EXPIRED',
                'NO_SHOW',
            ]);
            $table->string('reason', 255)->nullable();
            $table->json('metadata')->nullable()->comment('Contextual snapshot such as balance, gateway order');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete()->comment('Staff user; NULL if system event');
            $table->timestamp('created_at')->useCurrent();

            $table->index('booking_id', 'idx_bsh_booking');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_status_history');
    }
};

