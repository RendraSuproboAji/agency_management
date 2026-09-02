<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectScene;
use App\Models\ServiceRate;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Support\QuotationEstimator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menawar sebelumnya berarti mengetik tiap baris dari nol, jadi harga untuk
 * pekerjaan serupa bisa berbeda antar staf tanpa ada yang menyadarinya.
 */
class ServiceRateTest extends TestCase
{
    use RefreshDatabase;

    private function rate(array $attributes = []): ServiceRate
    {
        return ServiceRate::create($attributes + [
            'service_type' => 'gaussian_splatting',
            'unit' => 'sqm',
            'label' => 'Pemindaian area',
            'unit_price' => 25_000,
            'min_charge' => null,
            'active' => true,
        ]);
    }

    public function test_area_and_scene_lines_come_from_the_rate_card(): void
    {
        $this->rate(['unit' => 'sqm', 'unit_price' => 25_000, 'label' => 'Pemindaian area']);
        $this->rate(['unit' => 'scene', 'unit_price' => 1_500_000, 'label' => 'Pengolahan per scene']);

        $items = QuotationEstimator::suggest('gaussian_splatting', 400, 3, 1.0);

        $this->assertCount(2, $items);
        $this->assertSame(400.0, (float) $items[0]['qty']);
        $this->assertSame(25_000.0, (float) $items[0]['unit_price']);
        $this->assertSame(3.0, (float) $items[1]['qty']);
        $this->assertSame(1_500_000.0, (float) $items[1]['unit_price']);
    }

    public function test_the_minimum_charge_acts_as_a_floor(): void
    {
        $this->rate(['unit' => 'sqm', 'unit_price' => 25_000, 'min_charge' => 15_000_000]);

        $items = QuotationEstimator::suggest('gaussian_splatting', 100, 0, 1.0);

        // 100 × 25.000 = 2.500.000, di bawah biaya minimum.
        $this->assertSame(15_000_000.0, (float) $items[0]['qty'] * (float) $items[0]['unit_price']);
        $this->assertStringContainsString('minimum', $items[0]['description']);
    }

    public function test_the_difficulty_multiplier_reaches_price_and_description(): void
    {
        $this->rate(['unit' => 'sqm', 'unit_price' => 20_000]);

        $items = QuotationEstimator::suggest('gaussian_splatting', 200, 0, 1.25);

        $this->assertSame(25_000.0, (float) $items[0]['unit_price']);
        $this->assertStringContainsString('1,25', $items[0]['description']);
    }

    public function test_a_service_type_without_rates_suggests_nothing(): void
    {
        $this->rate(['service_type' => 'photogrammetry']);

        $this->assertSame([], QuotationEstimator::suggest('drone_survey', 400, 2, 1.0));
    }

    public function test_inactive_rates_and_missing_measurements_are_skipped(): void
    {
        $this->rate(['unit' => 'sqm', 'active' => false]);
        $this->rate(['unit' => 'scene', 'unit_price' => 1_000_000]);

        $this->assertSame([], QuotationEstimator::suggest('gaussian_splatting', 400, 0, 1.0));
        $this->assertCount(1, QuotationEstimator::suggest('gaussian_splatting', 400, 2, 1.0));
    }

    public function test_the_estimate_endpoint_counts_the_projects_scenes(): void
    {
        $admin = User::factory()->admin()->create();
        $this->rate(['unit' => 'scene', 'unit_price' => 1_000_000]);

        $project = Project::factory()->create([
            'service_type' => 'gaussian_splatting',
            'area_sqm' => null,
            'owner_id' => $admin->id,
        ]);
        ProjectScene::factory()->count(3)->create(['project_id' => $project->id]);

        $response = $this->actingAs($admin)
            ->getJson(route('projects.quotations.estimate', $project).'?multiplier=1');

        $response->assertOk();
        $this->assertSame(3.0, (float) $response->json('items.0.qty'));
    }

    public function test_a_prospect_estimate_is_not_swallowed_by_the_show_route(): void
    {
        $admin = User::factory()->admin()->create();
        $this->rate(['unit' => 'sqm', 'unit_price' => 30_000]);

        $serviceRequest = ServiceRequest::factory()->create([
            'service_type' => 'gaussian_splatting',
            'area_sqm' => 250,
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('requests.quotations.estimate', $serviceRequest).'?multiplier=1');

        $response->assertOk();
        $this->assertSame(250.0, (float) $response->json('items.0.qty'));
    }

    public function test_an_unknown_multiplier_falls_back_to_normal(): void
    {
        $admin = User::factory()->admin()->create();
        $this->rate(['unit' => 'sqm', 'unit_price' => 30_000]);

        $serviceRequest = ServiceRequest::factory()->create([
            'service_type' => 'gaussian_splatting',
            'area_sqm' => 100,
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('requests.quotations.estimate', $serviceRequest).'?multiplier=99');

        $this->assertSame(30_000.0, (float) $response->json('items.0.unit_price'));
    }

    public function test_only_admins_manage_the_rate_card(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($staff)->get(route('rates.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('rates.index'))->assertOk();

        $this->actingAs($staff)->post(route('rates.store'), [
            'service_type' => 'gaussian_splatting',
            'unit' => 'sqm',
            'label' => 'Diam-diam',
            'unit_price' => 1,
        ])->assertForbidden();

        $this->assertSame(0, ServiceRate::count());
    }
}
