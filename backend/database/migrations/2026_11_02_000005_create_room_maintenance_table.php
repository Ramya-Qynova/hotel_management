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
        Schema::create('room_maintenance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date')->comment('Exclusive end boundary');
            $table->string('reason', 255);
            $table->enum('status', ['SCHEDULED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'])->default('SCHEDULED');
            $table->decimal('maintenance_cost', 10, 2)->default(0.00);
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['room_id', 'start_date', 'end_date', 'status'], 'idx_rm_room_dates_status');
        });

        DB::statement('ALTER TABLE room_maintenance ADD CONSTRAINT chk_rm_dates CHECK (end_date > start_date)');
        DB::statement('ALTER TABLE room_maintenance ADD CONSTRAINT chk_rm_cost CHECK (maintenance_cost >= 0.00)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_maintenance');
    }
};

