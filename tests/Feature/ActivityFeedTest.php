<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Equipment;
use App\Models\Project;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * ActivityLogger mengisi project_id dari $subject->project_id, jadi aktivitas
 * klien dan peralatan tersimpan dengan project_id null — tercatat rapi tetapi
 * tidak pernah tampil di layar mana pun, karena halaman project satu-satunya
 * yang menampilkannya.
 */
class ActivityFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_and_equipment_activity_reaches_a_screen(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $client = Client::factory()->create(['name' => 'Studio Ambar']);
        $equipment = Equipment::factory()->create(['name' => 'Kamera Utama']);

        $this->actingAs($staff);
        ActivityLogger::log($client, 'client.created', 'Klien Studio Ambar ditambahkan.');
        ActivityLogger::log($equipment, 'equipment.updated', 'Kamera Utama masuk perawatan.');

        $this->get(route('activities.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Activities/Index')
                ->where('activities.data.0.description', 'Kamera Utama masuk perawatan.')
                ->where('activities.data.1.description', 'Klien Studio Ambar ditambahkan.')
                ->where('activities.data.0.actor', $staff->name)
            );
    }

    public function test_the_feed_can_be_narrowed_to_one_kind_of_subject(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $client = Client::factory()->create();
        $project = Project::factory()->create();

        $this->actingAs($staff);
        ActivityLogger::log($client, 'client.created', 'Klien baru.');
        ActivityLogger::log($project, 'project.created', 'Project baru.');

        $this->get(route('activities.index', ['subject' => 'client']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('activities.data', 1)
                ->where('activities.data.0.description', 'Klien baru.')
            );
    }

    public function test_a_client_page_shows_its_own_history(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $client = Client::factory()->create();
        $other = Client::factory()->create();

        $this->actingAs($staff);
        ActivityLogger::log($client, 'client.updated', 'Alamat klien ini diperbarui.');
        ActivityLogger::log($other, 'client.updated', 'Klien lain diperbarui.');

        $this->get(route('clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('client.activities', 1)
                ->where('client.activities.0.description', 'Alamat klien ini diperbarui.')
            );
    }

    public function test_the_feed_does_not_query_once_per_row(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        $count = function (int $rows): int {
            DB::table('activities')->delete();

            foreach (Client::factory()->count($rows)->create() as $client) {
                ActivityLogger::log($client, 'client.created', 'Klien '.$client->name.'.');
            }

            $queries = 0;
            DB::listen(function () use (&$queries) {
                $queries++;
            });

            $this->get(route('activities.index'))->assertOk();

            return $queries;
        };

        $this->assertSame($count(2), $count(12), 'jumlah kuerinya tidak boleh ikut naik bersama jumlah barisnya');
    }
}
