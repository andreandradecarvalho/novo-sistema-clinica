# 12 — Novo schema de banco de dados unificado

## 12.1 Princípios

- [Confirmado] **MySQL ou MariaDB**, nas versões estáveis de destino da [matriz única em 14 §14.1](14-arquitetura-laravel.md#141-stack--fonte-única-de-versões), **InnoDB**, **`utf8mb4`** e FKs reais. O DDL existente usa `utf8mb4_0900_ai_ci`; suporte e semântica da collation devem ser homologados no motor escolhido. A atualização documental das versões não valida nem modifica as tabelas.
- **Soft delete** (`deleted_at`) e **auditoria** (`created_at`, `updated_at`, `created_by`, `updated_by`) em todas as tabelas de domínio.
- **Dinheiro** em `DECIMAL(12,2)` (ou inteiro em centavos) — nunca `float`. Datas em `DATE`/`DATETIME`, nunca `varchar`.
- **Status/tipo** como **enum PHP *backed*** + coluna `varchar`/`enum` correspondente.
- **IDs públicos não sequenciais**: `ULID` em colunas `public_id` usadas em URL e API; `BIGINT` interno como PK.
- **Isolamento por banco de tenant** (estratégia C, [09](09-estrategia-multi-tenant.md)): as tabelas de tenant **não** precisam de `tenant_id` (o banco já é do tenant). Mantém-se `unit_id` para multiunidade. *(Se no futuro algum módulo voltar a banco único, os índices compostos devem iniciar por `tenant_id`.)*
- Charset/collation uniformes eliminam o mojibake do legado.

## 12.2 Banco central (plataforma)

```mermaid
erDiagram
  tenants ||--o{ domains : tem
  tenants ||--o{ subscriptions : assina
  plans ||--o{ subscriptions : contrata
  plans ||--o{ plan_features : define
  subscriptions ||--o{ subscription_items : add-ons
  subscriptions ||--o{ invoices : gera
  invoices ||--o{ payments : quita
  tenants ||--o{ usage_records : consome
  super_admins ||--o{ audit_log_central : registra
  tenants {
    bigint id PK
    ulid public_id
    string name
    string db_connection "banco do tenant"
    string status "trial active suspended"
    timestamp created_at
  }
  plans {
    bigint id PK
    string code
    decimal price
    string cycle
  }
  subscriptions {
    bigint id PK
    bigint tenant_id FK
    bigint plan_id FK
    string status
    date trial_ends_at
    date current_period_end
  }
```

Tabelas: `tenants`, `domains`, `plans`, `plan_features`, `subscriptions`, `subscription_items`, `invoices`, `payments`, `usage_records`, `coupons`, `webhook_events`, `super_admins`, `audit_log_central`.

## 12.3 Banco do tenant — módulos

### Cadastros, identidade, auditoria

```mermaid
erDiagram
  units ||--o{ users : lotados
  users }o--o{ roles : "spatie (team=tenant)"
  roles }o--o{ permissions : "spatie"
  patients ||--o| patient_contacts : contato
  companies ||--o{ company_units : filiais
  companies ||--o{ patient_company : vincula
  patients ||--o{ patient_company : vincula
  professionals ||--o| users : "é um user"
  audit_log }o--|| users : autor
  patients {
    bigint id PK
    ulid public_id
    string full_name
    string cpf "único quando presente, validado"
    date birth_date "DATE real"
    string sex "enum M F O"
    string phone
    string email
    bigint primary_company_id FK
    timestamp created_at
    timestamp deleted_at
  }
  companies {
    bigint id PK
    ulid public_id
    string name
    bigint parent_id FK "hierarquia"
    string type "cliente convenio"
  }
  users {
    bigint id PK
    ulid public_id
    string name
    string email "único"
    string password "bcrypt/argon2"
    bool two_factor_enabled
    bigint unit_id FK
  }
  audit_log {
    bigint id PK
    bigint user_id FK
    string action
    string subject_type
    bigint subject_id
    string ip
    json meta
    timestamp created_at
  }
```

### Catálogo de exames + anamnese

```mermaid
erDiagram
  exam_types ||--o{ exam_type_preparations : preparo
  exam_types ||--o{ exam_type_attachments : "PDFs de orientacoes/"
  patients ||--o{ anamneses : responde
  anamneses ||--o{ anamnesis_answers : respostas
  exam_types {
    bigint id PK
    string code
    string name
    bool requires_fasting
    int default_duration_min
    decimal price "por tabela/empresa"
  }
  anamneses {
    bigint id PK
    bigint patient_id FK
    bigint appointment_id FK
    date answered_at
    json consent "termo versionado + data"
  }
```

### Agenda, recepção, chamada (TV), resultados/laudos

```mermaid
erDiagram
  patients ||--o{ appointments : agenda
  units ||--o{ appointments : em
  appointments ||--o{ appointment_exams : contém
  exam_types ||--o{ appointment_exams : tipo
  appointments ||--o| checkins : checkin
  checkins ||--o{ checkin_exam_status : "por exame"
  rooms ||--o{ room_status : estado
  appointments ||--o{ call_events : chamadas
  rooms ||--o{ call_events : sala
  appointments ||--o{ patient_route : roteiro
  rooms ||--o{ patient_route : etapa
  appointments ||--o| exam_results : resultado
  exam_results ||--o{ exam_result_items : "exames/indicadores"
  exam_results ||--o{ laudos : laudo
  laudos ||--o| laudo_signatures : "ICP-Brasil"
  appointments {
    bigint id PK
    ulid public_id
    bigint patient_id FK "identidade única"
    bigint unit_id FK
    datetime scheduled_at "dia OU horário"
    string type "checkup cma ..."
    string status "scheduled confirmed checked_in in_progress done canceled no_show enum backed"
    bool is_priority "idoso/gestante/PCD"
    bool is_special
    bool first_time
    timestamp canceled_at
    timestamp deleted_at
  }
  exam_results {
    bigint id PK
    bigint appointment_id FK
    bigint patient_id FK
    int framingham
    json indicators "colesterol, HDL, LDL, glicose, PSA, TSH... tipados"
    timestamp released_at
  }
  laudos {
    bigint id PK
    ulid public_id
    bigint exam_result_id FK
    string storage_path "FORA do webroot, disco do tenant"
    string status "draft signed released"
    timestamp signed_at
  }
  rooms {
    bigint id PK
    string key "único"
    string name
    int ordem
    bool active
    string pin_hash "PIN com hash, não texto"
  }
  room_status {
    bigint room_id PK "FK real para rooms, corrige sala fantasma"
    string status "free busy enum"
    bigint current_appointment_id FK
    timestamp updated_at
  }
```

### Notificações, pesquisa, billing-uso

```mermaid
erDiagram
  notification_templates ||--o{ notifications : usa
  patients ||--o{ notifications : destinatário
  appointments ||--o{ notifications : sobre
  surveys ||--o{ survey_answers : respostas
  appointments ||--o| surveys : "NPS+satisfação ligados (corrige B5)"
  notifications {
    bigint id PK
    string channel "enum email whatsapp sms"
    string status "enum queued sent failed"
    json payload
    timestamp sent_at
  }
  surveys {
    bigint id PK
    bigint appointment_id FK "vinculo real"
    int nps "0 a 10"
    int punctuality
    int reception
    int environment
    int consult_time
    text observation
  }
```

## 12.4 Como o novo schema resolve os problemas do legado

| Problema legado (doc 07) | Solução no schema |
|---|---|
| Identidade por nome em 3 bancos | `patients` único; agenda/resultado referenciam `patient_id` (FK) |
| CPF duplicado/ausente | `cpf` único **quando presente** (índice único parcial via lógica/validação), validação de DV; dedupe no ETL |
| Datas em varchar, formatos mistos | colunas `DATE`/`DATETIME`; parser por linha no ETL |
| `Status` lixo / `DesistiuCancelou` nunca null | enum `appointment.status` *backed*, único campo de estado |
| Sala fantasma (sem FK) | `room_status.room_id` **FK real** → `rooms` |
| Roteiro apagado em cascata | `patient_route` ligado a `appointment` (não ao espelho recriado); sem o sync destrutivo |
| NPS ↔ satisfação solto | `surveys.appointment_id` liga tudo |
| Colunas de exame repetidas (19/~20) | `appointment_exams` / `checkin_exam_status` / `exam_result_items` (linhas, não colunas) |
| Resultado clínico só no Access | `exam_results` + `exam_result_items` como entidade 1ª classe |
| Sem soft delete / auditoria | `deleted_at` + `created_by`/`updated_by` + `audit_log` |
| Laudo no webroot, URL adivinhável | `laudos.storage_path` fora do webroot + link assinado |
| Mojibake | `utf8mb4` uniforme + limpeza no ETL |
| PIN de sala em texto | `rooms.pin_hash` |

## 12.5 Observações

- **Índices**: `patients(cpf)`, `patients(full_name, birth_date)`, `appointments(unit_id, scheduled_at, status)`, `room_status(status)`, `laudos(public_id)`, `audit_log(subject_type, subject_id)`.
- **Anamnese** pode ser `json` tipado (respostas) para flexibilidade por tipo de exame, com as perguntas versionadas em `exam_types`.
- O ER acima é por módulo; o diagrama geral e as migrations na ordem estão em [14](14-arquitetura-laravel.md).
- Nomes de tabela em inglês são sugestão (decisão de idioma em [14](14-arquitetura-laravel.md) §14.7 → perguntas-em-aberto #20).
