# 21 — Schema da base de dados + o que aproveitar do tailadmin

> Este documento entrega o **schema SQL** da nova implementação e analisa, à parte, **o que vale reaproveitar** do projeto `tailadmin` (um esforço anterior, Laravel 11 single-tenant em cPanel). O tailadmin **não é a base** do novo sistema — dele colhemos decisões de modelagem e artefatos pontuais.

## 21.1 Arquivos de schema

Dois arquivos, coerentes com a estratégia de **um banco por clínica** ([09](09-estrategia-multi-tenant.md)):

| Arquivo | Banco | Tabelas | Conteúdo |
|---|---|---|---|
| [`schema/central.sql`](../schema/central.sql) | **central (plataforma)** | 13 | tenants, domínios, planos/features, assinaturas, faturas, pagamentos, uso, cupons, webhooks, staff/super-admin, auditoria central |
| [`schema/tenant.sql`](../schema/tenant.sql) | **por tenant (clínica)** | 37 | unidades, usuários/ACL/auditoria, empresas/convênios, pacientes (identidade única), anamnese dirigida por dados, agenda/check-in, TV/salas, resultados/laudos/medições, documentos+LGPD, mensageria, pesquisa, settings |

[Confirmado] O alvo passa a ser **MySQL ou MariaDB** conforme a [matriz de versões em 14 §14.1](14-arquitetura-laravel.md#141-stack--fonte-única-de-versões). Os arquivos atuais descrevem InnoDB e `utf8mb4_0900_ai_ci`; a escolha de collation e sua semântica precisam ser verificadas no motor escolhido. O ER por módulo está em [12](12-novo-schema-unificado.md).

[Confirmado] A redação anterior declarava validação estrutural; não há nesta atualização execução dos DDLs nas novas versões. Não considerar balanceamento textual ou criação de banco vazio como homologação de FKs, índices ou compatibilidade. Mudanças dos arquivos SQL são registradas em [schema/CHANGELOG.md](../schema/CHANGELOG.md).

### Decisões que o schema aplica (todas já endereçam bugs reais do legado — docs [05](../analise/05-seguranca-lgpd-conformidade.md)/[07](../analise/07-inconsistencias-modelagem.md))
- **Sem coluna `tenant_id`** nas tabelas do tenant — o banco já é do tenant (isolamento físico).
- **`patients.cpf` `CHAR(11)` único e nullable** (38% do legado sem CPF); `normalized_name` indexado + `(normalized_name, birth_date)` para dedupe; `FULLTEXT(name)`; `is_partial` para os ~30% de agendamentos sem cadastro.
- **`appointments` com único `(patient_id, scheduled_date, deleted_at)`** — a trava de duplicidade que nunca funcionou no legado; `scheduled_date` + `scheduled_at` para suportar agenda por dia **ou** por horário; `snapshot_company_name` para o nome de empresa livre do legado.
- **`appointment_exams` com único `(appointment_id, exam_type_id)`** — substitui as 19 colunas de `checkincliente` e as ~40 do `espelho_atendimento` por linhas.
- **`room_states.room_id` como PK com FK 1:1 para `rooms`** — corrige a sala fantasma `CLINICO`; `rooms.pin_hash` (PIN era texto plano).
- **Resultado clínico de 1ª classe**: `exam_results` + `exam_measurements` (as 63 colunas do `relatorio_prec` viram linhas tipadas com faixa de referência e flag) + `exam_conclusions` (textos).
- **`documents`** fora do webroot, com `checksum_sha256`, único `(patient_id, checksum)`, campos de **assinatura ICP-Brasil** e `document_accesses` (log de acesso a laudo — LGPD).
- **Enums como `VARCHAR` + Enum PHP** (nunca `ENUM` do MySQL — mudar valor não vira `ALTER TABLE`); dinheiro em `DECIMAL`; datas em `DATE`/`DATETIME`.
- **`legacy_*_id`/`legacy_code`/`legacy_path`** em toda tabela migrada → idempotência e conferência do ETL ([13](13-plano-migracao-dados.md)).
- **`public_id` ULID** para URLs/API; soft delete + `activity_log` (Spatie) + auditoria.
- Sem trigger/procedure/event — regra na aplicação.

### Como aplicar
[Confirmado] Validar primeiro em bancos descartáveis **local/staging**, no motor/versão de destino definido no fluxo 14: um banco para `central.sql` e outro para `tenant.sql`, conferindo FKs e collations. Não executar DDL/DML em produção. Na implementação prevista, o provisionamento (`tenant:create`, [20](20-plano-desenvolvimento.md) Etapa 1) cria o banco do tenant e aplica migrations Laravel derivadas do DDL; comandos ainda precisam ser implementados. O DDL é a referência e, quando existirem, as migrations Laravel serão a fonte executável do projeto.

---

## 21.2 O que aproveitar do tailadmin (e o que não)

O tailadmin é **Laravel 11, single-tenant, cPanel/hospedagem compartilhada, front Sneat/TailAdmin**, com schema já implementado em ~40 migrations. A modelagem clínica dele é madura e nasceu da mesma análise do legado — por isso vale colher. O que **não** serve é a premissa de arquitetura (single-tenant/cPanel) e o acoplamento ao design system.

### ✅ Aproveitar (já incorporado no schema acima ou a portar)

| Item | De onde | Como entra no novo |
|---|---|---|
| **Modelagem das tabelas clínicas** | migrations/`SCHEMA.md`/`FASE-02-schema.md` | já refletida em `tenant.sql` (patients rico, appointment_exams, exam_results/measurement_types/exam_conclusions, health_* dirigido por dados, documents com checksum, patient_merges, room_states PK=room_id) |
| **Value Objects** `Cpf` (valida DV), `PhoneNumber` (E.164/DDI 55), `NormalizedName` (sem `utf8_encode`, usa `Normalizer`/`iconv`), `LegacyDate` (detecta `d/m/Y` vs `Y-m-d`) | `app/Support/` | portar quase direto — são agnósticos de arquitetura e resolvem exatamente o lixo de dados do legado |
| **Catálogo de Enums** string-backed com `label()`/`options()` | `app/Enums/` | reusar a lista e o padrão; **desacoplar** `badgeClasses()` do Sneat (ver abaixo) |
| **Seeders de produção** | `ExamTypeSeeder`, `RoomSeeder` (18 salas reais, `OTORRINO`, `UROL`="Clínico Urologia"), `MeasurementTypeSeeder`, `HealthQuestionSeeder` (~40 colunas de `saude_usuario` → perguntas), `MessageTemplateSeeder` | reusar o conteúdo (os mapas consolidados do legado valem ouro); rodar por tenant |
| **ETL e idempotência** | `ETL.md`, `DIVERGENCIAS-LEGADO.md`, colunas `legacy_*_id` | alinhar com [13](13-plano-migracao-dados.md); reaproveitar regras de dedupe e `LegacyDate` |
| **PatientObserver** (preenche `normalized_name` no `saving`), scopes (`Patient::search`, `Appointment::forDate`), config `LogsActivity` | `app/Models` | portar |
| **Estruturas de produção** `legacy-schema/*.sql` | `docs/legacy-schema/` | fonte do ETL (idênticas às que já extraí) |
| **Setup de qualidade e auth** | `pint.json`, `phpstan.neon`, `composer check`, migrations de 2FA/passkeys, Spatie Permission + Activitylog | reaproveitar a configuração; subir Laravel 11→13 |

### 🔁 Adaptar (muda com o multi-tenant)

| Item | Por quê |
|---|---|
| Migrations single-DB | viram **migrations de tenant** + um conjunto **novo central** (tenants/planos/assinaturas) — ver `central.sql` |
| Restrições "sem Redis/sem event/cPanel" (`FASE-02-schema.md`) | eram do host compartilhado antigo; o SaaS roda com Redis/Horizon/Reverb em infra própria — reavaliar |
| `company_units` (unidades do **cliente**) | correto, mas faltava **`units`** (unidades da **própria clínica**, multiunidade) — adicionada no novo schema |
| `ClinicalSyncRun` (espelho contínuo do Access) | no novo, o Access vira **ETL único** de migração, não sync permanente — descartável após a Etapa 7 |
| Portal/mensageria (migrations `..._private_portal`, `..._phase07`) | os conceitos valem; reimplementar sobre fila/jobs e storage por tenant |

### ❌ Não aproveitar

| Item | Por quê |
|---|---|
| A premissa **single-tenant / cPanel** como arquitetura | o produto é SaaS multi-tenant com banco por clínica |
| **`badgeClasses()` com classes literais do Sneat** dentro dos Enums | acopla domínio a um design system; no novo, cor/estilo saem para a camada de view — o front (layout/CSS/JS/design system) **será apontado depois** |
| O template **TailAdmin/Sneat** como design definitivo | decisão de front adiada; não prender o schema/domínio a ele |

### Resumo
Do tailadmin, **colher a inteligência de dados** — modelagem clínica, Value Objects, Enums, seeders de produção e regras de ETL, todos já destilados do legado — e **descartar a arquitetura** (single-tenant/cPanel) e o acoplamento ao design system. O schema em `central.sql` + `tenant.sql` já reflete esse recorte.
