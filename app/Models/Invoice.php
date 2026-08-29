<?php

namespace App\Models;

use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'project_id', 'quotation_id', 'number', 'issued_at', 'due_at',
    'amount', 'status', 'notes',
])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, SoftDeletes;

    public const STATUSES = ['draft', 'sent', 'partial', 'paid', 'void'];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'due_at' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function paidAmount(): float
    {
        return round((float) $this->payments()->sum('amount'), 2);
    }

    public function outstanding(): float
    {
        return round(max((float) $this->amount - $this->paidAmount(), 0), 2);
    }

    /**
     * Derive the status from the payments recorded so far. Draft and void
     * invoices are left alone — they are not part of the billing flow yet.
     */
    public function recalculateStatus(): void
    {
        if (in_array($this->status, ['draft', 'void'], true)) {
            return;
        }

        $paid = $this->paidAmount();

        $this->update([
            'status' => match (true) {
                $paid <= 0 => 'sent',
                $paid < (float) $this->amount => 'partial',
                default => 'paid',
            },
        ]);
    }

    /** Tagihan yang masih menunggu pembayaran. */
    public function scopeUnsettled(Builder $query): Builder
    {
        return $query->whereIn('status', ['sent', 'partial']);
    }
}
