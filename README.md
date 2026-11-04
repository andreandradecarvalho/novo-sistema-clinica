# Novo Sistema Clínica — Precordis

[Confirmado] Repositório de preparação do novo sistema de gestão para clínicas de exames. O produto previsto é um SaaS por assinatura, com a Precordis como primeiro tenant, cobrindo o atendimento do agendamento à entrega de resultados e laudos. Consulte a [visão do produto](docs/fluxos/08-visao-produto-bounded-contexts.md).

## Estado do projeto

[Confirmado] A implementação começou na raiz. A fundação entrega autenticação Fortify/2FA, contexto de clínica, ACL por tenant, sessões, auditoria de acesso e interface Blade/Vuexy. Os checks locais estão registrados no [fluxo 20](docs/fluxos/20-plano-desenvolvimento.md); a homologação local isolada e a revisão técnica foram concluídas, com aceite autorizado da etapa 1. Por decisão do usuário, o CI remoto será verificado futuramente. A etapa 2 entrega [cadastro de pacientes](docs/outros/cadastro-pacientes.md), [busca e comparação de homônimos](docs/outros/busca-revisao-pacientes.md), [merge com vínculos empregatícios preservados](docs/outros/merge-pacientes.md), [empresas clientes/estabelecimentos](docs/outros/cadastro-empresas.md), [unidades clínicas/profissionais](docs/outros/cadastro-unidades-profissionais.md) e [vínculos empregatícios históricos](docs/outros/vinculos-empregaticios.md), além da [atribuição de integrantes a unidades](docs/outros/atribuicao-unidades.md) e da [gestão de concessões empresariais](docs/outros/concessoes-empresariais.md), com os limites registrados no plano. Os demais módulos clínicos e ocupacionais seguem o planejamento desse fluxo.

[Confirmado] Os arquivos SQL são referências de modelagem; sua presença não comprova homologação no banco de destino. As decisões documentais ainda abertas estão em [PENDENCIAS.md](docs/outros/PENDENCIAS.md). O andamento oficial das etapas fica no [plano de desenvolvimento, §20.4](docs/fluxos/20-plano-desenvolvimento.md#204-controle-de-fases-manter-atualizado).

## Arquitetura e tecnologias

[Confirmado] A arquitetura de destino usa **PHP/Laravel**, **MySQL ou MariaDB** e **Vuexy** como referência de interface. O isolamento é organizado em **banco único com tenant_id**, separação lógica e autorização por clínica, conforme a [estratégia multi-tenant](docs/fluxos/09-estrategia-multi-tenant.md).

[Confirmado] As versões exatas, fontes oficiais, política de atualização e requisitos de homologação são mantidos somente na [matriz de arquitetura, §14.1](docs/fluxos/14-arquitetura-laravel.md#141-stack--fonte-única-de-versões). As dependências efetivamente instaladas estão fixadas em composer.lock e package-lock.json; componentes de fases posteriores continuam propostas.

[Confirmado] Mapa central das fontes: [docs/README.md](docs/README.md). Saúde ocupacional possui contrato próprio de vínculos, avaliação e ASO, integrado ao atendimento dos demais exames.

## Documentação

[Confirmado] Cada assunto deve ser consultado em sua fonte, sem reproduzir especificações neste README:

| Estado | Pasta | Conteúdo e ponto de entrada |
|---|---|---|
| [Confirmado] | [docs/analise/](docs/analise/) | Diagnóstico do legado, regras, segurança e dicionário de dados. Começar pelo [sumário executivo](docs/analise/00-sumario-executivo.md). |
| [Confirmado] | [docs/fluxos/](docs/fluxos/) | Especificação do novo produto, arquitetura, migração, qualidade e planejamento. Consultar o [índice dos fluxos](docs/fluxos/00-indice.md). |
| [Confirmado] | [raiz do repositório](./) | Aplicação Laravel na raiz, com estrutura prevista na [arquitetura §14.2](docs/fluxos/14-arquitetura-laravel.md#142-estrutura-de-pastas-modular-por-domínio). |
| [Confirmado] | [docs/layout/](docs/layout/) | Referências visuais e recursos Vuexy. Consultar [assets e modelos por página](docs/layout/vuexy-assets-e-modelos-por-pagina.md) e [cobertura das páginas](docs/layout/vuexy-cobertura-completa-144-paginas.md). |
| [Confirmado] | [docs/schema/](docs/schema/) | DDL de referência: [schema.sql](docs/schema/schema.sql) e [histórico de mudanças](docs/schema/CHANGELOG.md). |
| [Confirmado] | [docs/outros/](docs/outros/) | Material complementar e [pendências de conciliação](docs/outros/PENDENCIAS.md). |

## Organização do repositório

[Confirmado] Estrutura disponível nesta fase:

```text
.
├── .codex/agents/precordis.toml
├── app/
├── bootstrap/
├── config/
├── database/
├── docker/
├── docs/
│   ├── analise/
│   ├── fluxos/
│   ├── layout/
│   ├── outros/
│   └── schema/
├── public/
├── resources/
├── routes/
├── scripts/
├── storage/
├── tests/
├── artisan
├── composer.json
├── composer.lock
├── compose.yaml
├── package.json
├── AGENTS.md
├── README.md
└── novo-sistema-clinica.code-workspace
```

## Como começar

1. [Confirmado] Ler o [AGENTS.md](AGENTS.md), que orquestra a implementação e define fontes, convenções e guardrails.
2. [Confirmado] Consultar as [pendências](docs/outros/PENDENCIAS.md) e o [plano de desenvolvimento](docs/fluxos/20-plano-desenvolvimento.md) antes de iniciar uma etapa. Respeitar dependências e critérios de aceite.
3. [Confirmado] Para trabalhar no VS Code, abrir [novo-sistema-clinica.code-workspace](novo-sistema-clinica.code-workspace), configurado para a raiz do projeto.
4. [Confirmado] Para tarefas com o agente do projeto, consultar [.codex/agents/precordis.toml](.codex/agents/precordis.toml). Suas instruções apontam para o `AGENTS.md` e a documentação existente.

## Ambiente local

[Confirmado] Executar na raiz, com Docker Compose, Python 3 e Node 24. O PHP do host não atende ao composer.json; os comandos PHP abaixo usam o container. O preparador gera valores ausentes somente no .env ignorado pelo Git, preservando os existentes e sem exibi-los.

```bash
python3 scripts/prepare_local_env.py
docker compose build app
docker compose up -d --wait db redis
docker compose run --rm --no-deps app composer install --prefer-dist
npm ci --ignore-scripts
npm run build

# Somente no banco local do Compose, inicialmente vazio.
docker compose run --rm --no-deps app php artisan migrate
docker compose run --rm --no-deps app php artisan tenant:create Precordis --slug=precordis --domain=localhost

# Substituir pelos dados nominais do administrador. Senha solicitada sem eco.
docker compose run --rm --no-deps app php artisan tenant:admin precordis seu-email@example.test "Seu nome"
docker compose up -d app
```

[Confirmado] Acesso: http://localhost:8080/login. A criação do administrador exige senha forte; o primeiro acesso solicita troca de senha e configuração do segundo fator antes de liberar acesso clínico. Não existe administrador ou senha padrão. tenant:create é idempotente; tenant:admin cria um usuário novo e rejeita e-mail duplicado na clínica.

[Confirmado] O Compose usa uma instância MariaDB própria, em volume separado, com host interno db:3306. Não publica essa porta nem altera a instância anteriormente acessada em 127.0.0.1:3306. Dentro do container, DB_HOST e REDIS_HOST são definidos pelo Compose. Apenas public/ pode ser webroot; docs/ e .env não são públicos.

[Confirmado] O banco novo_sistema_clinica_testing é criado pelo inicializador da primeira instalação do volume. A suíte exige esse nome e host local; usa somente fixtures sintéticas. Não apontar testes ao banco de trabalho nem executar migrate:fresh nele.

```bash
# Catálogo global; papéis por clínica são configurados em tenant:create.
docker compose run --rm --no-deps app php artisan db:seed

# Pint, Rector sem alterações, Larastan e Pest, incluindo worker Redis real.
docker compose run --rm --no-deps app composer check
npm run build

# Worker local; manter MariaDB/Redis ativos.
docker compose run --rm --no-deps app php artisan horizon

# Encerrar serviços preservando o volume de desenvolvimento.
docker compose down
```

[Confirmado] O dashboard Horizon nega acesso pela web, inclusive local, até existir identidade operacional da plataforma; usuários clínicos não podem inspecionar jobs de outras clínicas. Redis de desenvolvimento é descartável. SMTP, HTTPS e infraestrutura de staging ainda exigem configuração própria. O [workflow de CI](.github/workflows/ci.yml) executa os mesmos checks em ambiente descartável; não há resultado remoto publicado nesta sessão.

## Homologação local com duas clínicas

[Confirmado] Ambiente independente na porta 8082, com banco/Redis/storage próprios e código somente leitura. Preparação, operação, acessos privados e evidências: [homologação local](docs/outros/homologacao-local.md). O ambiente de desenvolvimento na porta 8080 permanece separado.

## Desenvolvimento e contribuição

- [Confirmado] O [fluxo 20](docs/fluxos/20-plano-desenvolvimento.md) é a fonte do ciclo de implementação, branches, PRs, dependências e registro das sessões.
- [Confirmado] O [fluxo 15](docs/fluxos/15-qualidade-testes-devops.md) define a estratégia de testes e CI, incluindo isolamento entre tenants.
- [Confirmado] Mudanças no DDL devem preservar isolamento e contratos clínicos/ocupacionais, ter justificativa e registro no [changelog do schema](docs/schema/CHANGELOG.md).
- [Confirmado] Divergências devem ser registradas em [PENDENCIAS.md](docs/outros/PENDENCIAS.md), sem escolher uma versão silenciosamente. Não declarar funcionalidades ou etapas concluídas sem evidências de implementação e validação.

## Segurança e dados

- [Confirmado] Não incluir credenciais de produção neste README ou em outros arquivos versionados; seguir as regras de segredos e ambiente do [AGENTS.md](AGENTS.md#segurança-lgpd-e-referência-legada).
- [Confirmado] Não executar DDL/DML em produção. Validações devem usar bancos autorizados local/staging, no motor e versão de destino.
- [Confirmado] O legado é referência somente leitura. Respeitar as exclusões de indexação definidas no `AGENTS.md`; não copiar PDFs de laudos nem dados de pacientes para o repositório.
- [Confirmado] Usar dados sintéticos em fixtures e testes. Requisitos de acesso, auditoria e proteção de dados estão no [diagnóstico de segurança e LGPD](docs/analise/05-seguranca-lgpd-conformidade.md) e nos fluxos do domínio.
