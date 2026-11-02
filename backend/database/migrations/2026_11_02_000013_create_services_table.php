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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique()->comment('e.g. Laundry, Breakfast Buffet, Extra Bed');
            $table->enum('category', ['FOOD', 'LAUNDRY', 'EXTRA_BED', 'OTHER'])->default('OTHER');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('tax_rate_percent', 5, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE services ADD CONSTRAINT chk_services_price CHECK (unit_price >= 0.00)');
        DB::statement('ALTER TABLE services ADD CONSTRAINT chk_services_tax CHECK (tax_rate_percent >= 0.00)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};

