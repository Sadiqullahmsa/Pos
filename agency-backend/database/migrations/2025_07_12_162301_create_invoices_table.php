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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->string('invoice_type')->default('standard'); // standard, thermal, receipt, quote, etc.
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->string('currency_code', 3)->default('INR');
            $table->decimal('exchange_rate', 15, 8)->default(1.0);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('shipping_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('balance_amount', 15, 2)->default(0);
            $table->enum('payment_status', ['pending', 'partial', 'paid', 'overdue', 'cancelled'])->default('pending');
            $table->enum('invoice_status', ['draft', 'sent', 'viewed', 'paid', 'cancelled'])->default('draft');
            $table->date('invoice_date');
            $table->date('due_date');
            $table->json('billing_address');
            $table->json('shipping_address')->nullable();
            $table->json('line_items'); // Invoice line items
            $table->json('tax_breakdown')->nullable(); // Detailed tax breakdown
            $table->json('discount_breakdown')->nullable(); // Discount details
            $table->json('payment_terms')->nullable(); // Payment terms and conditions
            $table->text('notes')->nullable();
            $table->text('terms_and_conditions')->nullable();
            $table->text('footer_text')->nullable();
            $table->string('template_id')->nullable(); // Template used for invoice
            $table->json('print_settings')->nullable(); // Print configuration (A4, thermal, etc.)
            $table->json('pdf_settings')->nullable(); // PDF generation settings
            $table->json('thermal_settings')->nullable(); // Thermal printer settings
            $table->string('pdf_path')->nullable(); // Generated PDF file path
            $table->string('thermal_data')->nullable(); // Thermal printer data
            $table->boolean('is_recurring')->default(false);
            $table->json('recurring_config')->nullable(); // Recurring invoice configuration
            $table->foreignId('parent_invoice_id')->nullable()->constrained('invoices')->onDelete('set null');
            $table->integer('sequence_number')->default(1);
            $table->json('custom_fields')->nullable(); // Custom invoice fields
            $table->json('attachments')->nullable(); // Invoice attachments
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('email_log')->nullable(); // Email sending log
            $table->json('print_log')->nullable(); // Print history
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index('invoice_number');
            $table->index('customer_id');
            $table->index('invoice_status');
            $table->index('payment_status');
            $table->index('invoice_date');
            $table->index('due_date');
            $table->index('currency_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
