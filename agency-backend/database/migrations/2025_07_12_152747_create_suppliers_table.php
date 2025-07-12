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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_id')->unique();
            $table->string('name');
            $table->string('company_name');
            $table->string('contact_person');
            $table->string('email')->unique();
            $table->string('phone');
            $table->string('alternate_phone')->nullable();
            $table->json('address');
            $table->string('gst_number')->unique();
            $table->string('pan_number')->unique();
            $table->string('bank_account_number');
            $table->string('bank_name');
            $table->string('ifsc_code');
            $table->string('account_holder_name');
            $table->enum('supplier_type', ['cylinder', 'gas', 'accessories', 'service', 'others']);
            $table->decimal('credit_limit', 12, 2)->default(0);
            $table->integer('credit_days')->default(30);
            $table->decimal('outstanding_balance', 12, 2)->default(0);
            $table->enum('status', ['active', 'inactive', 'blocked'])->default('active');
            $table->decimal('rating', 3, 2)->default(0);
            $table->json('product_categories')->nullable(); // Array of product categories
            $table->json('price_list')->nullable(); // Current price list
            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->json('contract_terms')->nullable();
            $table->string('license_number')->nullable();
            $table->date('license_expiry')->nullable();
            $table->json('certifications')->nullable(); // Array of certifications
            $table->integer('lead_time_days')->default(7);
            $table->decimal('minimum_order_value', 10, 2)->default(0);
            $table->json('delivery_areas')->nullable(); // Areas they deliver to
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('supplier_id');
            $table->index('name');
            $table->index('supplier_type');
            $table->index('status');
            $table->index('gst_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
