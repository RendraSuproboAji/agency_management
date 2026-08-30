# Agency Management

Sistem manajemen informasi untuk agensi jasa **immersive 3D reconstruction** —
mencatat request klien, menjadwalkan pengambilan gambar/scan di lokasi, melacak
produksi (photogrammetry / gaussian splatting / panorama), sampai menyerahkan
deliverable dan mendapat persetujuan klien.

Dibangun dengan Laravel 13 (PHP 8.4) + Inertia + React 19 + Tailwind v4, di-build
dengan Vite, di atas SQLite. Repo saudaranya,
[GalleryVT](https://github.com/RendraSuproboAji/GalleryVT), menjadi viewer virtual
tour hasil produksinya.

Inertia dipilih daripada Next.js secara sadar: Next punya bundler sendiri (bukan
Vite), dan memakainya berarti membangun API JSON untuk seluruh controller,
memasang Sanctum + CORS, serta menjalankan dua deployment. Dengan Inertia,
controller, dua guard sesi, dan seluruh aturan otorisasi tetap dipakai apa adanya.

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
| **Arsip** | Data yang dihapus masuk arsip dan bisa dipulihkan; hapus permanen khusus admin |

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

Tiga service: **web** (nginx) melayani berkas statis dan unduhan besar,
**app** (PHP-FPM) menangani request aplikasi, **scheduler** menjalankan backup
harian. Kode di-*bake* ke dalam image web dan app saat build — tidak ada volume
kode bersama, jadi tidak ada risiko aset basi setelah redeploy; hanya `storage/`
yang dibagi.

Alasan nginx: deliverable bisa ratusan MB, dan dengan mod_php setiap unduhan
menahan satu proses yang membawa interpreter PHP utuh. Diukur di container:
mengalirkan berkas 200 MB memakai ~5 MB memori di nginx.

Aplikasi tersedia di <http://localhost:8080>. Kalau `APP_KEY` tidak diisi,
entrypoint membuatnya sekali dan menyimpannya di `storage/app.key` di dalam
volume, jadi sesi dan data terenkripsi tetap valid setelah restart. Database
SQLite dan berkas deliverable disimpan di volume `agency-storage`, jadi tetap ada
setelah container di-restart.

## Menjalankan secara lokal

```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed        # isi ADMIN_EMAIL & ADMIN_PASSWORD dulu di .env
php artisan storage:link
php artisan serve                 # terminal 1
npm run dev                       # terminal 2 (hot reload); atau npm run build sekali
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

## Waktu dan zona waktu

Waktu tampil dan penjadwalan mengikuti `APP_TIMEZONE` (default `Asia/Jakarta`
di `.env.example` dan docker-compose). Dengan UTC, agenda pagi hari di Indonesia
akan terhitung "kemarin" dan hilang dari dashboard.

## Berkas dan keamanan

Lampiran internal — kontrak, denah, foto survei — disimpan di disk **privat**
dan hanya bisa diambil lewat route unduh yang terautentikasi. Deliverable tetap
di disk publik karena memang dibagikan ke klien lewat portal.

Unggahan dibatasi daftar-izin ekstensi (`App\Support\UploadRules`): dokumen,
gambar, video, aset 3D, dan arsip. SVG dan HTML ditolak karena tersimpan dengan
ekstensinya dan disajikan dari origin aplikasi, sehingga bisa membawa skrip yang
membajak sesi orang yang membukanya.

## Arsip dan backup

Menghapus klien, project, penawaran, invoice, deliverable, atau peralatan tidak
membuang datanya — semuanya masuk arsip di `/archive` (admin) dan bisa dipulihkan.
Mengarsipkan klien ikut mengarsipkan seluruh turunannya dengan penanda waktu yang
sama, sehingga memulihkannya hanya mengembalikan yang diarsipkan bersamaan; anak
yang sudah lebih dulu diarsipkan tetap tinggal di arsip. Hapus permanen membuang
berkas fisiknya sekalian.

```bash
php artisan backup:run --keep=14      # snapshot DB (VACUUM INTO) + berkas unggahan
php artisan backup:restore 2026-08-29_020000
```

Backup berjalan otomatis tiap hari pukul 02:00 lewat service `scheduler` di
docker-compose.

## Booking peralatan

Satu alat tidak bisa dipakai dua sesi aktif pada **tanggal kalender yang sama** —
kru memesan alat per hari dan sistem tidak menyimpan jam selesai, jadi tanggal
adalah satuan bentrok yang jujur di sini. Sesi berstatus `cancelled` melepas
alatnya kembali.

## Pengujian

```bash
php artisan test      # 148 tes: auth, request, klien, project, sesi, deliverable,
                      #          penawaran, invoice, pembayaran, lampiran, catatan,
                      #          log, cetak dokumen, portal klien, peralatan, job,
                      #          arsip, backup
npm run build         # bundel Vite
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
resources/js/Pages/    halaman React (Inertia), satu berkas per layar
resources/js/Layouts/  AppLayout (internal) dan PortalLayout (klien)
resources/views/       hanya root Inertia, halaman cetak, dan form publik
app/Http/Middleware/   EnsureAdmin (alias middleware "admin")
app/Support/           Slug (slug unik), DocumentNumber (nomor dokumen),
                       ActivityLogger (jejak aktivitas)
public/css/print.css   gaya dokumen cetak, di luar Tailwind
```
