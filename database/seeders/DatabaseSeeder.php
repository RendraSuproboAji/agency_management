<?php

namespace Database\Seeders;

use App\Models\CaptureSession;
use App\Models\Client;
use App\Models\Deliverable;
use App\Models\Project;
use App\Models\User;
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
        ];

        foreach ($samples as [$clientName, $projectTitle, $status, $serviceType]) {
            $client = Client::create([
                'name' => $clientName,
                'slug' => Slug::uniqueFor(Client::class, $clientName),
                'contact_name' => 'Narahubung '.Str::before($clientName, ' '),
                'email' => Str::slug($clientName).'@example.com',
                'phone' => '08120000'.random_int(1000, 9999),
                'industry' => 'Properti & Interior',
                'address' => 'Jakarta',
                'status' => 'active',
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
        }
    }
}
