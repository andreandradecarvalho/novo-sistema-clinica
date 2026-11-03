# 14 — Arquitetura Laravel

## 14.1 Stack — fonte única de versões

> [Confirmado] Matriz atualizada em **31/10/2026**, por solicitação do usuário, para usar versões estáveis recentes de PHP, Laravel e MySQL ou MariaDB. Esta seção substitui as escolhas anteriores de PHP 8.4 e MySQL 8.4 como alvo do novo sistema; versões históricas do legado permanecem como evidência.

| Camada | Escolha | Versão de referência / evidência | Observação |
|---|---|---|---|
| Framework | **Laravel 13.x** | [Confirmado] **13.39.0**, publicado em 27/10/2026 | Release estável mais recente consultada; Laravel 13 suporta PHP 8.3–8.5. Fontes: [releases oficiais](https://github.com/laravel/framework/releases/tag/v13.39.0), [política de suporte](https://laravel.com/framework/docs/releases). |
| Linguagem | **PHP 8.5.x** | [Confirmado] **8.5.12**, publicado em 21/10/2026 | Última versão estável consultada; PHP 8.6 está em RC e não integra o alvo. Fontes: [lançamentos](https://www.php.net/index.php), [suporte](https://www.php.net/supported-versions.php). |
| Banco — opção MySQL | **MySQL Community Server 26.10.x** | [Confirmado] **26.10.0**, publicado em 20/10/2026 | Versão GA mais recente consultada, linha Innovation. Fontes: [download GA](https://dev.mysql.com/downloads/mysql/), [release notes](https://dev.mysql.com/doc/relnotes/mysql/26.10/en/), [Innovation e LTS](https://dev.mysql.com/doc/refman/26.10/en/mysql-releases.html). |
| Banco — opção MariaDB | **MariaDB Server 13.0.x** | [Confirmado] **13.0.2**, publicado em 15/09/2026 | Versão Stable mais recente consultada, linha rolling; 13.1 RC e 13.2 Preview não integram o alvo. Fontes: [releases oficiais](https://mariadb.org/mariadb/all-releases/), [anúncio de estabilidade](https://mariadb.org/mariadb-13-0-is-now-stable/). |
| Cache/fila/sessão | **Redis** + **Laravel Horizon** | atual | |
| Tempo real | **Laravel Reverb** | v1.12.0 (22/09/2026) | TV e fila de recepção em tempo real — **substitui o polling** do `precordistv`/`checkin` |
| Multi-tenant | [Inferido] **TenantResolver / TenantContext + scopes** | contrato próprio, banco compartilhado | Não instalar provisionamento de bancos por tenant; pacote só mediante necessidade e compatibilidade testadas. |
| ACL | **spatie/laravel-permission** (teams=tenant) | v8.x (v8.3.2 em 19/10/2026, suporta ^13) | |
| Auth | **Fortify** + **Sanctum** (+2FA TOTP) | atual | Passport só se necessário |
| Back-office (recepção/agenda/cadastros/admin) | [Inferido] **Vuexy + Blade + Vite** | integração proposta nesta conciliação | Reutilizar a referência HTML/Bootstrap; não instalar Filament como substituto visual. |
| Portais paciente/empresa + TV (alto polimento) | [Inferido] **Vuexy + Blade + JS por página** | mesma base visual do back-office | TV utiliza Reverb; SPA só se uma necessidade posterior justificar. |
| Busca | **Laravel Scout** (se necessário) | atual | autocomplete de paciente |
| Testes | **Pest 4** | pest-plugin-laravel v4.1 suporta L13 | |
| Análise estática | **Larastan/PHPStan 3** (nível máximo viável) | v3 | |
| Estilo/refactor | **Laravel Pint**, **Rector** | atual | |
| Observabilidade (dev) | **Pulse / Telescope** | atual | só em dev |
| PDF do laudo | **dompdf** (já no legado) ou equivalente | dompdf 3.x | geração server-side |

### 14.1.1 Política de atualização e compatibilidade

- [Confirmado] A matriz acima é o registro central das versões de PHP, Laravel e banco. Os demais documentos devem apontar para esta seção, sem manter matrizes concorrentes. Os componentes complementares da tabela são propostas anteriores; esta atualização não certifica suas versões ou compatibilidade.
- [Confirmado] Adotar uma das opções de banco por ambiente de implantação. A estratégia multi-tenant é **banco único + `tenant_id` + global scopes** (ver [fluxo 09](09-estrategia-multi-tenant.md)); não há mais "um banco por clínica". O suporte documentado do Laravel a MySQL e MariaDB não comprova que os DDLs e pacotes deste projeto já foram homologados nessas versões. Fonte: [bancos suportados pelo Laravel](https://laravel.com/framework/docs/13.x/database).
- [Confirmado] “Mais recente estável” não significa “LTS”. Para uma decisão explícita de suporte longo, as alternativas LTS consultadas são **MySQL 9.7.4** e **MariaDB 12.3.3**; não são a escolha mais recente das linhas GA/Stable acima. Fontes: [MySQL 9.7](https://dev.mysql.com/doc/relnotes/mysql/9.7/en/), [MariaDB 12.3 LTS](https://mariadb.org/mariadb-server-12-3-lts-released/), [lista de versões MariaDB](https://mariadb.org/mariadb/all-releases/).
- [Confirmado] Antes de instalar ou atualizar, reconfirmar a última versão estável oficial, registrar a data e fixar as versões efetivas em `composer.lock` e nas imagens de infraestrutura. Não usar tags flutuantes `latest` como registro de versão nem adotar Preview, Beta ou RC como padrão.
- [Inferido] Na fundação, validar dependências/extensões em PHP 8.5, migrations/seeds e testes no motor escolhido, registrando a versão exata do servidor. Só anunciar suporte aos dois motores após validar ambos. Repetir a homologação ao trocar de motor ou atualizar sua versão principal.
- [Confirmado] O DDL único em [schema.sql](../schema/schema.sql) usa `utf8mb4_unicode_ci` (collation portável entre MySQL e MariaDB, substituiu o `utf8mb4_0900_ai_ci` exclusivo do MySQL). Ainda assim, verificar no servidor/versão escolhido o suporte e o comportamento de comparação/ordenação antes de importar dados. Consultar a [referência MariaDB](https://mariadb.com/docs/server/reference/data-types/string-data-types/character-sets/supported-character-sets-and-collations).

### 14.1.2 Ambiente local observado

[Confirmado] A validação de 03/11/2026 retornou MariaDB **13.0.2-MariaDB-ubu2604**. Resultado e limitações estão no [changelog](../schema/CHANGELOG.md). Essa observação se refere ao teste do DDL completo na instância anterior do host, cuja tag era flutuante. A fundação agora usa outra instância local pelo compose.yaml, com versão fixada conforme a matriz, PHP por digest e banco/Redis separados. Aplicação/migrations foram testadas nela; evidências no fluxo 20. MySQL continua sem homologação. A observação antiga de MariaDB 10.5.29 é histórica.

<a id="142-estrutura-de-pastas-modular-por-domínio"></a>

## 14.2 Estrutura de pastas e integração visual

[Confirmado] Raiz da aplicação: [raiz do repositório](../../). Fundação implementada e aceita com homologação local isolada; CI remota adiada por orientação do usuário, conforme status e sessão no fluxo 20. Código, manifests, Docker e testes ficam na raiz. Documentação e DDL de referência ficam em docs/. Configurar somente public/ da raiz como webroot; não expor docs/, vendor/ ou .env.

[Inferido] Organização modular em app/Domain/{Tenancy,Identity,Patients,Companies,Scheduling,Visits,Exams,Anamnesis,Results,Occupational,ClinicalFinance,Notifications,Billing,Audit,Migration,Shared}, com Models/Actions/DTOs/Enums/Policies/Contracts conforme necessário. HTTP em app/Http; migrations em database/migrations, rodando uma vez. Testes Unit/Feature/Arch/Tenancy.

[Inferido] Integração inicial: Blade + Vite, componentes/layouts Vuexy e JS específico por página. Não carregar demonstrações, analytics de demo, dados mock ou tokens fixos. Referências: [assets Vuexy](../layout/vuexy-assets-e-modelos-por-pagina.md) e [cobertura](../layout/vuexy-cobertura-completa-144-paginas.md). Login usa POST/CSRF do Laravel, nunca o GET de demonstração.

## 14.3 Camadas e padrões

- [Inferido] Controllers finos → Form Requests → Actions/Services transacionais → Models; Repository apenas onde houver ganho. DTOs tipados e VOs compartilhados centralizam CPF/CNPJ, dinheiro, telefone e datas.
- [Inferido] Eventos externos após commit, com outbox/idempotência quando necessário. SDKs isolados por contratos, sem presumir intercambialidade de capacidades não suportadas por todo provedor.
- [Inferido] Tenancy não muda conexão por clínica: contrato em [09](09-estrategia-multi-tenant.md). Autorização em [11](11-identidade-autenticacao-acl.md)/[24](24-seguranca-retencao-operacao.md). Modelo físico em [12](12-novo-schema-unificado.md), atendimento em [22](22-atendimento-clinico.md), ocupacional em [23](23-saude-ocupacional.md).
- [Inferido] Models seguem os nomes reais do schema. Não implementar Anamnesis/Laudo/Checkin em tabelas paralelas: usar HealthRecord/Document/Visit e relacionamentos definidos no DDL. A ordem das migrations respeita dependências, mantendo FKs habilitadas.

## 14.4 Configuração e qualidade

- [Inferido] Código/identificadores em inglês, interface/documentação em português. Enums novos e estados do fluxo 22 seguem uma convenção explícita; nomes legados ou valores já existentes são mapeados no ETL, nunca traduzidos por heurística.
- [Inferido] PHP tipado, funções pequenas, DRY, validação de entrada, saída escapada, queries vinculadas e transações. Prepared statements não protegem identificadores SQL dinâmicos; esses usam listas permitidas.
- [Inferido] Horários/timezone, capacidade, catálogos, protocolos e regras empresariais configuráveis por tenant. Conteúdo clínico aprovado pelo responsável técnico, sem condições hardcoded pelo nome do empregador.
- [Inferido] CI e testes em [15](15-qualidade-testes-devops.md); fases e PR em [20](20-plano-desenvolvimento.md); recuperação/segredos/guarda em [24](24-seguranca-retencao-operacao.md). Nenhuma tecnologia planejada comprova dependência instalada.

[Inferido] VO/máscaras de CNPJ devem aceitar o formato alfanumérico vigente, preservando letras e validando dígitos verificadores conforme especificação oficial. Não aplicar normalizador “somente dígitos” de CPF ao CNPJ. CHAR(14) comporta ambos os formatos; a homologação depende da implementação do VO/integradores.
