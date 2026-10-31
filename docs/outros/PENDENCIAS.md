# Pendências de conciliação documental

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
