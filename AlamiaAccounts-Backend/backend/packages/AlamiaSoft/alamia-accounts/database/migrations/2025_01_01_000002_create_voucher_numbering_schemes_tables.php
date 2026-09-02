<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_numbering_schemes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_type_id')->constrained('custom_voucher_types')->onDelete('cascade');
            $table->string('prefix', 10);
            $table->integer('starting_number')->default(1);
            $table->integer('padding')->default(4);
            $table->string('separator', 10)->default('-');
            $table->string('custom_separator', 10)->nullable();
            $table->boolean('include_year')->default(false);
            $table->boolean('include_month')->default(false);
            $table->enum('reset_period', ['never', 'yearly', 'monthly', 'quarterly'])->default('never');
            $table->timestamps();
            
            $table->unique('voucher_type_id');
        });
        
        Schema::create('voucher_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_type_id')->constrained('custom_voucher_types')->onDelete('cascade');
            $table->string('period', 20); // e.g., '2025', '2025-01', '2025-Q1', 'default'
            $table->integer('current_number')->default(1);
            $table->timestamps();
            
            $table->unique(['voucher_type_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_number_sequences');
        Schema::dropIfExists('voucher_numbering_schemes');
    }
};
