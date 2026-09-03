<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->resolveDuplicates();

        Schema::table('deliverables', function (Blueprint $table) {
            $table->unique(['project_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::table('deliverables', function (Blueprint $table) {
            $table->dropUnique(['project_id', 'version']);
        });
    }

    /**
     * Basis data yang sudah berjalan bisa memuat versi kembar, dan indeks unik
     * menolak dibuat di atasnya. Baris yang lebih baru dinaikkan ke versi bebas
     * berikutnya di project yang sama — termasuk yang terarsip, karena barisnya
     * tetap ada di tabel dan ikut terkena indeks.
     */
    private function resolveDuplicates(): void
    {
        $duplicates = DB::table('deliverables')
            ->select('project_id', 'version')
            ->groupBy('project_id', 'version')
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $rows = DB::table('deliverables')
                ->where('project_id', $duplicate->project_id)
                ->where('version', $duplicate->version)
                ->orderBy('id')
                ->pluck('id')
                ->skip(1);

            foreach ($rows as $id) {
                $next = (int) DB::table('deliverables')
                    ->where('project_id', $duplicate->project_id)
                    ->max('version') + 1;

                DB::table('deliverables')->where('id', $id)->update(['version' => $next]);
            }
        }
    }
};
