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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('category'); // system, api, payment, notification, security, etc.
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('value'); // Flexible value storage
            $table->string('type'); // text, number, boolean, json, password, file, select, etc.
            $table->json('options')->nullable(); // For select types or validation options
            $table->json('validation_rules')->nullable(); // Validation rules
            $table->boolean('is_encrypted')->default(false);
            $table->boolean('is_public')->default(false); // Can be accessed by frontend
            $table->boolean('requires_restart')->default(false); // Requires system restart
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable(); // Additional metadata
            $table->timestamps();
            $table->index(['category', 'key']);
            $table->index('category');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
