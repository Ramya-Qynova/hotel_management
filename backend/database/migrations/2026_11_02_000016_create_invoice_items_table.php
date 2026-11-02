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
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->enum('item_type', [
                'ROOM_CHARGE',
                'SERVICE_CHARGE',
                'EXTRA_GUEST',
                'LATE_CHECKOUT',
                'DAMAGE_FEE',
                'DISCOUNT',
                'ADJUSTMENT',
            ]);
            $table->foreignId('booking_room_id')->nullable()->constrained('booking_rooms')->nullOnDelete();
            $table->foreignId('booking_service_id')->nullable()->constrained('booking_services')->nullOnDelete();
            $table->string('description', 255);
            $table->decimal('quantity', 8, 2)->default(1.00);
            $table->decimal('unit_price', 10, 2)->comment('Can be negative for adjustments/discounts');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax_rate_percent', 5, 2)->default(0.00);
            $table->decimal('tax_amount', 10, 2)->default(0.00);
            $table->decimal('total_amount', 10, 2);
            $table->timestamps();

            $table->index('invoice_id', 'idx_ii_invoice');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};

