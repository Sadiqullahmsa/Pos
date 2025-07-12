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
        Schema::create('automations', function (Blueprint $table) {
            $table->id();
            $table->string('automation_id')->unique();
            $table->string('name');
            $table->string('type'); // email, sms, webhook, action, workflow, etc.
            $table->string('category'); // marketing, notification, system, business, etc.
            $table->text('description')->nullable();
            $table->json('trigger_events'); // Events that trigger automation
            $table->json('trigger_conditions'); // Conditions to check
            $table->json('filters')->nullable(); // Additional filters
            $table->json('actions'); // Actions to perform
            $table->json('schedule')->nullable(); // Schedule settings
            $table->boolean('is_active')->default(true);
            $table->boolean('is_recurring')->default(false);
            $table->json('recurring_config')->nullable(); // Recurring settings
            $table->integer('priority')->default(1);
            $table->integer('delay_minutes')->default(0); // Delay before execution
            $table->json('rate_limiting')->nullable(); // Rate limiting rules
            $table->json('target_audience')->nullable(); // Target audience filters
            $table->json('personalization')->nullable(); // Personalization rules
            $table->json('ab_testing')->nullable(); // A/B testing configuration
            $table->json('success_metrics')->nullable(); // Success measurement
            $table->json('failure_conditions')->nullable(); // Failure conditions
            $table->json('rollback_actions')->nullable(); // Rollback actions
            $table->json('monitoring_config')->nullable(); // Monitoring settings
            $table->json('reporting_config')->nullable(); // Reporting settings
            $table->json('compliance_rules')->nullable(); // Compliance settings
            $table->json('approval_workflow')->nullable(); // Approval requirements
            $table->json('testing_config')->nullable(); // Testing settings
            $table->boolean('dry_run_mode')->default(false);
            $table->json('dry_run_results')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->json('timezone_config')->nullable();
            $table->json('blackout_periods')->nullable(); // When not to run
            $table->json('dependencies')->nullable(); // Dependencies
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->integer('execution_count')->default(0);
            $table->integer('success_count')->default(0);
            $table->integer('failure_count')->default(0);
            $table->decimal('success_rate', 5, 2)->default(0);
            $table->integer('total_processing_time')->default(0);
            $table->timestamp('last_executed_at')->nullable();
            $table->timestamp('next_execution_at')->nullable();
            $table->json('execution_history')->nullable();
            $table->json('performance_metrics')->nullable();
            $table->json('analytics_data')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index('automation_id');
            $table->index('type');
            $table->index('category');
            $table->index('is_active');
            $table->index('next_execution_at');
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automations');
    }
};
