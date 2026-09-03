<?php

namespace Database\Seeders;

use App\Models\CaptureSession;
use App\Models\Client;
use App\Models\Deliverable;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\User;
use App\Support\DocumentNumber;
use App\Support\Slug;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = $this->seedAdmin();

        if (! $admin || ! filter_var(env('SEED_DEMO', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        if (Client::exists()) {
            return;
        }

        $this->seedDemoData($admin);
    }

    private function seedAdmin(): ?User
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! $email) {
            $this->command?->warn('ADMIN_EMAIL belum diisi, seeding admin dilewati.');

            return null;
        }

        $user = User::where('email', $email)->first();

        if ($user) {
            return $user;
        }

        return User::create([
            'name' => env('ADMIN_NAME', 'Administrator'),
            'email' => $email,
            'role' => 'admin',
            'password' => Hash::make($password ?: Str::random(16)),
        ]);
    }

    private function seedDemoData(User $admin): void
    {
        $staff = User::firstOrCreate(
            ['email' => 'staff@example.com'],
            ['name' => 'Rina Kapture', 'role' => 'staff', 'password' => Hash::make('password')],
        );

        $samples = [
            ['Studio Interior Nusantara', 'Showroom Kemang 3D Tour', 'capture', 'gaussian_splatting'],
            ['Museum Kota Lama', 'Digitalisasi Galeri Utama', 'processing', 'photogrammetry'],
            ['PT Properti Sejahtera', 'Marketing Gallery Cluster Aster', 'review', 'panorama_360'],
            // Nama panjang memang lazim di sini, dan sengaja disertakan: tata
            // letak harus tahan terhadap teks tanpa titik patah, dan tes
            // regresi tampilan baru berguna kalau datanya memuat kasus itu.
            ['PT Pembangunan Infrastruktur Digital Nusantara Sejahtera', 'Rekonstruksi 3D Gedung Perkantoran Terpadu Blok A sampai Blok F', 'lead', 'drone_survey'],
        ];

        $portalPassword = env('DEMO_CLIENT_PASSWORD');

        foreach ($samples as $index => [$clientName, $projectTitle, $status, $serviceType]) {
            $client = Client::create([
                'name' => $clientName,
                'slug' => Slug::uniqueFor(Client::class, $clientName),
                'contact_name' => 'Narahubung '.Str::before($clientName, ' '),
                'email' => Str::slug($clientName).'@example.com',
                'phone' => '08120000'.random_int(1000, 9999),
                'industry' => 'Properti & Interior',
                'address' => 'Jakarta',
                'status' => 'active',
                // Portal hanya dinyalakan bila kata sandinya sengaja
                // dikonfigurasi; kalau tidak, seeding demo tidak boleh
                // meninggalkan akun klien berkata sandi yang bisa ditebak.
                'portal_enabled' => $index === 0 && filled($portalPassword),
                'password' => $index === 0 && filled($portalPassword) ? $portalPassword : null,
            ]);

            $project = Project::create([
                'client_id' => $client->id,
                'owner_id' => $status === 'capture' ? $staff->id : $admin->id,
                'title' => $projectTitle,
                'slug' => Slug::uniqueFor(Project::class, $projectTitle),
                'brief' => 'Rekonstruksi 3D immersive untuk kebutuhan pemasaran, hasil akhir tayang di viewer virtual tour.',
                'service_type' => $serviceType,
                'status' => $status,
                'budget' => random_int(15, 60) * 1_000_000,
                'deadline' => now()->addDays(random_int(10, 60))->toDateString(),
                'site_location' => 'Jakarta Selatan',
                'area_sqm' => random_int(120, 900),
            ]);

            CaptureSession::create([
                'project_id' => $project->id,
                'crew_id' => $staff->id,
                'scheduled_at' => now()->addDays(random_int(1, 14))->setTime(9, 0),
                'location' => $project->site_location,
                'equipment_note' => 'Sony A7IV + lensa 16-35mm, tripod, color checker',
                'status' => 'scheduled',
            ]);

            if (in_array($status, ['processing', 'review'], true)) {
                Deliverable::create([
                    'project_id' => $project->id,
                    'title' => 'Scene utama v1',
                    'type' => 'splat',
                    'version' => 1,
                    'external_url' => 'https://gallery.example.com/p/'.$project->slug,
                    'status' => $status === 'review' ? 'submitted' : 'draft',
                    'submitted_at' => $status === 'review' ? now()->subDays(2) : null,
                ]);
            }

            $this->seedBilling($project);
        }
    }

    /**
     * Penawaran dan invoice untuk project yang sudah berjalan.
     *
     * Tanpa ini demo tidak memperlihatkan sisi penagihan sama sekali — halaman
     * tagihan kosong, dokumen cetak tidak pernah bisa dibuka, dan portal klien
     * kehilangan separuh isinya.
     */
    private function seedBilling(Project $project): void
    {
        if (in_array($project->status, ['lead', 'survey'], true)) {
            return;
        }

        $amount = (float) $project->budget;

        $quotation = DocumentNumber::assign(Quotation::class, 'QUO', fn (string $number) => Quotation::create([
            'project_id' => $project->id,
            'number' => $number,
            'issued_at' => now()->subDays(30),
            'valid_until' => now()->subDays(30)->addDays(14),
            'tax_percent' => 11,
            'status' => 'accepted',
            'notes' => 'Harga sudah termasuk satu kali revisi minor.',
        ]));

        $quotation->items()->create([
            'description' => 'Pemindaian dan rekonstruksi 3D '.$project->title,
            'qty' => 1,
            'unit' => 'paket',
            'unit_price' => $amount,
        ]);

        $invoice = DocumentNumber::assign(Invoice::class, 'INV', fn (string $number) => Invoice::create([
            'project_id' => $project->id,
            'quotation_id' => $quotation->id,
            'number' => $number,
            'issued_at' => now()->subDays(14),
            'due_at' => now()->addDays(7),
            'amount' => $amount,
            'status' => 'sent',
        ]));

        // Satu project dibayar sebagian supaya sisa tagihan ikut terlihat.
        if ($project->status === 'review') {
            $invoice->payments()->create([
                'amount' => round($amount * 0.4),
                'paid_at' => now()->subDays(7),
                'method' => 'transfer',
                'note' => 'DP 40%',
            ]);

            $invoice->refresh()->recalculateStatus();
        }
    }
}
