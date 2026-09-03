<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indeks untuk kolom foreign key.
 *
 * SQLite tidak membuat indeks otomatis untuk foreign key, jadi setiap relasi
 * yang dimuat — deliverable sebuah project, pembayaran sebuah invoice — memindai
 * seluruh tabel. Kolom status, tanggal, dan morph sudah diindeks sejak awal;
 * kolom penghubungnya justru terlewat.
 */
return new class extends Migration
{
    private const INDEXES = [
        'projects' => ['client_id', 'owner_id'],
        'quotations' => ['project_id'],
        'quotation_items' => ['quotation_id'],
        'invoices' => ['project_id', 'quotation_id'],
        'payments' => ['invoice_id'],
        'deliverables' => ['project_id', 'scene_id'],
        'capture_sessions' => ['project_id', 'crew_id', 'scene_id'],
        'processing_jobs' => ['project_id', 'capture_session_id'],
        'attachments' => ['project_id', 'uploaded_by'],
        'notes' => ['project_id'],
        'service_requests' => ['client_id'],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                foreach ($columns as $column) {
                    $blueprint->index($column);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns) {
                foreach ($columns as $column) {
                    $blueprint->dropIndex($table.'_'.$column.'_index');
                }
            });
        }
    }
};
