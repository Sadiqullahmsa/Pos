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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->unique();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('connection_id')->constrained('connections')->onDelete('cascade');
            $table->enum('type', ['regular', 'emergency', 'exchange', 'new_connection']);
            $table->enum('status', ['pending', 'confirmed', 'processing', 'dispatched', 'delivered', 'cancelled', 'returned'])->default('pending');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_amount', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('subsidy_amount', 10, 2)->default(0);
            $table->decimal('final_amount', 10, 2);
            $table->enum('payment_status', ['pending', 'paid', 'partial', 'refunded'])->default('pending');
            $table->enum('payment_method', ['cash', 'card', 'upi', 'online', 'wallet'])->nullable();
            $table->json('delivery_address');
            $table->string('delivery_slot')->nullable();
            $table->datetime('preferred_delivery_time')->nullable();
            $table->datetime('actual_delivery_time')->nullable();
            $table->text('special_instructions')->nullable();
            $table->boolean('is_urgent')->default(false);
            $table->decimal('urgency_charges', 10, 2)->default(0);
            $table->string('source')->default('phone'); // phone, online, app, walk-in
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('order_id');
            $table->index('customer_id');
            $table->index('connection_id');
            $table->index('status');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
