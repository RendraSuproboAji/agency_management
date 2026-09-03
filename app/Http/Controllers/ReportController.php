<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Csv;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        [$from, $to] = $this->range($request);

        $invoiced = $this->monthlySums(
            Invoice::query()->whereNot('status', 'void'),
            'issued_at',
            'amount',
            $from,
            $to,
        );

        $received = $this->monthlySums(
            Payment::query()->whereHas('invoice'),
            'paid_at',
            'amount',
            $from,
            $to,
        );

        $months = $this->months($from, $to)->map(fn (string $month) => [
            'month' => $month,
            'label' => Carbon::createFromFormat('Y-m-d', $month.'-01')->translatedFormat('F Y'),
            'invoiced' => round((float) ($invoiced[$month] ?? 0), 2),
            'received' => round((float) ($received[$month] ?? 0), 2),
        ]);

        return Inertia::render('Reports/Index', [
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'months' => $months,
            'totals' => [
                'invoiced' => round($months->sum('invoiced'), 2),
                'received' => round($months->sum('received'), 2),
                // Piutang adalah keadaan hari ini, bukan hasil penjumlahan
                // periode: invoice lama yang belum lunas tetap terhitung.
                'outstanding' => round(
                    Invoice::unsettled()->with('payments')->get()
                        ->sum(fn (Invoice $invoice) => $invoice->outstanding()),
                    2,
                ),
            ],
            'exports' => [
                'invoices' => route('reports.invoices', $request->only(['from', 'to'])),
                'payments' => route('reports.payments', $request->only(['from', 'to'])),
            ],
        ]);
    }

    public function invoices(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);

        $rows = Invoice::query()
            ->with('project.client', 'payments')
            ->whereBetween('issued_at', [$from->toDateString(), $to->toDateString()])
            ->orderBy('issued_at')
            ->get()
            ->map(fn (Invoice $invoice) => [
                $invoice->number,
                $invoice->issued_at->toDateString(),
                $invoice->due_at?->toDateString() ?? '',
                $invoice->project?->client?->name ?? '',
                $invoice->project?->title ?? '',
                $invoice->status,
                number_format((float) $invoice->amount, 2, '.', ''),
                number_format($invoice->paidAmount(), 2, '.', ''),
                number_format($invoice->outstanding(), 2, '.', ''),
            ]);

        return Csv::stream(
            'invoice-'.$from->toDateString().'-'.$to->toDateString().'.csv',
            ['Nomor', 'Terbit', 'Jatuh tempo', 'Klien', 'Project', 'Status', 'Nilai', 'Dibayar', 'Sisa'],
            $rows,
        );
    }

    public function payments(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);

        $rows = Payment::query()
            ->with('invoice.project.client')
            ->whereHas('invoice')
            ->whereBetween('paid_at', [$from->toDateString(), $to->toDateString()])
            ->orderBy('paid_at')
            ->get()
            ->map(fn (Payment $payment) => [
                $payment->paid_at->toDateString(),
                $payment->invoice->number,
                $payment->invoice->project?->client?->name ?? '',
                $payment->method,
                $payment->reference ?? '',
                number_format((float) $payment->amount, 2, '.', ''),
                $payment->note ?? '',
            ]);

        return Csv::stream(
            'pembayaran-'.$from->toDateString().'-'.$to->toDateString().'.csv',
            ['Tanggal', 'Invoice', 'Klien', 'Metode', 'Referensi', 'Jumlah', 'Catatan'],
            $rows,
        );
    }

    /**
     * Rentang tanggalnya, bawaan 12 bulan terakhir.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(Request $request): array
    {
        $from = $this->date($request->query('from')) ?? Carbon::now()->subMonths(11)->startOfMonth();
        $to = $this->date($request->query('to')) ?? Carbon::now()->endOfMonth();

        // Rentang terbalik hanya menghasilkan laporan kosong yang membingungkan;
        // ditukar diam-diam lebih menolong daripada galat.
        return $from->isAfter($to) ? [$to, $from] : [$from, $to];
    }

    private function date(?string $value): ?Carbon
    {
        try {
            return $value ? Carbon::createFromFormat('Y-m-d', $value)->startOfDay() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Jumlah per bulan, dihitung di basis data.
     *
     * @return Collection<string, float>
     */
    private function monthlySums($query, string $dateColumn, string $sumColumn, Carbon $from, Carbon $to): Collection
    {
        return $query
            ->whereBetween($dateColumn, [$from->toDateString(), $to->toDateString()])
            ->groupBy('bulan')
            ->pluck(DB::raw('sum('.$sumColumn.')'), DB::raw("strftime('%Y-%m', {$dateColumn}) as bulan"));
    }

    /** @return Collection<int, string> */
    private function months(Carbon $from, Carbon $to): Collection
    {
        $months = collect();
        $cursor = $from->copy()->startOfMonth();

        while ($cursor->lessThanOrEqualTo($to)) {
            $months->push($cursor->format('Y-m'));
            $cursor->addMonth();
        }

        return $months;
    }
}
