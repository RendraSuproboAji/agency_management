<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('brief')->nullable();
            $table->string('service_type')->default('gaussian_splatting');
            $table->string('status')->default('lead');
            $table->decimal('budget', 14, 2)->nullable();
            $table->date('deadline')->nullable();
            $table->string('site_location')->nullable();
            $table->unsignedInteger('area_sqm')->nullable();
            $table->string('gallery_url')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('deadline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
