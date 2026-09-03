<?php

namespace App\Support;

use App\Models\ServiceRate;

class QuotationEstimator
{
    /**
     * Faktor kesulitan sebagai konstanta, bukan tabel keempat: nilainya jarang
     * berubah, dan satu layar admin lagi hanya menambah yang harus dirawat.
     */
    public const MULTIPLIERS = [
        '1' => 'Normal',
        '1.25' => 'Sulit — akses terbatas, ketinggian, atau kerja malam',
        '1.5' => 'Sangat sulit — area aktif, izin khusus, jadwal sempit',
    ];

    /**
     * Usulan baris penawaran dari kartu tarif.
     *
     * Hasilnya titik awal, bukan harga mati: pemanggilnya menambahkan baris ini
     * ke form dan setiap angkanya masih bisa disunting sebelum dikirim.
     *
     * @return array<int, array{description: string, qty: float, unit: string, unit_price: float}>
     */
    public static function suggest(string $serviceType, ?int $areaSqm, int $sceneCount, float $multiplier): array
    {
        $quantities = [
            'sqm' => $areaSqm ? (float) $areaSqm : 0.0,
            'scene' => (float) $sceneCount,
            'paket' => 1.0,
        ];

        $rates = ServiceRate::active()->where('service_type', $serviceType)->get()
            // Urutan satuannya, bukan abjad: penawaran dibaca dari yang paling
            // besar cakupannya ke yang paling kecil, dan "per scene" di atas
            // "per m²" membaca terbalik.
            ->sortBy(fn (ServiceRate $rate) => array_search($rate->unit, ServiceRate::UNITS, true));

        $items = [];

        foreach ($rates as $rate) {
            $qty = $quantities[$rate->unit] ?? 0.0;

            // Satuan yang tidak terukur di data project dilewati diam-diam;
            // baris berjumlah nol hanya jadi pekerjaan menghapus bagi penawar.
            if ($qty <= 0) {
                continue;
            }

            $items[] = self::line($rate, $qty, $multiplier);
        }

        return $items;
    }

    /** @return array{description: string, qty: float, unit: string, unit_price: float} */
    private static function line(ServiceRate $rate, float $qty, float $multiplier): array
    {
        $unitPrice = round((float) $rate->unit_price * $multiplier, 2);
        $description = $rate->label;

        if ($multiplier != 1.0) {
            $description .= ' (faktor kesulitan '.number_format($multiplier, 2, ',', '.').')';
        }

        $minimum = (float) $rate->min_charge;

        // Biaya minimum jadi lantai harga, dan alasannya ikut tertulis: klien
        // yang melihat harga per m² tidak cocok dengan totalnya berhak tahu
        // kenapa, alih-alih menemukan angka yang diam-diam dinaikkan.
        if ($minimum > 0 && $qty * $unitPrice < $minimum) {
            return [
                'description' => $description.' — biaya minimum berlaku',
                'qty' => 1.0,
                'unit' => 'paket',
                'unit_price' => round($minimum * $multiplier, 2),
            ];
        }

        return [
            'description' => $description,
            'qty' => $qty,
            'unit' => ServiceRate::UNIT_LABELS[$rate->unit] ?? $rate->unit,
            'unit_price' => $unitPrice,
        ];
    }
}
