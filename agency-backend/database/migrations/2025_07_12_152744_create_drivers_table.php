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
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('driver_id')->unique();
            $table->string('name');
            $table->string('phone')->unique();
            $table->string('alternate_phone')->nullable();
            $table->string('email')->unique()->nullable();
            $table->json('address');
            $table->string('license_number')->unique();
            $table->enum('license_type', ['light', 'heavy', 'commercial']);
            $table->date('license_expiry');
            $table->string('license_document_path')->nullable();
            $table->string('aadhar_number')->unique();
            $table->string('aadhar_document_path')->nullable();
            $table->string('pan_number')->unique()->nullable();
            $table->string('pan_document_path')->nullable();
            $table->string('photo_path')->nullable();
            $table->date('date_of_birth');
            $table->date('joining_date');
            $table->enum('employment_type', ['full_time', 'part_time', 'contract']);
            $table->decimal('salary', 10, 2)->nullable();
            $table->decimal('commission_rate', 5, 2)->default(0); // Percentage
            $table->boolean('is_active')->default(true);
            $table->boolean('is_available')->default(true);
            $table->string('current_location')->nullable();
            $table->json('emergency_contact');
            $table->decimal('rating', 3, 2)->default(0);
            $table->integer('total_deliveries')->default(0);
            $table->integer('successful_deliveries')->default(0);
            $table->json('working_hours')->nullable(); // JSON for flexible hours
            $table->json('skills')->nullable(); // JSON array of skills
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('driver_id');
            $table->index('phone');
            $table->index('license_number');
            $table->index('is_active');
            $table->index('is_available');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
