ALTER TABLE `units`
    ADD COLUMN `hari_mulai_periode` int(11) NOT NULL DEFAULT 26 AFTER `toleransi_terlambat_menit`,
    ADD COLUMN `hari_akhir_periode` int(11) NOT NULL DEFAULT 25 AFTER `hari_mulai_periode`;
