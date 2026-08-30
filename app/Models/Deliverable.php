<?php

namespace App\Models;

use Database\Factories\DeliverableFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'project_id', 'scene_id', 'title', 'type', 'version', 'file_path', 'external_url',
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

    public function scene(): BelongsTo
    {
        return $this->belongsTo(ProjectScene::class, 'scene_id');
    }

    /**
     * Tautan tur publik, bila ada.
     *
     * Dulu metode ini juga mengembalikan URL berkas di disk publik. Berkasnya
     * kini privat dan hanya dilayani lewat rute unduh ber-autentikasi, jadi
     * kedua hal itu sengaja dipisah: yang ini publik, yang itu tidak.
     */
    public function url(): ?string
    {
        return $this->external_url ?: null;
    }

    public function hasFile(): bool
    {
        return filled($this->file_path);
    }
}
