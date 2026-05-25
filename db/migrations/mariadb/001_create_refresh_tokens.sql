-- M2.a: refresh_tokens tábla
-- MariaDB / MySQL 8+ syntax. A doctrine/migrations M4.a-ban érkezik.

CREATE TABLE IF NOT EXISTS refresh_tokens (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id       BIGINT UNSIGNED NOT NULL,
    family_id     VARCHAR(64)     NOT NULL,
    token_hash    CHAR(64)        NOT NULL,
    rotated_from  BIGINT UNSIGNED NULL,
    expires_at    DATETIME(6)     NOT NULL,
    revoked_at    DATETIME(6)     NULL,
    user_agent    TEXT            NULL,
    ip            VARCHAR(45)     NULL,
    created_at    DATETIME(6)     NOT NULL,
    UNIQUE KEY refresh_tokens_hash_unique (token_hash),
    KEY refresh_tokens_user_id_idx   (user_id),
    KEY refresh_tokens_family_id_idx (family_id),
    KEY refresh_tokens_expires_idx   (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
