<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('category')->default('camera');
            $table->string('serial_number')->nullable();
            $table->string('status')->default('available');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('category');
            $table->index('status');
        });

        Schema::create('capture_session_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('capture_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['capture_session_id', 'equipment_id']);
        });

        // Kolom teks lama jadi catatan bebas; nama "equipment" kini dipakai
        // relasi ke inventaris, dan atribut kolom akan menutupinya.
        Schema::table('capture_sessions', function (Blueprint $table) {
            $table->renameColumn('equipment', 'equipment_note');
        });
    }

    public function down(): void
    {
        Schema::table('capture_sessions', function (Blueprint $table) {
            $table->renameColumn('equipment_note', 'equipment');
        });

        Schema::dropIfExists('capture_session_equipment');
        Schema::dropIfExists('equipment');
    }
};
