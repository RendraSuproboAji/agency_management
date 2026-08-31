<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\CaptureSession;
use App\Models\Client;
use App\Models\Deliverable;
use App\Models\Equipment;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Menyusuri setiap route GET bernama dan memastikan halamannya benar-benar
 * dirender. Tanpa ini, kesalahan seperti controller yang masih menunjuk view
 * Blade yang sudah dihapus lolos tanpa terdeteksi — persis yang terjadi pada
 * halaman deliverable. Route baru otomatis ikut terjaring.
 */
class SmokeTest extends TestCase
{
    use RefreshDatabase;

    /** Rute yang punya tes sendiri atau bukan halaman aplikasi. */
    private const SKIPPED = [
        'storage.local',        // rute bawaan framework
        'quotations.print',     // dijamin PrintDocumentTest
        // Penawaran calon klien punya binding sendiri; dijamin ProspectQuotationTest.
        'requests.quotations.print',
        'invoices.print',
        'portal.login',         // alur portal dijamin PortalAuthTest
        'portal.dashboard',
        'portal.projects.show',
        'portal.deliverables.download',
        'portal.quotations.print',
        'portal.invoices.print',
        'login',
        // Alur lupa kata sandi hanya untuk tamu dan punya PasswordResetTest.
        'password.request',
        'password.reset',
        'portal.password.request',
        'portal.password.reset',
    ];

    public function test_every_named_get_page_renders(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('attachments/kontrak.pdf', 'x');
        Storage::disk('local')->put('deliverables/scene.ply', 'x');

        $admin = User::factory()->admin()->create();
        $client = Client::factory()->create();
        $project = Project::factory()->create(['client_id' => $client->id, 'owner_id' => $admin->id]);
        $session = CaptureSession::factory()->create(['project_id' => $project->id]);
        $deliverable = Deliverable::factory()->create([
            'project_id' => $project->id,
            'file_path' => 'deliverables/scene.ply',
        ]);
        $quotation = Quotation::factory()->create(['project_id' => $project->id]);
        $invoice = Invoice::factory()->create(['project_id' => $project->id]);
        $equipment = Equipment::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create();
        $attachment = Attachment::factory()->create([
            'project_id' => $project->id,
            'file_path' => 'attachments/kontrak.pdf',
        ]);

        $bindings = [
            'project' => $project->slug,
            'client' => $client->slug,
            'session' => $session->id,
            'deliverable' => $deliverable->id,
            'quotation' => $quotation->id,
            'invoice' => $invoice->id,
            'equipment' => $equipment->id,
            'serviceRequest' => $serviceRequest->id,
            'user' => $admin->id,
            'attachment' => $attachment->id,
        ];

        $checked = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if (! $name || ! in_array('GET', $route->methods(), true) || in_array($name, self::SKIPPED, true)) {
                continue;
            }

            $parameters = collect($route->parameterNames())
                ->mapWithKeys(fn (string $parameter) => [$parameter => $bindings[$parameter] ?? null]);

            $this->assertEmpty(
                $parameters->filter(fn ($value) => $value === null)->keys()->all(),
                "Route [{$name}] memakai parameter yang belum disiapkan tes ini.",
            );

            $response = $this->actingAs($admin)
                ->get(route($name, $parameters->all()))
                ->assertOk("Route [{$name}] tidak merender halaman.");

            $this->assertComponentExists($name, $response);

            $checked[] = $name;
        }

        // Jaring pengaman: kalau daftar route menyusut drastis, tes ini harus gagal
        // alih-alih diam-diam menguji sedikit halaman.
        $this->assertGreaterThanOrEqual(25, count($checked));
    }

    /**
     * Inertia::render() tetap menjawab 200 walau berkas komponennya tidak ada —
     * kesalahannya baru terlihat sebagai layar kosong di browser. Status 200
     * saja karena itu bukan jaring pengaman yang cukup.
     */
    private function assertComponentExists(string $name, TestResponse $response): void
    {
        // Halaman non-Inertia (unduhan berkas, view Blade cetak) tidak punya
        // prop "page" sama sekali.
        try {
            $component = $response->viewData('page')['component'] ?? null;
        } catch (\Throwable) {
            return;
        }

        if ($component === null) {
            return;
        }

        $this->assertFileExists(
            resource_path('js/Pages/'.$component.'.jsx'),
            "Route [{$name}] merender komponen [{$component}] yang tidak punya berkas.",
        );
    }

    public function test_the_public_pages_render_for_guests(): void
    {
        $this->get(route('public.request.create'))->assertOk();
        $this->get(route('login'))->assertOk();
        $this->get(route('portal.login'))->assertOk();
    }
}
