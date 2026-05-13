-- -----------------------------------------------
-- MedBoard — Schema do banco de dados
-- Charset: utf8mb4
-- -----------------------------------------------

CREATE DATABASE IF NOT EXISTS sistema_medico
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE sistema_medico;

-- USUÁRIOS
CREATE TABLE usuarios (
    id_usuario    INT          AUTO_INCREMENT PRIMARY KEY,
    nome          VARCHAR(120) NOT NULL,
    email         VARCHAR(150) NOT NULL,
    senha         VARCHAR(255) NOT NULL,
    idioma        VARCHAR(10)  NOT NULL DEFAULT 'pt-BR',
    criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_usuarios_email UNIQUE (email)
) ENGINE=InnoDB;

-- LOCAIS DE TRABALHO
CREATE TABLE locais_trabalho (
    id_local          INT          AUTO_INCREMENT PRIMARY KEY,
    id_usuario        INT          NOT NULL,
    nome              VARCHAR(120) NOT NULL,
    endereco          VARCHAR(180) NULL,
    bairro            VARCHAR(100) NULL,
    cidade            VARCHAR(100) NOT NULL,
    observacao        TEXT         NULL,
    cor_identificacao VARCHAR(7)   NOT NULL DEFAULT '#3B82F6',
    ativo             TINYINT(1)   NOT NULL DEFAULT 1,
    criado_em         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_locais_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_locais_ativo
        CHECK (ativo IN (0, 1))
) ENGINE=InnoDB;

-- AGENDA DE TRABALHO
CREATE TABLE agenda_trabalho (
    id_agenda     INT         AUTO_INCREMENT PRIMARY KEY,
    id_usuario    INT         NOT NULL,
    id_local      INT         NOT NULL,
    data_trabalho DATE        NOT NULL,
    data_fim      DATE        NULL DEFAULT NULL,
    turno         VARCHAR(20) NOT NULL,
    hora_inicio   TIME        NOT NULL,
    hora_fim      TIME        NOT NULL,
    status_agenda VARCHAR(20) NOT NULL DEFAULT 'agendado',
    observacao    TEXT        NULL,
    criado_em     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_agenda_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_agenda_local
        FOREIGN KEY (id_local) REFERENCES locais_trabalho(id_local)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_agenda_turno
        CHECK (turno IN ('manha','tarde','noite','manha/tarde','tarde/noite','manha/tarde/noite')),
    CONSTRAINT chk_agenda_status
        CHECK (status_agenda IN ('agendado','concluido','cancelado','folga'))
) ENGINE=InnoDB;

-- REGISTRO DIÁRIO
CREATE TABLE registro_diario (
    id_registro          INT         AUTO_INCREMENT PRIMARY KEY,
    id_agenda            INT         NOT NULL,
    pacientes_atendidos  INT         NOT NULL DEFAULT 0,
    faltas               INT         NOT NULL DEFAULT 0,
    cancelamentos        INT         NOT NULL DEFAULT 0,
    encaixes             INT         NOT NULL DEFAULT 0,
    tempo_medio_consulta INT         NULL,
    hora_real_inicio     TIME        NULL,
    hora_real_fim        TIME        NULL,
    duracao_real_minutos INT         NULL,
    situacao_dia         VARCHAR(30) NOT NULL DEFAULT 'realizado',
    observacao           TEXT        NULL,
    criado_em            DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em        DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_registro_id_agenda
        UNIQUE (id_agenda),
    CONSTRAINT fk_registro_agenda
        FOREIGN KEY (id_agenda) REFERENCES agenda_trabalho(id_agenda)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_registro_pacientes
        CHECK (pacientes_atendidos >= 0),
    CONSTRAINT chk_registro_situacao
        CHECK (situacao_dia IN ('realizado','realizado_parcialmente','nao_realizado','substituido'))
) ENGINE=InnoDB;

-- LEMBRETES
CREATE TABLE lembretes (
    id_lembrete     INT          AUTO_INCREMENT PRIMARY KEY,
    id_usuario      INT          NOT NULL,
    titulo          VARCHAR(120) NOT NULL,
    descricao       TEXT         NULL,
    data_lembrete   DATE         NOT NULL,
    hora_lembrete   TIME         NULL,
    status_lembrete VARCHAR(20)  NOT NULL DEFAULT 'pendente',
    criado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_lembretes_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_lembretes_status
        CHECK (status_lembrete IN ('pendente','concluido'))
) ENGINE=InnoDB;

-- ÍNDICES
CREATE INDEX idx_locais_usuario_ativo
    ON locais_trabalho (id_usuario, ativo);
CREATE INDEX idx_agenda_usuario_data
    ON agenda_trabalho (id_usuario, data_trabalho);
CREATE INDEX idx_agenda_local_data
    ON agenda_trabalho (id_local, data_trabalho);
CREATE INDEX idx_agenda_status_data
    ON agenda_trabalho (status_agenda, data_trabalho);
CREATE INDEX idx_lembretes_usuario_data_status
    ON lembretes (id_usuario, data_lembrete, status_lembrete);