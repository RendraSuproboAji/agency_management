<?php

namespace Tests\Feature;

use App\Models\Client;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Sengaja tanpa RefreshDatabase: trait itu membungkus tiap tes dalam
 * transaksi, sedangkan VACUUM INTO — inti dari snapshot yang konsisten —
 * tidak bisa berjalan di dalam transaksi. Jadi tes ini memakai berkas
 * SQLite sungguhan yang dibuat dan dibuang sendiri.
 */
class BackupTest extends TestCase
{
    private string $database;

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = storage_path('framework/testing/backup-'.uniqid().'.sqlite');
        File::ensureDirectoryExists(dirname($this->database));
        File::put($this->database, '');
        File::deleteDirectory(storage_path('backups'));

        config(['database.connections.sqlite.database' => $this->database]);
        $this->app['db']->purge('sqlite');

        $this->artisan('migrate', ['--force' => true])->run();
    }

    protected function tearDown(): void
    {
        $this->app['db']->purge('sqlite');
        File::delete($this->database);
        File::deleteDirectory(storage_path('backups'));

        parent::tearDown();
    }

    private function latestBackup(): string
    {
        $folder = collect(File::directories(storage_path('backups')))->sort()->last();
        $this->assertNotNull($folder, 'folder backup harus terbentuk');

        return $folder;
    }

    public function test_a_backup_captures_the_current_data(): void
    {
        Client::factory()->create(['name' => 'Museum Kota Lama']);

        $this->artisan('backup:run')->assertSuccessful();

        $snapshot = new \PDO('sqlite:'.$this->latestBackup().'/database.sqlite');

        $this->assertSame(
            'Museum Kota Lama',
            $snapshot->query('select name from clients limit 1')->fetchColumn(),
        );
    }

    public function test_uploaded_files_are_copied_too(): void
    {
        File::ensureDirectoryExists(storage_path('app/public/deliverables'));
        File::put(storage_path('app/public/deliverables/scene.ply'), 'x');

        $this->artisan('backup:run')->assertSuccessful();

        $this->assertFileExists($this->latestBackup().'/public/deliverables/scene.ply');

        File::deleteDirectory(storage_path('app/public/deliverables'));
    }

    public function test_old_backups_are_pruned(): void
    {
        foreach (['2026-01-01_000000', '2026-01-02_000000', '2026-01-03_000000'] as $stamp) {
            File::ensureDirectoryExists(storage_path('backups/'.$stamp));
        }

        $this->artisan('backup:run', ['--keep' => 2])->assertSuccessful();

        $remaining = collect(File::directories(storage_path('backups')))
            ->map(fn ($path) => basename($path));

        $this->assertCount(2, $remaining);
        $this->assertFalse($remaining->contains('2026-01-01_000000'));
    }

    public function test_restoring_brings_deleted_rows_back(): void
    {
        Client::factory()->create(['name' => 'Museum Kota Lama']);
        $this->artisan('backup:run')->assertSuccessful();
        $stamp = basename($this->latestBackup());

        Client::query()->forceDelete();
        $this->assertSame(0, Client::withTrashed()->count());

        $this->artisan('backup:restore', ['stamp' => $stamp, '--force' => true])->assertSuccessful();

        $this->assertSame(1, Client::count());
    }

    public function test_restoring_an_unknown_backup_fails(): void
    {
        $this->artisan('backup:restore', ['stamp' => 'tidak-ada', '--force' => true])->assertFailed();
    }
}
