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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('vehicle_id')->unique();
            $table->string('registration_number')->unique();
            $table->enum('type', ['two_wheeler', 'three_wheeler', 'four_wheeler', 'truck']);
            $table->string('make');
            $table->string('model');
            $table->integer('year');
            $table->string('color');
            $table->string('engine_number')->nullable();
            $table->string('chassis_number')->nullable();
            $table->integer('capacity'); // Number of cylinders it can carry
            $table->decimal('fuel_capacity', 8, 2)->nullable();
            $table->decimal('mileage', 8, 2)->nullable(); // km per liter
            $table->enum('fuel_type', ['petrol', 'diesel', 'cng', 'electric']);
            $table->enum('status', ['active', 'inactive', 'maintenance', 'repair', 'retired'])->default('active');
            $table->boolean('is_available')->default(true);
            $table->date('purchase_date');
            $table->decimal('purchase_price', 12, 2)->nullable();
            $table->date('insurance_expiry');
            $table->string('insurance_policy_number')->nullable();
            $table->string('insurance_company')->nullable();
            $table->date('fitness_certificate_expiry');
            $table->date('pollution_certificate_expiry');
            $table->string('permit_number')->nullable();
            $table->date('permit_expiry')->nullable();
            $table->foreignId('assigned_driver_id')->nullable()->constrained('drivers')->onDelete('set null');
            $table->string('current_location')->nullable();
            $table->integer('odometer_reading')->default(0);
            $table->date('last_service_date')->nullable();
            $table->date('next_service_date')->nullable();
            $table->integer('service_interval_km')->default(5000);
            $table->json('maintenance_history')->nullable();
            $table->decimal('maintenance_cost', 10, 2)->default(0);
            $table->json('documents')->nullable(); // Store document paths
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('vehicle_id');
            $table->index('registration_number');
            $table->index('type');
            $table->index('status');
            $table->index('is_available');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
