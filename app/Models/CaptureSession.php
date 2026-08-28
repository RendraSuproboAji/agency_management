<?php

namespace App\Models;

use Database\Factories\CaptureSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'project_id', 'crew_id', 'scheduled_at', 'completed_at', 'location',
    'equipment_note', 'shot_count', 'raw_size_gb', 'frame_count',
    'backup_location', 'weather_note', 'status', 'notes',
])]
class CaptureSession extends Model
{
    /** @use HasFactory<CaptureSessionFactory> */
    use HasFactory;

    public const STATUSES = ['scheduled', 'done', 'cancelled'];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
            'raw_size_gb' => 'decimal:2',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function equipment(): BelongsToMany
    {
        return $this->belongsToMany(Equipment::class, 'capture_session_equipment');
    }

    public function crew(): BelongsTo
    {
        return $this->belongsTo(User::class, 'crew_id');
    }
}
