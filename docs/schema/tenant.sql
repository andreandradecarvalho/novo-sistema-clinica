-- =====================================================================
--  SaaS multi-tenant para clínicas de exames — BANCO DO TENANT (clínica)
--  [Confirmado] Versões de destino: docs/fluxos/14-arquitetura-laravel.md, seção 14.1.
--  [Confirmado] DDL existente: InnoDB · utf8mb4 / utf8mb4_0900_ai_ci.
--  [Confirmado] Homologação em MySQL/MariaDB de destino pendente; ver CHANGELOG.md.
--  Um banco por clínica (estratégia C, doc 09): por isso NÃO há coluna
--  tenant_id — o banco já é do tenant. Multiunidade usa `units`.
--  Dado clínico é sensível (LGPD art. 11): soft delete, auditoria e
--  log de acesso a laudo/anamnese são obrigatórios.
--  Convenções: id BIGINT PK; public_id CHAR(26) ULID em URL/API;
--  enums como VARCHAR (nunca ENUM MySQL); datas em DATE/DATETIME;
--  dinheiro em DECIMAL; legacy_* para idempotência/conferência do ETL.
--  Sem trigger/procedure/event — toda regra fica na aplicação.
--  Gerado em 31/10/2026. Modelo: doc 12; decisões reaproveitadas: doc 21.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================================
--  IDENTIDADE, ACESSO E AUDITORIA
-- =====================================================================

-- Unidades da PRÓPRIA clínica (multiunidade). Não confundir com companies (clientes).
CREATE TABLE `units` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id`  CHAR(26) NOT NULL,
  `name`       VARCHAR(150) NOT NULL,
  `city`       VARCHAR(120) NULL,
  `state`      CHAR(2) NULL,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  `deleted_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_units_public` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Usuários internos (recepção, médico/laudador, técnico, financeiro, admin da clínica)
CREATE TABLE `users` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id`          CHAR(26) NOT NULL,
  `unit_id`            BIGINT UNSIGNED NULL,
  `name`               VARCHAR(180) NOT NULL,
  `email`              VARCHAR(180) NOT NULL,
  `email_verified_at`  DATETIME NULL,
  `password`           VARCHAR(255) NOT NULL,          -- bcrypt/argon2
  `must_change_password` TINYINT(1) NOT NULL DEFAULT 1,-- migração força redefinição
  `cpf`                CHAR(11) NULL,
  `two_factor_secret`  TEXT NULL,                       -- 2FA obrigatório p/ acesso clínico
  `two_factor_recovery_codes` TEXT NULL,
  `two_factor_confirmed_at` DATETIME NULL,
  `is_active`          TINYINT(1) NOT NULL DEFAULT 1,
  `last_login_at`      DATETIME NULL,
  `last_login_ip`      VARCHAR(45) NULL,
  `legacy_id`          BIGINT UNSIGNED NULL,            -- usuario.PKId
  `remember_token`     VARCHAR(100) NULL,
  `created_at`         TIMESTAMP NULL,
  `updated_at`         TIMESTAMP NULL,
  `deleted_at`         TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_public` (`public_id`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_cpf` (`cpf`),
  KEY `ix_users_legacy` (`legacy_id`),
  KEY `ix_users_unit` (`unit_id`),
  CONSTRAINT `fk_users_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Perfil clínico do profissional (CRM, especialidade) — 1:1 opcional com users
CREATE TABLE `professionals` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     BIGINT UNSIGNED NOT NULL,
  `council`     VARCHAR(10) NULL,                       -- CRM, CRO...
  `council_uf`  CHAR(2) NULL,
  `council_number` VARCHAR(20) NULL,
  `specialty`   VARCHAR(60) NULL,
  `created_at`  TIMESTAMP NULL,
  `updated_at`  TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_professionals_user` (`user_id`),
  CONSTRAINT `fk_professionals_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ACL — Spatie laravel-permission (teams=tenant; aqui, por banco, sem team_id)
CREATE TABLE `roles` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(125) NOT NULL,
  `guard_name`  VARCHAR(125) NOT NULL,
  `created_at`  TIMESTAMP NULL,
  `updated_at`  TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `permissions` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(125) NOT NULL,                   -- agenda.view, laudo.sign...
  `guard_name`  VARCHAR(125) NOT NULL,
  `created_at`  TIMESTAMP NULL,
  `updated_at`  TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `model_has_roles` (
  `role_id`    BIGINT UNSIGNED NOT NULL,
  `model_type` VARCHAR(190) NOT NULL,
  `model_id`   BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `ix_mhr_model` (`model_id`,`model_type`),
  CONSTRAINT `fk_mhr_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `model_has_permissions` (
  `permission_id` BIGINT UNSIGNED NOT NULL,
  `model_type`    VARCHAR(190) NOT NULL,
  `model_id`      BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `ix_mhp_model` (`model_id`,`model_type`),
  CONSTRAINT `fk_mhp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `role_has_permissions` (
  `permission_id` BIGINT UNSIGNED NOT NULL,
  `role_id`       BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  CONSTRAINT `fk_rhp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rhp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Auditoria (Spatie Activitylog) + log dedicado de acesso a dado clínico (LGPD)
CREATE TABLE `activity_log` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `log_name`     VARCHAR(120) NULL,
  `description`  TEXT NOT NULL,
  `subject_type` VARCHAR(190) NULL,
  `subject_id`   BIGINT UNSIGNED NULL,
  `causer_type`  VARCHAR(190) NULL,
  `causer_id`    BIGINT UNSIGNED NULL,
  `event`        VARCHAR(60) NULL,
  `batch_uuid`   CHAR(36) NULL,
  `properties`   JSON NULL,
  `created_at`   TIMESTAMP NULL,
  `updated_at`   TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `ix_activity_subject` (`subject_type`,`subject_id`),
  KEY `ix_activity_causer` (`causer_type`,`causer_id`),
  KEY `ix_activity_log_name` (`log_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================================
--  CADASTROS
-- =====================================================================

-- Empresas clientes / convênios (hierárquico). É o cliente PJ, não a unidade da clínica.
CREATE TABLE `companies` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id`   CHAR(26) NOT NULL,
  `parent_id`   BIGINT UNSIGNED NULL,
  `name`        VARCHAR(180) NOT NULL,
  `slug`        VARCHAR(180) NOT NULL,
  `cnpj`        CHAR(14) NULL,
  `type`        VARCHAR(20) NOT NULL DEFAULT 'cliente', -- cliente|convenio
  `storage_prefix` VARCHAR(190) NULL,
  `requires_registration_number` TINYINT(1) NOT NULL DEFAULT 0, -- regra "matrícula obrigatória" (ex-SAMARCO), agora por empresa
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `legacy_id`   BIGINT UNSIGNED NULL,                   -- empresas.em_id
  `created_at`  TIMESTAMP NULL,
  `updated_at`  TIMESTAMP NULL,
  `deleted_at`  TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_companies_public` (`public_id`),
  UNIQUE KEY `uq_companies_slug` (`slug`),
  UNIQUE KEY `uq_companies_cnpj` (`cnpj`),
  KEY `ix_companies_parent` (`parent_id`),
  KEY `ix_companies_legacy` (`legacy_id`),
  CONSTRAINT `fk_companies_parent` FOREIGN KEY (`parent_id`) REFERENCES `companies` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Unidades/filiais da empresa cliente
CREATE TABLE `company_units` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `name`       VARCHAR(180) NOT NULL,
  `city`       VARCHAR(120) NULL,
  `state`      CHAR(2) NULL,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `ix_company_units_company` (`company_id`,`is_active`),
  CONSTRAINT `fk_company_units_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Pacientes — identidade única (resolve o vínculo-por-nome do legado)
CREATE TABLE `patients` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id`        CHAR(26) NOT NULL,
  `user_id`          BIGINT UNSIGNED NULL,               -- se o paciente tiver login interno (raro)
  `company_id`       BIGINT UNSIGNED NULL,
  `company_unit_id`  BIGINT UNSIGNED NULL,
  `legacy_code`      VARCHAR(60) NULL,                   -- "código" (senha de 5 díg. do legado)
  `legacy_id`        BIGINT UNSIGNED NULL,               -- usuario.PKId
  `name`             VARCHAR(180) NOT NULL,
  `normalized_name`  VARCHAR(180) NOT NULL,              -- preenchido por observer; dedupe
  `cpf`              CHAR(11) NULL,                      -- único quando presente; 38% do legado sem CPF
  `rg`               VARCHAR(30) NULL,
  `birth_date`       DATE NULL,                          -- legado é varchar em formatos mistos
  `sex`              VARCHAR(12) NULL,                   -- M|F|O
  `email`            VARCHAR(180) NULL,
  `mobile_phone`     VARCHAR(20) NULL,
  `home_phone`       VARCHAR(20) NULL,
  `work_phone`       VARCHAR(20) NULL,
  `registration_number` VARCHAR(50) NULL,                -- matrícula
  `job_title`        VARCHAR(150) NULL,
  `occupation`       VARCHAR(150) NULL,
  `manager_name`     VARCHAR(180) NULL,
  `marital_status`   VARCHAR(30) NULL,
  `birthplace`       VARCHAR(150) NULL,
  `address_line`     VARCHAR(255) NULL,
  `district`         VARCHAR(120) NULL,
  `city`             VARCHAR(120) NULL,
  `state`            CHAR(2) NULL,
  `zipcode`          CHAR(8) NULL,
  `is_first_exam`    TINYINT(1) NOT NULL DEFAULT 1,
  `is_partial`       TINYINT(1) NOT NULL DEFAULT 0,      -- "paciente parcial" do ETL (agendamento sem cadastro)
  `possible_duplicate_of_id` BIGINT UNSIGNED NULL,       -- fila de revisão de dedupe
  `terms_accepted_at`   DATETIME NULL,                   -- consentimento LGPD
  `terms_accepted_text` TEXT NULL,
  `terms_accepted_ip`   VARCHAR(45) NULL,
  `password`         VARCHAR(255) NULL,                  -- portal do paciente
  `remember_token`   VARCHAR(100) NULL,
  `portal_last_login_at` DATETIME NULL,
  `created_at`       TIMESTAMP NULL,
  `updated_at`       TIMESTAMP NULL,
  `deleted_at`       TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_patients_public` (`public_id`),
  UNIQUE KEY `uq_patients_cpf` (`cpf`),
  KEY `ix_patients_norm_birth` (`normalized_name`,`birth_date`),
  KEY `ix_patients_company` (`company_id`,`deleted_at`),
  KEY `ix_patients_legacy_code` (`legacy_code`),
  KEY `ix_patients_legacy_id` (`legacy_id`),
  KEY `ix_patients_email` (`email`),
  KEY `ix_patients_dup` (`possible_duplicate_of_id`),
  FULLTEXT KEY `ft_patients_name` (`name`),
  CONSTRAINT `fk_patients_user`    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_patients_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_patients_cunit`   FOREIGN KEY (`company_unit_id`) REFERENCES `company_units` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_patients_dup`     FOREIGN KEY (`possible_duplicate_of_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Histórico de fusão de pacientes (dedupe)
CREATE TABLE `patient_merges` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `survivor_patient_id` BIGINT UNSIGNED NOT NULL,
  `merged_patient_id`  BIGINT UNSIGNED NOT NULL,
  `merged_by`          BIGINT UNSIGNED NULL,
  `payload_snapshot`   JSON NULL,
  `reason`             TEXT NULL,
  `created_at`         TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `ix_merges_pair` (`survivor_patient_id`,`merged_patient_id`),
  CONSTRAINT `fk_merges_survivor` FOREIGN KEY (`survivor_patient_id`) REFERENCES `patients` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_merges_merged`   FOREIGN KEY (`merged_patient_id`) REFERENCES `patients` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_merges_user`     FOREIGN KEY (`merged_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================================
--  CATÁLOGO DE EXAMES
-- =====================================================================
CREATE TABLE `exam_types` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id`     CHAR(26) NOT NULL,
  `code`          VARCHAR(20) NOT NULL,                  -- AUDIO, CARDIO, LAB... consolida checkincliente+espelho
  `name`          VARCHAR(120) NOT NULL,
  `scope`         VARCHAR(20) NOT NULL DEFAULT 'exame',  -- ExamScope
  `requires_fasting` TINYINT(1) NOT NULL DEFAULT 0,
  `default_duration_min` SMALLINT UNSIGNED NULL,
  `price`         DECIMAL(12,2) NULL,
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `ordem`         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`    TIMESTAMP NULL,
  `updated_at`    TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_exam_types_code` (`code`),
  UNIQUE KEY `uq_exam_types_public` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `exam_type_preparations` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `exam_type_id` BIGINT UNSIGNED NOT NULL,
  `title`        VARCHAR(150) NOT NULL,
  `instructions` TEXT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_prep_exam` (`exam_type_id`),
  CONSTRAINT `fk_prep_exam` FOREIGN KEY (`exam_type_id`) REFERENCES `exam_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Orientações (PDFs de orientacoes/) anexadas ao tipo de exame
CREATE TABLE `exam_type_attachments` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `exam_type_id` BIGINT UNSIGNED NOT NULL,
  `label`        VARCHAR(150) NOT NULL,
  `disk`         VARCHAR(40) NOT NULL DEFAULT 'tenant',
  `path`         VARCHAR(512) NOT NULL,
  `created_at`   TIMESTAMP NULL,
  `updated_at`   TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `ix_examatt_exam` (`exam_type_id`),
  CONSTRAINT `fk_examatt_exam` FOREIGN KEY (`exam_type_id`) REFERENCES `exam_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================================
--  ANAMNESE (questionário dirigido por dados — substitui as ~40 colunas de saude_usuario)
-- =====================================================================
CREATE TABLE `health_question_groups` (
  `id`     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`   VARCHAR(40) NOT NULL,                         -- habitos, cardiovascular, digestivo...
  `name`   VARCHAR(120) NOT NULL,
  `ordem`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hqg_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `health_questions` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id`   BIGINT UNSIGNED NOT NULL,
  `code`       VARCHAR(60) NOT NULL,
  `label`      VARCHAR(255) NOT NULL,
  `type`       VARCHAR(20) NOT NULL DEFAULT 'text',      -- HealthQuestionType: bool|text|choice|multichoice
  `options`    JSON NULL,
  `applies_to` VARCHAR(12) NOT NULL DEFAULT 'all',       -- AppliesTo: all|M|F
  `ordem`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `version`    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hq_code` (`code`),
  KEY `ix_hq_group` (`group_id`),
  CONSTRAINT `fk_hq_group` FOREIGN KEY (`group_id`) REFERENCES `health_question_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `health_records` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id`   CHAR(26) NOT NULL,
  `patient_id`  BIGINT UNSIGNED NOT NULL,
  `appointment_id` BIGINT UNSIGNED NULL,
  `filled_at`   DATETIME NULL,
  `filled_by`   BIGINT UNSIGNED NULL,                    -- users (recepção) ou NULL se auto do paciente
  `legacy_id`   BIGINT UNSIGNED NULL,                    -- saude_usuario.id
  `created_at`  TIMESTAMP NULL,
  `updated_at`  TIMESTAMP NULL,
  `deleted_at`  TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_public` (`public_id`),
  KEY `ix_hr_patient` (`patient_id`,`filled_at`),
  KEY `ix_hr_legacy` (`legacy_id`),
  CONSTRAINT `fk_hr_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_filledby` FOREIGN KEY (`filled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `health_answers` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `health_record_id` BIGINT UNSIGNED NOT NULL,
  `question_id`      BIGINT UNSIGNED NOT NULL,
  `answer`           TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ha_record_question` (`health_record_id`,`question_id`),
  KEY `ix_ha_question` (`question_id`),
  CONSTRAINT `fk_ha_record`   FOREIGN KEY (`health_record_id`) REFERENCES `health_records` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ha_question` FOREIGN KEY (`question_id`) REFERENCES `health_questions` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================================
--  AGENDA, RECEPÇÃO E CHECK-IN
-- =====================================================================
CREATE TABLE `appointments` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id`       CHAR(26) NOT NULL,
  `patient_id`      BIGINT UNSIGNED NOT NULL,
  `unit_id`         BIGINT UNSIGNED NULL,
  `company_id`      BIGINT UNSIGNED NULL,
  `company_unit_id` BIGINT UNSIGNED NULL,
  `scheduled_date`  DATE NOT NULL,                        -- agenda por dia
  `scheduled_at`    DATETIME NULL,                        -- horário, quando a clínica usar
  `specialty`       VARCHAR(20) NOT NULL DEFAULT 'checkup',-- Specialty: checkup|cma
  `status`          VARCHAR(20) NOT NULL DEFAULT 'pre_agendado', -- AppointmentStatus
  `is_priority`     TINYINT(1) NOT NULL DEFAULT 0,         -- idoso/gestante/PCD (Lei 10.048)
  `is_special`      TINYINT(1) NOT NULL DEFAULT 0,         -- "cliente especial" do legado
  `has_gynecology`  TINYINT(1) NOT NULL DEFAULT 0,
  `snapshot_company_name` VARCHAR(180) NULL,              -- desnormalização proposital (Empresa texto do legado)
  `confirmed_at`    DATETIME NULL,
  `checked_in_at`   DATETIME NULL,
  `canceled_at`     DATETIME NULL,
  `notes`           TEXT NULL,
  `legacy_id`       BIGINT UNSIGNED NULL,                 -- agendamento.codigoAgenda
  `created_by`      BIGINT UNSIGNED NULL,
  `created_at`      TIMESTAMP NULL,
  `updated_at`      TIMESTAMP NULL,
  `deleted_at`      TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_appt_public` (`public_id`),
  UNIQUE KEY `uq_appt_patient_date` (`patient_id`,`scheduled_date`,`deleted_at`), -- trava de duplicidade REAL
  KEY `ix_appt_date_status` (`scheduled_date`,`status`),
  KEY `ix_appt_unit_date` (`unit_id`,`scheduled_date`),
  KEY `ix_appt_legacy` (`legacy_id`),
  CONSTRAINT `fk_appt_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_appt_unit`    FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_appt_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_appt_cunit`   FOREIGN KEY (`company_unit_id`) REFERENCES `company_units` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_appt_createdby` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Eventos do agendamento (confirmou, cancelou, faltou, remarcou) — trilha, não colunas soltas
CREATE TABLE `appointment_events` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `appointment_id` BIGINT UNSIGNED NOT NULL,
  `type`           VARCHAR(30) NOT NULL,                  -- AppointmentEventType
  `channel`        VARCHAR(20) NULL,                      -- whatsapp|email|manual
  `occurred_at`    DATETIME NOT NULL,
  `user_id`        BIGINT UNSIGNED NULL,
  `meta`           JSON NULL,
  PRIMARY KEY (`id`),
  KEY `ix_apptev_appt` (`appointment_id`,`occurred_at`),
  CONSTRAINT `fk_apptev_appt` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_apptev_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Exames do atendimento + progresso de check-in (substitui 19 colunas de checkincliente e ~40 do espelho)
CREATE TABLE `appointment_exams` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `appointment_id` BIGINT UNSIGNED NOT NULL,
  `exam_type_id`   BIGINT UNSIGNED NOT NULL,
  `status`         VARCHAR(20) NOT NULL DEFAULT 'pendente', -- ExamStatus: pendente|em_andamento|concluido|nao_realizado|dispensado
  `room_id`        BIGINT UNSIGNED NULL,
  `started_at`     DATETIME NULL,
  `finished_at`    DATETIME NULL,
  `ordem`          SMALLINT UNSIGNED NOT NULL DEFAULT 0,  -- roteiro do paciente
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_appt_exam` (`appointment_id`,`exam_type_id`),
  KEY `ix_apptexam_exam` (`exam_type_id`),
  KEY `ix_apptexam_room` (`room_id`),
  CONSTRAINT `fk_apptexam_appt` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_apptexam_exam` FOREIGN KEY (`exam_type_id`) REFERENCES `exam_types` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================================
--  PAINEL DE TV / SALAS
-- =====================================================================
CREATE TABLE `rooms` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_id`    BIGINT UNSIGNED NULL,
  `room_key`   VARCHAR(20) NOT NULL,                      -- AUDIO, CARDIO...
  `name`       VARCHAR(100) NOT NULL,                     -- UROL = "Clínico Urologia"
  `ordem`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `pin_hash`   VARCHAR(255) NULL,                         -- PIN com hash (legado era texto plano)
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rooms_key` (`room_key`),
  KEY `ix_rooms_unit` (`unit_id`),
  CONSTRAINT `fk_rooms_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Estado atual da sala — PK = room_id com FK 1:1 (corrige a sala fantasma do legado)
CREATE TABLE `room_states` (
  `room_id`       BIGINT UNSIGNED NOT NULL,
  `status`        VARCHAR(12) NOT NULL DEFAULT 'livre',   -- RoomStatus: livre|ocupada
  `appointment_id` BIGINT UNSIGNED NULL,
  `patient_label` VARCHAR(200) NULL,
  `updated_at`    DATETIME NOT NULL,
  PRIMARY KEY (`room_id`),
  KEY `ix_roomstate_status` (`status`),
  CONSTRAINT `fk_roomstate_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_roomstate_appt` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Histórico de chamadas na TV
CREATE TABLE `tv_calls` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `appointment_id` BIGINT UNSIGNED NULL,
  `room_id`        BIGINT UNSIGNED NULL,
  `patient_label`  VARCHAR(200) NOT NULL,
  `room_label`     VARCHAR(100) NOT NULL,
  `called_at`      DATETIME NOT NULL,
  `released_at`    DATETIME NULL,
  `displayed`      TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_tvcalls_room` (`room_id`,`called_at`),
  KEY `ix_tvcalls_appt` (`appointment_id`),
  CONSTRAINT `fk_tvcalls_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tvcalls_appt` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================================
--  RESULTADOS CLÍNICOS E LAUDOS (recebe relatorio_prec/Access como entidade 1ª classe)
-- =====================================================================
CREATE TABLE `measurement_types` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(40) NOT NULL,                     -- colesterol_total, hdl, ldl, glicose, psa, tsh, pcr...
  `name`        VARCHAR(120) NOT NULL,
  `unit`        VARCHAR(20) NULL,                         -- mg/dL, mmHg, kg...
  `ref_min`     DECIMAL(12,3) NULL,                       -- faixa de referência (validar clinicamente)
  `ref_max`     DECIMAL(12,3) NULL,
  `ordem`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mtype_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `exam_results` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id`      CHAR(26) NOT NULL,
  `appointment_id` BIGINT UNSIGNED NULL,
  `patient_id`     BIGINT UNSIGNED NOT NULL,
  `exam_type_id`   BIGINT UNSIGNED NULL,
  `scope`          VARCHAR(20) NOT NULL DEFAULT 'exame',  -- ExamScope
  `framingham`     INT NULL,                              -- escore de risco
  `exam_date`      DATE NULL,
  `released_at`    DATETIME NULL,
  `legacy_id`      BIGINT UNSIGNED NULL,                  -- relatorio_prec.id
  `created_at`     TIMESTAMP NULL,
  `updated_at`     TIMESTAMP NULL,
  `deleted_at`     TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_result_public` (`public_id`),
  KEY `ix_result_patient` (`patient_id`,`exam_date`),
  KEY `ix_result_appt` (`appointment_id`),
  KEY `ix_result_legacy` (`legacy_id`),
  CONSTRAINT `fk_result_appt`    FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_result_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_result_exam`    FOREIGN KEY (`exam_type_id`) REFERENCES `exam_types` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Indicadores do resultado (as 63 colunas do relatorio_prec viram linhas tipadas)
CREATE TABLE `exam_measurements` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `exam_result_id`     BIGINT UNSIGNED NOT NULL,
  `measurement_type_id` BIGINT UNSIGNED NOT NULL,
  `value_numeric`      DECIMAL(12,3) NULL,
  `value_text`         VARCHAR(255) NULL,
  `flag`               VARCHAR(12) NOT NULL DEFAULT 'normal', -- MeasurementFlag: normal|alterado|critico
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_measure` (`exam_result_id`,`measurement_type_id`),
  KEY `ix_measure_type` (`measurement_type_id`),
  CONSTRAINT `fk_measure_result` FOREIGN KEY (`exam_result_id`) REFERENCES `exam_results` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_measure_type`   FOREIGN KEY (`measurement_type_id`) REFERENCES `measurement_types` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Conclusões textuais por área (ConclusaoCln, ConclusaoUltrs, HipoteseDiagnost, PontoCritico...)
CREATE TABLE `exam_conclusions` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `exam_result_id` BIGINT UNSIGNED NOT NULL,
  `kind`           VARCHAR(40) NOT NULL,                  -- ConclusionKind
  `body`           MEDIUMTEXT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_concl_result` (`exam_result_id`,`kind`),
  CONSTRAINT `fk_concl_result` FOREIGN KEY (`exam_result_id`) REFERENCES `exam_results` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Documentos/laudos (PDF) fora do webroot, com integridade e assinatura ICP-Brasil
CREATE TABLE `documents` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id`       CHAR(26) NOT NULL,
  `patient_id`      BIGINT UNSIGNED NOT NULL,
  `appointment_id`  BIGINT UNSIGNED NULL,
  `exam_result_id`  BIGINT UNSIGNED NULL,
  `company_id`      BIGINT UNSIGNED NULL,
  `kind`            VARCHAR(30) NOT NULL DEFAULT 'laudo', -- DocumentKind: laudo|aso|orientacao|anexo
  `title`           VARCHAR(200) NOT NULL,
  `disk`            VARCHAR(40) NOT NULL DEFAULT 'tenant',
  `path`            VARCHAR(512) NOT NULL,                -- fora do webroot
  `mime`            VARCHAR(100) NULL,
  `size_bytes`      BIGINT UNSIGNED NULL,
  `checksum_sha256` CHAR(64) NOT NULL,                    -- integridade
  `status`          VARCHAR(20) NOT NULL DEFAULT 'draft', -- draft|signed|released
  `signed_at`       DATETIME NULL,
  `signed_by`       BIGINT UNSIGNED NULL,
  `signature_provider` VARCHAR(30) NULL,                  -- icp_brasil|crm_digital
  `signature_ref`   VARCHAR(190) NULL,                    -- id/serial do carimbo, verificação por QR
  `released_at`     DATETIME NULL,
  `legacy_path`     VARCHAR(512) NULL,                    -- caminho original em private/
  `created_at`      TIMESTAMP NULL,
  `updated_at`      TIMESTAMP NULL,
  `deleted_at`      TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_documents_public` (`public_id`),
  UNIQUE KEY `uq_documents_patient_checksum` (`patient_id`,`checksum_sha256`),
  KEY `ix_documents_appt` (`appointment_id`),
  KEY `ix_documents_result` (`exam_result_id`),
  KEY `ix_documents_kind` (`kind`),
  CONSTRAINT `fk_documents_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_documents_appt`    FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_documents_result`  FOREIGN KEY (`exam_result_id`) REFERENCES `exam_results` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_documents_signer`  FOREIGN KEY (`signed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Log de acesso a documento (LGPD: quem abriu/baixou qual laudo)
CREATE TABLE `document_accesses` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_id` BIGINT UNSIGNED NOT NULL,
  `accessor_type` VARCHAR(20) NOT NULL,                  -- user|patient|company_user
  `accessor_id` BIGINT UNSIGNED NULL,
  `action`      VARCHAR(20) NOT NULL,                    -- view|download
  `ip`          VARCHAR(45) NULL,
  `created_at`  TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `ix_docacc_document` (`document_id`,`created_at`),
  CONSTRAINT `fk_docacc_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================================
--  MENSAGERIA E PESQUISA
-- =====================================================================
CREATE TABLE `message_templates` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`       VARCHAR(60) NOT NULL,
  `title`      VARCHAR(150) NOT NULL,
  `channel`    VARCHAR(20) NOT NULL,                      -- MessageChannel: email|whatsapp|sms
  `subject`    VARCHAR(200) NULL,
  `body`       MEDIUMTEXT NOT NULL,
  `applies_to` VARCHAR(12) NOT NULL DEFAULT 'all',
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_msgtpl_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `campaigns` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id`   CHAR(26) NOT NULL,
  `name`        VARCHAR(150) NOT NULL,
  `template_id` BIGINT UNSIGNED NULL,
  `status`      VARCHAR(20) NOT NULL DEFAULT 'rascunho', -- CampaignStatus
  `scheduled_at` DATETIME NULL,
  `created_by`  BIGINT UNSIGNED NULL,
  `created_at`  TIMESTAMP NULL,
  `updated_at`  TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_campaigns_public` (`public_id`),
  KEY `ix_campaigns_template` (`template_id`),
  CONSTRAINT `fk_campaigns_template` FOREIGN KEY (`template_id`) REFERENCES `message_templates` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_campaigns_user`     FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `messages` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `campaign_id`  BIGINT UNSIGNED NULL,
  `template_id`  BIGINT UNSIGNED NULL,
  `patient_id`   BIGINT UNSIGNED NULL,
  `appointment_id` BIGINT UNSIGNED NULL,
  `channel`      VARCHAR(20) NOT NULL,                    -- MessageChannel
  `to_address`   VARCHAR(190) NOT NULL,                   -- e-mail ou telefone
  `status`       VARCHAR(20) NOT NULL DEFAULT 'queued',   -- MessageStatus: queued|sent|failed|read
  `provider_ref` VARCHAR(120) NULL,
  `error`        TEXT NULL,
  `sent_at`      DATETIME NULL,
  `created_at`   TIMESTAMP NULL,
  `updated_at`   TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `ix_messages_patient` (`patient_id`),
  KEY `ix_messages_appt` (`appointment_id`),
  KEY `ix_messages_status` (`status`),
  KEY `ix_messages_campaign` (`campaign_id`),
  CONSTRAINT `fk_messages_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_messages_template` FOREIGN KEY (`template_id`) REFERENCES `message_templates` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_messages_patient`  FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_messages_appt`     FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- NPS + satisfação ligados ao atendimento (corrige o vínculo quebrado do legado)
CREATE TABLE `surveys` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id`      CHAR(26) NOT NULL,
  `appointment_id` BIGINT UNSIGNED NULL,
  `patient_id`     BIGINT UNSIGNED NULL,
  `nps`            TINYINT UNSIGNED NULL,                 -- 0..10
  `punctuality`    TINYINT UNSIGNED NULL,                -- 0..10
  `reception`      TINYINT UNSIGNED NULL,
  `environment`    TINYINT UNSIGNED NULL,
  `consult_time`   TINYINT UNSIGNED NULL,
  `professional_ref` VARCHAR(180) NULL,
  `observation`    TEXT NULL,
  `answered_at`    DATETIME NULL,
  `created_at`     TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_surveys_public` (`public_id`),
  KEY `ix_surveys_appt` (`appointment_id`),
  CONSTRAINT `fk_surveys_appt`    FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_surveys_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================================
--  SISTEMA
-- =====================================================================
CREATE TABLE `settings` (
  `id`        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key`       VARCHAR(120) NOT NULL,                      -- horário, capacidade, marca, modelos de laudo...
  `value`     JSON NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

SET FOREIGN_KEY_CHECKS = 1;
