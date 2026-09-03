import { useCallback, useEffect, useState } from 'react';

const KEY = 'tema';

/** Apa yang sedang tampil sekarang, entah dari pilihan pengguna atau perangkat. */
function activeTheme() {
    const chosen = document.documentElement.dataset.theme;

    if (chosen === 'light' || chosen === 'dark') {
        return chosen;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

/**
 * Mode terang/gelap.
 *
 * Bawaannya mengikuti perangkat; begitu pengguna menekan tombol, pilihannya
 * disimpan dan menang atas setelan perangkat. localStorage dibungkus try/catch
 * karena di mode penyamaran sebagian browser melemparkan galat saat diakses.
 */
export function useTheme() {
    const [theme, setTheme] = useState(activeTheme);

    // Selama pengguna belum memilih, perubahan setelan perangkat tetap diikuti.
    useEffect(() => {
        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const sync = () => document.documentElement.dataset.theme || setTheme(activeTheme());

        media.addEventListener('change', sync);

        return () => media.removeEventListener('change', sync);
    }, []);

    const toggle = useCallback(() => {
        const next = activeTheme() === 'dark' ? 'light' : 'dark';

        document.documentElement.dataset.theme = next;
        setTheme(next);

        try {
            localStorage.setItem(KEY, next);
        } catch (error) {
            // Penyimpanan ditolak; pilihannya tetap berlaku sampai halaman ditutup.
        }
    }, []);

    return [theme, toggle];
}
