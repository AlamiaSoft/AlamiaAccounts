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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'company_code')) {
                $table->string('company_code', 50)->nullable()->after('role')->index();
            }
            if (!Schema::hasColumn('users', 'allowed_companies')) {
                $table->json('allowed_companies')->nullable()->after('company_code');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'allowed_companies')) {
                $table->dropColumn('allowed_companies');
            }
            if (Schema::hasColumn('users', 'company_code')) {
                $table->dropColumn('company_code');
            }
        });
    }
};
