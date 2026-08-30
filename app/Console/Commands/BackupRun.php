<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class BackupRun extends Command
{
    protected $signature = 'backup:run {--keep=14 : Jumlah backup terakhir yang disimpan}';

    protected $description = 'Snapshot database dan berkas unggahan ke storage/backups';

    public function handle(): int
    {
        $stamp = now()->format('Y-m-d_His');
        $target = storage_path('backups/'.$stamp);
        File::ensureDirectoryExists($target);

        $this->snapshotDatabase($target.'/database.sqlite');
        // Kedua disk harus ikut: lampiran (kontrak, denah) dan berkas
        // deliverable hidup di disk privat, bukan di public/storage.
        $this->copyUploads(storage_path('app/public'), $target.'/public');
        $this->copyUploads(storage_path('app/private'), $target.'/private');

        $this->pruneOld((int) $this->option('keep'));

        $this->info('Backup selesai: '.$target);

        return self::SUCCESS;
    }

    /**
     * VACUUM INTO membuat salinan yang konsisten selagi aplikasi berjalan.
     * Menyalin berkas SQLite mentah bisa menangkap transaksi setengah jadi.
     */
    private function snapshotDatabase(string $path): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->warn('Koneksi bukan SQLite; snapshot database dilewati.');

            return;
        }

        DB::statement('VACUUM INTO '.DB::getPdo()->quote($path));
    }

    private function copyUploads(string $source, string $path): void
    {
        if (File::isDirectory($source)) {
            File::copyDirectory($source, $path);
        }
    }

    private function pruneOld(int $keep): void
    {
        if ($keep < 1) {
            return;
        }

        $backups = collect(File::directories(storage_path('backups')))
            ->sortDesc()
            ->slice($keep);

        foreach ($backups as $old) {
            File::deleteDirectory($old);
            $this->line('Membuang backup lama: '.basename($old));
        }
    }
}
