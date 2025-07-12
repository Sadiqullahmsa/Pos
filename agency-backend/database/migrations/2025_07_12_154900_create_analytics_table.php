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
        Schema::create('analytics', function (Blueprint $table) {
            $table->id();
            $table->string('metric_type'); // sales, inventory, customer_behavior, delivery_performance, etc.
            $table->string('metric_name');
            $table->string('period_type'); // hourly, daily, weekly, monthly, yearly
            $table->date('period_date');
            $table->json('dimensions'); // Breakdown dimensions (location, product_type, customer_segment, etc.)
            $table->decimal('value', 15, 4);
            $table->string('unit')->nullable();
            $table->json('metadata')->nullable(); // Additional context data
            $table->json('predictions')->nullable(); // AI-generated predictions
            $table->decimal('confidence_score', 5, 4)->nullable(); // Prediction confidence (0-1)
            $table->json('anomaly_detection')->nullable(); // Anomaly detection results
            $table->json('trends')->nullable(); // Trend analysis data
            $table->string('data_source')->nullable(); // Source of the data
            $table->boolean('is_prediction')->default(false);
            $table->boolean('is_anomaly')->default(false);
            $table->timestamp('calculated_at');
            $table->timestamps();
            $table->index(['metric_type', 'period_date']);
            $table->index(['metric_name', 'period_type']);
            $table->index('period_date');
            $table->index('is_prediction');
            $table->index('is_anomaly');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics');
    }
};
