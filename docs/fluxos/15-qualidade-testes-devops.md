# 15 — Qualidade, testes, DevOps e observabilidade

## 15.1 Estratégia de testes (Pest 4)

| Tipo | O que cobre | Meta |
|---|---|---|
| **Unitários** | Value Objects (`Cpf`, `Money`, `PeriodoAgenda`), regras de agenda, derivação de status, dedupe | alta p/ regras críticas |
| **Feature (HTTP)** | fluxos: agendar → confirmar → check-in → chamar → laudar → liberar; billing; reset de senha | fluxos principais cobertos |
| **Isolamento de tenant** | 2 tenants: um nunca acessa dado do outro em query/cache/fila/storage/broadcast ([09](09-estrategia-multi-tenant.md) §9.4) | **100% — bloqueante no CI** |
| **Permissão por papel** | cada papel só faz o que pode; médico só assina o que executou; empresa só vê seus funcionários | cobertura dos papéis |
| **Arquitetura (Pest Arch)** | controllers não acessam Models direto; domínio não depende de HTTP; nenhum SDK de gateway fora de `Billing/Gateways`; nenhuma query a modelo de tenant sem contexto | regras de fronteira |

**Cobertura**: meta alta (ex.: 90%+) nas regras críticas (agenda, ACL, billing, laudo), não perseguir 100% global.

## 15.2 CI/CD

- **GitHub Actions**: pipeline `pint` (estilo) → `rector --dry-run` → `larastan` (nível máx viável) → `pest` (inclui tenancy/arch) → build front (Vite) → artefato.
- **Deploy** com **migrations seguras / zero-downtime** (expand-contract: adiciona coluna → backfill → troca uso → remove), rodando migration **por tenant** (`tenants:migrate`).
- **Ambientes**: local (**Laravel Sail** ou **Herd**), **staging** (espelho de 1 tenant anonimizado), **produção**.
- [Confirmado] **Versões dos ambientes e do CI:** usar a [matriz de destino em 14 §14.1](14-arquitetura-laravel.md#141-stack--fonte-única-de-versões). Registrar PHP, Laravel e servidor efetivos; validar migrations, seeds, FKs e isolamento de dois tenants no motor escolhido. Sucesso no MariaDB 10.5 local não homologa as versões de destino; suporte simultâneo a MySQL/MariaDB exige testes em ambos. Usar bancos descartáveis de teste, sem DDL/DML em produção.
- **Docker** para paridade; segredos em cofre/variáveis de ambiente (nunca no git — lição do legado).

## 15.3 Observabilidade e operação

- **Logs estruturados** por tenant; **monitoramento de erros** (Sentry/Flare); **métricas por tenant** (Pulse).
- **Horizon** para filas (notificações, ETL, geração de laudo).
- **Health checks**: fila, Reverb, storage, conexão de cada tenant, e — enquanto houver — o **sincronizador do Access** (o legado não monitorava: o sync ficou parado desde 29/10 sem alarme, e o `send-mail` morto desde 2022 passou despercebido).
- **Backups** com retenção compatível com a **guarda de prontuário (≥ 20 anos)**: backup do banco por tenant + do storage de laudos; **restore testado por tenant**; política de retenção documentada.
- **Alertas**: fila travada, falha de pagamento, tenant próximo do limite do plano, laudo não assinado há X dias.
