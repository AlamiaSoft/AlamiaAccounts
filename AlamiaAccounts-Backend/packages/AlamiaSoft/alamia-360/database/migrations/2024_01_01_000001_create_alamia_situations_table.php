<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alamia_situations', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index();
            $table->string('summary');
            $table->string('priority')->default('normal');
            $table->string('status')->default('open')->index();
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->json('context')->nullable();
            $table->string('recommended_action')->nullable();
            $table->string('responsible_actor_id')->nullable();
            $table->json('resolution')->nullable();
            $table->timestamp('detected_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alamia_situations');
    }
};
