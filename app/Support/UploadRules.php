<?php

namespace App\Support;

class UploadRules
{
    /**
     * Ekstensi yang boleh diunggah: dokumen, gambar, video, dan format 3D yang
     * memang jadi bahan kerja agensi.
     *
     * Sengaja daftar-izin, bukan daftar-tolak. Laravel menamai berkas dari hasil
     * sniffing isinya, jadi sumber PHP mendarat tanpa ekstensi dan tidak pernah
     * dieksekusi — tetapi SVG dan HTML tersimpan dengan ekstensinya, disajikan
     * dari origin aplikasi, dan bisa membawa skrip yang membajak sesi pembukanya.
     * Daftar-tolak akan selalu ketinggalan satu ekstensi.
     */
    private const ALLOWED = [
        // dokumen
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'md', 'rtf',
        // gambar
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tif', 'tiff', 'heic', 'dng', 'raw',
        // video & audio
        'mp4', 'mov', 'mkv', 'webm', 'avi', 'mp3', 'wav',
        // aset 3D dan data mentah
        'ply', 'splat', 'ksplat', 'obj', 'mtl', 'fbx', 'glb', 'gltf', 'usdz',
        'las', 'laz', 'e57', 'xyz', 'pcd', 'json',
        // arsip
        'zip', '7z', 'rar', 'tar', 'gz',
    ];

    /**
     * @return array<int, string>
     */
    public static function file(bool $required = true, int $maxKilobytes = 262144): array
    {
        return [
            $required ? 'required' : 'nullable',
            'file',
            'max:'.$maxKilobytes,
            'extensions:'.implode(',', self::ALLOWED),
        ];
    }

    /** @return array<int, string> */
    public static function allowed(): array
    {
        return self::ALLOWED;
    }
}
