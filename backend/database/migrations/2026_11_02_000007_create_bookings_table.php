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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_number', 30)->unique()->comment('e.g. BK-20261102-0001');
            $table->foreignId('guest_id')->constrained('guests')->restrictOnDelete();
            $table->enum('booking_source', ['WALK_IN', 'ONLINE'])->default('WALK_IN');
            $table->enum('status', [
                'PENDING_PAYMENT',
                'CONFIRMED',
                'CHECKED_IN',
                'CHECKED_OUT',
                'CANCELLED',
                'EXPIRED',
                'NO_SHOW',
            ])->default('PENDING_PAYMENT');
            $table->date('check_in_date');
            $table->date('check_out_date')->comment('Exclusive checkout boundary');
            $table->time('expected_check_in_time')->nullable();
            $table->timestamp('actual_check_in_at')->nullable();
            $table->timestamp('actual_check_out_at')->nullable();
            $table->timestamp('late_check_out_until')->nullable();
            $table->unsignedTinyInteger('adults_count')->default(1);
            $table->unsignedTinyInteger('children_count')->default(0);
            $table->timestamp('hold_expires_at')->nullable()->comment('Online booking temporary hold expiration');
            $table->string('cancellation_reason', 255)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('special_requests')->nullable();
            $table->decimal('base_room_charges', 10, 2)->default(0.00);
            $table->decimal('total_service_charges', 10, 2)->default(0.00);
            $table->decimal('total_tax_amount', 10, 2)->default(0.00);
            $table->decimal('total_discount_amount', 10, 2)->default(0.00);
            $table->decimal('grand_total', 10, 2)->default(0.00);
            $table->decimal('required_advance_amount', 10, 2)->default(0.00);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->comment('Staff user; NULL if guest booked online');
            $table->timestamps();

            // Composite unique key to support composite FK from invoices
            $table->unique(['id', 'guest_id'], 'uq_bookings_id_guest');
            $table->index(['check_in_date', 'check_out_date', 'status'], 'idx_bookings_dates_status');
            $table->index('guest_id', 'idx_bookings_guest');
            $table->index(['status', 'hold_expires_at'], 'idx_bookings_hold_exp');
        });

        DB::statement('ALTER TABLE bookings ADD CONSTRAINT chk_bookings_dates CHECK (check_out_date > check_in_date)');
        DB::statement('ALTER TABLE bookings ADD CONSTRAINT chk_bookings_grand_total CHECK (grand_total >= 0.00)');
        DB::statement('ALTER TABLE bookings ADD CONSTRAINT chk_bookings_advance CHECK (required_advance_amount >= 0.00)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};

