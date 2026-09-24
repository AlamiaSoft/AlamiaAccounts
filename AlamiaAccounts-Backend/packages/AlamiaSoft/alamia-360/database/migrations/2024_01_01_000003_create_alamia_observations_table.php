<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alamia_observations', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index();
            $table->string('source');
            $table->string('entity_type')->nullable()->index();
            $table->string('entity_id')->nullable()->index();
            $table->json('payload')->nullable();
            $table->timestamp('observed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alamia_observations');
    }
};
