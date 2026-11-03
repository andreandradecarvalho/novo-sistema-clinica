# Histórico do schema

## 2026-11-03 — Migrations executáveis da fundação Laravel

- [Confirmado] Criadas na raiz duas migrations: [foundation](../../database/migrations/2026_11_03_000001_create_foundation.php) seleciona 12 tabelas da referência schema.sql, com a mesma estrutura e ordem de dependências; [runtime](../../database/migrations/2026_11_03_000002_create_runtime_tables.php) acrescenta password_reset_tokens, sessions, jobs, job_batches e failed_jobs. schema.sql não foi alterado nesta entrega: continua referência do modelo de domínio completo das fases seguintes, sem representar os módulos já implementados.
- [Confirmado] password_reset_tokens tem PK(tenant_id,email), evitando colisão de tokens para e-mails iguais em clínicas diferentes. sessions tem PK(tenant_id,id), índice tenant/usuário e FK composta ao usuário, para leitura/revogação isoladas e impedimento de vínculo com outra clínica. Ambas referenciam tenants com RESTRICT. As três tabelas de fila são infraestrutura Laravel com envelope de tenant na aplicação.
- [Confirmado] Validação local na instância Compose fixada na matriz do fluxo 14: migrations → rollback → reaplicação aprovados, com FKs habilitadas. Novo_sistema_clinica_testing contém 18 tabelas incluindo migrations, 13 FKs, servidor 13.0.2-MariaDB-ubu2604 e foreign_key_checks=1. Só dados sintéticos. Resultado do DDL completo anterior permanece na entrada própria abaixo.
- [Confirmado] Recuperação local de instalação vazia foi verificada pelo down/up das migrations. Isso não autoriza rollback destrutivo com dados clínicos; alterações futuras seguem expand-contract e backup/restore do fluxo 24. Não foi executada homologação MySQL nem alteração em produção/legado.

## 2026-11-03 — Conciliação clínica/ocupacional e integridade do banco único

- [Confirmado] Motivação: correção das pendências P01–P12 do [relatório documental](../analise/08-relatorio-documentacao-saas-2026-11-03.md), sob pedido do usuário. Mantido banco único do ADR vigente; o split central/tenant é lógico, não dois arquivos/bancos. Fontes funcionais: fluxos [09](../fluxos/09-estrategia-multi-tenant.md), [13](../fluxos/13-plano-migracao-dados.md), [22](../fluxos/22-atendimento-clinico.md), [23](../fluxos/23-saude-ocupacional.md) e [24](../fluxos/24-seguranca-retencao-operacao.md).

### Alterações e justificativas

| Estado | Alteração | Justificativa |
|---|---|---|
| [Confirmado] | DDL passou de 50 para **69 tabelas**, com **170 FKs**, das quais **106 compostas**. Pais referenciados recebem UNIQUE(tenant_id,id); referências clínicas diretas usam tenant_id + id. | Impedir pai de outra clínica, sem depender só do scope do ORM. Relações polimórficas e igualdade de paciente/empresa exigem validação de aplicação. |
| [Confirmado] | FKs de tenant e vínculos compostos usam RESTRICT, removendo cascade/SET NULL nesses vínculos. | Preservar histórico e impedir hard-delete do tenant com dependências. SET NULL composto apagaria também tenant_id; desvinculação opcional exige Action autorizada, sem apagar prontuários. |
| [Confirmado] | Criação ordenada por dependências, com FOREIGN_KEY_CHECKS=1 desde o início. | Validar FKs reais na criação; não usar desativação como substituto de ordenação/homologação. |
| [Confirmado] | patient_company_links, occupational_programs, occupational_protocols, occupational_assessments e aso_records. | Vínculo/função com períodos; programa/protocolo versionados; avaliação e ASO com contexto preservado. Nenhum protocolo clínico foi inventado ou pré-carregado. |
| [Confirmado] | exam_orders, exam_order_items, visits e exam_executions; resultado vinculado à execução e revisão profissional. | Distinguir pedido/reserva/atendimento/tentativa, permitindo múltiplos exames e repetição sem sobrescrita. |
| [Confirmado] | clinical_receivables com pedido, pagador, valor, vencimento, estado e idempotência; preços por item no pedido. | Financeiro mínimo da clínica separado da mensalidade SaaS. Não equivale a ERP/contabilidade/TISS implementados. |
| [Confirmado] | company_user_access, devices, patient_consents, download_grants e document_deliveries. | Concessões por empresa e vigência, token de dispositivo revogável, eventos por finalidade, download com revogação e evidência de disponibilização. |
| [Confirmado] | migration_runs, legacy_references, migration_issues e patient_match_candidates. | Contrato único de rastreabilidade/idempotência preserva campos dos dois desenhos anteriores, revisão humana e hash por origem. |
| [Confirmado] | CPF em users/patients mudou de CHAR(11) para cpf_encrypted TEXT + cpf_hash CHAR(64) + crypto_key_version; índices usam hash por tenant. | Compatibilizar ciphertext e busca HMAC. Criptografia e derivação de chave serão implementadas pela aplicação; DDL não cifra dados sozinho. |
| [Confirmado] | health_questions único por tenant/código/versão; health_answers preserva texto/tipo/opções e answer_encrypted; exam_conclusions usa body_encrypted. | Preservar contexto histórico e definir espaço para ciphertext; imutabilidade após uso continua regra de aplicação. |
| [Confirmado] | exam_measurements preserva unidade/referência/método; impede valor textual e numérico simultâneos; default de flag passa a uninterpreted em VARCHAR(20). | Não rotular ausência de interpretação como normal nem recalcular retrospectivamente com catálogo editado. |
| [Confirmado] | documents ganhou grupo/versão, referência à versão anterior, motivo, evidência de assinatura, prazo de guarda e impedimento de descarte. Índice paciente/hash deixa de ser único. | Permitir reemissão rastreável, inclusive mesmo conteúdo em contextos/versionamentos distintos. Arquivo liberado continua imutável por contrato da aplicação/storage. |
| [Confirmado] | appointments.active_flag considera exclusão e cancelamento; unique inclui unidade e slot horário, com NULL normalizado. | Liberar slot cancelado e permitir unidades/horários distintos; regras adicionais de capacidade seguem configuração do fluxo 22. slot_key usa segundos do horário para compatibilidade verificada no MariaDB. |
| [Confirmado] | CHECK de data/horário coerentes, notas 0–10, quantidades/preços e completude mínima de avaliação ocupacional. | Rejeitar valores estruturalmente inválidos sem decidir aptidão médica. |
| [Confirmado] | messages ganhou idempotency_key única por tenant e content_snapshot_encrypted; tenants ganhou timezone IANA. | Histórico de envio/retries e interpretação consistente de agenda/prazos por clínica. |

### Validação observada

- [Confirmado] Servidor local **13.0.2-MariaDB-ubu2604**, em 03/11/2026. Banco descartável exclusivo por execução; criado e removido ao final. O banco de trabalho novo_sistema_clinica não foi importado, alterado ou apagado, e nenhuma permissão foi ampliada.
- [Confirmado] DDL criou **69 tabelas e 170 FKs com checks habilitados**. **25 verificações sintéticas aprovadas**, com códigos de erro esperados para constraints: relações entre tenants, duplicidade/cancelamento/horário de reserva, notas inválidas, retenção de tenant, pedido/visita/tentativas, medição com snapshot/tipos incompatíveis, idempotência de mensagem/ETL, avaliação médica e versão de documento/ASO.
- [Confirmado] Execução reproduzível a partir da raiz: `python3 scripts/validate_schema.py`. Usa DB_* do .env ignorado e exige CREATE/DROP apenas para seu banco descartável. Neste ambiente o usuário de desenvolvimento não tinha esse privilégio; foi utilizado `python3 scripts/validate_schema.py --container-root`, exclusivamente no contêiner local mariadb_container, com segredo interno sem exposição/versionamento.
- [Confirmado] Resultado e SHA-256 do DDL: [validation-2026-11-03.json](validation-2026-11-03.json); código do validador: [scripts/validate_schema.py](../../scripts/validate_schema.py).
- [Confirmado] MySQL não foi homologado nesta sessão. Não houve instalação da aplicação ou testes HTTP/Policies/criptografia/assinatura/restore. Esses aceites permanecem nas etapas do fluxo 20. SQL válido não equivale a conformidade clínica implementada.

## Histórico anterior — registros preservados

[Confirmado] As entradas abaixo descrevem snapshots e decisões anteriores; as contagens, pendências e limites de validação vigentes são os da entrada de 03/11/2026 acima.

## 2026-10-31 — Referência de versões de destino

- [Confirmado] Em `central.sql` e `tenant.sql`, somente os comentários do cabeçalho foram alterados: a indicação fixa de MySQL 8.4 LTS foi substituída por referência à [matriz única no fluxo 14 §14.1](../fluxos/14-arquitetura-laravel.md#141-stack--fonte-única-de-versões), conforme solicitação do usuário para atualizar PHP/Laravel e MySQL ou MariaDB.
- [Confirmado] Os cabeçalhos agora distinguem o DDL existente da homologação pendente nas versões de destino. Não houve alteração de instruções SQL, tabelas, colunas, índices, collations ou FKs; o split central/tenant foi preservado.
- [Confirmado] A instância MariaDB 10.5 local e o banco vazio criado anteriormente não comprovam compatibilidade dos DDLs. Nenhum SQL foi executado nesta atualização documental; a validação deve ocorrer em bancos descartáveis local/staging, no motor/versão de destino, antes de declarar suporte.


## 2026-10-31 — Migração para BANCO ÚNICO + tenant_id (substitui banco-por-tenant)

- [Confirmado] Por decisão do usuário (alvo: milhares de tenants pequenos, custo/ops no limite), a estratégia multi-tenant passou de "banco central + um banco por clínica" para **banco único + coluna `tenant_id` + global scopes**. Ver ADR em `../fluxos/09-estrategia-multi-tenant.md`.
- [Confirmado] `central.sql` e `tenant.sql` foram **substituídos por um único `schema.sql`** (Parte 1 centrais sem `tenant_id`; Parte 2 do tenant, todas com `tenant_id` NOT NULL + FK para `tenants`). 50 tabelas (14 centrais, incl. `permissions` global; 36 do tenant).
- [Confirmado] Chaves únicas "por clínica" passaram a incluir `tenant_id` (ex.: `UNIQUE(tenant_id, cpf)`, `(tenant_id, code)`, `(tenant_id, room_key)`); índices compostos das tabelas grandes começam por `tenant_id`; `public_id` (ULID) permanece único global. ACL Spatie em modo teams: `permissions` global, `roles`/pivôs com `tenant_id`.
- [Confirmado] Collation alterado de `utf8mb4_0900_ai_ci` (exclusivo do MySQL) para **`utf8mb4_unicode_ci`**, portável entre MySQL e MariaDB e coerente com o banco local `novo_sistema_clinica`. Confirmar comportamento de ordenação no motor/versão de destino antes de homologar (§14.1.1).
- [Confirmado] Cabeçalho do `schema.sql` deixou de fixar "MySQL 8.4 LTS" e passou a referenciar a matriz única de versões (`../fluxos/14-arquitetura-laravel.md` §14.1).
- [Inferido/Pendente] Validação apenas estrutural (parênteses/crases, FKs resolvidas, constraints únicas, `tenant_id`+FK em toda tabela do tenant). Nenhum SQL executado em servidor. Homologar `schema.sql` em banco descartável no motor/versão de destino.
- [Atenção] Documentos que ainda citam "banco central + um banco por clínica" ou os arquivos `central.sql`/`tenant.sql` (ex.: fluxo 14 §14.1.1) precisam de varredura de consistência — pendente de confirmação do usuário.

## 2026-10-31 — Varredura de consistência + correções de DDL (pós banco único)

- [Confirmado] Varredura concluída. Atualizados para refletir banco único: `../fluxos/14-arquitetura-laravel.md` §14.1.1 (removido "banco central + um banco por clínica"; referência a `central.sql`/`tenant.sql` trocada por `schema.sql` + `utf8mb4_unicode_ci`), `../analise/00-sumario-executivo.md` (recomendação) e `Precordis — Processo de Migração dos Bancos Legados.md` (P03 resolvida a favor deste desenho).
- [Confirmado] `schema.sql`: adicionadas 3 FKs antes ausentes — `health_records.appointment_id → appointments`, `appointment_exams.room_id → rooms`, `documents.company_id → companies` (todas `ON DELETE SET NULL`). Resolve a observação de FK do `PENDENCIAS.md`.
- [Confirmado] `schema.sql`: `appointments` ganhou coluna gerada `active_flag` e a chave única passou a `UNIQUE(tenant_id, patient_id, scheduled_date, active_flag)`, corrigindo a P05 (o `UNIQUE` com `deleted_at` NULL não bloqueava duplicados ativos no MySQL).
- [Confirmado] P03, P04, P05 e a observação de FK marcadas como resolvidas no `../outros/PENDENCIAS.md`. P01 (contrato do ETL: `legacy_map` vs `legacy_references`) e P02 (sequência do MVP) permanecem abertas — decisão do responsável.
- [Inferido/Pendente] Validação ainda estrutural; homologar `schema.sql` em banco descartável no motor/versão de destino (§14.1). O item [Atenção] da entrada anterior fica atendido quanto à varredura documental.


## 2026-11-03 — Migration inicial do cadastro de pacientes (etapa 2)

- [Confirmado] Adicionada [2026_11_03_000003_create_patient_registry.php](../../database/migrations/2026_11_03_000003_create_patient_registry.php): reproduz exatamente as definições de companies, company_units e patients do schema.sql, nesta ordem. Empresas/unidades entram como dependências das FKs de patients; nenhum DDL clínico futuro foi importado em bloco e o schema.sql não foi alterado.
- [Confirmado] Preservados CPF em TEXT ciphertext, hash CHAR(64), versão de chave, unique por tenant e FKs compostas, inclusive usuário, empresa/unidade e possível duplicata. A aplicação ainda não aceita esses vínculos pela entrada HTTP, até as regras específicas serem entregues.
- [Confirmado] up executado em MariaDB 13.0.2 nos bancos locais testing, homologacao e desenvolvimento do Compose. Rollback somente desta migration e reaplicação aprovados em novo_sistema_clinica_testing após confirmar as três tabelas vazias; foreign_key_checks=1 ao final. Nenhum rollback no ambiente de trabalho/homologação ou acesso ao legado/produção.
- [Confirmado] As migrations anteriores permanecem intactas. O down remove as três tabelas em ordem inversa e é destrutivo; validação em banco vazio não autoriza rollback com pacientes. Em instalação com dados, preservar backup/chaves e corrigir por migration progressiva.


## 2026-11-03 — Cadastro profissional e interface de unidades clínicas (etapa 2)

- [Confirmado] Adicionada [2026_11_03_000004_create_professionals.php](../../database/migrations/2026_11_03_000004_create_professionals.php), reproduzindo exatamente professionals do schema.sql: vínculo único com user_id, FK composta (tenant_id,user_id), campos de conselho/especialidade e chave composta para vínculos clínicos futuros. Não adicionada coluna de situação ou vínculo paralelo com unidade; a situação do integrante pertence a users.
- [Confirmado] units já existia na migration da fundação e recebeu somente interface/Actions/Policy. Nenhuma alteração nas migrations anteriores ou no schema.sql.
- [Confirmado] up aprovado em MariaDB 13.0.2 nos bancos locais de testes, desenvolvimento e homologação isolada. Rollback apenas da nova migration e reaplicação aprovados em novo_sistema_clinica_testing após confirmar professionals vazia e a migration como a última aplicada; foreign_key_checks=1 ao final. Nenhum rollback no banco de trabalho/homologação.
- [Confirmado] O down remove professionals e é destrutivo; em instalação com registros, preservar banco/chaves e preferir correções progressivas. A validação em banco vazio não autoriza apagar perfis referenciados por registros clínicos futuros. Legado e produção não foram acessados.

## 2026-11-03 — Histórico de vínculos paciente–empregador (etapa 2)

- [Confirmado] Adicionada [2026_11_03_000005_create_patient_company_links.php](../../database/migrations/2026_11_03_000005_create_patient_company_links.php), reproduzindo exatamente a tabela patient_company_links do schema.sql. Preservados campos, índices, FKs compostas de tenant/paciente/empresa/estabelecimento, RESTRICT e CHECK do período. Objetivo: executar o cadastro histórico previsto no fluxo 23 sem importar tabelas futuras de avaliação/ASO. Migrations anteriores e DDL de referência não foram alterados.
- [Confirmado] Snapshot dos nomes de empresa/estabelecimento usa context_encrypted já previsto; não acrescenta coluna. A igualdade entre empresa e estabelecimento é validada pela Action, além das FKs de tenant. Imutabilidade histórica, data de desligamento e vínculo com paciente exato também são regras da aplicação; o CHECK isolado não implementa todo o contrato.
- [Confirmado] up aprovado em MariaDB 13.0.2 nos bancos locais de desenvolvimento, homologação isolada e testes. Rollback de uma única migration e reaplicação aprovados somente em novo_sistema_clinica_testing, após conferir destino, última migration e tabela vazia; foreign_key_checks=1 ao final. Testes exercitam FK cruzada, período inválido e reversão transacional quando a auditoria falha.
- [Confirmado] down remove a tabela e é destrutivo; o ensaio vazio não autoriza reversão com histórico real. Instalações com dados exigem backup/chaves e correção por migration progressiva. Nenhuma alteração em legado/produção, nenhum rollback de desenvolvimento/homologação e nenhuma homologação MySQL nesta entrega.

## 2026-11-03 — Concessões empresariais por usuário e vigência (etapa 2)

- [Confirmado] Adicionada [2026_11_03_000006_create_company_user_access.php](../../database/migrations/2026_11_03_000006_create_company_user_access.php), reproduzindo exatamente company_user_access do schema.sql. Preservados tipos, índices, FKs compostas de tenant/usuário/empresa e CHECK valid_until >= valid_from. Objetivo: executar a gestão administrativa de concessões dos fluxos 11/23/24. Nenhuma alteração nas migrations anteriores ou no DDL de referência.
- [Confirmado] Categoria permitida, atividade dos participantes, conversão do fuso da clínica para UTC, imutabilidade e revogação são regras da aplicação; o DDL isolado não implementa autorização de portal/documento. Não foi adicionado UNIQUE: registros sobrepostos são independentes, e revogar uma concessão não elimina outra vigente. Uso e limites no [documento operacional](../outros/concessoes-empresariais.md).
- [Confirmado] up aprovado em MariaDB 13.0.2 nos bancos locais de desenvolvimento, homologação isolada e testes. Rollback somente da migration 000006 e reaplicação aprovados em novo_sistema_clinica_testing após conferir destino, última migration e tabela vazia; foreign_key_checks=1 ao final. Testes exercitam FK cruzada, CHECK temporal e reversão da criação/revogação diante de falha da auditoria.
- [Confirmado] down é destrutivo e remove as concessões. O ensaio vazio não autoriza rollback com histórico real; instalações com dados exigem backup e migration progressiva. Nenhuma escrita no legado/produção, rollback no banco de desenvolvimento/homologação ou homologação MySQL nesta entrega.
