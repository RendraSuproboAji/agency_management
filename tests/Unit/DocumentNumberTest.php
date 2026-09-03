<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\Quotation;
use App\Support\DocumentNumber;
use Illuminate\Database\UniqueConstraintViolationException;
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

    /**
     * Balapan sungguhan sulit dipentaskan di satu koneksi: baris pengganggu yang
     * dibuat di dalam transaksi ikut ter-rollback. Yang diuji di sini mekanismenya
     * — penyisipan yang kalah balapan diulang, bukan diteruskan sebagai galat 500.
     */
    public function test_assign_retries_when_the_number_is_taken(): void
    {
        $project = Project::factory()->create();
        $attempts = 0;

        $quotation = DocumentNumber::assign(Quotation::class, 'QUO', function (string $number) use ($project, &$attempts) {
            if (++$attempts === 1) {
                throw new UniqueConstraintViolationException('sqlite', 'insert', [], new \Exception('UNIQUE constraint failed'));
            }

            return Quotation::create([
                'project_id' => $project->id,
                'number' => $number,
                'issued_at' => now(),
                'tax_percent' => 11,
                'status' => 'draft',
            ]);
        });

        $this->assertSame(2, $attempts);
        $this->assertSame('QUO/'.date('Y').'/0001', $quotation->number);
        $this->assertSame(1, Quotation::count());
    }

    public function test_assign_gives_up_instead_of_looping_forever(): void
    {
        $this->expectException(UniqueConstraintViolationException::class);

        DocumentNumber::assign(Quotation::class, 'QUO', function (): never {
            throw new UniqueConstraintViolationException('sqlite', 'insert', [], new \Exception('UNIQUE constraint failed'));
        }, attempts: 2);
    }

    public function test_the_sequence_restarts_every_year(): void
    {
        Quotation::factory()->create(['number' => 'QUO/2026/0042']);

        $this->assertSame('QUO/2027/0001', DocumentNumber::next(Quotation::class, 'QUO', 2027));
    }
}
