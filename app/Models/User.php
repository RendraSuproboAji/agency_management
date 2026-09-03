<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLES = ['admin', 'staff'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function ownedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'owner_id');
    }

    /**
     * Sesi lain yang sudah menjadwalkan orang ini pada tanggal yang sama.
     *
     * Sejajar Equipment::conflictingSessionOn(), dan dengan alasan yang sama:
     * satuan bentroknya tanggal kalender, bukan rentang jam, karena jam
     * selesainya memang tidak kita simpan.
     */
    public function conflictingSessionOn(string $date, ?int $exceptSessionId = null): ?CaptureSession
    {
        return $this->captureSessions()
            ->whereNot('capture_sessions.status', 'cancelled')
            ->whereDate('scheduled_at', $date)
            ->when($exceptSessionId, fn ($query) => $query->whereKeyNot($exceptSessionId))
            ->with('project')
            ->first();
    }

    public function captureSessions(): HasMany
    {
        return $this->hasMany(CaptureSession::class, 'crew_id');
    }
}
