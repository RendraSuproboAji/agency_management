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
     * Median per jenis job, diingat selama satu permintaan.
     *
     * Halaman project memanggilnya sekali per job antre, dan tiap panggilan
     * menanyakan hal yang sama persis. Perhitungannya murni baca dan tidak
     * berubah di tengah permintaan, jadi mengingatnya aman.
     *
     * Disimpan di container, bukan di properti statis: container dibangun
     * ulang tiap permintaan dan tiap tes, jadi ingatannya tidak pernah bocor
     * ke permintaan berikutnya. Properti statis sempat dipakai di sini dan
     * langsung membocorkan hasil antar tes.
     */
    private const CACHE_KEY = 'job-estimator.minutes-per-gb.';

    /**
     * Lupakan yang sudah diingat.
     *
     * Container hidup selama satu permintaan HTTP, tetapi sebuah perintah
     * artisan yang berjalan lama memakai container yang sama dari awal sampai
     * akhir — di situ perkiraan yang dihitung di menit pertama bisa basi.
     */
    public static function forget(): void
    {
        foreach (ProcessingJob::KINDS as $kind) {
            app()->forgetInstance(self::CACHE_KEY.$kind);
        }
    }

    /**
     * Median menit per GB untuk satu jenis job.
     *
     * Median, bukan rata-rata: satu job yang tertinggal semalaman karena mesin
     * hang akan menggeser seluruh perkiraan kalau dirata-ratakan.
     */
    public static function minutesPerGb(string $kind): ?Estimate
    {
        $key = self::CACHE_KEY.$kind;

        if (app()->bound($key)) {
            return app($key)['value'];
        }

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
            return self::remember($key, null);
        }

        return self::remember($key, new Estimate(self::median($rates), $rates->count()));
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

    private static function remember(string $key, ?Estimate $estimate): ?Estimate
    {
        // Dibungkus larik supaya null pun tercatat sebagai jawaban, bukan
        // sebagai "belum pernah dihitung".
        app()->instance($key, ['value' => $estimate]);

        return $estimate;
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
