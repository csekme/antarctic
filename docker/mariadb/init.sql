-- initial script

/**
 * Default Signup Model
 */
CREATE TABLE `user` (
     `id` int(11) AUTO_INCREMENT PRIMARY KEY,
     `uuid` UUID NOT NULL,
     `username` varchar(45) DEFAULT NULL,
     `firstname` varchar(45) DEFAULT NULL,
     `lastname` varchar(45) DEFAULT NULL,
     `email` varchar(255) DEFAULT NULL,
     `password_hash` varchar(255) DEFAULT NULL,
     `activation_hash` varchar(64) DEFAULT NULL,
     `is_active` tinyint(4) DEFAULT 0,
     `password_reset_hash` varchar(64) DEFAULT NULL,
     `password_reset_expires_at` datetime DEFAULT NULL,
     `created_at` datetime DEFAULT current_timestamp(),
     `updated_at` datetime DEFAULT null,
     `allowed`  tinyint(4) DEFAULT 0
);

ALTER TABLE `user`
    ADD UNIQUE KEY `UQ_USERNAME` (`username`),
    ADD UNIQUE KEY `UQ_EMAIL` (`email`),
    ADD UNIQUE KEY `UQ_PASSWORD_RESET_HASH` (`password_reset_hash`),
    ADD UNIQUE KEY `UQ_ACTIVATION_HASH` (`activation_hash`);