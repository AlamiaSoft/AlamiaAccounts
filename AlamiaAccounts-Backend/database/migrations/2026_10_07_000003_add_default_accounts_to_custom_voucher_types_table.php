<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_voucher_types', function (Blueprint $table) {
            if (!Schema::hasColumn('custom_voucher_types', 'default_debit_account')) {
                $table->string('default_debit_account', 50)->nullable()->after('description');
            }
            if (!Schema::hasColumn('custom_voucher_types', 'default_credit_account')) {
                $table->string('default_credit_account', 50)->nullable()->after('default_debit_account');
            }
        });
    }

    public function down(): void
    {
        Schema::table('custom_voucher_types', function (Blueprint $table) {
            if (Schema::hasColumn('custom_voucher_types', 'default_credit_account')) {
                $table->dropColumn('default_credit_account');
            }
            if (Schema::hasColumn('custom_voucher_types', 'default_debit_account')) {
                $table->dropColumn('default_debit_account');
            }
        });
    }
};
