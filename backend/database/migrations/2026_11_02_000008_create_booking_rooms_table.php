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
        Schema::create('booking_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->unsignedBigInteger('room_type_id');
            $table->unsignedBigInteger('room_id');
            $table->date('check_in_date');
            $table->date('check_out_date')->comment('Exclusive checkout boundary');
            $table->decimal('rate_per_night', 10, 2)->comment('Price snapshot at booking time');
            $table->unsignedSmallInteger('nights_count');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax_rate_percent', 5, 2)->default(0.00);
            $table->decimal('tax_amount', 10, 2)->default(0.00);
            $table->decimal('total_amount', 10, 2);
            $table->timestamps();

            // Prevents allocating the same physical room multiple times within a single booking
            $table->unique(['booking_id', 'room_id'], 'uq_br_booking_room');
            $table->index(['room_id', 'check_in_date', 'check_out_date'], 'idx_br_room_dates');
            $table->index('booking_id', 'idx_br_booking');

            // Composite FK strictly guaranteeing room_id matches room_type_id
            $table->foreign(['room_id', 'room_type_id'], 'fk_br_room_and_type')
                ->references(['id', 'room_type_id'])
                ->on('rooms')
                ->restrictOnDelete();
        });

        DB::statement('ALTER TABLE booking_rooms ADD CONSTRAINT chk_br_dates CHECK (check_out_date > check_in_date)');
        DB::statement('ALTER TABLE booking_rooms ADD CONSTRAINT chk_br_rate CHECK (rate_per_night >= 0.00)');
        DB::statement('ALTER TABLE booking_rooms ADD CONSTRAINT chk_br_nights CHECK (nights_count >= 1)');
        DB::statement('ALTER TABLE booking_rooms ADD CONSTRAINT chk_br_total CHECK (total_amount >= 0.00)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_rooms');
    }
};

