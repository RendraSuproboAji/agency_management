<?php

namespace App\Models;

use Database\Factories\EquipmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'code', 'category', 'serial_number', 'status', 'notes'])]
class Equipment extends Model
{
    /** @use HasFactory<EquipmentFactory> */
    use HasFactory;

    public const CATEGORIES = ['camera', 'drone', 'lidar', 'support', 'other'];

    public const STATUSES = ['available', 'maintenance', 'retired'];

    protected $table = 'equipment';

    public function captureSessions(): BelongsToMany
    {
        return $this->belongsToMany(CaptureSession::class, 'capture_session_equipment');
    }

    /**
     * Sesi lain yang sudah memakai alat ini pada tanggal yang sama. Kru memesan
     * alat per hari, jadi tanggal kalender adalah satuan bentroknya — bukan
     * rentang jam, yang memang tidak kita simpan.
     */
    public function conflictingSessionOn(string $date, ?int $exceptSessionId = null): ?CaptureSession
    {
        return $this->captureSessions()
            ->whereNot('capture_sessions.status', 'cancelled')
            ->whereDate('scheduled_at', $date)
            ->when($exceptSessionId, fn ($query) => $query->whereKeyNot($exceptSessionId))
            ->with('project')
            ->first();
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 'available');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")
                ->orWhere('serial_number', 'like', "%{$term}%");
        });
    }
}
