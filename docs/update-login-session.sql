ALTER TABLE `users`
    ADD COLUMN `session_login_token` varchar(255) DEFAULT NULL AFTER `remember_token`;
