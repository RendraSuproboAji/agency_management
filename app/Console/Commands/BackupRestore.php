<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class BackupRestore extends Command
{
    protected $signature = 'backup:restore {stamp : Nama folder backup, mis. 2026-08-29_020000} {--force}';

    protected $description = 'Kembalikan database dan berkas unggahan dari satu backup';

    public function handle(): int
    {
        $source = storage_path('backups/'.$this->argument('stamp'));

        if (! File::isDirectory($source)) {
            $this->error('Backup tidak ditemukan: '.$source);

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Menimpa database dan berkas saat ini. Lanjutkan?')) {
            return self::FAILURE;
        }

        $database = config('database.connections.sqlite.database');

        if (File::exists($source.'/database.sqlite')) {
            DB::disconnect();
            File::copy($source.'/database.sqlite', $database);
        }

        // Arsip lama hanya punya /public; bagian yang tidak ada dilewati
        // supaya backup sebelum perubahan ini tetap bisa dipulihkan.
        foreach (['public', 'private'] as $disk) {
            if (File::isDirectory($source.'/'.$disk)) {
                File::copyDirectory($source.'/'.$disk, storage_path('app/'.$disk));
            }
        }

        $this->info('Backup '.$this->argument('stamp').' dipulihkan.');

        return self::SUCCESS;
    }
}
