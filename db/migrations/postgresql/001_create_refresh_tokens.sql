-- M2.a: refresh_tokens tábla
-- A doctrine/migrations integráció M4.a-ban érkezik; addig kézzel futtatandó.

CREATE TABLE IF NOT EXISTS refresh_tokens (
    id            BIGSERIAL PRIMARY KEY,
    user_id       BIGINT      NOT NULL,
    family_id     VARCHAR(64) NOT NULL,
    token_hash    CHAR(64)    NOT NULL,
    rotated_from  BIGINT      NULL,
    expires_at    TIMESTAMP WITH TIME ZONE NOT NULL,
    revoked_at    TIMESTAMP WITH TIME ZONE NULL,
    user_agent    TEXT        NULL,
    ip            VARCHAR(45) NULL,
    created_at    TIMESTAMP WITH TIME ZONE NOT NULL,
    CONSTRAINT refresh_tokens_hash_unique UNIQUE (token_hash)
);

CREATE INDEX IF NOT EXISTS refresh_tokens_user_id_idx   ON refresh_tokens (user_id);
CREATE INDEX IF NOT EXISTS refresh_tokens_family_id_idx ON refresh_tokens (family_id);
CREATE INDEX IF NOT EXISTS refresh_tokens_expires_idx   ON refresh_tokens (expires_at);
