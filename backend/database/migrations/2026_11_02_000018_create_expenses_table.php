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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_category_id')->constrained('expense_categories')->restrictOnDelete();
            $table->string('title', 150);
            $table->decimal('amount', 10, 2);
            $table->enum('payment_method', ['CASH', 'UPI', 'CARD', 'BANK_TRANSFER'])->default('CASH');
            $table->date('expense_date');
            $table->string('paid_to', 150);
            $table->string('reference_no', 100)->nullable();
            $table->string('receipt_image_path', 255)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['expense_category_id', 'expense_date'], 'idx_expenses_cat_date');
            $table->index('expense_date', 'idx_expenses_date');
        });

        DB::statement('ALTER TABLE expenses ADD CONSTRAINT chk_expenses_amount CHECK (amount > 0.00)');

        // Attach deferred foreign key on cash_register_transactions
        Schema::table('cash_register_transactions', function (Blueprint $table) {
            $table->foreign('expense_id', 'fk_crt_expense_id')
                ->references('id')
                ->on('expenses')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_register_transactions', function (Blueprint $table) {
            $table->dropForeign('fk_crt_expense_id');
        });

        Schema::dropIfExists('expenses');
    }
};

