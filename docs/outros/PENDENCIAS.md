# Pendências de conciliação documental

## Estado vigente — correção de 03/11/2026

[Confirmado] O usuário solicitou corrigir as pendências do relatório. As divergências técnicas foram conciliadas nas fontes e no DDL, sem alterar o legado. A tabela abaixo é o estado atual; os registros antigos seguintes são históricos e seus números de linha descrevem os snapshots anteriores.

| Pendência | Estado atual | Resolução e evidência |
|---|---|---|
| [Confirmado] P01 | CONCILIADA | legacy_references incorpora campos de origem, destino, confiança, hash e execução; contrato único no [13](../fluxos/13-plano-migracao-dados.md) e tabelas de ETL no [schema](../schema/schema.sql). |
| [Confirmado] P02 | CONCILIADA | MVP clínico completo inclui etapa 6 antes da migração completa da etapa 7. Etapas/status em [20](../fluxos/20-plano-desenvolvimento.md); roadmap sem numeração concorrente. |
| [Confirmado] P03/P06 | CONCILIADAS | Banco único do ADR propagado a AGENTS/README/09/12/14/15/20/21. Migration única, onboarding sem provisionar banco; propriedade direta/indireta e exceções no [09](../fluxos/09-estrategia-multi-tenant.md). |
| [Confirmado] P04 | CONCILIADA NO CONTRATO/DDL | ACL teams usa tenant_id, catálogo global e exceções explícitas. Configuração de pacote e testes de permissão são aceite da implementação da etapa 1, não conclusão presumida. |
| [Confirmado] P05 | CORRIGIDA NO DDL/VALIDADA | Índice com slot/unidade e active_flag libera cancelados, impede duplicado do mesmo slot e permite horários distintos; regras de capacidade configuráveis em [22](../fluxos/22-atendimento-clinico.md). Testes SQL no [CHANGELOG](../schema/CHANGELOG.md). |
| [Confirmado] P07 | CONCILIADA | Etapa 0 passa a governança/preparação do novo sistema, sem patches no legado. Contenção operacional externa não bloqueia a fundação; orientação somente leitura preservada. |
| [Confirmado] P08 | CONCILIADA | Vuexy/Blade/Vite e JS por página em [14](../fluxos/14-arquitetura-laravel.md), versões referenciadas pela matriz única; removidas escolhas concorrentes do plano. |
| [Confirmado] P09 | CONCILIADA NO CONTRATO/DDL | Diagramas correspondem ao schema; entidades clínicas/ocupacionais e CPF ciphertext/HMAC têm campos próprios; FKs compostas e regras de propriedade documentadas. Não afirmar criptografia ou Policy implementada. |
| [Confirmado] P10 | CONCILIADA | Status derivado de flags, sem importar Status; identidade ambígua exige revisão, delta cobre updates e convivência Access é temporária/condicional. [13](../fluxos/13-plano-migracao-dados.md) substitui processo antigo, mantido como histórico. |
| [Confirmado] P11 | ESPECIFICADA E MODELADA | [23](../fluxos/23-saude-ocupacional.md) define vínculo/função/contexto, avaliação, ASO e limites empresariais; entidades no DDL validado. Homologação clínica e aplicação continuam nas etapas 3/6. |
| [Confirmado] P13 | CONCILIADA POR DECISÃO DO USUÁRIO EM 04/11/2026 | Modelos/snapshots de perguntas na etapa 3; coleta de respostas na etapa 4, junto de appointments. Registro abaixo e sequência no fluxo 20. |
| [Confirmado] P14 | CONCILIADA POR DECISÃO DO USUÁRIO | Aceite técnico da etapa 3 autorizado e desenvolvimento da etapa 4 com dados sintéticos; aprovação do conteúdo real obrigatória antes do uso clínico. |
| [Confirmado] P12 | REFERÊNCIAS/POLÍTICA CORRIGIDAS | Tutela da saúde em art. 11, II, f; marcos distintos de guarda e assinatura por processo no [24](../fluxos/24-seguranca-retencao-operacao.md). Aprovação da política por responsável técnico/jurídico antes do uso correspondente. |

[Confirmado] Preparação documental complementada com [mapa central](../README.md), [banco legado](banco-legado.md) e [índice legado](indice-legado.md). Verificação SQL: 69 tabelas/170 FKs, 25 verificações sintéticas aprovadas em MariaDB 13.0.2. Resultado, hash do DDL e limites no CHANGELOG. Não houve importação no banco de trabalho nem ampliação de privilégios do usuário local.

## O que continua necessário na implementação/implantação

- [Confirmado] A fundação Laravel na raiz foi implementada e testada localmente; evidências/limites e aceite autorizado da etapa 1 estão no [fluxo 20](../fluxos/20-plano-desenvolvimento.md). Este DDL não implementa casts clínicos, assinatura, restore nem os módulos das etapas seguintes. A homologação local isolada com duas clínicas foi executada, conforme [roteiro/evidência](homologacao-local.md); a revisão técnica local e o aceite foram registrados no fluxo 20. Por instrução explícita do usuário, CI remota fica para verificação futura, sem bloquear o avanço local; implantação externa permanece pendente.
- [Inferido] Antes de ativar cada domínio, homologar com a clínica protocolos/periodicidades/modelos, acesso empresarial, portfólio e financeiro contratado; perguntas únicas em [19](../fluxos/19-perguntas-em-aberto-negocio.md). Não são respostas fornecidas pelo negócio nesta correção.
- [Confirmado] MySQL ainda não foi homologado; o resultado observado é MariaDB. Não anunciar suporte testado a ambos nem conformidade clínica integral.

<a id="p13-sequencia-questionarios"></a>

## P13 — Sequência de questionários e dependência da agenda (04/11/2026)

- [Confirmado] Fontes ao identificar a divergência: fluxo 20 §20.2 atribuía questionários/snapshots à etapa 3 e agenda à etapa 4; o ponto §20.4.1 ainda incluía coleta de respostas entre as pendências da etapa 3. O schema.sql, health_records (linha 942 no snapshot inspecionado), exige FK composta para appointments. Migrations existentes iam até 000012 e não criavam appointments/health_records/health_answers.
- [Confirmado] Impacto: criar a persistência completa de respostas na etapa 3 exigiria antecipar agenda ou adiar sua FK. A implementação afetada foi interrompida para decisão, sem criar tabelas fictícias, omitir FK ou alterar o DDL por conta própria.
- [Confirmado] O usuário escolheu: **“Preparar modelos/snapshots na etapa 3 e coletar respostas na etapa 4”**. Sequência conciliada nos fluxos 20/22 e no documento de questionários. Mantidos DDL/FKs de referência; coleta de respostas continua pendente até a etapa 4, após aceite da etapa 3.

<a id="p14-aceite-tecnico-clinico"></a>

## P14 — Aceite técnico e ativação clínica (04/11/2026)

- [Confirmado] O [fluxo 20 §20.4.1](../fluxos/20-plano-desenvolvimento.md#2041-ponto-de-retomada--04112026) mantém validação de conteúdo real e aceite integral entre as pendências da etapa 3, condicionando a etapa 4 ao aceite. O [AGENTS, Ciclo de implementação](../../AGENTS.md#ciclo-de-implementação), item 2, exige dependências concluídas. Em contrapartida, o [fluxo 23 §23.5](../fluxos/23-saude-ocupacional.md#235-aceite-e-configuração-da-clínica) situa aprovação de protocolos/periodicidades/modelos antes da ativação em uma clínica e esclarece que são parâmetros de implantação, sem exigir inventar protocolos durante o desenvolvimento. Esta divergência foi identificada na retomada após a revisão técnica, sem escolher silenciosamente uma interpretação.
- [Confirmado] Código/testes e homologação sintética estão concretos para revisão: [relatório da etapa 3](revisao-etapa-03.md), 186 testes/2.244 asserções e 168 verificações locais. Não existe evidência de aprovação de conteúdo real pela clínica. O pedido de continuidade não foi registrado como validação médica.
- [Inferido] Decisão necessária: autorizar aceite do escopo técnico da etapa 3 e desenvolvimento da etapa 4 com dados sintéticos, mantendo aprovação do conteúdo real como requisito antes de uso clínico; ou manter a dependência bloqueando a etapa 4 até validação clínica. A implementação dependente aguardou resposta, sem iniciar tabelas/rotas de agenda ou conceder permissões clínicas durante a consulta.

- [Confirmado] Decisão posterior do usuário: **“Sim, avançar com dados sintéticos”**, em resposta à autorização explícita para concluir o escopo técnico e iniciar a etapa 4, mantendo validação do conteúdo real obrigatória antes do uso clínico. Aceite técnico registrado no fluxo 20; não representa aprovação médica nem liberação para produção.

## Histórico — registros anteriores à conciliação

[Confirmado] Os textos seguintes são preservados para rastreabilidade. Declarações antigas de “aberta”, arquitetura, contagens e ausência de validação não substituem o estado vigente acima.

## P01 — Contrato de rastreabilidade da migração

- [Confirmado] Divergência documental encontrada em 2026-10-31. A regra da tarefa determina parar e registrar divergências, sem escolher uma versão. A preparação da base de conhecimento está interrompida neste ponto; nenhuma das alternativas foi adotada.
- [Confirmado] A confirmação abaixo se refere ao conteúdo dos documentos, não à existência dessas tabelas em código executável ou em banco.

### Evidências

| Estado | Fonte | Localização | Contrato descrito |
|---|---|---|---|
| [Confirmado] | [13 — Plano de migração de dados](../fluxos/13-plano-migracao-dados.md#132-estratégia-geral) | Linha 14 | Tabela `legacy_map`, com `source_db`, `source_table`, `source_id`, `target_type`, `target_id`, `match_method` e `confidence`. |
| [Confirmado] | [18 — Riscos da migração](../fluxos/18-riscos-migracao.md#185-rollback) | Linha 41 | Reimportação apoiada em ETL idempotente e `legacy_map`. |
| [Confirmado] | [20 — Plano de desenvolvimento](../fluxos/20-plano-desenvolvimento.md) | Linha 161, etapa 7 | Comandos `legacy:import {etapa}` com tabela de-para `legacy_map`, referenciando o fluxo 13. |
| [Confirmado] | [Processo de Migração dos Bancos Legados](<Precordis — Processo de Migração dos Bancos Legados.md>) | Linhas 151, 155, 436 e 440 | Tabela `legacy_references`; chave única `(tenant_id, source, legacy_table, legacy_id)`; campos `entity_type`, `entity_id`, `row_hash` e `run_id`; rollback por execução. |

### Decisão necessária

- [Confirmado] Os documentos prescrevem nomes e conjuntos de campos diferentes para a rastreabilidade do ETL.
- [Inferido] A diferença pode afetar migrations, importadores, deduplicação, idempotência e rollback; tratar os nomes como sinônimos sem conciliar seus contratos pode perder requisitos.
- [Inferido] É necessária uma decisão do responsável pelo projeto: indicar a fonte normativa e o contrato a adotar, incluindo se os requisitos do outro documento devem ser incorporados ou substituídos. Este registro não recomenda nem implementa uma das alternativas.

### Retomada após a decisão

- [Inferido] Com a decisão registrada, conciliar as referências afetadas antes de declarar uma fonte única no `AGENTS.md` ou no índice central.
- [Confirmado] Nesta execução, a leitura integral de `docs/`, a indexação do legado, os documentos solicitados e a revisão/validação MySQL 8.4 dos schemas permanecem pendentes. Somente este registro foi criado; não houve execução de SQL nem alteração do legado ou dos schemas.
- [Inferido] A leitura restante pode revelar outras divergências; este registro não representa uma revisão completa da documentação.

## P02 — Sequência do MVP e dependência da etapa 7

- [Confirmado] O [fluxo 20](../fluxos/20-plano-desenvolvimento.md), §20.2, descreve o MVP como etapas `0 → 1 → 2 → 3 → 4 → 5 → 7`, mantendo laudo/Access por fora até a etapa 6. No quadro de dependências, no detalhamento da etapa 7 e no controle §20.4, a etapa 7 depende também da etapa 6.
- [Inferido] A sequência resumida do MVP e as dependências não permitem determinar sozinhas se existe uma migração parcial anterior à etapa 6. Confirmar o escopo do MVP e conciliar as passagens antes de executar essa sequência; não remover dependências nem declarar etapas concluídas para contornar a divergência.

## Continuidade documental — AGENTS.md

- [Confirmado] Após o pedido explícito do usuário para criar o arquivo de orquestração, foi criado o [AGENTS.md da raiz](../../AGENTS.md), com mapa das pastas e fontes, workflow e guardrails. Isso não resolve P01/P02 nem comprova leitura integral, implementação ou validação dos schemas.

## P03 — Modelo de isolamento no documento de migração

- [Confirmado] O [Processo de Migração dos Bancos Legados](<Precordis — Processo de Migração dos Bancos Legados.md>), seção “Modelo de destino no escopo da migração”, descrevia banco único com `tenant_id` nas tabelas. Os fluxos [09](../fluxos/09-estrategia-multi-tenant.md) e [21](../fluxos/21-schema-e-reaproveitamento.md), os DDLs e a instrução do usuário descrevem banco central + banco por tenant; o fluxo 21 explicita ausência de `tenant_id` nas tabelas da clínica.
- [Confirmado] A atualização de versões sinalizou essa divergência no documento de migração e preservou o split solicitado. Não conciliou o contrato do ETL nem alterou o desenho das tabelas.
- [Inferido] Revisar os mapeamentos e chaves do ETL antes de implementá-lo, conciliando esse modelo com o isolamento prescrito e com P01.

## Atualização de versões — 2026-10-31

- [Confirmado] Por novo pedido do usuário, as versões de destino de PHP/Laravel e MySQL ou MariaDB passaram a ter fonte única no [fluxo 14 §14.1](../fluxos/14-arquitetura-laravel.md#141-stack--fonte-única-de-versões), com consulta a fontes oficiais. Isso substitui a exigência anterior de MySQL 8.4 como versão de validação; a restrição a local/staging e a proibição de DDL/DML em produção continuam vigentes.
- [Confirmado] Em ação anterior autorizada, foi criado o banco local vazio `novo_sistema_clinica` no MariaDB 10.5.29, com `utf8mb4_unicode_ci`. A instância não foi atualizada e os DDLs não foram executados nesta atualização documental; a homologação nas versões de destino permanece pendente.

## P04 — ACL com teams versus tabelas sem identificador de equipe

- [Confirmado] O [fluxo 11, §11.2](../fluxos/11-identidade-autenticacao-acl.md#112-acl-multi-tenant), linha 21, prescreve `teams = tenant`. O `tenant.sql` (histórico; ver [changelog](../schema/CHANGELOG.md)), linhas 85–130, declara ACL isolada por banco, sem `team_id` nas tabelas de papéis e associações.
- [Inferido] É necessário conciliar a configuração do pacote e as migrations antes de implementar ACL: habilitar teams sem ajustar o schema pode produzir consultas a colunas inexistentes. Não foi escolhida uma alternativa nesta análise.

## P05 — Garantia de unicidade de agendamento com soft delete

- [Confirmado] O [fluxo 21](../fluxos/21-schema-e-reaproveitamento.md), linha 21, descreve o índice de agendamento como correção da trava de duplicidade. O `tenant.sql` (histórico; ver [changelog](../schema/CHANGELOG.md)), linhas 401–404, define `deleted_at` nullable e `UNIQUE(patient_id, scheduled_date, deleted_at)`.
- [Confirmado] Um índice UNIQUE do MySQL permite múltiplos valores NULL. Assim, esse índice não impede duas linhas com o mesmo paciente e dia quando `deleted_at IS NULL`, contrariando a garantia descrita. Fonte: [documentação oficial de índices únicos](https://dev.mysql.com/doc/refman/8.4/en/create-index.html#unique-indexes).
- [Inferido] Definir a regra de agendamento ativo, inclusive cancelamento e possibilidade de múltiplos horários/unidades no mesmo dia, antes de substituir o índice. Uma chave gerada para registros ativos é uma alternativa a avaliar; nenhum DDL foi alterado ou executado nesta análise.

## Observações da leitura estática dos schemas — 2026-10-31

- [Confirmado] O DDL tem colunas relacionais sem FK declarada: `health_records.appointment_id` (tenant.sql:348), `appointment_exams.room_id` (tenant.sql:436) e `documents.company_id` (tenant.sql:569). Revisar os vínculos nas migrations; um índice isolado não estabelece integridade referencial.
- [Confirmado] Os scripts desabilitam `FOREIGN_KEY_CHECKS` durante a criação e o reabilitam no final. Isso não substitui homologação de integridade; no MySQL, reabilitar a opção não verifica retroativamente as linhas existentes. Fonte: [documentação oficial de FKs](https://dev.mysql.com/doc/refman/8.4/en/create-table-foreign-keys.html#foreign-key-checks).
- [Confirmado] Esta revisão foi somente leitura dos arquivos SQL. Não executou queries, importação, DDL/DML nem correções nos schemas ou no legado.

## Resolução — 2026-10-31 (decisão: BANCO ÚNICO + tenant_id)

> O usuário adotou **banco único + `tenant_id` + global scopes**. `central.sql`/`tenant.sql` foram substituídos por `../schema/schema.sql` (50 tabelas). Isso resolve parte das pendências acima:

- [Confirmado] **P03 — resolvida a favor do banco único.** O modelo do documento de migração (banco único com `tenant_id`) passou a ser o oficial; fluxos 09/21 e `schema.sql` foram atualizados. Não há mais "banco por tenant" a conciliar.
- [Confirmado] **P04 — resolvida.** O `schema.sql` declara ACL em modo Spatie *teams*: `permissions` global e `roles`/`model_has_roles`/`model_has_permissions` com `tenant_id`. Habilitar `teams` no pacote passa a casar com o schema.
- [Confirmado] **P05 — resolvida no DDL.** `appointments` ganhou coluna gerada `active_flag` (`= 1` ativo, `NULL` soft-deleted) e a chave passou a `UNIQUE(tenant_id, patient_id, scheduled_date, active_flag)`, que bloqueia de fato dois agendamentos **ativos** do mesmo paciente no mesmo dia. **Em aberto (negócio):** se a clínica permitir múltiplos agendamentos no mesmo dia por unidade/horário, incluir `unit_id`/`scheduled_at` na chave (perguntas #8/#9).
- [Confirmado] **Observações de FK — resolvidas.** Adicionadas FKs: `health_records.appointment_id → appointments`, `appointment_exams.room_id → rooms`, `documents.company_id → companies` (todas `ON DELETE SET NULL`).
- [Atenção] **P01 (legacy_map vs legacy_references) e P02 (sequência do MVP / dependência da etapa 7) continuam ABERTAS** — são decisões do responsável pelo projeto; a mudança para banco único não as resolve. O documento de migração usa `legacy_references`; os fluxos 13/18/20 usam `legacy_map`.
- [Confirmado] Validação dos schemas segue **somente estrutural** (parênteses/crases, FKs resolvidas, constraints únicas, `tenant_id`+FK em toda tabela do tenant). Nenhum SQL executado em servidor; homologação no motor/versão de destino permanece pendente.
- [Inferido] Referências a `tenant.sql`/`central.sql` com números de linha nas pendências acima são históricas; o DDL vigente é `../schema/schema.sql`.

## Revisão documental — 2026-11-03

- [Confirmado] A revisão do estado local está em [Relatório do SaaS clínico e ocupacional](../analise/08-relatorio-documentacao-saas-2026-11-03.md). Os registros abaixo acrescentam divergências encontradas sem apagar as resoluções anteriores ou escolher novas alternativas. Não houve alteração do legado, dos DDLs ou execução de SQL.

### P06 — Resolução de isolamento não propagada às fontes de execução

- [Confirmado] Embora P03 esteja registrada como resolvida, [AGENTS.md](../../AGENTS.md), linhas 13–16, e [README.md](../../README.md), linha 13, continuam prescrevendo banco central + banco por clínica. Os fluxos [09](../fluxos/09-estrategia-multi-tenant.md)/[21](../fluxos/21-schema-e-reaproveitamento.md) e o [schema atual](../schema/schema.sql) prescrevem banco único. O índice dos fluxos contém ambas as descrições.
- [Confirmado] O [fluxo 20](../fluxos/20-plano-desenvolvimento.md), etapa 1, descreve migration única, mas §20.1 exige `tenants:migrate` e o aceite manda provisionar banco. O [fluxo 15, §15.2](../fluxos/15-qualidade-testes-devops.md) mantém migrations por tenant.
- [Inferido] Confirmar o alcance da decisão registrada e conciliar todas as fontes antes da implementação de tenancy/migrations. A revisão preserva as mudanças locais existentes e não restaura os antigos DDLs.

### P07 — Pré-requisito de alterar o legado versus escopo somente leitura

- [Confirmado] O [fluxo 20](../fluxos/20-plano-desenvolvimento.md), §20.2–20.3, condiciona a fundação à etapa 0, com patches e verificações em produção. Os fluxos 13/17 também contêm ações sobre o legado. A orientação atual do usuário e o [AGENTS.md, Segurança](../../AGENTS.md) limitam o legado a leitura/aprendizado.
- [Inferido] Conciliar o plano separando responsabilidades de contenção operacional e construção do novo sistema; não executar essas alterações nem declarar a etapa 0 concluída neste escopo.

### P08 — Vuexy e matriz única versus interface/versões concorrentes

- [Confirmado] O [AGENTS.md](../../AGENTS.md) estabelece Vuexy e referência única de versões em [14, §14.1](../fluxos/14-arquitetura-laravel.md). O fluxo 14 mantém Filament/Inertia/React como propostas e o [fluxo 20](../fluxos/20-plano-desenvolvimento.md) mantém interface “a definir” e versões anteriores no cabeçalho/etapa 1.
- [Inferido] Definir integração com a referência Vuexy já escolhida e alinhar as instruções de implementação, sem substituir o design system por decisão implícita.

### P09 — Mapeamentos e garantias documentados não correspondem ao DDL

- [Confirmado] Os fluxos [12](../fluxos/12-novo-schema-unificado.md)/[14](../fluxos/14-arquitetura-laravel.md) e o [processo de migração](<Precordis — Processo de Migração dos Bancos Legados.md>) descrevem entidades como vínculos empresariais, visitas, consentimentos e assinaturas que não têm correspondência explícita no [schema](../schema/schema.sql). O processo também prevê CPF criptografado + hash, enquanto o DDL mantém `cpf CHAR(11)` indexado diretamente, sem hash.
- [Confirmado] O cabeçalho do DDL e o fluxo 09 dizem que centrais não têm `tenant_id`, embora `domains/subscriptions/invoices/usage_records` o tenham; dizem NOT NULL em toda tabela de tenant, mas `roles/activity_log` aceitam NULL e `role_has_permissions` não possui essa coluna.
- [Inferido] Conciliar os conceitos, a classificação de propriedade e as exceções; não tratar diagramas como mera renomeação do DDL nem aplicar criptografia incompatível com os campos existentes.

### P10 — Divergências no ETL e na convivência com o Access

- [Confirmado] O [processo de migração](<Precordis — Processo de Migração dos Bancos Legados.md>), mapeamento de agendamento, propõe derivar status de `agendamento.Status`; [análise 07](../analise/07-inconsistencias-modelagem.md) e [fluxo 13](../fluxos/13-plano-migracao-dados.md) determinam descartar essa coluna e derivar estado de flags. O processo prevê sincronização externa funcionando após o corte; [fluxo 08](../fluxos/08-visao-produto-bounded-contexts.md) prevê descartar o sincronizador após ETL.
- [Inferido] Definir o de-para e a duração da convivência antes dos importadores/corte; manter P01/P02 abertas. Revisar também regras de match automático diante de logins duplicados e delta por PK crescente para resultados que possam ser atualizados.

### P11 — ASO/ocupacional prometido sem contrato de domínio correspondente

- [Confirmado] O [produto](../fluxos/08-visao-produto-bounded-contexts.md) e a etapa 10 do [plano](../fluxos/20-plano-desenvolvimento.md) incluem ocupacional/ASO. O documento de migração propõe vínculo paciente–empresa com vigência; o DDL atual guarda empresa/cargo/matrícula no paciente, sem vínculo histórico ou avaliação/ASO estruturados.
- [Inferido] Definir os conceitos e critérios de aceite de ocupacional, o histórico de vínculo/função/riscos, a emissão do ASO e o limite de acesso empresarial antes dos módulos afetados. A ausência no legado não exclui esse requisito do novo SaaS.

### P12 — Referência legal e política de retenção precisam de conciliação

- [Confirmado] A [análise 05, §5.5](../analise/05-seguranca-lgpd-conformidade.md) indica tutela da saúde na alínea “a” do art. 11, II; a [LGPD oficial](https://www2.camara.leg.br/legin/fed/lei/2018/lei-13709-14-agosto-2018-787077-normaatualizada-pl.pdf) a situa na alínea “f”. O relatório detalha também a diferença entre retenção por último registro e por desligamento do empregado, e a generalização da exigência de assinatura.
- [Inferido] Revisar a fonte normativa com o responsável técnico/jurídico e definir retenção por categoria/marco, sem assumir exclusão clínica automática por desligamento do tenant ou aceite genérico como base de todas as finalidades. Não se alterou a política nem o schema nesta revisão.
