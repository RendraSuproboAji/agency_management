<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

class Csv
{
    /**
     * Awalan yang membuat spreadsheet memperlakukan sebuah sel sebagai rumus,
     * bukan teks.
     */
    private const FORMULA_STARTS = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Kirim berkas CSV baris per baris.
     *
     * @param  array<int, string>  $headings
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    public static function stream(string $filename, array $headings, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows) {
            $handle = fopen('php://output', 'w');

            // BOM supaya Excel di Windows membaca UTF-8 dengan benar; tanpa ini
            // nama berhuruf beraksen berantakan.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, $headings);

            foreach ($rows as $row) {
                fputcsv($handle, array_map(self::defuse(...), $row));
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Lucuti nilai yang akan dieksekusi sebagai rumus.
     *
     * Isi berkas ini sebagian datang dari luar — nama klien dan catatan
     * pembayaran, yang bisa berasal dari formulir permintaan publik. Sel
     * berawalan "=" dijalankan begitu berkasnya dibuka, jadi diberi kutip
     * tunggal di depan: spreadsheet menampilkannya apa adanya sebagai teks.
     */
    private static function defuse(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return in_array($value[0], self::FORMULA_STARTS, true) ? "'".$value : $value;
    }
}
