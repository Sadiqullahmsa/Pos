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
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->string('workflow_id')->unique();
            $table->string('name');
            $table->string('category'); // order, delivery, payment, customer, emergency, etc.
            $table->text('description')->nullable();
            $table->json('trigger_conditions'); // When workflow should be triggered
            $table->string('trigger_type'); // event, schedule, manual, api, etc.
            $table->json('steps'); // Workflow steps/actions
            $table->json('conditions'); // Conditional logic
            $table->json('variables')->nullable(); // Workflow variables
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->integer('priority')->default(1); // 1=low, 5=high
            $table->integer('timeout_minutes')->default(60);
            $table->json('retry_policy')->nullable(); // Retry configuration
            $table->json('error_handling')->nullable(); // Error handling rules
            $table->json('notification_settings')->nullable(); // Notification preferences
            $table->json('approval_settings')->nullable(); // Approval requirements
            $table->json('parallel_execution')->nullable(); // Parallel processing config
            $table->json('schedule_config')->nullable(); // Schedule settings
            $table->json('dependencies')->nullable(); // Workflow dependencies
            $table->json('success_actions')->nullable(); // Actions on success
            $table->json('failure_actions')->nullable(); // Actions on failure
            $table->json('monitoring_config')->nullable(); // Monitoring settings
            $table->json('analytics_config')->nullable(); // Analytics settings
            $table->json('permissions')->nullable(); // Who can execute/modify
            $table->string('version')->default('1.0');
            $table->json('changelog')->nullable(); // Version history
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->integer('execution_count')->default(0);
            $table->integer('success_count')->default(0);
            $table->integer('failure_count')->default(0);
            $table->decimal('success_rate', 5, 2)->default(0);
            $table->integer('average_execution_time')->default(0); // In seconds
            $table->timestamp('last_executed_at')->nullable();
            $table->json('performance_metrics')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index('workflow_id');
            $table->index('category');
            $table->index('trigger_type');
            $table->index('is_active');
            $table->index('priority');
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflows');
    }
};
