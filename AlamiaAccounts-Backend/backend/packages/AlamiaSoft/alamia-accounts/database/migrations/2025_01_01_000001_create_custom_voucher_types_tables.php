<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_voucher_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('prefix', 10);
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            
            $table->unique('prefix');
        });
        
        Schema::create('custom_voucher_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_type_id')->constrained('custom_voucher_types')->onDelete('cascade');
            $table->string('name');
            $table->string('type'); // text, number, date, dropdown, etc.
            $table->boolean('required')->default(false);
            $table->json('options')->nullable(); // for dropdown/multiselect
            $table->timestamps();
        });
        
        Schema::create('voucher_account_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_type_id')->constrained('custom_voucher_types')->onDelete('cascade');
            $table->enum('side', ['debit', 'credit']);
            $table->json('account_groups'); // array of allowed account groups
            $table->timestamps();
        });
        
        Schema::create('voucher_validation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_type_id')->constrained('custom_voucher_types')->onDelete('cascade');
            $table->string('field_name');
            $table->string('type'); // required, min_value, max_value, regex, etc.
            $table->string('value')->nullable();
            $table->string('message')->nullable();
            $table->timestamps();
        });
        
        Schema::create('voucher_calculation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_type_id')->constrained('custom_voucher_types')->onDelete('cascade');
            $table->string('target_field');
            $table->text('formula');
            $table->text('description')->nullable();
            $table->timestamps();
        });
        
        Schema::create('voucher_default_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_type_id')->constrained('custom_voucher_types')->onDelete('cascade');
            $table->string('field_name');
            $table->string('condition')->nullable();
            $table->string('default_value');
            $table->timestamps();
        });
        
        Schema::create('voucher_approval_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_type_id')->constrained('custom_voucher_types')->onDelete('cascade');
            $table->string('condition');
            $table->string('approver_role');
            $table->decimal('min_amount', 15, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_approval_rules');
        Schema::dropIfExists('voucher_default_rules');
        Schema::dropIfExists('voucher_calculation_rules');
        Schema::dropIfExists('voucher_validation_rules');
        Schema::dropIfExists('voucher_account_rules');
        Schema::dropIfExists('custom_voucher_fields');
        Schema::dropIfExists('custom_voucher_types');
    }
};
