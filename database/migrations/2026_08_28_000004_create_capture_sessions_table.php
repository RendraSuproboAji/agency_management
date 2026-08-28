<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capture_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crew_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('scheduled_at');
            $table->dateTime('completed_at')->nullable();
            $table->string('location')->nullable();
            $table->text('equipment')->nullable();
            $table->unsignedInteger('shot_count')->nullable();
            $table->string('weather_note')->nullable();
            $table->string('status')->default('scheduled');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('scheduled_at');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capture_sessions');
    }
};
