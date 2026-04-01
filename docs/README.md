# SIGAJI Native

Mirror project `sistem-penggajian` ke PHP Native sederhana, tetap mengikuti flow lama dan schema `hark8423_gaji.sql`.

## Struktur Lama vs Baru

Sistem lama:
- Laravel 12
- Filament resource multi halaman
- Import absensi via action Filament
- Penggajian manual/otomatis via resource
- PDF slip dan laporan via controller

Sistem baru:
- PHP Native
- Single page dengan sidebar tetap
- Content dimuat via AJAX
- CRUD/validasi memakai modal
- Slip dan laporan dibuka sebagai halaman print-friendly

## Apa yang Dimirror

- Login dengan pilihan unit
- Scope data per `unit_id`
- Import absensi dan parsing file
- Auto-create user dan `master_gaji` saat import bila belum ada
- Hitung keterlambatan berbasis shift/jabatan
- Setting toleransi keterlambatan global per unit dan override per user
- Validasi master gaji
- Generate penggajian otomatis per periode
- Override payroll manual
- Slip gaji dan laporan periode

## Apa yang Diubah

- Filament resource diubah menjadi endpoint `/ajax`
- Multi halaman diubah menjadi satu shell SPA
- PDF library tidak dipakai; diganti halaman print-friendly
- UI dibangun dari komponen `/components`

## Kenapa Diubah

- Menghilangkan ketergantungan Laravel/Filament
- Menjaga flow lama tetapi menyederhanakan operasional
- Mempermudah deploy ke shared hosting PHP biasa

## Struktur Folder

- `index.php`: entry point login + single page shell
- `ajax/`: seluruh content section dan action AJAX
- `ajax/users.php`: modul CRUD user native
- `ajax/units.php`: modul CRUD unit native
- `components/`: button, modal, table, icon, panel, field, badge, stat
- `services/`: auth, import absensi, payroll calculation
- `bootstrap/`: bootstrap app, helper, session, db
- `docs/README.md`: catatan mirror dan cara jalan

## Cara Menjalankan

1. Buat file `.env` dari `.env.example`.
2. Isi koneksi database ke schema `hark8423_gaji`.
3. Import SQL `hark8423_gaji.sql` ke MySQL.
4. Jika database lama sudah terlanjur berjalan, jalankan `docs/update-toleransi-terlambat.sql`.
5. Jalankan lewat web server PHP biasa, misalnya document root ke folder ini.
6. Login memakai user yang ada pada tabel `users`.

## Deploy Shared Hosting cPanel

- Jika subdomain diarahkan langsung ke `public_html/penggajian`, simpan seluruh isi repo di folder itu.
- Jangan pakai flow Laravel seperti `artisan optimize:clear` atau copy folder `public`.
- Pakai sample file cron deploy di `docs/deploy-sigaji-cron.php.example`.
- Simpan sebagai `/home/hark8423/public_html/deploy-sigaji-cron.php`.
- Tambahkan cron:
  - `* * * * * /usr/bin/php /home/hark8423/public_html/deploy-sigaji-cron.php`
- Flow deploy:
  - `git push` dari local ke GitHub
  - cron cek setiap 1 menit
  - jika ada commit baru di `main`, server otomatis `git pull`
- Tidak perlu webhook GitHub.
- Tidak perlu buka file deploy manual dari browser.
- Root project sudah disiapkan `.htaccess` agar:
  - `assets/*` diarahkan ke `public/assets/*`
  - `uploads/*` diarahkan ke `public/uploads/*`
  - folder internal seperti `bootstrap`, `config`, `services`, `storage`, dan `.env` tidak bisa diakses dari web
- Penting:
  - folder `/home/hark8423/public_html/penggajian` harus benar-benar repository Git
  - kalau shared hosting tidak punya SSH, buat repository itu lewat fitur cPanel `Git Version Control`
  - kalau folder dibuat biasa lewat File Manager tanpa `.git`, script deploy tidak akan bisa `git pull`

## TODO LIST

- Mirror absensi
- Mirror validasi gaji
- Mirror generate gaji
- UI simplifikasi
- AJAX optimization
- Mirror halaman user
- Mirror halaman unit

## Catatan Implementasi

- Field `potongan_khusus` dipakai juga sebagai tempat override hutang manual agar tetap mengikuti struktur lama tanpa menambah tabel baru.
- Toleransi keterlambatan memakai fallback `users.toleransi_terlambat_menit` lalu `units.toleransi_terlambat_menit`. Jika override user kosong, sistem memakai setting global unit aktif.
- Formula payroll mengikuti logic project lama sejauh yang terwakili di schema SQL aktif.

## Belum Dimirror Sepenuhnya

- Detail slip dan laporan sekarang sudah dibuat lebih dekat ke format project lama, tetapi field rekening/bank tidak dimunculkan karena schema `hark8423_gaji.sql` tidak memiliki kolom tersebut.
- Modul upload foto user dan logo unit memakai penyimpanan sederhana `public/uploads`, belum memakai media manager seperti project Laravel lama.
