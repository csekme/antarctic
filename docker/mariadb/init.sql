-- initial script

-- create user table
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

-- add unique constraints
ALTER TABLE `user`
    ADD UNIQUE KEY `UQ_USERNAME` (`username`),
    ADD UNIQUE KEY `UQ_EMAIL` (`email`),
    ADD UNIQUE KEY `UQ_PASSWORD_RESET_HASH` (`password_reset_hash`),
    ADD UNIQUE KEY `UQ_ACTIVATION_HASH` (`activation_hash`);

-- create remembered_logins table
CREATE TABLE `remembered_logins` (
     `token_hash` varchar(64) NOT NULL,
     `user_id` int(11) DEFAULT NULL,
     `expires_at` datetime DEFAULT NULL
);

-- add unique constraints
ALTER TABLE `remembered_logins`
    ADD PRIMARY KEY (`token_hash`),
    ADD KEY `fk_rmb_uid_idx` (`user_id`);

-- add foreign key constraints
ALTER TABLE `remembered_logins`
    ADD CONSTRAINT `fk_rmb_uid` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;


-- create role table
CREATE TABLE `role` (
     `id` int(11) AUTO_INCREMENT PRIMARY KEY,
     `name` varchar(45) DEFAULT NULL,
     `description` varchar(255) DEFAULT NULL
);

-- add unique constraints
ALTER TABLE `role`
    ADD UNIQUE KEY `UQ_ROLE_NAME` (`name`);

-- create user_role table
CREATE TABLE `user_role` (
     `user_id` int(11) DEFAULT NULL,
     `role_id` int(11) DEFAULT NULL
);

-- add foreign key constraints
ALTER TABLE `user_role`
    ADD KEY `fk_ur_uid_idx` (`user_id`),
    ADD KEY `fk_ur_rid_idx` (`role_id`);

-- add foreign key constraints
ALTER TABLE `user_role`
    ADD CONSTRAINT `fk_ur_uid` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_ur_rid` FOREIGN KEY (`role_id`) REFERENCES `role` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- add basic roles
insert into role (name, description) values ('ROLE_USER', 'User Role');
insert into role (name, description) values ('ROLE_ADMIN', 'Admin Role');