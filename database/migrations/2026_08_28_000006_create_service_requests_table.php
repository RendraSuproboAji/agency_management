<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('service_type')->default('gaussian_splatting');
            $table->string('site_location')->nullable();
            $table->unsignedInteger('area_sqm')->nullable();
            $table->text('message')->nullable();
            $table->string('status')->default('new');
            $table->foreignId('converted_project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
