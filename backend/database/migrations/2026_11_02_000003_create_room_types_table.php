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
        Schema::create('room_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique()->comment('e.g. AC Room, Normal Room');
            $table->string('slug', 100)->unique();
            $table->decimal('base_price_per_night', 10, 2);
            $table->unsignedTinyInteger('max_occupancy')->default(2);
            $table->decimal('extra_person_charge', 10, 2)->default(0.00);
            $table->decimal('extra_bed_charge', 10, 2)->default(0.00);
            $table->json('amenities')->nullable()->comment('Array of amenities');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE room_types ADD CONSTRAINT chk_room_types_price CHECK (base_price_per_night >= 0.00)');
        DB::statement('ALTER TABLE room_types ADD CONSTRAINT chk_room_types_occupancy CHECK (max_occupancy >= 1)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_types');
    }
};

