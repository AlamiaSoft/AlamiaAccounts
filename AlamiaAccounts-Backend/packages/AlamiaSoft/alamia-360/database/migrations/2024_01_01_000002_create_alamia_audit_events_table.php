<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alamia_audit_events', function (Blueprint $table) {
            $table->id();
            $table->string('capability')->index();
            $table->string('side_effect');
            $table->string('actor_id');
            $table->string('actor_type');
            $table->unsignedBigInteger('situation_id')->nullable();
            $table->json('input')->nullable();
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alamia_audit_events');
    }
};
