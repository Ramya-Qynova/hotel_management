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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete()->comment('Target staff user; NULL if role broadcast');
            $table->foreignId('role_id')->nullable()->constrained('roles')->cascadeOnDelete()->comment('Target role e.g. housekeeping');
            $table->string('type', 100)->comment('e.g. ROOM_CLEANING_REQUIRED, PAYMENT_RECEIVED');
            $table->string('title', 150);
            $table->text('message');
            $table->json('data')->nullable()->comment('Contextual payload {booking_id, room_id, amount}');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'is_read'], 'idx_notif_user_read');
            $table->index(['role_id', 'is_read'], 'idx_notif_role_read');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};

