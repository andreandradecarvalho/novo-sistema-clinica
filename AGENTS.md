# Orquestração da implementação — novo sistema Precordis

## Escopo e evidências

- [Confirmado] Este arquivo orienta o trabalho em todo o repositório `/home/somvibe/Projetos/pessoal/precordis/novo-sistema-clinica`. Instruções explícitas do usuário prevalecem sobre recomendações documentais.
- [Confirmado] O produto previsto é um SaaS por assinatura para clínicas de exames: cadastros, agenda, check-in, TV, resultados/laudos, portais, notificações, billing e auditoria. A Precordis será o primeiro tenant. Fonte: [visão do produto](docs/fluxos/08-visao-produto-bounded-contexts.md).
- [Confirmado] A aplicação deve ser implementada **na raiz do repositório**, conforme a instrução mais recente do usuário. `docs/` contém somente documentação e referências; não criar outra aplicação dentro dela. A fundação foi implementada e testada localmente; o aceite da etapa 1 segue exclusivamente o fluxo 20. Não confundir checks locais com CI remota, staging ou módulos clínicos concluídos.
- [Confirmado] Marcar afirmações com `[Confirmado]` quando houver evidência identificada e com `[Inferido]` quando forem hipóteses ou propostas. Para comportamento do legado, exigir arquivo/linha ou estrutura do dump. Confirmação documental comprova o que o documento diz, não o funcionamento do software.
- [Confirmado] Não duplicar especificações: consultar e referenciar a fonte por assunto abaixo. Ao alterar uma regra, atualizar sua fonte, sem criar versões paralelas neste arquivo.

## Skill principal do agente

- [Confirmado] Por solicitação explícita do usuário, usar **[Laravel 13 Multi-Tenant Expert](https://chatgpt.com/plugins/Plugin_6b5714f05f3c8191abac812ac2d0f97c)** como skill principal em implementação/revisão Laravel e multi-tenant. A configuração [.codex/agents/precordis.toml](.codex/agents/precordis.toml) referencia esse requisito sem duplicar as instruções da skill.
- [Confirmado] Localizar a skill no catálogo/ambiente efetivamente disponível e ler seu `SKILL.md` antes do trabalho dependente. Referenciar o link não instala nem habilita o plugin. Não inventar seu conteúdo, nome técnico ou caminho local.
- [Inferido] Se não estiver disponível, informar a limitação e solicitar habilitação do plugin; preservar o trabalho em andamento e interromper somente a implementação dependente. Não substituir silenciosamente por uma skill genérica.
- [Confirmado] Instruções explícitas do usuário e guardrails deste projeto prevalecem: banco único com `tenant_id`, MySQL/MariaDB, Vuexy, segredos fora do Git, nenhum DDL/DML em produção e legado somente leitura.

- [Confirmado] Acesso verificado em 03/11/2026: plugin `laravel-13-multi-tenant-expert`, versão observada `1.0.0`; skill `laravel-multi-tenant`, arquivo interno `skills/laravel-multi-tenant/SKILL.md`, lido pelo acesso autorizado ao plugin. Esse caminho não presume instalação local. Se o catálogo/busca genérica não listar o plugin, verificar seu identificador exato antes de informar indisponibilidade.

## Stack e arquitetura de destino

- [Confirmado] Por orientação atualizada do usuário: **PHP e Laravel estáveis recentes, MySQL ou MariaDB estável recente e Vuexy no front-end**, com **banco único + tenant_id**, conforme o ADR consolidado na correção de pendências de 03/11/2026. A fonte única das versões exatas, data de consulta e política de atualização é [arquitetura §14.1](docs/fluxos/14-arquitetura-laravel.md#141-stack--fonte-única-de-versões). A preferência genérica por PostgreSQL não se aplica a este projeto.
- [Confirmado] [Tenancy](docs/fluxos/09-estrategia-multi-tenant.md) define o isolamento; [arquitetura](docs/fluxos/14-arquitetura-laravel.md) descreve módulos de domínio, Redis/Horizon, Reverb, autenticação e contratos. Não confundir indicação documental de pacote com dependência já instalada ou compatibilidade já testada.
- [Confirmado] A escolha explícita de Vuexy pelo usuário orienta layout/CSS/JS/imagens. As menções anteriores a front-end “a definir”, Filament e Inertia/React não autorizam substituir essa escolha. Consultar os mapas de layout antes de decidir a integração técnica.
- [Confirmado] Preservar a separação lógica entre dados da plataforma e dados da clínica no banco único, conforme o fluxo 09. Nunca consultar modelos de tenant sem contexto inicializado; isolar também cache, filas, storage e broadcast.

## Padrão obrigatório de layout

- [Confirmado] Por instrução explícita do usuário, **toda criação ou alteração de tela deve seguir o padrão documentado em [Vuexy — assets e modelos por página](docs/layout/vuexy-assets-e-modelos-por-pagina.md)**. Esse documento é a fonte normativa de layout, estrutura dos modelos e escolha de CSS, JS, ícones e imagens por categoria de página.
- [Confirmado] Antes de implementar a interface, consultar o modelo correspondente no documento e reutilizar sua estrutura, componentes, classes, tipografia, espaçamentos e comportamento responsivo. Centralizar elementos compartilhados nos layouts/componentes Blade e integrar os assets pelo Vite, conforme a arquitetura do projeto.
- [Confirmado] Carregar somente os recursos core e as dependências necessárias à página, conforme o mapa. Adaptar os exemplos ao fluxo real do Laravel, com rotas, formulários POST/CSRF, validação e autorização no servidor; excluir dados fictícios, analytics, customizador e estilos exclusivos de demonstração indicados para remoção no documento. Referenciar a documentação sem duplicar seus inventários neste arquivo.

## Mapa de documentação e fonte por assunto

| Estado | Pasta / fonte | Responsabilidade |
|---|---|---|
| [Confirmado] | [Raiz da aplicação](./) · [arquitetura §14.2](docs/fluxos/14-arquitetura-laravel.md#142-estrutura-de-pastas-modular-por-domínio) | Raiz da aplicação: `app/`, `config/`, `resources/`, `database/`, `tests/`, `public/` e manifests. Documentação e schemas de referência permanecem em `docs/`. |
| [Confirmado] | [docs/analise/](docs/analise/) · [sumário](docs/analise/00-sumario-executivo.md) | Diagnóstico do legado; não é especificação automática do novo comportamento. |
| [Confirmado] | [Inventário](docs/analise/01-inventario.md), [dependências e atores](docs/analise/02-mapa-dependencias-atores.md), [telas e formulários](docs/analise/03-fluxos-telas-formularios.md), [regras](docs/analise/04-regras-de-negocio.md) | Localizar funcionalidades, pontos de entrada e regras observadas no legado. |
| [Confirmado] | [Segurança/LGPD](docs/analise/05-seguranca-lgpd-conformidade.md), [dicionário/ER legado](docs/analise/06-bancos-legados-dicionario-er.md), [inconsistências](docs/analise/07-inconsistencias-modelagem.md) | Riscos, estrutura legada e problemas de modelagem a verificar contra código/dumps. |
| [Confirmado] | [docs/fluxos/](docs/fluxos/) · [índice](docs/fluxos/00-indice.md) | Especificação funcional e arquitetural do novo produto; entrada para os fluxos 08–24. |
| [Confirmado] | [08 — Produto](docs/fluxos/08-visao-produto-bounded-contexts.md), [09 — Tenancy](docs/fluxos/09-estrategia-multi-tenant.md), [10 — Billing](docs/fluxos/10-assinatura-billing.md), [11 — Identidade/ACL](docs/fluxos/11-identidade-autenticacao-acl.md) | Escopo, fronteiras de módulos, isolamento, assinatura e autorização. |
| [Confirmado] | [12 — Modelo novo](docs/fluxos/12-novo-schema-unificado.md), [14 — Arquitetura](docs/fluxos/14-arquitetura-laravel.md), [15 — Qualidade](docs/fluxos/15-qualidade-testes-devops.md), [16 — Funcionalidades](docs/fluxos/16-novas-funcionalidades.md) | Modelo conceitual, organização do código, testes/CI e melhorias funcionais. |
| [Confirmado] | [17 — Roadmap](docs/fluxos/17-roadmap-fases.md), [18 — Riscos](docs/fluxos/18-riscos-migracao.md), [19 — Questões de negócio](docs/fluxos/19-perguntas-em-aberto-negocio.md) | Planejamento de alto nível, riscos e decisões ainda abertas. |
| [Confirmado] | [20 — Plano de desenvolvimento](docs/fluxos/20-plano-desenvolvimento.md) | **Fonte única da execução:** dependências, entregáveis, testes, aceite, controle de fases (§20.4) e registro de sessões (§20.5). Não manter outro quadro de status aqui. |
| [Confirmado] | [docs/layout/](docs/layout/) · [assets e modelos Vuexy](docs/layout/vuexy-assets-e-modelos-por-pagina.md), [cobertura de páginas](docs/layout/vuexy-cobertura-completa-144-paginas.md), [assets](docs/layout/assets/) | Referência visual e mapa de CSS, JS, imagens e dependências por página. Reutilizar componentes/recursos pertinentes; não copiar todas as demonstrações para a aplicação. |
| [Confirmado] | [docs/schema/](docs/schema/) · [schema.sql](docs/schema/schema.sql), [21 — Schema e reaproveitamento](docs/fluxos/21-schema-e-reaproveitamento.md) | DDL de referência do banco único e critérios de reaproveitamento. Após implementadas, migrations Laravel serão a fonte executável; manter DDL e migrations coerentes. Existência dos arquivos não comprova validação MySQL. |
| [Confirmado] | [docs/outros/](docs/outros/) · [processo de migração](<docs/outros/Precordis — Processo de Migração dos Bancos Legados.md>), [13 — Migração](docs/fluxos/13-plano-migracao-dados.md) | Material de apoio ao ETL. Contrato normativo de rastreabilidade no fluxo 13; o processo antigo é histórico. |
| [Confirmado] | [PENDENCIAS.md](docs/outros/PENDENCIAS.md), [questões do legado](docs/analise/perguntas-em-aberto.md) e [questões de negócio](docs/fluxos/19-perguntas-em-aberto-negocio.md) | Divergências documentais, dúvidas técnicas e decisões do negócio, respectivamente. |

- [Confirmado] Fontes funcionais adicionais: [22 — Atendimento](docs/fluxos/22-atendimento-clinico.md), [23 — Saúde ocupacional/ASO](docs/fluxos/23-saude-ocupacional.md), [24 — Segurança, retenção e operação](docs/fluxos/24-seguranca-retencao-operacao.md). Referência histórica não substitui esses contratos.

## Ciclo de implementação

1. [Confirmado] Ao iniciar uma sessão, ler este arquivo, as pendências e o fluxo 20 (§20.4–20.5). Ler a documentação do domínio afetado antes de editar código. Na preparação inicial da base de conhecimento, ler todos os documentos das cinco pastas; não declarar essa leitura concluída sem realizá-la.
2. [Confirmado] Identificar a etapa no fluxo 20 e conferir suas dependências. Só iniciar etapa cujas dependências estejam `CONCLUÍDA`, com evidência de aceite. A etapa 0 é governança/preparação do novo sistema; não inclui contenção de produção no legado.
3. [Confirmado] Se documentos divergirem, parar a decisão/implementação afetada e registrar em `docs/outros/PENDENCIAS.md`: fontes, trechos/linhas, impacto e decisão necessária. Não escolher silenciosamente nem promover recomendação a requisito. A conciliação atual adota `legacy_references`, com contrato no fluxo 13; não reintroduzir `legacy_map`.
4. [Confirmado] Seguir o ciclo do §20.1: branch `feat/etapa-NN-<slug>` → migrations/models/enums → Actions/Services/Policies/Form Requests → endpoints/Resources → interface Vuexy aplicável → testes → CI → PR revisado → squash merge. Etapa 0 não cria branch de patches do legado.
5. [Confirmado] No PR, explicar problema, comportamento entregue, etapa/fontes, alterações de banco, testes e limitações. Exigir CI verde: Pint, Rector em modo de verificação, Larastan e Pest; build do front quando aplicável, conforme fluxo 15. Publicação e merge dependem da autorização vigente do usuário.
6. [Confirmado] Concluir etapa somente com o aceite do fluxo 20: código, testes exigidos, migrations seguras e plano de recuperação, execução limpa em dois tenants e documentação no PR. Atualizar §20.4 e acrescentar sessão no §20.5, inclusive testes não executados, bloqueios e próximos passos. Não duplicar o histórico neste arquivo.

## Conexões de banco de dados

### Banco local — desenvolvimento

- [Confirmado] Dados fornecidos pelo usuário; acesso ao banco validado via TCP em `127.0.0.1:3306` com o usuário `usuario`. A instância retornou MariaDB **13.0.2** na verificação mais recente.
- [Confirmado] A senha de desenvolvimento deve ficar em `.env`, ignorado pelo Git. A tabela documenta os parâmetros de conexão sem incluir o valor da senha.

| Estado | Parâmetro | Valor local | Variável de ambiente |
|---|---|---|---|
| [Confirmado] | Conexão Laravel prevista | `mariadb` | `DB_CONNECTION` |
| [Confirmado] | IP | `127.0.0.1` | `DB_HOST` |
| [Confirmado] | Porta | `3306` | `DB_PORT` |
| [Confirmado] | Usuário | `usuario` | `DB_USERNAME` |
| [Confirmado] | Password | Não versionada; configurar em `.env` | `DB_PASSWORD` |
| [Confirmado] | Banco | `novo_sistema_clinica` | `DB_DATABASE` |

- [Confirmado] O usuário `usuario` recebeu permissões no banco `novo_sistema_clinica`, e o acesso foi validado após a concessão. Esse é o banco de trabalho do desenho compartilhado. Homologações destrutivas usam banco descartável separado; não importar o DDL por cima do banco de trabalho.

### Bancos online — legado

- [Confirmado] Inventário de conexões informado pelo usuário. A coluna “Conexão” identifica o acesso usado pelo legado; não prescreve a implementação do novo sistema.

| Estado | Uso | Host | Banco | Usuário | Conexão |
|---|---|---|---|---|---|
| [Confirmado] | Sistema principal | `precordis2016.mysql.dbaas.com.br` | `precordis2016` | `precordis2016` | MySQLi |
| [Confirmado] | Agenda e check-in | `agendaprec.mysql.dbaas.com.br` | `agendaprec` | `agendaprec` | MySQLi e PDO |
| [Confirmado] | Precordis TV | `samarco.mysql.dbaas.com.br` | `samarco` | `samarco` | PDO, porta `3306`, charset `utf8mb4` |

- [Confirmado] Senhas online não foram fornecidas nesta instrução e não devem ser registradas neste arquivo. O legado permanece somente leitura: não executar DDL/DML nesses bancos. Para estrutura e relações, consultar o [dicionário/ER legado](docs/analise/06-bancos-legados-dicionario-er.md); preferir dumps de estrutura autorizados.

## Execução local, migrations, seeds e testes

- [Confirmado] A fonte operacional dos comandos é [README — ambiente local](README.md#ambiente-local): preparação segura de .env, build PHP, serviços MariaDB/Redis, instalação, migrations, seeds, criação interativa de administrador e composer check. Executar sempre na raiz. Não duplicar nem criar outro roteiro em docs/laravel.
- [Confirmado] Usar o PHP do container; não contornar o requisito de plataforma com ignore-platform-reqs. Segredos permanecem em .env, modo 0600, ignorado pelo Git. Não sobrescrever APP_KEY de instalação existente.
- [Confirmado] Migrations são únicas no banco compartilhado. tenant:create não cria bancos e é idempotente. db:seed mantém catálogo global; papéis são configurados com contexto de clínica no provisionamento. Nunca incluir administrador/senha fixa em seed.
- [Confirmado] A suíte Pest exige novo_sistema_clinica_testing em host local e testa o motor MariaDB escolhido, sem SQLite. Manter Redis ativo para o teste de worker persistente. Não usar o banco de trabalho para rollback de teste, migrate:fresh ou fixtures.
- [Confirmado] Mudanças nas migrations/DDL exigem registro em [CHANGELOG](docs/schema/CHANGELOG.md). DDL de domínio em schema.sql é referência completa futura; migrations atuais implementam somente fundação e infraestrutura Laravel. Não importar todo o DDL para simular entrega de módulos futuros.
- [Confirmado] Validar isolamento também em cache, fila, storage, broadcast, sessões e reset de senha. Raw SQL, toBase(), remoção explícita de scopes e pivôs polimórficos continuam fronteiras de revisão obrigatória; não presumir proteção universal do scope.
- [Confirmado] Evidências, limites e aceite ficam no [fluxo 20](docs/fluxos/20-plano-desenvolvimento.md). Não anunciar suporte MySQL, CI remota verde ou staging validado sem executá-los.

## Código e idioma

- [Confirmado] Seguir a arquitetura modular do fluxo 14: controllers finos, validação/autorização em Form Requests, casos de uso em Actions/Services e Policies para acesso. Usar PHP tipado, funções pequenas e DRY; não introduzir abstrações sem necessidade.
- [Confirmado] Usar Eloquent/query builder com parâmetros vinculados ou PDO com prepared statements. Nunca concatenar entradas em SQL; validar identificadores dinâmicos por lista permitida. Evitar `SELECT *`, usar transações para operações dependentes e avaliar índices de filtros/joins frequentes.
- [Confirmado] Escapar saída HTML com Blade ou `htmlspecialchars`; validar entradas no servidor e tratar erros sem expor dados sensíveis. Não replicar regras de negócio entre interface, backend e banco.
- [Confirmado] Respostas e documentação em português. A convenção técnica consolidada no fluxo 14 §14.4 é inglês para identificadores novos e português para interface/documentação.
- [Inferido] Preservar nomes existentes ao revisar código; mapear valores legados explicitamente no ETL e evitar traduções heurísticas.

## Segurança, LGPD e referência legada

- [Confirmado] Legado em `/home/somvibe/Projetos/pessoal/precordis/precordis-admin-completo`: **somente leitura**. A etapa 0 prepara a governança do novo sistema; necessidades de contenção são encaminhamentos externos, sem modificar o legado.
- [Confirmado] Em qualquer inventário, busca ou indexação legada, excluir em todos os níveis: `private/`, `compactacao/`, `arquivos_zip/`, `vendor/`, `Stuk/`, `node_modules/` e `.git/`. Não abrir nem copiar PDFs de laudo para este repositório. Referências ao legado devem apontar arquivo/linha, sem transcrever dados pessoais ou segredos.
- [Confirmado] Nunca escrever senhas/credenciais de produção em arquivos versionados, exemplos, documentação, logs ou saída de ferramentas. Segredos somente em `.env` fora do Git; `.env.example` deve conter apenas nomes e valores fictícios. Documentar acessos com senha mascarada e nomes de variáveis, sem valor real, inclusive para o ambiente local.
- [Confirmado] **Não executar DDL/DML em produção**, inclusive via migrations, seeds, ETL ou testes. Usar somente MySQL/MariaDB local/staging na versão de destino da matriz do fluxo 14 para validação. Não conectar ao banco remoto por conveniência para descobrir estrutura; preferir dumps de estrutura autorizados.
- [Confirmado] Usar apenas estrutura de dumps na base de conhecimento; não copiar `INSERT`s de pacientes. Não incluir CPF, contatos, laudos ou outros dados clínicos reais em documentação, fixtures, screenshots ou PRs.
- [Confirmado] Laudos ficam fora do webroot, com autorização no servidor e isolamento por tenant. Políticas de auditoria, consentimento e acesso devem seguir as fontes de segurança e os fluxos do domínio; não inferir autorização apenas da interface.
- [Confirmado] Antes de entregar, revisar os arquivos alterados e o diff quanto a segredos e dados de saúde, verificar links locais e relatar exatamente o que foi testado. Não alterar staging do Git nem desfazer mudanças preexistentes sem necessidade/autorização.
