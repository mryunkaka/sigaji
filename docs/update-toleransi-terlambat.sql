ALTER TABLE `units`
    ADD COLUMN `toleransi_terlambat_menit` int(11) NOT NULL DEFAULT 0 AFTER `logo_unit`;

ALTER TABLE `users`
    ADD COLUMN `toleransi_terlambat_menit` int(11) DEFAULT NULL AFTER `jabatan`;
