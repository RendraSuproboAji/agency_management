<?php

namespace App\Models;

use Database\Factories\DeliverableFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'project_id', 'title', 'type', 'version', 'file_path', 'external_url',
    'status', 'review_note', 'submitted_at', 'approved_at',
])]
class Deliverable extends Model
{
    /** @use HasFactory<DeliverableFactory> */
    use HasFactory, SoftDeletes;

    public const TYPES = ['splat', 'mesh', 'panorama', 'video', 'floorplan', 'other'];

    public const STATUSES = ['draft', 'submitted', 'approved', 'revision'];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** Tautan eksternal (mis. GalleryVT) diutamakan; kalau tidak ada pakai berkas terunggah. */
    public function url(): ?string
    {
        if ($this->external_url) {
            return $this->external_url;
        }

        return $this->file_path ? Storage::disk('public')->url($this->file_path) : null;
    }
}
