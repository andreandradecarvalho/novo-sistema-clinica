# Proposta — Novo sistema (SaaS multi-tenant em Laravel)

> Especificação oficial do produto que substitui o legado Precordis. Parte do diagnóstico em [`../analise/`](../analise/00-sumario-executivo.md) e segue a numeração de seções do escopo do projeto. Versões de tecnologia **verificadas em 31/10/2026** (reconfirmar no início da implementação).

## Em uma frase

Plataforma por **assinatura** para clínicas de exames: do agendamento à entrega do laudo assinado digitalmente, com painel de TV em tempo real, portais de paciente e empresa, e auditoria LGPD — cada clínica isolada logicamente por tenant_id no banco compartilhado. A **Precordis é o primeiro tenant**.

## Índice

| Seção | Documento |
|---|---|
| 8 | [Visão do produto, bounded contexts e matriz de rastreabilidade](08-visao-produto-bounded-contexts.md) |
| 9 | [Estratégia multi-tenant](09-estrategia-multi-tenant.md) |
| 10 | [Assinatura e billing](10-assinatura-billing.md) |
| 11 | [Identidade, autenticação e ACL (+ mapeamento dos níveis legados)](11-identidade-autenticacao-acl.md) |
| 12 | [Novo schema unificado (com ER por módulo)](12-novo-schema-unificado.md) |
| 13 | [Plano de migração de dados](13-plano-migracao-dados.md) |
| 14 | [Arquitetura Laravel (stack, pastas, models, migrations, SOLID/DRY)](14-arquitetura-laravel.md) |
| 15 | [Qualidade, testes, DevOps e observabilidade](15-qualidade-testes-devops.md) |
| 16 | [Novas funcionalidades e melhorias](16-novas-funcionalidades.md) |
| 17 | [Roadmap em fases (MVP → corte, strangler)](17-roadmap-fases.md) |
| 18 | [Riscos e pontos de atenção da migração](18-riscos-migracao.md) |
| 19 | [Perguntas em aberto para o negócio](19-perguntas-em-aberto-negocio.md) |
| 20 | [Plano de desenvolvimento: fluxo, etapas e testes](20-plano-desenvolvimento.md) |
| 21 | [Schema da base de dados + o que aproveitar do tailadmin](21-schema-e-reaproveitamento.md) · SQL em [schema.sql](../schema/schema.sql) |

## Fontes normativas

- [Confirmado] Versões e integração Vuexy: [14](14-arquitetura-laravel.md). Banco único: [09](09-estrategia-multi-tenant.md). Execução/status: [20](20-plano-desenvolvimento.md). DDL: [schema](../schema/schema.sql).
- [Confirmado] [22 — Atendimento e financeiro clínico](22-atendimento-clinico.md), [23 — Saúde ocupacional e ASO](23-saude-ocupacional.md), [24 — Segurança, retenção e operação](24-seguranca-retencao-operacao.md).
- [Confirmado] Fontes completas e classificação de documentos: [mapa central](../README.md). Legado somente leitura; etapa 0 não executa contenção no legado.
