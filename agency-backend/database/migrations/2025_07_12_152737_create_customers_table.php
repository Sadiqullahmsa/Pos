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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_id')->unique();
            $table->string('name');
            $table->string('email')->unique()->nullable();
            $table->string('phone');
            $table->string('alternate_phone')->nullable();
            $table->json('address');
            $table->string('id_type'); // aadhar, pan, driving_license, passport, etc.
            $table->string('id_number')->unique();
            $table->string('id_document_path')->nullable();
            $table->enum('kyc_status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->text('kyc_notes')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('occupation')->nullable();
            $table->decimal('monthly_income', 10, 2)->nullable();
            $table->string('photo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('credit_limit', 10, 2)->default(0);
            $table->decimal('outstanding_balance', 10, 2)->default(0);
            $table->string('preferred_delivery_time')->nullable();
            $table->json('preferences')->nullable(); // JSON for additional preferences
            $table->timestamp('last_order_date')->nullable();
            $table->timestamps();
            $table->index('customer_id');
            $table->index('phone');
            $table->index('kyc_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
