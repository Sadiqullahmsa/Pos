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
        Schema::create('emergencies', function (Blueprint $table) {
            $table->id();
            $table->string('emergency_id')->unique();
            $table->string('emergency_type'); // gas_leak, fire, accident, medical, safety, etc.
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('status', ['reported', 'acknowledged', 'in_progress', 'resolved', 'closed'])->default('reported');
            $table->string('title');
            $table->text('description');
            $table->foreignId('reported_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null');
            $table->json('location'); // GPS coordinates and address
            $table->json('contact_information'); // Emergency contact details
            $table->json('response_team')->nullable(); // Assigned response team
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reported_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('response_started_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->integer('response_time_minutes')->nullable(); // Response time in minutes
            $table->integer('resolution_time_minutes')->nullable(); // Resolution time in minutes
            $table->json('actions_taken')->nullable(); // Actions taken during emergency
            $table->json('resources_used')->nullable(); // Resources used for response
            $table->json('casualties')->nullable(); // Casualties if any
            $table->json('damage_assessment')->nullable(); // Damage assessment
            $table->json('evidence')->nullable(); // Photos, documents, etc.
            $table->json('witness_information')->nullable(); // Witness details
            $table->json('emergency_contacts_notified')->nullable(); // Notified contacts
            $table->json('authorities_notified')->nullable(); // Police, fire dept, etc.
            $table->json('insurance_information')->nullable(); // Insurance related data
            $table->json('follow_up_actions')->nullable(); // Follow-up actions required
            $table->text('lessons_learned')->nullable(); // Lessons learned
            $table->text('recommendations')->nullable(); // Recommendations for future
            $table->json('cost_analysis')->nullable(); // Cost associated with emergency
            $table->boolean('is_drill')->default(false); // Is this a drill/test?
            $table->json('drill_results')->nullable(); // Results if it's a drill
            $table->json('compliance_check')->nullable(); // Compliance verification
            $table->json('communication_log')->nullable(); // Communication history
            $table->json('escalation_history')->nullable(); // Escalation chain
            $table->json('media_coverage')->nullable(); // Media coverage if any
            $table->json('regulatory_reporting')->nullable(); // Regulatory reports
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index('emergency_id');
            $table->index('emergency_type');
            $table->index('severity');
            $table->index('status');
            $table->index('reported_at');
            $table->index('customer_id');
            $table->index('assigned_to');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emergencies');
    }
};
