-- Migration 003 — Adicionar coluna ativo à tabela usuario para soft delete
ALTER TABLE usuario ADD COLUMN ativo TINYINT(1) NOT NULL DEFAULT 1 AFTER mfa_ativo;
