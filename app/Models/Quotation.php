<?php

namespace App\Models;

use Database\Factories\QuotationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['project_id', 'number', 'issued_at', 'valid_until', 'tax_percent', 'status', 'notes'])]
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
            'tax_percent' => 'decimal:2',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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
