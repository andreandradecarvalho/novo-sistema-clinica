# 09 — Estratégia multi-tenant

## 9.1 Decisão consolidada

[Confirmado] O estado documental consolidado em 03/11/2026 segue o ADR já registrado: **banco único, tenant_id e isolamento lógico**. O pedido de corrigir pendências autoriza conciliar as fontes que ainda descreviam banco por clínica. Não se apresenta essa escolha como exigência universal de SaaS de saúde. [Schema](../schema/schema.sql), [arquitetura](14-arquitetura-laravel.md) e [execução](20-plano-desenvolvimento.md) seguem o mesmo modelo.

[Inferido] A opção reduz multiplicação de migrations/bancos, mas aumenta responsabilidade de autorização e recuperação seletiva. Custo, escala e SLA precisam de medição; não afirmar custo mínimo ou onboarding instantâneo sem evidência. Banco por tenant e híbrido continuam alternativas para exigências contratuais futuras, sem implementação presumida.

## 9.2 Propriedade dos dados

| Categoria | Exemplos | Regra |
|---|---|---|
| [Confirmado] Global da plataforma | platform_users, tenants, plans, plan_features, permissions, coupons | Não aplicar scope de clínica indiscriminadamente. |
| [Confirmado] Plataforma associada ao tenant | domains, subscriptions, invoices, usage_records | Possuem tenant_id; acesso central autorizado e área comercial da clínica com escopo. |
| [Confirmado] Propriedade indireta | payments via invoice, subscription_items via subscription, role_has_permissions via role | Autorizar pelo pai; não pressupor tenant_id presente na tabela filha. |
| [Confirmado] Clínica | patients, appointments, results/documents e demais domínios clínicos | tenant_id obrigatório e vínculos com pais da mesma clínica. |
| [Confirmado] Exceções técnicas de ACL/auditoria | roles admite tenant_id NULL para papel global; activity_log admite NULL para evento de plataforma | Não usar NULL para evento clínico. Pivôs polimórficos exigem validação específica. |

## 9.3 Contrato de isolamento

- [Inferido] Middleware resolve domínio/header/token e confirma associação do principal autenticado ao tenant. Header não concede acesso. Sem contexto válido, toda operação clínica falha de forma fechada.
- [Inferido] BelongsToTenant aplica scope Eloquent, define tenant_id no create e rejeita troca no update. SQL cru, bulk, joins, imports e relatórios obedecem ao mesmo contrato. Scope não equivale a RLS: não é política obrigatória do servidor e pode ser removido.
- [Inferido] FKs compostas de tenant + id asseguram igualdade do tenant nos vínculos suportados pelo DDL. Não garantem sozinhas autorização clínica ou igualdade de paciente em vínculos redundantes; Actions/Policies verificam ambos.
- [Inferido] ACL teams usa tenant_id como team key; roles globais são exceção explícita, sem concessão automática de leitura clínica. Trocar de contexto limpa cache/roles carregados. Pivôs polimórficos validam tenant do usuário e do papel, permitindo global somente quando previsto.
- [Inferido] Cache, sessão, busca, storage, tokens, filas e canais são isolados. Workers restauram/limpam contexto inclusive após erro. Administração cross-tenant exige caso de uso explícito e auditoria; nunca exposição irrestrita de prontuário por ser super-admin.

## 9.4 Operação e teste

- [Inferido] Migration roda **uma vez** no banco compartilhado; tenant:create cria tenant/domínio e sementes com contexto, sem CREATE DATABASE. Seeds globais e por tenant são distintos. Onboarding é transacional/idempotente.
- [Inferido] Dois tenants sintéticos nos testes: leitura/escrita, IDOR, FK cruzada, header falso, contexto ausente, worker reutilizado, cache de ACL, download, busca e broadcast. Exigir proteção independentemente da interface.
- [Inferido] Backup, restore seletivo, desligamento e retenção seguem [24](24-seguranca-retencao-operacao.md). Não apagar tenant em cascata como rotina de offboarding.
- [Inferido] Extração para banco dedicado requer mapa de dependências indiretas, migração de storage/chaves/auth, corte consistente e ensaio. Não é somente executar WHERE tenant_id.
