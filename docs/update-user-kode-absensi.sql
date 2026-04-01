ALTER TABLE `users`
    ADD COLUMN `kode_absensi` varchar(100) DEFAULT NULL AFTER `no_hp`,
    ADD COLUMN `tanggal_resign` date DEFAULT NULL AFTER `tanggal_bergabung`,
    ADD UNIQUE KEY `users_unit_kode_absensi_unique` (`unit_id`, `kode_absensi`);
