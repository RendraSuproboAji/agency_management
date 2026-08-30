<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_scenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->unsignedInteger('position')->default(0);
            $table->string('gallery_url')->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Slug cukup unik di dalam satu project: dua project boleh
            // sama-sama punya scene "lantai-1".
            $table->unique(['project_id', 'slug']);
            $table->index('position');
        });

        Schema::table('deliverables', function (Blueprint $table) {
            $table->foreignId('scene_id')->nullable()->after('project_id')
                ->constrained('project_scenes')->nullOnDelete();
        });

        Schema::table('capture_sessions', function (Blueprint $table) {
            $table->foreignId('scene_id')->nullable()->after('project_id')
                ->constrained('project_scenes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('capture_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scene_id');
        });

        Schema::table('deliverables', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scene_id');
        });

        Schema::dropIfExists('project_scenes');
    }
};
