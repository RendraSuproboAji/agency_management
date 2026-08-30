<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40);
            $table->morphs('remindable');
            $table->date('sent_on');
            $table->timestamps();

            // Kunci idempotensi: menjalankan ulang perintahnya pada hari yang
            // sama tidak boleh mengirim email kedua.
            $table->unique(['type', 'remindable_type', 'remindable_id', 'sent_on'], 'reminder_logs_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_logs');
    }
};
