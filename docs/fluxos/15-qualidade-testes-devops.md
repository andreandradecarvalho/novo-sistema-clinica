# 15 — Qualidade, testes, DevOps e observabilidade

## 15.1 Estratégia de testes (Pest 4)

[Confirmado] A suíte local configura `memory_limit=256M` em [phpunit.xml](../../phpunit.xml), restrito ao processo de testes. O limite padrão de 128 MiB foi atingido pela execução integral; essa configuração não altera PHP da aplicação ou do worker. Evidências de execução no fluxo 20.

| Tipo | O que cobre | Meta |
|---|---|---|
| **Unitários** | Value Objects (`Cpf`, `Money`, `PeriodoAgenda`), regras de agenda, derivação de status, dedupe | alta p/ regras críticas |
| **Feature (HTTP)** | fluxos: agendar → confirmar → check-in → chamar → laudar → liberar; billing; reset de senha | fluxos principais cobertos |
| **Isolamento de tenant** | 2 tenants: um nunca acessa dado do outro em query/cache/fila/storage/broadcast ([09](09-estrategia-multi-tenant.md) §9.4) | **100% — bloqueante no CI** |
| **Permissão por papel** | cada papel só faz o que pode; autoria/revisão/liberação conforme habilitação; empresa vê somente categorias autorizadas por vínculo histórico | cobertura dos papéis |
| **Arquitetura (Pest Arch)** | controllers não acessam Models direto; domínio não depende de HTTP; nenhum SDK de gateway fora de `Billing/Gateways`; nenhuma query a modelo de tenant sem contexto | regras de fronteira |

**Cobertura**: meta alta (ex.: 90%+) nas regras críticas (agenda, ACL, billing, laudo), não perseguir 100% global.

## 15.2 CI/CD

- **GitHub Actions**: pipeline `pint` (estilo) → `rector --dry-run` → `larastan` (nível máx viável) → `pest` (inclui tenancy/arch) → build front (Vite) → artefato.
- **Deploy** com **migrations seguras / zero-downtime** (expand-contract: adiciona coluna → backfill → troca uso → remove), rodando migration **uma vez no banco compartilhado** (`migrate`), com regressão em dois tenants.
- **Ambientes**: local (**Laravel Sail** ou **Herd**), **staging** (dois tenants sintéticos; cópias clínicas autorizadas isoladas do ambiente comum), **produção**.
- **Docker** para paridade; segredos em cofre/variáveis de ambiente (nunca no git — lição do legado).

## 15.3 Observabilidade e operação

- **Logs estruturados** por tenant; **monitoramento de erros** (Sentry/Flare); **métricas por tenant** (Pulse).
- **Horizon** para filas (notificações, ETL, geração de laudo).
- **Health checks**: fila, Reverb, storage, banco compartilhado e resolução de contexto, e — enquanto houver — o **sincronizador do Access** (o legado não monitorava: o sync ficou parado desde 29/10 sem alarme, e o `send-mail` morto desde 2022 passou despercebido).
- **Backups** com retenção compatível com a **guarda de prontuário (≥ 20 anos)**: backup do banco único + do storage de laudos; **export/restore por `tenant_id`** testado (portabilidade e offboarding LGPD); política de retenção/arquivamento documentada (ver [21](21-schema-e-reaproveitamento.md) §retenção).
- **Alertas**: fila travada, falha de pagamento, tenant próximo do limite do plano, laudo não assinado há X dias.

## 15.4 Contratos adicionais de aceite

- [Inferido] Testes clínicos/ocupacionais e concorrência seguem [22](22-atendimento-clinico.md)/[23](23-saude-ocupacional.md); autorização por categoria, restore e RPO/RTO seguem [24](24-seguranca-retencao-operacao.md). Não declarar 100% de isolamento com base apenas em cobertura de linhas.
- [Confirmado] Validação SQL reproduzível: scripts/validate_schema.py, a partir da raiz; resultados registrados no [CHANGELOG](../schema/CHANGELOG.md). Não equivale a suíte da aplicação.
