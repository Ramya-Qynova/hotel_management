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
        Schema::create('booking_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $table->unsignedBigInteger('room_id')->nullable()->comment('Optional: specific room receiving the service');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2)->comment('Price snapshot at consumption time');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax_rate_percent', 5, 2)->default(0.00);
            $table->decimal('tax_amount', 10, 2)->default(0.00);
            $table->decimal('total_amount', 10, 2);
            $table->date('service_date');
            $table->foreignId('added_by')->constrained('users')->restrictOnDelete();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            // Composite FK strictly ensuring room_id belongs to the booking
            $table->foreign(['booking_id', 'room_id'], 'fk_bs_booking_room')
                ->references(['booking_id', 'room_id'])
                ->on('booking_rooms')
                ->restrictOnDelete();

            $table->index('booking_id', 'idx_bs_booking');
            $table->index('service_id', 'idx_bs_service');
        });

        DB::statement('ALTER TABLE booking_services ADD CONSTRAINT chk_bs_quantity CHECK (quantity >= 1)');
        DB::statement('ALTER TABLE booking_services ADD CONSTRAINT chk_bs_price CHECK (unit_price >= 0.00)');
        DB::statement('ALTER TABLE booking_services ADD CONSTRAINT chk_bs_total CHECK (total_amount >= 0.00)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_services');
    }
};

