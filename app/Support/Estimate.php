<?php

namespace App\Support;

/**
 * Satu perkiraan, selalu bersama jumlah sampelnya.
 *
 * Angka dan dasarnya sengaja dibawa bersama: perkiraan yang lepas dari jumlah
 * sampelnya terbaca sebagai janji, padahal ditebak dari sedikit data.
 */
class Estimate
{
    public function __construct(
        public readonly float $minutesPerGb,
        public readonly int $samples,
        public readonly ?int $minutes = null,
    ) {}

    /** Perkiraan durasi yang enak dibaca, mis. "≈14 jam". */
    public function humanDuration(): ?string
    {
        if ($this->minutes === null) {
            return null;
        }

        if ($this->minutes < 60) {
            return '≈'.$this->minutes.' menit';
        }

        $hours = $this->minutes / 60;

        return $hours < 24
            ? '≈'.round($hours, 1).' jam'
            : '≈'.round($hours / 24, 1).' hari';
    }

    public function basis(): string
    {
        return 'median dari '.$this->samples.' job';
    }
}
