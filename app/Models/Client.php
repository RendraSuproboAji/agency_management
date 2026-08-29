<?php

namespace App\Models;

use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable([
    'name', 'slug', 'contact_name', 'email', 'phone',
    'industry', 'address', 'notes', 'status', 'password', 'portal_enabled',
])]
#[Hidden(['password', 'remember_token'])]
class Client extends Authenticatable
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory, SoftDeletes;

    public const STATUSES = ['lead', 'active', 'inactive'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'portal_enabled' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /** Klien hanya bisa masuk portal bila diaktifkan dan kata sandinya sudah diisi. */
    public function canUsePortal(): bool
    {
        return $this->portal_enabled && filled($this->password);
    }

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
