<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            // Statusnya saja tidak cukup: "disetujui" tanpa siapa dan kapan
            // bukan bukti apa-apa saat kemudian dipertanyakan.
            $table->dateTime('accepted_at')->nullable()->after('status');
            $table->string('accepted_by')->nullable()->after('accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn(['accepted_at', 'accepted_by']);
        });
    }
};
