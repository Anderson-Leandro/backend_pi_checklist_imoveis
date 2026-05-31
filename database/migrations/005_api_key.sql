-- Migration 005 — Tabela de API Keys para acesso externo autenticado

CREATE TABLE IF NOT EXISTS api_key (
    id         CHAR(36)     NOT NULL,
    nome       VARCHAR(100) NOT NULL,
    key_hash   VARCHAR(255) NOT NULL,
    ativo      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_api_key_hash (key_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
