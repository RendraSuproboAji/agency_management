<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['project_id', 'user_id', 'actor', 'subject_type', 'subject_id', 'action', 'description', 'properties'])]
class Activity extends Model
{
    protected function casts(): array
    {
        return ['properties' => 'array'];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Nama yang ditampilkan di riwayat: staff, klien portal, atau sistem. */
    public function actorName(): string
    {
        return $this->user?->name ?? $this->actor ?? 'Sistem';
    }
}
