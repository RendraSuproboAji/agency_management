<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class DocumentNumber
{
    /**
     * Build the next sequential document number for the given year,
     * e.g. QUO/2026/0001. Sequence restarts every year.
     *
     * @param  class-string<Model>  $model
     */
    public static function next(string $model, string $prefix, ?int $year = null): string
    {
        $year ??= (int) date('Y');
        $scope = sprintf('%s/%d/', $prefix, $year);

        $last = SoftDeleteAware::query($model)
            ->where('number', 'like', $scope.'%')
            ->orderByDesc('number')
            ->value('number');

        $sequence = $last ? ((int) substr($last, strlen($scope))) + 1 : 1;

        return $scope.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Buat satu dokumen bernomor, aman terhadap permintaan yang datang bersamaan.
     *
     * Membaca nomor terakhir lalu menyisipkannya bukan operasi atomik: dua
     * permintaan bisa membaca angka yang sama dan salah satunya mati kena indeks
     * unik. Di sini penyisipan diulang dengan nomor berikutnya saat itu terjadi,
     * jadi yang kalah balapan tetap mendapat nomor, bukan galat 500.
     *
     * @param  class-string<Model>  $model
     * @param  callable(string): Model  $create
     */
    public static function assign(string $model, string $prefix, callable $create, int $attempts = 3): Model
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(fn () => $create(self::next($model, $prefix)));
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= $attempts) {
                    throw $exception;
                }
            }
        }
    }
}
