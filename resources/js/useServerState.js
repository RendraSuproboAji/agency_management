import { useState } from 'react';

/**
 * State lokal yang mengikuti nilai dari server ketika nilai itu berubah.
 *
 * Dibutuhkan karena aksi lain di halaman yang sama bisa mengubah nilainya di
 * server — menyelesaikan sebuah sesi, misalnya, memajukan status project — dan
 * useState biasa hanya membaca props sekali. Tanpa ini select menampilkan nilai
 * lama sementara badge di kepala halaman sudah menampilkan yang baru, dan
 * mengirim form itu akan diam-diam memundurkan status.
 *
 * Memakai pola resmi React: menyetel ulang saat render ketika props berubah,
 * bukan lewat useEffect yang menyebabkan render kedua.
 */
export function useServerState(serverValue) {
    const [value, setValue] = useState(serverValue);
    const [previous, setPrevious] = useState(serverValue);

    if (serverValue !== previous) {
        setPrevious(serverValue);
        setValue(serverValue);
    }

    return [value, setValue];
}
