<?php

namespace App\Models;

use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'slug', 'contact_name', 'email', 'phone',
    'industry', 'address', 'notes', 'status',
])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    public const STATUSES = ['lead', 'active', 'inactive'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return $query->when($status, fn (Builder $q) => $q->where('status', $status));
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('contact_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('industry', 'like', "%{$term}%");
        });
    }
}
