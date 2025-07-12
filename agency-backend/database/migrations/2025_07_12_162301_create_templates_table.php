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
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('template_id')->unique();
            $table->string('name');
            $table->string('type'); // invoice, email, sms, whatsapp, pdf, thermal, etc.
            $table->string('category'); // billing, notification, marketing, system, etc.
            $table->text('description')->nullable();
            $table->longText('content'); // Template content/HTML
            $table->json('variables')->nullable(); // Available variables
            $table->json('styles')->nullable(); // CSS styles for the template
            $table->json('settings')->nullable(); // Template-specific settings
            $table->string('layout')->nullable(); // A4, thermal, email, etc.
            $table->json('dimensions')->nullable(); // Width, height, margins
            $table->json('fonts')->nullable(); // Font configurations
            $table->json('colors')->nullable(); // Color scheme
            $table->json('branding')->nullable(); // Logo, company info
            $table->json('headers')->nullable(); // Header configuration
            $table->json('footers')->nullable(); // Footer configuration
            $table->json('sections')->nullable(); // Template sections
            $table->string('preview_image')->nullable(); // Template preview
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false); // System template
            $table->boolean('is_customizable')->default(true);
            $table->json('permissions')->nullable(); // Who can use/edit
            $table->json('conditions')->nullable(); // Conditional logic
            $table->json('validation_rules')->nullable(); // Validation rules
            $table->json('multi_language')->nullable(); // Multi-language support
            $table->string('version')->default('1.0');
            $table->json('changelog')->nullable(); // Version history
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->integer('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->json('analytics')->nullable(); // Usage analytics
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index('template_id');
            $table->index('type');
            $table->index('category');
            $table->index('is_active');
            $table->index('is_default');
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
