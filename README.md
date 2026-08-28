# Agency Management

Sistem manajemen informasi untuk agensi jasa **immersive 3D reconstruction** —
mencatat request klien, menjadwalkan pengambilan gambar/scan di lokasi, melacak
produksi (photogrammetry / gaussian splatting / panorama), sampai menyerahkan
deliverable dan mendapat persetujuan klien.

Dibangun dengan Laravel 13 + Blade + SQLite, tanpa Node/Vite — mengikuti
konvensi repo saudaranya, [GalleryVT](https://github.com/RendraSuproboAji/GalleryVT),
yang menjadi viewer virtual tour hasil produksinya.

## Modul

| Modul | Isi |
|---|---|
| **Klien & Kontak** | Perusahaan, narahubung, industri, status (`lead` / `active` / `inactive`), catatan |
| **Project & Brief** | Brief/request klien, jenis layanan, budget, deadline, lokasi site, luas area, PIC, tautan virtual tour |
| **Sesi Pengambilan Gambar** | Jadwal, kru, lokasi, peralatan, jumlah shot, catatan cuaca, status |
| **Deliverable & Aset** | Berkas terunggah atau tautan eksternal, versi, alur `draft → submitted → approved / revision` |
| **Pengguna** | Kelola akun dan peran (admin saja) |

### Pipeline produksi

```
lead → survey → capture → processing → review → delivered → archived
```

Menandai sesi pengambilan gambar sebagai selesai otomatis memajukan project ke
tahap `processing` bila masih berada di tahap `lead`, `survey`, atau `capture`.

### Peran pengguna

- **admin** — akses penuh, termasuk menghapus klien/project dan mengelola pengguna.
- **staff** — melihat semua klien dan project, tetapi hanya boleh mengubah
  project yang dirinya menjadi penanggung jawab (beserta sesi dan deliverable-nya).

## Menjalankan dengan Docker

```bash
cp .env.example .env          # opsional, hanya untuk menyetel ADMIN_*
ADMIN_EMAIL=admin@agency.test ADMIN_PASSWORD=rahasia123 docker compose up --build
```

Aplikasi tersedia di <http://localhost:8080>, healthcheck di `/up`. Database
SQLite dan berkas deliverable disimpan di volume `agency-storage`, jadi tetap ada
setelah container di-restart.

## Menjalankan secara lokal

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed        # isi ADMIN_EMAIL & ADMIN_PASSWORD dulu di .env
php artisan storage:link
php artisan serve
```

Set `SEED_DEMO=true` di `.env` untuk mengisi contoh klien, project, sesi, dan
deliverable.

Lupa kata sandi? Tidak ada alur reset lewat web — gunakan artisan:

```bash
php artisan admin:set-password admin@agency.test kata-sandi-baru
```

## Pengujian

```bash
php artisan test      # 33 tes: auth, klien, project, sesi capture, deliverable
vendor/bin/pint       # format kode
```

## Integrasi dengan GalleryVT

Setiap project punya kolom `gallery_url`, dan setiap deliverable punya
`external_url`. Isi keduanya dengan URL project di GalleryVT (`/p/{slug}`) supaya
tur 3D hasil produksi bisa dibuka langsung dari halaman project.

## Struktur

```
app/Models/            Client, Project, CaptureSession, Deliverable, User
app/Http/Controllers/  satu controller per modul, validasi inline
app/Http/Middleware/   EnsureAdmin (alias middleware "admin")
app/Support/Slug.php   pembuat slug unik dipakai Client dan Project
resources/views/       Blade klasik, satu layout + partial
public/css, public/js  aset tulis tangan, tanpa build step
```
