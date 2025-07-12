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
        Schema::create('cylinders', function (Blueprint $table) {
            $table->id();
            $table->string('cylinder_id')->unique();
            $table->string('serial_number')->unique();
            $table->enum('type', ['domestic', 'commercial', 'industrial']);
            $table->decimal('capacity', 8, 2); // in kg
            $table->enum('status', ['available', 'dispatched', 'delivered', 'empty', 'maintenance', 'damaged'])->default('available');
            $table->decimal('tare_weight', 8, 2); // Empty weight
            $table->decimal('current_weight', 8, 2)->nullable();
            $table->date('manufacturing_date');
            $table->date('last_refill_date')->nullable();
            $table->date('next_due_date')->nullable();
            $table->integer('refill_count')->default(0);
            $table->string('manufacturer')->nullable();
            $table->string('brand')->nullable();
            $table->string('color')->nullable();
            $table->foreignId('current_customer_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->foreignId('current_connection_id')->nullable()->constrained('connections')->onDelete('set null');
            $table->string('location')->nullable(); // Current location
            $table->json('maintenance_history')->nullable();
            $table->decimal('purchase_price', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('cylinder_id');
            $table->index('serial_number');
            $table->index('status');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cylinders');
    }
};
