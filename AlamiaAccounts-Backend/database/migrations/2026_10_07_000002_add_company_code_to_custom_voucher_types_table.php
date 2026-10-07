<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_voucher_types', function (Blueprint $table) {
            if (!Schema::hasColumn('custom_voucher_types', 'company_code')) {
                $table->string('company_code', 50)->nullable()->after('prefix');
            }
        });
    }

    public function down(): void
    {
        Schema::table('custom_voucher_types', function (Blueprint $table) {
            if (Schema::hasColumn('custom_voucher_types', 'company_code')) {
                $table->dropColumn('company_code');
            }
        });
    }
};
