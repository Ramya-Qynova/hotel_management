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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 50)->unique()->comment('e.g. INV-2026-0001');
            $table->unsignedBigInteger('booking_id')->unique()->comment('One invoice per booking constraint');
            $table->unsignedBigInteger('guest_id');
            $table->date('issue_date');
            $table->date('due_date');
            $table->decimal('subtotal', 10, 2)->default(0.00);
            $table->decimal('tax_amount', 10, 2)->default(0.00);
            $table->decimal('discount_amount', 10, 2)->default(0.00);
            $table->decimal('total_amount', 10, 2)->default(0.00);
            $table->decimal('paid_amount', 10, 2)->default(0.00);
            $table->decimal('balance_due', 10, 2)->default(0.00);
            $table->enum('status', ['DRAFT', 'ISSUED', 'PAID', 'PARTIALLY_PAID', 'VOID'])->default('ISSUED');
            $table->string('pdf_path', 255)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            // Composite FK strictly guaranteeing invoice guest matches booking guest
            $table->foreign(['booking_id', 'guest_id'], 'fk_invoices_booking_guest')
                ->references(['id', 'guest_id'])
                ->on('bookings')
                ->restrictOnDelete();

            // Composite unique key to support composite FK from payments
            $table->unique(['id', 'booking_id'], 'uq_invoices_id_booking');
            $table->index('guest_id', 'idx_invoices_guest');
            $table->index('status', 'idx_invoices_status');
        });

        DB::statement('ALTER TABLE invoices ADD CONSTRAINT chk_invoices_total CHECK (total_amount >= 0.00)');
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT chk_invoices_paid CHECK (paid_amount >= 0.00)');
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT chk_invoices_dates CHECK (due_date >= issue_date)');

        // Attach deferred composite FK on payments table
        Schema::table('payments', function (Blueprint $table) {
            $table->foreign(['invoice_id', 'booking_id'], 'fk_payments_invoice_booking')
                ->references(['id', 'booking_id'])
                ->on('invoices')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign('fk_payments_invoice_booking');
        });

        Schema::dropIfExists('invoices');
    }
};

