# Agency Management

Sistem manajemen informasi untuk agensi jasa **immersive 3D reconstruction** —
mencatat request klien, menjadwalkan pengambilan gambar/scan di lokasi, melacak
produksi (photogrammetry / gaussian splatting / panorama), sampai menyerahkan
deliverable dan mendapat persetujuan klien.

Dibangun dengan Laravel 13 (PHP 8.4) + Blade + SQLite, tanpa Node/Vite — mengikuti
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
| **Peralatan** | Inventaris kamera/drone/lidar, ditugaskan ke sesi capture, dengan deteksi bentrok per hari |
| **Produksi** | Metadata data mentah (ukuran, jumlah frame, lokasi backup) dan job processing beserta durasinya |
| **Portal Klien** | Login terpisah untuk klien: progres project, jadwal, hasil pekerjaan, tagihan, approval deliverable |
| **Lampiran & Catatan** | Kontrak, denah, foto survei; catatan internal; riwayat aktivitas otomatis per project |
| **Pengguna** | Kelola akun dan peran (admin saja) |

### Alur kerja

```
request masuk → konversi jadi project → penawaran disetujui →
invoice & pembayaran → produksi → deliverable disetujui
```

Penawaran dan invoice punya halaman siap cetak (`/print`) berkop surat —
klien menyimpannya sebagai PDF lewat browser, tanpa dependensi tambahan.

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

- **klien** — guard terpisah di `/portal`: hanya melihat project miliknya
  sendiri, dan bisa menyetujui atau meminta revisi deliverable yang sudah
  diserahkan. Catatan internal, riwayat aktivitas, dan dokumen berstatus
  `draft` tidak pernah tampil di portal.

Catatan internal hanya boleh dihapus penulisnya sendiri atau admin. Menghapus
klien, project, penawaran, dan invoice khusus admin.

## Menjalankan dengan Docker

```bash
cp .env.example .env          # opsional, hanya untuk menyetel ADMIN_*
ADMIN_EMAIL=admin@agency.test ADMIN_PASSWORD=rahasia123 docker compose up --build
```

Aplikasi tersedia di <http://localhost:8080>. Kalau `APP_KEY` tidak diisi,
entrypoint membuatnya sekali dan menyimpannya di `storage/app.key` di dalam
volume, jadi sesi dan data terenkripsi tetap valid setelah restart. Database
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
php artisan client:set-password museum-kota-lama kata-sandi-portal --enable
```

## Portal klien

Aktifkan lewat halaman ubah klien (centang "Aktifkan portal" dan isi kata
sandi), atau lewat `client:set-password --enable`. Klien lalu masuk di
`/portal/login` memakai email yang tercatat pada datanya.

## Booking peralatan

Satu alat tidak bisa dipakai dua sesi aktif pada **tanggal kalender yang sama** —
kru memesan alat per hari dan sistem tidak menyimpan jam selesai, jadi tanggal
adalah satuan bentrok yang jujur di sini. Sesi berstatus `cancelled` melepas
alatnya kembali.

## Pengujian

```bash
php artisan test      # 106 tes: auth, request, klien, project, sesi, deliverable,
                      #          penawaran, invoice, pembayaran, lampiran, catatan,
                      #          log, cetak dokumen, portal klien, peralatan, job
vendor/bin/pint       # format kode
```

## Integrasi dengan GalleryVT

Setiap project punya kolom `gallery_url`, dan setiap deliverable punya
`external_url`. Isi keduanya dengan URL project di GalleryVT (`/p/{slug}`) supaya
tur 3D hasil produksi bisa dibuka langsung dari halaman project.

## Struktur

```
app/Models/            ServiceRequest, Client, Project, CaptureSession,
                       Deliverable, Quotation, Invoice, Payment, Equipment,
                       ProcessingJob, Attachment, Note, Activity, User
app/Http/Controllers/  satu controller per modul, validasi inline
app/Http/Controllers/Portal/  area klien (guard "client")
app/Http/Middleware/   EnsureAdmin (alias middleware "admin")
app/Support/           Slug (slug unik), DocumentNumber (nomor dokumen),
                       ActivityLogger (jejak aktivitas)
resources/views/       Blade klasik, satu layout + partial
public/css, public/js  aset tulis tangan, tanpa build step
```
