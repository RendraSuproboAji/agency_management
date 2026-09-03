<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;

return new class extends Migration
{
    /**
     * Lampiran dulu disimpan di disk publik, sehingga kontrak dan foto survei
     * bisa diambil siapa pun lewat /storage/... Memindahkan disknya saja tidak
     * cukup — berkas yang sudah telanjur ada harus ikut pindah, kalau tidak
     * tombol unduhnya jadi 404.
     */
    public function up(): void
    {
        $this->move(storage_path('app/public/attachments'), storage_path('app/private/attachments'));
    }

    public function down(): void
    {
        $this->move(storage_path('app/private/attachments'), storage_path('app/public/attachments'));
    }

    private function move(string $from, string $to): void
    {
        if (! File::isDirectory($from)) {
            return;
        }

        File::ensureDirectoryExists($to);
        File::copyDirectory($from, $to);
        File::deleteDirectory($from);
    }
};
