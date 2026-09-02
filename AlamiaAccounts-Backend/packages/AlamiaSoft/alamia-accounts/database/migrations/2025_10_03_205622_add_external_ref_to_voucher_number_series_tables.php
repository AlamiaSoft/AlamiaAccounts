<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::table('voucher_numbers', function (Blueprint $t) {
            $t->string('external_source')->nullable()->after('voucher_no'); // e.g. 'NUSUK'
            $t->string('external_number')->nullable()->after('external_source');
            $t->unique(['external_source','external_number'], 'voucher_numbers_ext_unique');
        });
    }
    public function down() {
        Schema::table('voucher_numbers', function (Blueprint $t) {
            $t->dropUnique('voucher_numbers_ext_unique');
            $t->dropColumn(['external_source','external_number']);
        });
    }
};
