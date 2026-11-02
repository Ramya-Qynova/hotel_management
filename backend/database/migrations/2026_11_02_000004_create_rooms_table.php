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
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('room_number', 20)->unique();
            $table->foreignId('room_type_id')->constrained('room_types')->restrictOnDelete();
            $table->integer('floor')->default(1);
            $table->enum('operational_status', ['READY', 'CLEANING', 'MAINTENANCE', 'OUT_OF_SERVICE'])->default('READY');
            $table->boolean('is_active')->default(true);
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            // Composite unique key to support composite FK from booking_rooms
            $table->unique(['id', 'room_type_id'], 'uq_rooms_id_room_type');
            $table->index(['room_type_id', 'operational_status'], 'idx_rooms_type_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};

