<?php

namespace Tests\Unit;

use App\Models\Quotation;
use App\Support\DocumentNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_first_number_of_a_year_starts_at_one(): void
    {
        $this->assertSame('QUO/2026/0001', DocumentNumber::next(Quotation::class, 'QUO', 2026));
    }

    public function test_it_continues_from_the_highest_number_of_that_year(): void
    {
        Quotation::factory()->create(['number' => 'QUO/2026/0007']);

        $this->assertSame('QUO/2026/0008', DocumentNumber::next(Quotation::class, 'QUO', 2026));
    }

    public function test_the_sequence_restarts_every_year(): void
    {
        Quotation::factory()->create(['number' => 'QUO/2026/0042']);

        $this->assertSame('QUO/2027/0001', DocumentNumber::next(Quotation::class, 'QUO', 2027));
    }
}
