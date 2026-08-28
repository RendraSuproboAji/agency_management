<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Support\Slug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_a_slug_from_the_source_string(): void
    {
        $this->assertSame('studio-interior-nusantara', Slug::uniqueFor(Client::class, 'Studio Interior Nusantara'));
    }

    public function test_it_appends_a_suffix_when_the_slug_is_taken(): void
    {
        Client::factory()->create(['slug' => 'museum-kota-lama']);

        $this->assertSame('museum-kota-lama-2', Slug::uniqueFor(Client::class, 'Museum Kota Lama'));
    }

    public function test_it_ignores_the_record_being_updated(): void
    {
        $client = Client::factory()->create(['slug' => 'museum-kota-lama']);

        $this->assertSame('museum-kota-lama', Slug::uniqueFor(Client::class, 'Museum Kota Lama', $client->id));
    }

    public function test_it_falls_back_when_the_source_has_no_slug_characters(): void
    {
        $this->assertSame('item', Slug::uniqueFor(Client::class, '???'));
    }
}
