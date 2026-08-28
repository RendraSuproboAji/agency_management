<?php

namespace App\Models;

use Database\Factories\ProcessingJobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'project_id', 'capture_session_id', 'kind', 'status', 'machine',
    'started_at', 'finished_at', 'output_size_gb', 'notes',
])]
class ProcessingJob extends Model
{
    /** @use HasFactory<ProcessingJobFactory> */
    use HasFactory;

    public const KINDS = ['photogrammetry', 'splat_training', 'mesh_export', 'cleanup', 'other'];

    public const STATUSES = ['queued', 'running', 'done', 'failed'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'output_size_gb' => 'decimal:2',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function captureSession(): BelongsTo
    {
        return $this->belongsTo(CaptureSession::class);
    }

    /** Lama job berjalan dalam menit; null selama belum selesai. */
    public function durationMinutes(): ?int
    {
        if (! $this->started_at || ! $this->finished_at) {
            return null;
        }

        return (int) $this->started_at->diffInMinutes($this->finished_at);
    }

    public function humanDuration(): string
    {
        $minutes = $this->durationMinutes();

        if ($minutes === null) {
            return '—';
        }

        return $minutes >= 60
            ? intdiv($minutes, 60).' j '.($minutes % 60).' m'
            : $minutes.' m';
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', 'running');
    }
}
