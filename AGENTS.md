# Orquestração da implementação — novo sistema Precordis

## Escopo e evidências

- [Confirmado] Este arquivo orienta o trabalho em todo o repositório `/home/somvibe/Projetos/pessoal/precordis/novo-sistema-clinica`. Instruções explícitas do usuário prevalecem sobre recomendações documentais.
- [Confirmado] O produto previsto é um SaaS por assinatura para clínicas de exames: cadastros, agenda, check-in, TV, resultados/laudos, portais, notificações, billing e auditoria. A Precordis será o primeiro tenant. Fonte: [visão do produto](docs/fluxos/08-visao-produto-bounded-contexts.md).
- [Confirmado] Na criação deste arquivo, a raiz contém documentação, sem `composer.json`, `artisan` ou `package.json`. Não tratar arquitetura planejada, etapas, comandos ou schemas como implementação concluída.
- [Confirmado] Marcar afirmações com `[Confirmado]` quando houver evidência identificada e com `[Inferido]` quando forem hipóteses ou propostas. Para comportamento do legado, exigir arquivo/linha ou estrutura do dump. Confirmação documental comprova o que o documento diz, não o funcionamento do software.
- [Confirmado] Não duplicar especificações: consultar e referenciar a fonte por assunto abaixo. Ao alterar uma regra, atualizar sua fonte, sem criar versões paralelas neste arquivo.

## Stack e arquitetura de destino

- [Confirmado] Por orientação atualizada do usuário: **PHP e Laravel estáveis recentes, MySQL ou MariaDB estável recente e Vuexy no front-end**, com **banco central + um banco por tenant**. A fonte única das versões exatas, data de consulta e política de atualização é [arquitetura §14.1](docs/fluxos/14-arquitetura-laravel.md#141-stack--fonte-única-de-versões). A preferência genérica por PostgreSQL não se aplica a este projeto.
- [Confirmado] [Tenancy](docs/fluxos/09-estrategia-multi-tenant.md) define o isolamento; [arquitetura](docs/fluxos/14-arquitetura-laravel.md) descreve módulos de domínio, Redis/Horizon, Reverb, autenticação e contratos. Não confundir indicação documental de pacote com dependência já instalada ou compatibilidade já testada.
- [Confirmado] A escolha explícita de Vuexy pelo usuário orienta layout/CSS/JS/imagens. As menções anteriores a front-end “a definir”, Filament e Inertia/React não autorizam substituir essa escolha. Consultar os mapas de layout antes de decidir a integração técnica.
- [Confirmado] Preservar o split central/tenant: dados da plataforma no central; dados clínicos e operacionais no banco da clínica. Nunca consultar modelos de tenant sem contexto inicializado; isolar também cache, filas, storage e broadcast.

## Mapa de documentação e fonte por assunto

| Estado | Pasta / fonte | Responsabilidade |
|---|---|---|
| [Confirmado] | [docs/analise/](docs/analise/) · [sumário](docs/analise/00-sumario-executivo.md) | Diagnóstico do legado; não é especificação automática do novo comportamento. |
| [Confirmado] | [Inventário](docs/analise/01-inventario.md), [dependências e atores](docs/analise/02-mapa-dependencias-atores.md), [telas e formulários](docs/analise/03-fluxos-telas-formularios.md), [regras](docs/analise/04-regras-de-negocio.md) | Localizar funcionalidades, pontos de entrada e regras observadas no legado. |
| [Confirmado] | [Segurança/LGPD](docs/analise/05-seguranca-lgpd-conformidade.md), [dicionário/ER legado](docs/analise/06-bancos-legados-dicionario-er.md), [inconsistências](docs/analise/07-inconsistencias-modelagem.md) | Riscos, estrutura legada e problemas de modelagem a verificar contra código/dumps. |
| [Confirmado] | [docs/fluxos/](docs/fluxos/) · [índice](docs/fluxos/00-indice.md) | Especificação funcional e arquitetural do novo produto; entrada para os fluxos 08–21. |
| [Confirmado] | [08 — Produto](docs/fluxos/08-visao-produto-bounded-contexts.md), [09 — Tenancy](docs/fluxos/09-estrategia-multi-tenant.md), [10 — Billing](docs/fluxos/10-assinatura-billing.md), [11 — Identidade/ACL](docs/fluxos/11-identidade-autenticacao-acl.md) | Escopo, fronteiras de módulos, isolamento, assinatura e autorização. |
| [Confirmado] | [12 — Modelo novo](docs/fluxos/12-novo-schema-unificado.md), [14 — Arquitetura](docs/fluxos/14-arquitetura-laravel.md), [15 — Qualidade](docs/fluxos/15-qualidade-testes-devops.md), [16 — Funcionalidades](docs/fluxos/16-novas-funcionalidades.md) | Modelo conceitual, organização do código, testes/CI e melhorias funcionais. |
| [Confirmado] | [17 — Roadmap](docs/fluxos/17-roadmap-fases.md), [18 — Riscos](docs/fluxos/18-riscos-migracao.md), [19 — Questões de negócio](docs/fluxos/19-perguntas-em-aberto-negocio.md) | Planejamento de alto nível, riscos e decisões ainda abertas. |
| [Confirmado] | [20 — Plano de desenvolvimento](docs/fluxos/20-plano-desenvolvimento.md) | **Fonte única da execução:** dependências, entregáveis, testes, aceite, controle de fases (§20.4) e registro de sessões (§20.5). Não manter outro quadro de status aqui. |
| [Confirmado] | [docs/layout/](docs/layout/) · [assets e modelos Vuexy](docs/layout/vuexy-assets-e-modelos-por-pagina.md), [cobertura de páginas](docs/layout/vuexy-cobertura-completa-144-paginas.md), [assets](docs/layout/assets/) | Referência visual e mapa de CSS, JS, imagens e dependências por página. Reutilizar componentes/recursos pertinentes; não copiar todas as demonstrações para a aplicação. |
| [Confirmado] | [docs/schema/](docs/schema/) · [central.sql](docs/schema/central.sql), [tenant.sql](docs/schema/tenant.sql), [21 — Schema e reaproveitamento](docs/fluxos/21-schema-e-reaproveitamento.md) | DDL de referência separado por banco e critérios de reaproveitamento. Após implementadas, migrations Laravel serão a fonte executável; manter DDL e migrations coerentes. Existência dos arquivos não comprova validação MySQL. |
| [Confirmado] | [docs/outros/](docs/outros/) · [processo de migração](<docs/outros/Precordis — Processo de Migração dos Bancos Legados.md>), [13 — Migração](docs/fluxos/13-plano-migracao-dados.md) | Material de apoio ao ETL. **Contrato de rastreabilidade sem fonte única conciliada:** consultar P01 antes de implementar. |
| [Confirmado] | [PENDENCIAS.md](docs/outros/PENDENCIAS.md), [questões do legado](docs/analise/perguntas-em-aberto.md) e [questões de negócio](docs/fluxos/19-perguntas-em-aberto-negocio.md) | Divergências documentais, dúvidas técnicas e decisões do negócio, respectivamente. |

## Ciclo de implementação

1. [Confirmado] Ao iniciar uma sessão, ler este arquivo, as pendências e o fluxo 20 (§20.4–20.5). Ler a documentação do domínio afetado antes de editar código. Na preparação inicial da base de conhecimento, ler todos os documentos das cinco pastas; não declarar essa leitura concluída sem realizá-la.
2. [Confirmado] Identificar a etapa no fluxo 20 e conferir suas dependências. Só iniciar etapa cujas dependências estejam `CONCLUÍDA`, com evidência de aceite. Não marcar contenção do legado como concluída por simples criação de documentação.
3. [Confirmado] Se documentos divergirem, parar a decisão/implementação afetada e registrar em `docs/outros/PENDENCIAS.md`: fontes, trechos/linhas, impacto e decisão necessária. Não escolher silenciosamente nem promover recomendação a requisito. P01 segue aberta: `legacy_map` versus `legacy_references`.
4. [Confirmado] Seguir o ciclo do §20.1: branch `feat/etapa-NN-<slug>` → migrations/models/enums → Actions/Services/Policies/Form Requests → endpoints/Resources → interface Vuexy aplicável → testes → CI → PR revisado → squash merge. A branch da etapa 0 está indicada separadamente no fluxo 20.
5. [Confirmado] No PR, explicar problema, comportamento entregue, etapa/fontes, alterações de banco, testes e limitações. Exigir CI verde: Pint, Rector em modo de verificação, Larastan e Pest; build do front quando aplicável, conforme fluxo 15. Publicação e merge dependem da autorização vigente do usuário.
6. [Confirmado] Concluir etapa somente com o aceite do fluxo 20: código, testes exigidos, migrations reversíveis, execução limpa em dois tenants e documentação no PR. Atualizar §20.4 e acrescentar sessão no §20.5, inclusive testes não executados, bloqueios e próximos passos. Não duplicar o histórico neste arquivo.

## Conexões de banco de dados

### Banco local — desenvolvimento

- [Confirmado] Dados fornecidos pelo usuário; acesso ao banco validado via TCP em `127.0.0.1:3306` com o usuário `usuario`. A instância retornou MariaDB **13.0.2** na verificação mais recente.
- [Confirmado] A senha de desenvolvimento deve ficar em `laravel/.env`, ignorado pelo Git. A tabela documenta os parâmetros de conexão sem incluir o valor da senha.

| Estado | Parâmetro | Valor local | Variável de ambiente |
|---|---|---|---|
| [Confirmado] | Conexão Laravel prevista | `mariadb` | `DB_CONNECTION` |
| [Confirmado] | IP | `127.0.0.1` | `DB_HOST` |
| [Confirmado] | Porta | `3306` | `DB_PORT` |
| [Confirmado] | Usuário | `usuario` | `DB_USERNAME` |
| [Confirmado] | Password | Não versionada; configurar em `laravel/.env` | `DB_PASSWORD` |
| [Confirmado] | Banco | `novo_sistema_clinica` | `DB_DATABASE` |

- [Confirmado] O usuário `usuario` recebeu permissões no banco `novo_sistema_clinica`, e o acesso foi validado após a concessão. Esse banco local não substitui a definição dos bancos central e de tenant da arquitetura; os demais bancos de desenvolvimento/teste deverão ser definidos na fundação.

### Bancos online — legado

- [Confirmado] Inventário de conexões informado pelo usuário. A coluna “Conexão” identifica o acesso usado pelo legado; não prescreve a implementação do novo sistema.

| Estado | Uso | Host | Banco | Usuário | Conexão |
|---|---|---|---|---|---|
| [Confirmado] | Sistema principal | `precordis2016.mysql.dbaas.com.br` | `precordis2016` | `precordis2016` | MySQLi |
| [Confirmado] | Agenda e check-in | `agendaprec.mysql.dbaas.com.br` | `agendaprec` | `agendaprec` | MySQLi e PDO |
| [Confirmado] | Precordis TV | `samarco.mysql.dbaas.com.br` | `samarco` | `samarco` | PDO, porta `3306`, charset `utf8mb4` |

- [Confirmado] Senhas online não foram fornecidas nesta instrução e não devem ser registradas neste arquivo. O legado permanece somente leitura: não executar DDL/DML nesses bancos. Para estrutura e relações, consultar o [dicionário/ER legado](docs/analise/06-bancos-legados-dicionario-er.md); preferir dumps de estrutura autorizados.

## Execução local, migrations, seeds e testes

- [Confirmado] Não existem comandos da aplicação executáveis nesta raiz no estado inicial. Primeiro implementar a fundação conforme a etapa 1 e documentar os comandos efetivamente disponíveis. Não executar os exemplos abaixo enquanto faltarem a aplicação e suas configurações.
- [Inferido] Após a fundação, o roteiro abaixo é uma proposta operacional; confirmar caminhos, opções e comandos com `php artisan list`, `php artisan help <comando>` e os scripts do `composer.json`. `tenant:create` e `tenants:migrate` são entregáveis previstos no fluxo 20, não comandos já implementados.

```bash
# Somente local/staging, após confirmar conexões e caminhos das migrations.
composer install
cp .env.example .env  # apenas se .env ainda não existir; nunca sobrescrever segredos locais
php artisan key:generate  # somente na instalação local inicial

# Proposta de diretórios separados; confirmar na implementação da fundação.
php artisan migrate --database=central --path=database/migrations/central
php artisan tenants:migrate --help
# Executar tenants:migrate conforme a interface implementada, em dois tenants de teste.

# Somente seed central explicitamente seguro; seed do tenant exige contexto de tenant.
php artisan db:seed --database=central --class=DatabaseSeeder

# Testes com o motor/versão de destino do fluxo 14 §14.1 e dados sintéticos.
php artisan test
./vendor/bin/pint --test
./vendor/bin/rector process --dry-run
./vendor/bin/phpstan analyse
```

- [Confirmado] Seeds devem separar catálogos/configurações de fixtures sintéticas e respeitar o banco/contexto do tenant. Não executar seed de tenant na conexão central, importar pacientes reais para fixtures ou criar credenciais reais em seeders.
- [Confirmado] Validar SQL no **MySQL ou MariaDB de destino definido no fluxo 14 §14.1, somente local/staging**, com bancos descartáveis separados para central e tenant. Preservar FKs habilitadas; registrar motor, versão, resultado e erros. Não usar SQLite, checagem textual ou sucesso em versão antiga como prova de compatibilidade com a versão de destino. Só declarar suporte aos dois motores após validar ambos.
- [Confirmado] A instância local observada na verificação mais recente é MariaDB 13.0.2, conforme a seção de conexões acima. O fluxo 14 §14.1.2 registra a observação anterior em MariaDB 10.5.29; criação de banco vazio e ajuste de permissões não comprovam homologação dos DDLs.
- [Confirmado] Toda correção em `docs/schema/*.sql` deve ser justificada contra as fontes, manter o split e ser registrada em `docs/schema/CHANGELOG.md` (criar quando houver revisão). Nunca declarar validação que não foi executada.
- [Confirmado] Aplicar os testes exigidos pelos fluxos 15 e 20: regras puras, HTTP, permissões, arquitetura e isolamento em dois tenants, incluindo query/cache/fila/storage/broadcast. Testes devem usar conexões próprias, sem acesso a produção.

## Código e idioma

- [Confirmado] Seguir a arquitetura modular do fluxo 14: controllers finos, validação/autorização em Form Requests, casos de uso em Actions/Services e Policies para acesso. Usar PHP tipado, funções pequenas e DRY; não introduzir abstrações sem necessidade.
- [Confirmado] Usar Eloquent/query builder com parâmetros vinculados ou PDO com prepared statements. Nunca concatenar entradas em SQL; validar identificadores dinâmicos por lista permitida. Evitar `SELECT *`, usar transações para operações dependentes e avaliar índices de filtros/joins frequentes.
- [Confirmado] Escapar saída HTML com Blade ou `htmlspecialchars`; validar entradas no servidor e tratar erros sem expor dados sensíveis. Não replicar regras de negócio entre interface, backend e banco.
- [Confirmado] Respostas e documentação em português. O idioma dos identificadores do domínio permanece uma decisão aberta no fluxo 14 §14.7 e no fluxo 20 (§20.1/20.6, pergunta #20).
- [Inferido] A recomendação documental é inglês para código/estrutura e português para a interface. Confirmar e registrar essa decisão na etapa 1 antes de criar convenções extensas; preservar nomes existentes ao revisar código e evitar mistura arbitrária de idiomas.

## Segurança, LGPD e referência legada

- [Confirmado] Legado em `/home/somvibe/Projetos/pessoal/precordis/precordis-admin-completo`: **somente leitura**. A etapa 0 não autoriza alterações nessa árvore durante este trabalho; registrar necessidades de contenção sem modificar o legado.
- [Confirmado] Em qualquer inventário, busca ou indexação legada, excluir em todos os níveis: `private/`, `compactacao/`, `arquivos_zip/`, `vendor/`, `Stuk/`, `node_modules/` e `.git/`. Não abrir nem copiar PDFs de laudo para este repositório. Referências ao legado devem apontar arquivo/linha, sem transcrever dados pessoais ou segredos.
- [Confirmado] Nunca escrever senhas/credenciais de produção em arquivos versionados, exemplos, documentação, logs ou saída de ferramentas. Segredos somente em `.env` fora do Git; `.env.example` deve conter apenas nomes e valores fictícios. Documentar acessos com senha mascarada e nomes de variáveis, sem valor real, inclusive para o ambiente local.
- [Confirmado] **Não executar DDL/DML em produção**, inclusive via migrations, seeds, ETL ou testes. Usar somente MySQL/MariaDB local/staging na versão de destino da matriz do fluxo 14 para validação. Não conectar ao banco remoto por conveniência para descobrir estrutura; preferir dumps de estrutura autorizados.
- [Confirmado] Usar apenas estrutura de dumps na base de conhecimento; não copiar `INSERT`s de pacientes. Não incluir CPF, contatos, laudos ou outros dados clínicos reais em documentação, fixtures, screenshots ou PRs.
- [Confirmado] Laudos ficam fora do webroot, com autorização no servidor e isolamento por tenant. Políticas de auditoria, consentimento e acesso devem seguir as fontes de segurança e os fluxos do domínio; não inferir autorização apenas da interface.
- [Confirmado] Antes de entregar, revisar os arquivos alterados e o diff quanto a segredos e dados de saúde, verificar links locais e relatar exatamente o que foi testado. Não alterar staging do Git nem desfazer mudanças preexistentes sem necessidade/autorização.
