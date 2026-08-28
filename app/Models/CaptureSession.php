<?php

namespace App\Models;

use Database\Factories\CaptureSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'project_id', 'crew_id', 'scheduled_at', 'completed_at', 'location',
    'equipment', 'shot_count', 'weather_note', 'status', 'notes',
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
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function crew(): BelongsTo
    {
        return $this->belongsTo(User::class, 'crew_id');
    }
}
