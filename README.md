# Novo Sistema Clínica — Precordis

[Confirmado] Repositório de preparação do novo sistema de gestão para clínicas de exames. O produto previsto é um SaaS por assinatura, com a Precordis como primeiro tenant, cobrindo o atendimento do agendamento à entrega de resultados e laudos. Consulte a [visão do produto](docs/fluxos/08-visao-produto-bounded-contexts.md).

## Estado do projeto

[Confirmado] O repositório está na fase de documentação e preparação da implementação. Contém análises do legado, especificações funcionais, referências Vuexy, schemas SQL e instruções para agentes. A pasta [docs/laravel/](docs/laravel/) está reservada para a aplicação e seu conteúdo versionado contém apenas `.gitkeep`; ainda não há framework instalado, `composer.json`, `artisan`, migrations Laravel, seeders ou suíte de testes da aplicação.

[Confirmado] Os arquivos SQL são referências de modelagem; sua presença não comprova homologação no banco de destino. As decisões documentais ainda abertas estão em [PENDENCIAS.md](docs/outros/PENDENCIAS.md). O andamento oficial das etapas fica no [plano de desenvolvimento, §20.4](docs/fluxos/20-plano-desenvolvimento.md#204-controle-de-fases-manter-atualizado).

## Arquitetura e tecnologias

[Confirmado] A arquitetura de destino usa **PHP/Laravel**, **MySQL ou MariaDB** e **Vuexy** como referência de interface. O isolamento é organizado em **banco central para a plataforma e um banco por clínica**, conforme a [estratégia multi-tenant](docs/fluxos/09-estrategia-multi-tenant.md).

[Confirmado] As versões exatas, fontes oficiais, política de atualização e requisitos de homologação são mantidos somente na [matriz de arquitetura, §14.1](docs/fluxos/14-arquitetura-laravel.md#141-stack--fonte-única-de-versões). As tecnologias indicadas representam o destino da implementação, não dependências já instaladas neste repositório.

## Documentação

[Confirmado] Cada assunto deve ser consultado em sua fonte, sem reproduzir especificações neste README:

| Estado | Pasta | Conteúdo e ponto de entrada |
|---|---|---|
| [Confirmado] | [docs/analise/](docs/analise/) | Diagnóstico do legado, regras, segurança e dicionário de dados. Começar pelo [sumário executivo](docs/analise/00-sumario-executivo.md). |
| [Confirmado] | [docs/fluxos/](docs/fluxos/) | Especificação do novo produto, arquitetura, migração, qualidade e planejamento. Consultar o [índice dos fluxos](docs/fluxos/00-indice.md). |
| [Confirmado] | [docs/laravel/](docs/laravel/) | Pasta reservada à aplicação Laravel, com estrutura prevista na [arquitetura §14.2](docs/fluxos/14-arquitetura-laravel.md#142-estrutura-de-pastas-modular-por-domínio). |
| [Confirmado] | [docs/layout/](docs/layout/) | Referências visuais e recursos Vuexy. Consultar [assets e modelos por página](docs/layout/vuexy-assets-e-modelos-por-pagina.md) e [cobertura das páginas](docs/layout/vuexy-cobertura-completa-144-paginas.md). |
| [Confirmado] | [docs/schema/](docs/schema/) | DDL de referência: [central.sql](docs/schema/central.sql), [tenant.sql](docs/schema/tenant.sql) e [histórico de mudanças](docs/schema/CHANGELOG.md). |
| [Confirmado] | [docs/outros/](docs/outros/) | Material complementar e [pendências de conciliação](docs/outros/PENDENCIAS.md). |

## Organização do repositório

[Confirmado] Estrutura disponível nesta fase:

```text
.
├── .codex/
│   └── agents/
│       └── precordis.toml
├── docs/
│   ├── analise/
│   ├── fluxos/
│   ├── laravel/
│   │   └── .gitkeep
│   ├── layout/
│   ├── outros/
│   └── schema/
├── AGENTS.md
├── README.md
└── novo-sistema-clinica.code-workspace
```

## Como começar

1. [Confirmado] Ler o [AGENTS.md](AGENTS.md), que orquestra a implementação e define fontes, convenções e guardrails.
2. [Confirmado] Consultar as [pendências](docs/outros/PENDENCIAS.md) e o [plano de desenvolvimento](docs/fluxos/20-plano-desenvolvimento.md) antes de iniciar uma etapa. Respeitar dependências e critérios de aceite.
3. [Confirmado] Para trabalhar no VS Code, abrir [novo-sistema-clinica.code-workspace](novo-sistema-clinica.code-workspace), configurado para a raiz do projeto.
4. [Confirmado] Para tarefas com o agente do projeto, consultar [.codex/agents/precordis.toml](.codex/agents/precordis.toml). Suas instruções apontam para o `AGENTS.md` e a documentação existente.

[Confirmado] A instalação e os comandos de migrations, seeds e testes dependem da implementação da fundação e devem usar `docs/laravel/` como diretório de trabalho. A documentação continua nas demais subpastas de `docs/`. O roteiro condicional está no [AGENTS.md](AGENTS.md#execução-local-migrations-seeds-e-testes); a pasta reservada ainda não contém aplicação executável.

## Desenvolvimento e contribuição

- [Confirmado] O [fluxo 20](docs/fluxos/20-plano-desenvolvimento.md) é a fonte do ciclo de implementação, branches, PRs, dependências e registro das sessões.
- [Confirmado] O [fluxo 15](docs/fluxos/15-qualidade-testes-devops.md) define a estratégia de testes e CI, incluindo isolamento entre tenants.
- [Confirmado] Mudanças no DDL devem preservar o split central/tenant, ter justificativa e registro no [changelog do schema](docs/schema/CHANGELOG.md).
- [Confirmado] Divergências devem ser registradas em [PENDENCIAS.md](docs/outros/PENDENCIAS.md), sem escolher uma versão silenciosamente. Não declarar funcionalidades ou etapas concluídas sem evidências de implementação e validação.

## Segurança e dados

- [Confirmado] Não incluir credenciais de produção neste README ou em outros arquivos versionados; seguir as regras de segredos e ambiente do [AGENTS.md](AGENTS.md#segurança-lgpd-e-referência-legada).
- [Confirmado] Não executar DDL/DML em produção. Validações devem usar bancos autorizados local/staging, no motor e versão de destino.
- [Confirmado] O legado é referência somente leitura. Respeitar as exclusões de indexação definidas no `AGENTS.md`; não copiar PDFs de laudos nem dados de pacientes para o repositório.
- [Confirmado] Usar dados sintéticos em fixtures e testes. Requisitos de acesso, auditoria e proteção de dados estão no [diagnóstico de segurança e LGPD](docs/analise/05-seguranca-lgpd-conformidade.md) e nos fluxos do domínio.
