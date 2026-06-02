-- Migration 006 — Adicionar coluna ativo à tabela endereco para soft delete
ALTER TABLE endereco ADD COLUMN ativo TINYINT(1) NOT NULL DEFAULT 1 AFTER longitude;
