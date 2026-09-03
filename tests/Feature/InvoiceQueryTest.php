<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InvoiceQueryTest extends TestCase
{
    use RefreshDatabase;

    /** Hitung kueri saat membuka daftar tagihan dengan sejumlah invoice. */
    private function queriesForInvoices(int $count): int
    {
        Invoice::query()->forceDelete();
        Invoice::factory()->count($count)->create(['status' => 'partial']);

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $this->actingAs(User::factory()->create())->get(route('invoices.index'))->assertOk();

        return $queries;
    }

    public function test_the_billing_list_does_not_query_per_row(): void
    {
        $few = $this->queriesForInvoices(2);
        $many = $this->queriesForInvoices(10);

        // Sebelum perbaikan, paidAmount() mengabaikan relasi yang sudah dimuat
        // dan menembak satu kueri per baris, sehingga selisihnya ikut tumbuh.
        $this->assertSame(
            $few,
            $many,
            "Jumlah kueri tumbuh mengikuti jumlah baris ({$few} → {$many}): daftar tagihan kembali N+1.",
        );
    }
}
