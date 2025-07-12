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
        Schema::create('external_apis', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('provider'); // razorpay, paytm, twilio, sendgrid, etc.
            $table->string('category'); // payment, sms, email, maps, weather, etc.
            $table->string('type'); // rest, soap, graphql, webhook
            $table->string('base_url');
            $table->string('version')->nullable();
            $table->json('endpoints'); // Available endpoints
            $table->json('authentication'); // API keys, tokens, etc.
            $table->json('headers')->nullable(); // Default headers
            $table->json('parameters')->nullable(); // Default parameters
            $table->integer('timeout')->default(30); // Request timeout in seconds
            $table->integer('retry_attempts')->default(3);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_sandbox')->default(false);
            $table->json('rate_limits')->nullable(); // Rate limiting configuration
            $table->json('webhook_config')->nullable(); // Webhook configurations
            $table->json('error_handling')->nullable(); // Error handling rules
            $table->json('response_mapping')->nullable(); // Response field mapping
            $table->json('request_log_config')->nullable(); // Logging configuration
            $table->boolean('auto_retry')->default(true);
            $table->json('health_check')->nullable(); // Health check configuration
            $table->timestamp('last_health_check')->nullable();
            $table->enum('status', ['active', 'inactive', 'error', 'maintenance'])->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['provider', 'category']);
            $table->index('category');
            $table->index('is_active');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('external_apis');
    }
};
