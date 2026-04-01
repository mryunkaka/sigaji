ALTER TABLE `users`
    ADD COLUMN `default_shift` int(11) DEFAULT NULL AFTER `toleransi_terlambat_menit`,
    ADD COLUMN `jam_masuk_default` time DEFAULT NULL AFTER `default_shift`,
    ADD COLUMN `jam_keluar_default` time DEFAULT NULL AFTER `jam_masuk_default`;

CREATE TABLE `shift_rules` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `unit_id` bigint(20) unsigned NOT NULL,
    `scope_type` varchar(20) NOT NULL,
    `jabatan` varchar(255) NOT NULL DEFAULT '',
    `shift_code` int(11) NOT NULL,
    `jam_masuk` time NOT NULL,
    `jam_keluar` time NOT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `shift_rules_unit_id_foreign` (`unit_id`),
    UNIQUE KEY `shift_rules_unique_scope` (`unit_id`, `scope_type`, `jabatan`, `shift_code`),
    CONSTRAINT `shift_rules_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE
);
