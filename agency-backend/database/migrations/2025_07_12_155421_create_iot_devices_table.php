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
        Schema::create('iot_devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_id')->unique();
            $table->string('device_type'); // cylinder_sensor, gps_tracker, pressure_monitor, temperature_sensor
            $table->string('manufacturer');
            $table->string('model');
            $table->string('firmware_version')->nullable();
            $table->enum('status', ['active', 'inactive', 'maintenance', 'error'])->default('active');
            $table->foreignId('cylinder_id')->nullable()->constrained('cylinders')->onDelete('set null');
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->onDelete('set null');
            $table->json('configuration'); // Device-specific configuration
            $table->json('location')->nullable(); // Current GPS coordinates
            $table->decimal('battery_level', 5, 2)->nullable();
            $table->timestamp('last_reading_at')->nullable();
            $table->json('sensor_data')->nullable(); // Real-time sensor readings
            $table->json('alert_thresholds'); // Configurable alert thresholds
            $table->boolean('is_online')->default(false);
            $table->string('network_type')->nullable(); // wifi, cellular, bluetooth, lora
            $table->string('signal_strength')->nullable();
            $table->json('maintenance_schedule')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('last_maintenance_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('device_id');
            $table->index('device_type');
            $table->index('status');
            $table->index('cylinder_id');
            $table->index('vehicle_id');
            $table->index('is_online');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('iot_devices');
    }
};
