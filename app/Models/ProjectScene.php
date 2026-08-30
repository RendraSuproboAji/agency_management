<?php

namespace App\Models;

use Database\Factories\ProjectSceneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['project_id', 'name', 'slug', 'position', 'gallery_url', 'notes'])]
class ProjectScene extends Model
{
    /** @use HasFactory<ProjectSceneFactory> */
    use HasFactory, SoftDeletes;

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(Deliverable::class, 'scene_id');
    }

    public function captureSessions(): HasMany
    {
        return $this->hasMany(CaptureSession::class, 'scene_id');
    }
}
