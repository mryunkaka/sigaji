-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Mar 23, 2026 at 05:04 PM
-- Server version: 11.4.10-MariaDB-cll-lve
-- PHP Version: 8.4.18

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hark8423_gaji`
--

-- --------------------------------------------------------

--
-- Table structure for table `absensi`
--

CREATE TABLE `absensi` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `shift` bigint(20) DEFAULT NULL,
  `tanggal` date DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `jam_masuk` time DEFAULT NULL,
  `jam_keluar` time DEFAULT NULL,
  `total_menit_terlambat` int(11) NOT NULL DEFAULT 0,
  `jumlah_potongan` bigint(20) NOT NULL DEFAULT 0,
  `keterangan_potongan` varchar(255) DEFAULT NULL,
  `lembur` bigint(20) NOT NULL DEFAULT 0,
  `keterangan_lembur` varchar(255) DEFAULT NULL,
  `potongan_kehadiran` bigint(20) NOT NULL DEFAULT 0,
  `keterangan_kehadiran` varchar(255) DEFAULT NULL,
  `potongan_ijin` bigint(20) NOT NULL DEFAULT 0,
  `keterangan_ijin` varchar(255) DEFAULT NULL,
  `potongan_khusus` bigint(20) NOT NULL DEFAULT 0,
  `keterangan_khusus` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `master_gaji`
--

CREATE TABLE `master_gaji` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `gaji_pokok` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tunjangan_bbm` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tunjangan_makan` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tunjangan_jabatan` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tunjangan_kehadiran` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tunjangan_lainnya` decimal(12,2) NOT NULL DEFAULT 0.00,
  `potongan_terlambat` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pot_bpjs_jht` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pot_bpjs_kes` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000001_create_cache_table', 1),
(2, '0001_01_01_000002_create_jobs_table', 1),
(3, '2025_04_16_105537_create_all_tables', 1),
(4, '2025_04_16_112253_create_sessions_table', 1),
(5, '2025_09_02_075243_add_two_factor_columns_to_users_table', 1);

-- --------------------------------------------------------

--
-- Table structure for table `penggajian`
--

CREATE TABLE `penggajian` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `tanggal_awal_gaji` date NOT NULL,
  `tanggal_akhir_gaji` date NOT NULL,
  `gaji_bersih` bigint(20) NOT NULL DEFAULT 0,
  `gaji_pokok` bigint(20) NOT NULL DEFAULT 0,
  `gaji_kotor` bigint(20) NOT NULL DEFAULT 0,
  `tunjangan_bbm` bigint(20) NOT NULL DEFAULT 0,
  `tunjangan_makan` bigint(20) NOT NULL DEFAULT 0,
  `tunjangan_jabatan` bigint(20) NOT NULL DEFAULT 0,
  `tunjangan_kehadiran` bigint(20) NOT NULL DEFAULT 0,
  `tunjangan_lainnya` bigint(20) NOT NULL DEFAULT 0,
  `lembur` bigint(20) NOT NULL DEFAULT 0,
  `potongan_kehadiran` bigint(20) NOT NULL DEFAULT 0,
  `potongan_khusus` bigint(20) NOT NULL DEFAULT 0,
  `potongan_ijin` bigint(20) NOT NULL DEFAULT 0,
  `potongan_terlambat` bigint(20) NOT NULL DEFAULT 0,
  `pot_bpjs_jht` bigint(20) NOT NULL DEFAULT 0,
  `pot_bpjs_kes` bigint(20) NOT NULL DEFAULT 0,
  `total_potongan` bigint(20) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `units`
--

CREATE TABLE `units` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nama_unit` varchar(255) NOT NULL,
  `alamat_unit` text DEFAULT NULL,
  `no_hp_unit` varchar(255) DEFAULT NULL,
  `logo_unit` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `units`
--

INSERT INTO `units` (`id`, `nama_unit`, `alamat_unit`, `no_hp_unit`, `logo_unit`, `created_at`, `updated_at`) VALUES
(1, 'HOTEL HARMONY', 'Jl. Raya Batulicin, Kp. Baru, Kec. Simpang Empat, Kab. Tanah Bumbu, Kalimantan Selatan 72271', '087878987654', NULL, '2025-11-28 12:31:21', '2025-11-28 12:31:21'),
(2, 'GUESTHOUSE RUMA', 'Jl. Suryagandamana, Kotabaru Tengah, Kec. Pulau Laut, ...', '087877521992', NULL, '2025-11-28 12:31:21', '2025-11-28 12:31:21'),
(3, 'HOTEL GALERY', 'Jl. Pangeran Hidayat No.26, Sebatung, ...', '085827191234', NULL, '2025-11-28 12:31:21', '2025-11-28 12:31:21'),
(4, 'HOTEL KARTIKA', 'Jl. Veteran No.2, Dirgahayu, ...', '082150942567', NULL, '2025-11-28 12:31:21', '2025-11-28 12:31:21'),
(5, 'HOTEL LAVENDER', 'Jl. Raya provinsi No. km 163, Sungai Cuka, ...', '085289987654', NULL, '2025-11-28 12:31:21', '2025-11-28 12:31:21');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `two_factor_secret` text DEFAULT NULL,
  `two_factor_recovery_codes` text DEFAULT NULL,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `no_hp` varchar(255) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `tempat_lahir` varchar(255) DEFAULT NULL,
  `jenis_kelamin` enum('Laki-laki','Perempuan') DEFAULT NULL,
  `agama` varchar(255) DEFAULT NULL,
  `status_perkawinan` varchar(255) DEFAULT NULL,
  `nik` varchar(255) DEFAULT NULL,
  `npwp` varchar(255) DEFAULT NULL,
  `jabatan` varchar(255) DEFAULT NULL,
  `role` varchar(255) NOT NULL DEFAULT 'karyawan',
  `unit_id` bigint(20) UNSIGNED NOT NULL,
  `tanggal_bergabung` date DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`, `no_hp`, `alamat`, `foto`, `tanggal_lahir`, `tempat_lahir`, `jenis_kelamin`, `agama`, `status_perkawinan`, `nik`, `npwp`, `jabatan`, `role`, `unit_id`, `tanggal_bergabung`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'John Doe', 'owner@example.com', '$2y$12$oKdV1yvh6TPvcljnfdUJ6eX4Vh6n5xAPD/mE01mADh7M6WNsj6Kvq', NULL, NULL, NULL, '081234567890', 'Jl. Raya Batulicin, Kp. Baru, Kec. Simpang Empat, Kabupaten Tanah Bumbu, Kalimantan Selatan 72271', NULL, '1980-01-01', 'Batulicin', 'Laki-laki', 'Islam', 'Menikah', '1234567890123456', '1234567890', 'Owner', 'owner', 1, '2020-01-01', NULL, '2025-11-28 12:31:21', '2025-11-28 12:31:21'),
(2, 'Admin', 'admin@example.com', '$2y$12$.5Xsd5UqzJRGTgtAl0DI8O9oJJnPaaI6H4O.1Gz0h8QpAZlzKcOOS', NULL, NULL, NULL, '081234567891', 'Jl. Raya Batulicin, Kp. Baru, Kec. Simpang Empat, Kabupaten Tanah Bumbu, Kalimantan Selatan 72271', NULL, '1990-05-10', 'Kotabaru', 'Perempuan', 'Kristen', 'Belum Menikah', '1234567890123457', '1234567891', 'Personalia', 'owner', 1, '2021-03-15', NULL, '2025-11-28 12:31:21', '2025-11-28 12:31:21');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `absensi`
--
ALTER TABLE `absensi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `absensi_user_id_foreign` (`user_id`);

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `master_gaji`
--
ALTER TABLE `master_gaji`
  ADD PRIMARY KEY (`id`),
  ADD KEY `master_gaji_user_id_foreign` (`user_id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `penggajian`
--
ALTER TABLE `penggajian`
  ADD PRIMARY KEY (`id`),
  ADD KEY `penggajian_user_id_foreign` (`user_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `units`
--
ALTER TABLE `units`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD KEY `users_unit_id_foreign` (`unit_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `absensi`
--
ALTER TABLE `absensi`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `master_gaji`
--
ALTER TABLE `master_gaji`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `penggajian`
--
ALTER TABLE `penggajian`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `units`
--
ALTER TABLE `units`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `absensi`
--
ALTER TABLE `absensi`
  ADD CONSTRAINT `absensi_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `master_gaji`
--
ALTER TABLE `master_gaji`
  ADD CONSTRAINT `master_gaji_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `penggajian`
--
ALTER TABLE `penggajian`
  ADD CONSTRAINT `penggajian_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
