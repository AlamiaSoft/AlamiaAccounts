<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('voucher_number_series', function (Blueprint $t) {
            $t->id();
            $t->string('voucher_type');             // e.g. PAYMENT, RECEIPT, SALES, PURCHASE, CONTRA, JOURNAL, FEE, BOOKING...
            $t->string('pattern');                  // e.g. "BK{YYYY}{MM}{DD}-{seq:2}"
            $t->string('reset_rule')->default('daily'); // daily|monthly|yearly|never
            $t->json('scope')->nullable();          // e.g. {"branch":"Makkah","domain":"MAIN"}
            $t->unsignedBigInteger('next_seq')->default(1);
            $t->timestamp('last_reset_at')->nullable();
            $t->timestamps();

            $t->index(['voucher_type']);
        });

        Schema::create('voucher_numbers', function (Blueprint $t) {
            $t->id();
            $t->uuid('entry_uuid')->unique();       // maps to Abivia entry uuid
            $t->string('voucher_type');
            $t->string('voucher_no')->unique();
            $t->json('context')->nullable();        // snapshot of pattern tokens used (date, branch, etc.)
            $t->timestamps();
        });
    }
    public function down() {
        Schema::dropIfExists('voucher_numbers');
        Schema::dropIfExists('voucher_number_series');
    }
};
