<?php

namespace App\Models;

use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['project_id', 'uploaded_by', 'uploaded_by_client_id', 'title', 'category', 'file_path', 'mime', 'size'])]
class Attachment extends Model
{
    /** @use HasFactory<AttachmentFactory> */
    use HasFactory;

    public const CATEGORIES = ['contract', 'floorplan', 'survey_photo', 'reference', 'other'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function uploaderClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'uploaded_by_client_id');
    }

    /** Pengunggahnya, staf atau klien — satu sumber untuk kedua layar. */
    public function uploaderName(): string
    {
        return $this->uploader?->name ?? $this->uploaderClient?->name ?? 'Tidak diketahui';
    }

    public function humanSize(): string
    {
        $size = (int) $this->size;

        return match (true) {
            $size >= 1_048_576 => round($size / 1_048_576, 1).' MB',
            $size >= 1024 => round($size / 1024).' KB',
            default => $size.' B',
        };
    }
}
