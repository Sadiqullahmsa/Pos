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
        Schema::create('connections', function (Blueprint $table) {
            $table->id();
            $table->string('connection_id')->unique();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->enum('connection_type', ['residential', 'commercial', 'industrial']);
            $table->enum('status', ['active', 'inactive', 'suspended', 'terminated'])->default('active');
            $table->date('registration_date');
            $table->date('activation_date')->nullable();
            $table->json('delivery_address');
            $table->string('subsidy_card_number')->nullable();
            $table->boolean('is_subsidized')->default(false);
            $table->decimal('security_deposit', 10, 2)->default(0);
            $table->decimal('monthly_quota', 8, 2)->default(14.2); // Default 14.2 kg
            $table->decimal('used_quota', 8, 2)->default(0);
            $table->date('quota_reset_date')->nullable();
            $table->string('cylinder_type')->default('domestic'); // domestic, commercial, industrial
            $table->string('agency_code')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('connection_id');
            $table->index('customer_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('connections');
    }
};
