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
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->string('complaint_id')->unique();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null');
            $table->foreignId('connection_id')->nullable()->constrained('connections')->onDelete('set null');
            $table->string('title');
            $table->text('description');
            $table->enum('category', ['delivery', 'product', 'service', 'billing', 'staff', 'safety', 'others']);
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('status', ['open', 'in_progress', 'resolved', 'closed', 'reopened'])->default('open');
            $table->string('source')->default('phone'); // phone, email, in_person, app, website
            $table->datetime('incident_date')->nullable();
            $table->json('affected_products')->nullable(); // Array of affected products/services
            $table->decimal('compensation_amount', 10, 2)->default(0);
            $table->enum('compensation_type', ['none', 'refund', 'replacement', 'credit', 'discount'])->default('none');
            $table->text('resolution_notes')->nullable();
            $table->datetime('resolution_date')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->json('attachments')->nullable(); // Array of file paths
            $table->decimal('customer_satisfaction', 3, 2)->nullable(); // Rating 1-5
            $table->text('customer_feedback')->nullable();
            $table->boolean('is_escalated')->default(false);
            $table->datetime('escalation_date')->nullable();
            $table->text('escalation_reason')->nullable();
            $table->json('followup_actions')->nullable(); // Array of follow-up actions
            $table->datetime('due_date')->nullable();
            $table->integer('response_time_hours')->nullable();
            $table->integer('resolution_time_hours')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamps();
            $table->index('complaint_id');
            $table->index('customer_id');
            $table->index('order_id');
            $table->index('category');
            $table->index('priority');
            $table->index('status');
            $table->index('assigned_to');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
