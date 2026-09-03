<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

/**
 * Berkas deliverable pindah dari disk publik ke disk privat.
 *
 * Selama ini splat/mesh — produk yang dibayar klien — dilayani lewat
 * public/storage, jadi siapa pun yang pernah memegang URL-nya bisa terus
 * mengunduh, bahkan setelah project diarsipkan. Lampiran sudah dipindah lebih
 * dulu; ini menyusul, memakai rute unduh ber-autentikasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->move(Storage::disk('public'), Storage::disk('local'));
    }

    public function down(): void
    {
        $this->move(Storage::disk('local'), Storage::disk('public'));
    }

    private function move(mixed $from, mixed $to): void
    {
        foreach ($from->allFiles('deliverables') as $path) {
            // Salin dulu, hapus setelah berhasil: kalau disk tujuan penuh,
            // berkasnya masih utuh di tempat asal.
            if ($to->put($path, $from->get($path))) {
                $from->delete($path);
            }
        }
    }
};
