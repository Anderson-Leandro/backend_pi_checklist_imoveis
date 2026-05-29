-- ============================================================
-- Migration 001 — Criação de todas as tabelas do One Check
-- Executar na ordem abaixo para respeitar as FKs
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ─── Usuário ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS usuario (
    id         CHAR(36)     NOT NULL,
    nome       VARCHAR(150) NOT NULL,
    email      VARCHAR(150) NOT NULL,
    senha_hash VARCHAR(255) NOT NULL,
    role       ENUM('admin','vistoriador','locatario') NOT NULL,
    mfa_secret VARCHAR(100) NULL,
    mfa_ativo  TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuario_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Imóvel ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS imovel (
    id            CHAR(36)    NOT NULL,
    tipo          VARCHAR(80) NOT NULL,
    tamanho       VARCHAR(80) NOT NULL,
    garagem       TINYINT(1)  NOT NULL DEFAULT 0,
    garagem_vagas INT         NOT NULL DEFAULT 0,
    status        ENUM('disponivel','locado','em_vistoria') NOT NULL DEFAULT 'disponivel',
    created_at    DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Endereço ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS endereco (
    id          CHAR(36)      NOT NULL,
    imovel_id   CHAR(36)      NOT NULL,
    rua         VARCHAR(200)  NOT NULL,
    numero      VARCHAR(20)   NOT NULL,
    complemento VARCHAR(100)  NULL,
    bloco       VARCHAR(50)   NULL,
    andar       VARCHAR(50)   NULL,
    cidade      VARCHAR(100)  NOT NULL,
    estado      CHAR(2)       NOT NULL,
    cep         CHAR(9)       NOT NULL,
    latitude    DECIMAL(10,7) NULL,
    longitude   DECIMAL(10,7) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_endereco_imovel (imovel_id),
    CONSTRAINT fk_endereco_imovel FOREIGN KEY (imovel_id) REFERENCES imovel(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Cômodo ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS comodo (
    id        CHAR(36)    NOT NULL,
    imovel_id CHAR(36)    NOT NULL,
    tipo      VARCHAR(80) NOT NULL,
    descricao TEXT        NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_comodo_imovel FOREIGN KEY (imovel_id) REFERENCES imovel(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Item de vistoria ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS item_vistoria (
    id        CHAR(36)    NOT NULL,
    nome      VARCHAR(100) NOT NULL,
    categoria VARCHAR(80)  NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Contrato ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS contrato (
    id           CHAR(36) NOT NULL,
    imovel_id    CHAR(36) NOT NULL,
    locatario_id CHAR(36) NOT NULL,
    data_inicio  DATE     NOT NULL,
    data_fim     DATE     NOT NULL,
    status       ENUM('ativo','encerrado','cancelado') NOT NULL DEFAULT 'ativo',
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_contrato_imovel    FOREIGN KEY (imovel_id)    REFERENCES imovel(id),
    CONSTRAINT fk_contrato_locatario FOREIGN KEY (locatario_id) REFERENCES usuario(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Checklist ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS checklist (
    id             CHAR(36) NOT NULL,
    contrato_id    CHAR(36) NOT NULL,
    vistoriador_id CHAR(36) NOT NULL,
    tipo           ENUM('inicial','encerramento') NOT NULL,
    status         ENUM('em_preenchimento','pendente_aceite','aceito','rejeitado','pendente_revisao') NOT NULL DEFAULT 'em_preenchimento',
    data_vistoria  DATETIME NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_checklist_contrato    FOREIGN KEY (contrato_id)    REFERENCES contrato(id),
    CONSTRAINT fk_checklist_vistoriador FOREIGN KEY (vistoriador_id) REFERENCES usuario(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Item do checklist ────────────────────────────────────
CREATE TABLE IF NOT EXISTS checklist_item (
    id               CHAR(36) NOT NULL,
    checklist_id     CHAR(36) NOT NULL,
    comodo_id        CHAR(36) NOT NULL,
    item_vistoria_id CHAR(36) NOT NULL,
    estado           ENUM('otimo','bom','regular','ruim') NOT NULL,
    observacao       TEXT     NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_checklist_item_checklist     FOREIGN KEY (checklist_id)     REFERENCES checklist(id),
    CONSTRAINT fk_checklist_item_comodo        FOREIGN KEY (comodo_id)        REFERENCES comodo(id),
    CONSTRAINT fk_checklist_item_item_vistoria FOREIGN KEY (item_vistoria_id) REFERENCES item_vistoria(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Foto do checklist ────────────────────────────────────
CREATE TABLE IF NOT EXISTS foto_checklist (
    id                CHAR(36)     NOT NULL,
    checklist_item_id CHAR(36)     NOT NULL,
    url               VARCHAR(500) NOT NULL,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_foto_checklist_item FOREIGN KEY (checklist_item_id) REFERENCES checklist_item(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Aceite do checklist ──────────────────────────────────
CREATE TABLE IF NOT EXISTS aceite_checklist (
    id              CHAR(36) NOT NULL,
    checklist_id    CHAR(36) NOT NULL,
    locatario_id    CHAR(36) NOT NULL,
    status          ENUM('aceito','rejeitado') NOT NULL,
    motivo_rejeicao TEXT     NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_aceite_checklist (checklist_id),
    CONSTRAINT fk_aceite_checklist  FOREIGN KEY (checklist_id) REFERENCES checklist(id),
    CONSTRAINT fk_aceite_locatario  FOREIGN KEY (locatario_id) REFERENCES usuario(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Registro de problema ─────────────────────────────────
CREATE TABLE IF NOT EXISTS registro_problema (
    id          CHAR(36)     NOT NULL,
    contrato_id CHAR(36)     NOT NULL,
    comodo_id   CHAR(36)     NOT NULL,
    titulo      VARCHAR(150) NOT NULL,
    descricao   TEXT         NOT NULL,
    foto_url    VARCHAR(500) NULL,
    status      ENUM('aberto','em_andamento','resolvido') NOT NULL DEFAULT 'aberto',
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_problema_contrato FOREIGN KEY (contrato_id) REFERENCES contrato(id),
    CONSTRAINT fk_problema_comodo   FOREIGN KEY (comodo_id)   REFERENCES comodo(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Atualização de problema ──────────────────────────────
CREATE TABLE IF NOT EXISTS atualizacao_problema (
    id          CHAR(36)     NOT NULL,
    problema_id CHAR(36)     NOT NULL,
    autor_id    CHAR(36)     NOT NULL,
    descricao   TEXT         NOT NULL,
    foto_url    VARCHAR(500) NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_atualizacao_problema FOREIGN KEY (problema_id) REFERENCES registro_problema(id),
    CONSTRAINT fk_atualizacao_autor    FOREIGN KEY (autor_id)    REFERENCES usuario(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Agendamento de vistoria ──────────────────────────────
CREATE TABLE IF NOT EXISTS agendamento_vistoria (
    id            CHAR(36) NOT NULL,
    contrato_id   CHAR(36) NOT NULL,
    tipo          ENUM('inicial','encerramento') NOT NULL,
    data_agendada DATETIME NOT NULL,
    observacao    TEXT     NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_agendamento_contrato FOREIGN KEY (contrato_id) REFERENCES contrato(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Log de operação ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS log_operacao (
    id          CHAR(36)    NOT NULL,
    usuario_id  CHAR(36)    NULL,
    acao        VARCHAR(50) NOT NULL,
    entidade    VARCHAR(80) NOT NULL,
    entidade_id CHAR(36)    NULL,
    payload     JSON        NULL,
    ip          VARCHAR(45) NULL,
    created_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_log_usuario FOREIGN KEY (usuario_id) REFERENCES usuario(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
