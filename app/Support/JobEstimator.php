<?php

namespace App\Support;

use App\Models\ProcessingJob;
use App\Models\Project;
use Illuminate\Support\Collection;

class JobEstimator
{
    /**
     * Di bawah ini perkiraannya tidak dihitung sama sekali.
     *
     * Satu-dua job bukan pola: dari sampel sesedikit itu angka yang keluar
     * terdengar pasti padahal kebetulan.
     */
    private const MINIMUM_SAMPLES = 3;

    /** Status yang pekerjaannya masih tersisa. */
    private const REMAINING = ['queued', 'running'];

    /**
     * Median menit per GB untuk satu jenis job.
     *
     * Median, bukan rata-rata: satu job yang tertinggal semalaman karena mesin
     * hang akan menggeser seluruh perkiraan kalau dirata-ratakan.
     */
    public static function minutesPerGb(string $kind): ?Estimate
    {
        $rates = ProcessingJob::query()
            ->where('kind', $kind)
            ->whereNotNull('started_at')
            ->whereNotNull('finished_at')
            ->whereHas('captureSession', fn ($query) => $query->whereNotNull('raw_size_gb'))
            ->with('captureSession')
            ->get()
            ->map(function (ProcessingJob $job) {
                $gb = (float) $job->captureSession->raw_size_gb;
                $minutes = $job->durationMinutes();

                return $gb > 0 && $minutes !== null ? $minutes / $gb : null;
            })
            ->filter()
            ->values();

        if ($rates->count() < self::MINIMUM_SAMPLES) {
            return null;
        }

        return new Estimate(self::median($rates), $rates->count());
    }

    /** Perkiraan durasi satu job, dari besar data mentah sesi capture-nya. */
    public static function forJob(ProcessingJob $job): ?Estimate
    {
        $estimate = self::minutesPerGb($job->kind);
        $gb = (float) $job->captureSession?->raw_size_gb;

        if (! $estimate || $gb <= 0) {
            return null;
        }

        return new Estimate(
            $estimate->minutesPerGb,
            $estimate->samples,
            (int) round($estimate->minutesPerGb * $gb),
        );
    }

    /**
     * Sisa pekerjaan satu project: job yang masih mengantre dan berjalan.
     *
     * Job berjalan dihitung utuh, tidak dipotong waktu yang sudah lewat —
     * memotongnya menuntut asumsi bahwa jalannya seragam sejak awal, dan itu
     * tidak benar untuk pelatihan splat.
     */
    public static function forProject(Project $project): ?Estimate
    {
        $jobs = $project->processingJobs()
            ->whereIn('status', self::REMAINING)
            ->with('captureSession')
            ->get();

        $estimates = $jobs->map(fn (ProcessingJob $job) => self::forJob($job))->filter();

        if ($estimates->isEmpty()) {
            return null;
        }

        return new Estimate(
            $estimates->first()->minutesPerGb,
            $estimates->min(fn (Estimate $estimate) => $estimate->samples),
            (int) $estimates->sum(fn (Estimate $estimate) => $estimate->minutes),
        );
    }

    /** @param Collection<int, float> $values */
    private static function median(Collection $values): float
    {
        $sorted = $values->sort()->values();
        $middle = intdiv($sorted->count(), 2);

        return $sorted->count() % 2 === 1
            ? (float) $sorted[$middle]
            : ((float) $sorted[$middle - 1] + (float) $sorted[$middle]) / 2;
    }
}
