-- Migration 004 — Adicionar coluna ativo à tabela imovel para soft delete
ALTER TABLE imovel ADD COLUMN ativo TINYINT(1) NOT NULL DEFAULT 1 AFTER status;
