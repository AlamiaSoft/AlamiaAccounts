<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_templates', function (Blueprint $table) {
            $table->id();
            $table->string('company_code', 32);
            $table->string('template_type', 50); // voucher, report, etc.
            $table->string('company_name')->nullable();
            $table->text('company_address')->nullable();
            $table->string('company_phone', 50)->nullable();
            $table->string('company_email')->nullable();
            $table->string('logo_url')->nullable();
            $table->text('footer_note')->nullable();
            $table->boolean('show_header')->default(true);
            $table->boolean('show_footer')->default(true);
            $table->text('custom_css')->nullable();
            $table->timestamps();
            
            $table->unique(['company_code', 'template_type']);
        });
        
        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('trigger_event'); // voucher_created, voucher_approved, etc.
            $table->json('conditions')->nullable();
            $table->json('actions'); // array of actions to perform
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_rules');
        Schema::dropIfExists('print_templates');
    }
};
