<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    /**
     * Halaman ini pengarah, bukan daftar keempat yang harus dirawat: tiap
     * kelompok berhenti di lima baris dan menautkan ke daftar aslinya yang
     * sudah tersaring.
     */
    private const PER_GROUP = 5;

    public function index(Request $request): Response
    {
        $term = trim((string) $request->query('q', ''));

        [$results, $counts] = $term === ''
            ? [$this->empty(), $this->empty()]
            : $this->find($term);

        return Inertia::render('Search/Index', [
            'q' => $term,
            'results' => $results,
            'counts' => $counts,
            'total' => collect($results)->sum(fn (Collection $rows) => $rows->count()),
        ]);
    }

    /** @return array{0: array<string, Collection>, 1: array<string, int>} */
    private function find(string $term): array
    {
        $groups = [
            'clients' => Client::search($term)->orderBy('name'),
            'projects' => Project::search($term)->with('client')->latest(),
            'requests' => ServiceRequest::search($term)->latest(),
            'quotations' => Quotation::search($term)->with('project')->latest('issued_at'),
            'invoices' => Invoice::search($term)->with('project')->latest('issued_at'),
        ];

        $results = [];
        $counts = [];

        foreach ($groups as $key => $query) {
            // Hitung dulu, lalu ambil lima: jumlah sebenarnya yang membuat
            // tautan "lihat semua" punya alasan.
            $counts[$key] = (clone $query)->count();
            $results[$key] = $this->rows($key, $query);
        }

        return [$results, $counts];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function rows(string $key, Builder $query): Collection
    {
        return $query->limit(self::PER_GROUP)->get()->map(fn ($row) => match ($key) {
            'clients' => [
                'id' => $row->id,
                'label' => $row->name,
                'meta' => $row->industry ?: $row->email,
                'url' => route('clients.show', $row),
            ],
            'projects' => [
                'id' => $row->id,
                'label' => $row->title,
                'meta' => $row->client?->name,
                'url' => route('projects.show', $row),
            ],
            'requests' => [
                'id' => $row->id,
                'label' => $row->company ?: $row->name,
                'meta' => $row->email,
                'url' => route('requests.show', $row),
            ],
            'quotations' => [
                'id' => $row->id,
                'label' => $row->number,
                'meta' => $row->project?->title,
                'url' => $row->project
                    ? route('quotations.show', [$row->project, $row])
                    : route('requests.quotations.show', [$row->service_request_id, $row]),
            ],
            'invoices' => [
                'id' => $row->id,
                'label' => $row->number,
                'meta' => $row->project?->title,
                'url' => route('invoices.show', [$row->project, $row]),
            ],
        });
    }

    /** @return array<string, Collection> */
    private function empty(): array
    {
        return [
            'clients' => collect(),
            'projects' => collect(),
            'requests' => collect(),
            'quotations' => collect(),
            'invoices' => collect(),
        ];
    }
}
