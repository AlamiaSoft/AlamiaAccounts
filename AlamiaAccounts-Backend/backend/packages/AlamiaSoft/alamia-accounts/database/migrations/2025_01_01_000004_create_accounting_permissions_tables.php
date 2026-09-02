<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role_id'); // References role in Laravel app
            $table->string('entity_type'); // voucher, account, report, etc.
            $table->string('action'); // create, view, edit, delete, approve
            $table->json('constraints')->nullable(); // Additional constraints like voucher_type, max_amount
            $table->timestamps();
            
            $table->index(['role_id', 'entity_type', 'action']);
        });
        
        Schema::create('field_level_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role_id');
            $table->string('voucher_type');
            $table->string('field_name');
            $table->boolean('visible')->default(true);
            $table->boolean('editable')->default(true);
            $table->timestamps();
            
            $table->unique(['role_id', 'voucher_type', 'field_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_level_permissions');
        Schema::dropIfExists('accounting_permissions');
    }
};
