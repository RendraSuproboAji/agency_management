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
| **Request Masuk** | Form publik di `/request` tanpa login, inbox request, konversi satu klik jadi Klien + Project |
| **Klien & Kontak** | Perusahaan, narahubung, industri, status (`lead` / `active` / `inactive`), catatan |
| **Project & Brief** | Brief/request klien, jenis layanan, budget, deadline, lokasi site, luas area, PIC, tautan virtual tour |
| **Sesi Pengambilan Gambar** | Jadwal, kru, lokasi, peralatan, jumlah shot, catatan cuaca, status |
| **Deliverable & Aset** | Berkas terunggah atau tautan eksternal, versi, alur `draft → submitted → approved / revision` |
| **Penawaran** | Penawaran berbaris item, subtotal/pajak/total, status `draft → sent → accepted / rejected` |
| **Invoice & Pembayaran** | Invoice (bisa disalin dari penawaran disetujui), pencatatan DP/termin/pelunasan, rekap piutang |
| **Lampiran & Catatan** | Kontrak, denah, foto survei; catatan internal; riwayat aktivitas otomatis per project |
| **Pengguna** | Kelola akun dan peran (admin saja) |

### Alur kerja

```
request masuk → konversi jadi project → penawaran disetujui →
invoice & pembayaran → produksi → deliverable disetujui
```

Nomor penawaran dan invoice berurutan per tahun (`QUO/2026/0001`,
`INV/2026/0001`). Status invoice mengikuti pembayaran yang tercatat:
`sent → partial → paid`; invoice `draft` dan `void` tidak ikut berubah.

### Pipeline produksi

```
lead → survey → capture → processing → review → delivered → archived
```

Menandai sesi pengambilan gambar sebagai selesai otomatis memajukan project ke
tahap `processing` bila masih berada di tahap `lead`, `survey`, atau `capture`.

### Peran pengguna

- **admin** — akses penuh, termasuk menghapus klien/project dan mengelola pengguna.
- **staff** — melihat semua klien dan project, tetapi hanya boleh mengubah
  project yang dirinya menjadi penanggung jawab (beserta sesi, deliverable,
  penawaran, invoice, lampiran, dan catatannya).

Catatan internal hanya boleh dihapus penulisnya sendiri atau admin. Menghapus
klien, project, penawaran, dan invoice khusus admin.

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
php artisan test      # 74 tes: auth, request, klien, project, sesi, deliverable,
                      #         penawaran, invoice, pembayaran, lampiran, catatan, log
vendor/bin/pint       # format kode
```

## Integrasi dengan GalleryVT

Setiap project punya kolom `gallery_url`, dan setiap deliverable punya
`external_url`. Isi keduanya dengan URL project di GalleryVT (`/p/{slug}`) supaya
tur 3D hasil produksi bisa dibuka langsung dari halaman project.

## Struktur

```
app/Models/            ServiceRequest, Client, Project, CaptureSession,
                       Deliverable, Quotation, Invoice, Payment,
                       Attachment, Note, Activity, User
app/Http/Controllers/  satu controller per modul, validasi inline
app/Http/Middleware/   EnsureAdmin (alias middleware "admin")
app/Support/           Slug (slug unik), DocumentNumber (nomor dokumen),
                       ActivityLogger (jejak aktivitas)
resources/views/       Blade klasik, satu layout + partial
public/css, public/js  aset tulis tangan, tanpa build step
```
