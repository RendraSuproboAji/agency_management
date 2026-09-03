<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capture_sessions', function (Blueprint $table) {
            // Sesi yang raw-nya sudah dihapus keluar dari total penyimpanan,
            // tetapi tanggal pembersihannya tetap tercatat — kalau tidak,
            // "belum pernah punya raw" dan "sudah dibersihkan" jadi sama saja.
            $table->dateTime('raw_purged_at')->nullable()->after('backup_location');
        });

        Schema::table('clients', function (Blueprint $table) {
            // Klien yang menuntut arsip panjang bisa dikecualikan tanpa
            // mengubah aturan umum.
            $table->unsignedSmallInteger('raw_retention_days')->nullable()->after('portal_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('raw_retention_days');
        });

        Schema::table('capture_sessions', function (Blueprint $table) {
            $table->dropColumn('raw_purged_at');
        });
    }
};
