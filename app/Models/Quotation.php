<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\QuotationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['project_id', 'service_request_id', 'number', 'issued_at', 'valid_until', 'tax_percent', 'status', 'accepted_at', 'accepted_by', 'notes'])]
class Quotation extends Model
{
    /** @use HasFactory<QuotationFactory> */
    use HasFactory, SoftDeletes;

    public const STATUSES = ['draft', 'sent', 'accepted', 'rejected'];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'valid_until' => 'date',
            'accepted_at' => 'datetime',
            'tax_percent' => 'decimal:2',
        ];
    }

    /**
     * Kedaluwarsanya diturunkan, tidak disimpan — sejalan dengan
     * Invoice::isOverdue(). Menyimpannya menuntut perintah terjadwal, dan
     * status penawaran sudah dipakai untuk hal lain: yang kedaluwarsa tetap
     * berstatus "sent" sampai seseorang memutuskan menerbitkannya ulang.
     */
    public function isExpired(): bool
    {
        return $this->status === 'sent'
            && $this->valid_until
            && $this->valid_until->isBefore(Carbon::today());
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', 'sent')
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '<', Carbon::today());
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    /**
     * Penerima penawaran, dari project-nya atau dari permintaan yang masuk.
     *
     * Satu sumber supaya halaman cetak tidak perlu tahu penawaran ini milik
     * klien yang sudah ada atau calon klien.
     *
     * @return array<string, string|null>
     */
    public function recipient(): array
    {
        if ($this->project) {
            $client = $this->project->client;

            return [
                'name' => $client->name,
                'contact_name' => $client->contact_name,
                'address' => $client->address,
                'email' => $client->email,
                'subject' => $this->project->title,
                'service_type' => str_replace('_', ' ', $this->project->service_type),
                'site_location' => $this->project->site_location,
                'area_sqm' => $this->project->area_sqm,
            ];
        }

        $request = $this->serviceRequest;

        return [
            'name' => $request?->company ?: $request?->name,
            'contact_name' => $request?->company ? $request->name : null,
            'address' => null,
            'email' => $request?->email,
            'subject' => 'Permintaan '.($request?->company ?: $request?->name),
            'service_type' => str_replace('_', ' ', (string) $request?->service_type),
            'site_location' => $request?->site_location,
            'area_sqm' => $request?->area_sqm,
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function subtotal(): float
    {
        return round($this->items->sum(fn (QuotationItem $item) => $item->lineTotal()), 2);
    }

    public function taxAmount(): float
    {
        return round($this->subtotal() * (float) $this->tax_percent / 100, 2);
    }

    public function total(): float
    {
        return round($this->subtotal() + $this->taxAmount(), 2);
    }
}
