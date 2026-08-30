<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sesi dan job ikut diarsipkan bersama project-nya.
 *
 * Tanpa kolom ini keduanya tetap "hidup" setelah project diarsipkan, sedangkan
 * relasi ->project mereka menjadi null karena Project memakai SoftDeletes —
 * cukup untuk menjatuhkan agenda sesi, dashboard, dan perintah pengingat.
 */
return new class extends Migration
{
    private const TABLES = ['capture_sessions', 'processing_jobs'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->softDeletes());
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropSoftDeletes());
        }
    }
};
