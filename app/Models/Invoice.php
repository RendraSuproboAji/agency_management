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
use Illuminate\Support\Carbon;

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

    /** Dicari lewat nomor dokumennya; itu yang orang ingat dan ketik. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        return $term === '' ? $query : $query->where('number', 'like', "%{$term}%");
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
        // Pakai relasi yang sudah dimuat bila ada; kalau selalu mengueri ulang,
        // eager loading di daftar tagihan jadi sia-sia dan berubah jadi N+1.
        $paid = $this->relationLoaded('payments')
            ? $this->payments->sum(fn (Payment $payment) => (float) $payment->amount)
            : (float) $this->payments()->sum('amount');

        return round($paid, 2);
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

    /**
     * Lewat jatuh tempo sengaja dihitung, bukan disimpan sebagai status.
     *
     * Sebuah invoice bisa sekaligus `partial` dan lewat tempo; menjadikannya
     * status tersimpan akan menghapus informasi pembayaran sebagiannya, dan
     * menuntut perintah terjadwal hanya untuk membalik status — yang berarti
     * datanya bisa basi kalau perintah itu tidak jalan.
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->unsettled()
            ->whereNotNull('due_at')
            ->whereDate('due_at', '<', Carbon::today());
    }

    public function isOverdue(): bool
    {
        return $this->due_at
            && $this->due_at->isBefore(Carbon::today())
            && $this->outstanding() > 0
            && ! in_array($this->status, ['draft', 'void', 'paid'], true);
    }

    /** Berapa hari lewat jatuh tempo; nol bila belum lewat. */
    public function daysOverdue(): int
    {
        return $this->isOverdue() ? $this->due_at->diffInDays(Carbon::today()) : 0;
    }
}
