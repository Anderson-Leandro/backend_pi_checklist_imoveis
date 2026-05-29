-- Migration 002
ALTER TABLE usuario MODIFY COLUMN mfa_secret VARCHAR(255) NULL;

CREATE TABLE IF NOT EXISTS refresh_token (
    id         CHAR(36)     NOT NULL,
    usuario_id CHAR(36)     NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME     NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_refresh_token_hash (token_hash),
    CONSTRAINT fk_refresh_token_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuario(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
