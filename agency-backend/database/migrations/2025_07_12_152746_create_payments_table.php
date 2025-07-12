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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_id')->unique();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->enum('type', ['full', 'partial', 'refund', 'advance']);
            $table->enum('method', ['cash', 'card', 'upi', 'netbanking', 'wallet', 'cheque']);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded'])->default('pending');
            $table->string('transaction_id')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('gateway')->nullable(); // Payment gateway used
            $table->json('gateway_response')->nullable();
            $table->decimal('gateway_charges', 10, 2)->default(0);
            $table->decimal('net_amount', 10, 2);
            $table->datetime('payment_date');
            $table->datetime('settlement_date')->nullable();
            $table->string('cheque_number')->nullable();
            $table->string('cheque_bank')->nullable();
            $table->date('cheque_date')->nullable();
            $table->string('upi_transaction_id')->nullable();
            $table->string('card_last_four')->nullable();
            $table->string('card_type')->nullable(); // visa, mastercard, etc.
            $table->json('billing_address')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('collected_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('receipt_number')->nullable();
            $table->string('receipt_path')->nullable();
            $table->boolean('is_refunded')->default(false);
            $table->decimal('refund_amount', 10, 2)->default(0);
            $table->text('refund_reason')->nullable();
            $table->timestamps();
            $table->index('payment_id');
            $table->index('order_id');
            $table->index('customer_id');
            $table->index('status');
            $table->index('method');
            $table->index('transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
