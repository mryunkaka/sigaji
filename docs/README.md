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
- `components/`: button, modal, table, icon, panel, field, badge, stat
- `services/`: auth, import absensi, payroll calculation
- `bootstrap/`: bootstrap app, helper, session, db
- `docs/README.md`: catatan mirror dan cara jalan

## Cara Menjalankan

1. Buat file `.env` dari `.env.example`.
2. Isi koneksi database ke schema `hark8423_gaji`.
3. Import SQL `hark8423_gaji.sql` ke MySQL.
4. Jalankan lewat web server PHP biasa, misalnya document root ke folder ini.
5. Login memakai user yang ada pada tabel `users`.

## TODO LIST

- Mirror absensi
- Mirror validasi gaji
- Mirror generate gaji
- UI simplifikasi
- AJAX optimization

## Catatan Implementasi

- Field `potongan_khusus` dipakai juga sebagai tempat override hutang manual agar tetap mengikuti struktur lama tanpa menambah tabel baru.
- Formula payroll mengikuti logic project lama sejauh yang terwakili di schema SQL aktif.
