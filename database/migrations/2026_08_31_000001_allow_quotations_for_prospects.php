<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penawaran bisa ditujukan ke calon klien.
 *
 * Sebelumnya project_id wajib, dan project wajib punya klien — jadi menawar ke
 * pihak yang baru mengirim permintaan memaksa membuat data klien dan project
 * lebih dulu, mengotori daftar klien dengan calon yang mungkin tidak pernah
 * jadi. Permintaan masuk sudah menyimpan semua yang dibutuhkan sebuah
 * penawaran: nama, perusahaan, email, telepon, layanan, lokasi, luas area.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->foreignId('service_request_id')->nullable()->after('project_id')
                ->constrained()->nullOnDelete();
            $table->index('service_request_id');
        });

        // SQLite tidak bisa melonggarkan NOT NULL lewat ALTER; kolomnya dibangun
        // ulang oleh doctrine-style change() yang sudah didukung Laravel 11+.
        Schema::table('quotations', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropForeign(['service_request_id']);
            $table->dropColumn('service_request_id');
        });
    }
};
