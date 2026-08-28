<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capture_sessions', function (Blueprint $table) {
            $table->decimal('raw_size_gb', 8, 2)->nullable()->after('shot_count');
            $table->unsignedInteger('frame_count')->nullable()->after('raw_size_gb');
            $table->string('backup_location')->nullable()->after('frame_count');
        });

        Schema::create('processing_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('capture_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind')->default('splat_training');
            $table->string('status')->default('queued');
            $table->string('machine')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->decimal('output_size_gb', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processing_jobs');

        Schema::table('capture_sessions', function (Blueprint $table) {
            $table->dropColumn(['raw_size_gb', 'frame_count', 'backup_location']);
        });
    }
};
