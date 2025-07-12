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
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('delivery_id')->unique();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('driver_id')->constrained('drivers')->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained('vehicles')->onDelete('cascade');
            $table->enum('status', ['assigned', 'picked_up', 'in_transit', 'delivered', 'failed', 'returned'])->default('assigned');
            $table->json('pickup_address');
            $table->json('delivery_address');
            $table->datetime('scheduled_time');
            $table->datetime('pickup_time')->nullable();
            $table->datetime('delivery_time')->nullable();
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->decimal('fuel_cost', 10, 2)->nullable();
            $table->decimal('delivery_charges', 10, 2)->default(0);
            $table->json('route_coordinates')->nullable(); // GPS coordinates
            $table->string('delivery_signature_path')->nullable();
            $table->string('delivery_photo_path')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->text('delivery_notes')->nullable();
            $table->text('failure_reason')->nullable();
            $table->integer('attempt_count')->default(1);
            $table->boolean('is_cod')->default(false); // Cash on delivery
            $table->decimal('cod_amount', 10, 2)->default(0);
            $table->boolean('cod_collected')->default(false);
            $table->json('gps_tracking')->nullable(); // Real-time tracking data
            $table->decimal('rating', 3, 2)->nullable(); // Customer rating
            $table->text('customer_feedback')->nullable();
            $table->timestamps();
            $table->index('delivery_id');
            $table->index('order_id');
            $table->index('driver_id');
            $table->index('vehicle_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
