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
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique(); // ISO 4217 currency code (USD, EUR, INR, etc.)
            $table->string('name');
            $table->string('symbol');
            $table->string('symbol_position')->default('before'); // before, after
            $table->integer('decimal_places')->default(2);
            $table->string('decimal_separator')->default('.');
            $table->string('thousands_separator')->default(',');
            $table->decimal('exchange_rate', 15, 8)->default(1.0); // Rate against base currency
            $table->boolean('is_base_currency')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_update_rate')->default(true);
            $table->string('exchange_rate_source')->nullable(); // API source for rates
            $table->timestamp('last_rate_update')->nullable();
            $table->json('formatting_rules')->nullable(); // Custom formatting rules
            $table->json('rounding_rules')->nullable(); // Rounding configuration
            $table->json('payment_gateway_mapping')->nullable(); // Payment gateway currency mapping
            $table->integer('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index('code');
            $table->index('is_active');
            $table->index('is_base_currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
