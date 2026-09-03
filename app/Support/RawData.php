<?php

namespace App\Support;

use App\Models\CaptureSession;
use App\Models\Deliverable;
use App\Models\Project;
use Carbon\Carbon;

class RawData
{
    public const HELD = 'ditahan';

    public const READY = 'siap_dibersihkan';

    public const NO_BACKUP = 'tanpa_backup';

    public const PURGED = 'sudah_dibersihkan';

    /**
     * Keadaan data mentah satu sesi capture.
     *
     * Urutan pemeriksaannya disengaja: sesi tanpa salinan dilaporkan lebih dulu
     * dan tidak pernah dinyatakan siap dibersihkan, seberapa pun lamanya
     * berlalu. Itu risiko kehilangan data, bukan sekadar boros tempat.
     */
    public static function status(CaptureSession $session): string
    {
        if ($session->raw_purged_at) {
            return self::PURGED;
        }

        if (! $session->backup_location) {
            return self::NO_BACKUP;
        }

        return self::retentionPassed($session) ? self::READY : self::HELD;
    }

    /** Berapa GB yang masih ditahan sesi ini. */
    public static function heldGb(CaptureSession $session): float
    {
        return $session->raw_purged_at ? 0.0 : (float) $session->raw_size_gb;
    }

    public static function retentionDays(Project $project): int
    {
        return $project->client?->raw_retention_days ?? (int) config('site.raw_retention_days');
    }

    /**
     * Masa retensi dihitung dari deliverable yang paling akhir disetujui, dan
     * hanya bila seluruhnya sudah disetujui. Satu revisi yang masih terbuka
     * berarti raw-nya mungkin dibutuhkan lagi untuk mengolah ulang.
     */
    private static function retentionPassed(CaptureSession $session): bool
    {
        $project = $session->project;

        if (! $project) {
            return false;
        }

        // Pengakses relasi, bukan ->deliverables()->get(): yang kedua selalu
        // menembak kueri baru, jadi eager loading pemanggilnya tidak pernah
        // terpakai dan halaman penyimpanan menanyakan sekali per sesi.
        $deliverables = $project->deliverables;

        if ($deliverables->isEmpty()) {
            return false;
        }

        if ($deliverables->contains(fn (Deliverable $item) => $item->status !== 'approved')) {
            return false;
        }

        $approvedAt = $deliverables->max('approved_at');

        return $approvedAt
            && Carbon::parse($approvedAt)->addDays(self::retentionDays($project))->isPast();
    }
}
