# Prompt: Análise Profunda do Legado Precordis (PHP 5/7) e Especificação de um SaaS Multi-tenant em Laravel

## PAPEL

Você vai atuar como **arquiteto de software sênior e tech lead** especializado em modernização de sistemas legados PHP e em produtos SaaS para a área da saúde. Domínio esperado:

* PHP 5, 7 e 8.x (diferenças de sintaxe, tipagem, depreciações e remoções entre versões: `mysql_*`, `ereg*`, `each()`, `create_function`, `register_globals`, `magic_quotes` etc.)
* Laravel (versão estável mais recente), Eloquent, migrations, filas, eventos, policies, Form Requests, API Resources
* Arquitetura SaaS **multi-tenant** (isolamento de dados, identificação de tenant, billing por assinatura, onboarding self-service)
* [Confirmado] Modelagem relacional em MySQL ou MariaDB, conforme a [matriz vigente](../fluxos/14-arquitetura-laravel.md#141-stack--fonte-única-de-versões), normalização, índices e particionamento
* SOLID, DRY, KISS, YAGNI, Clean Code, Clean Architecture / DDD tático (bounded contexts, value objects, domain events)
* Segurança (OWASP Top 10, OWASP ASVS), **LGPD aplicada a dados de saúde** (dado pessoal sensível) e normas do setor (prontuário eletrônico, laudos, assinatura digital)

## CONTEXTO DO PROJETO LEGADO

O código-fonte completo está na pasta **`precordis-admin-completo/`**. Trata-se do sistema de uma **clínica médica que realiza diversos exames** (ex.: cardiológicos, de imagem, laboratoriais, ocupacionais — confirme pelo código e pelos dados).

* **Raiz (`precordis-admin-completo/`)** → painel **administrador** da empresa: gestão de empresas (clientes PJ/convênios), pacientes, médicos, exames, resultados/laudos e níveis de acesso.
* **Subsistemas já conhecidos** (cada um em sua pasta):
  * `agendaprecordis` → agendamento de pacientes e exames
  * `precordistv` → provável painel de TV (chamada de senhas/pacientes na recepção ou sala de espera)
  * `pesquisaSatisfacao` → pesquisa de satisfação (NPS/CSAT) com pacientes
  * `checkinCliente` → check-in do paciente na chegada (totem/recepção)
  * `cadastrar_dados` → cadastro/atualização de dados (confirmar se é do paciente, da empresa ou formulário público)
* **Esta lista NÃO é exaustiva.** Você deve descobrir e documentar todos os demais subsistemas, módulos, scripts avulsos, crons, integrações, APIs internas, webhooks, geradores de PDF/laudo, importadores, relatórios e painéis que existirem na árvore.

As descrições acima são hipóteses iniciais. **Confirme ou corrija cada uma com base no código** e deixe explícito quando algo for inferência.

### Heurísticas para descobrir subsistemas

Considere como candidato a subsistema qualquer pasta ou conjunto de arquivos que tenha pelo menos um destes sinais:

* `index.php`, `login.php` ou outro ponto de entrada próprio;
* arquivo próprio de conexão/configuração (`conexao.php`, `config.php`, `db.php`, `.env`, `connect.inc` etc.);
* sessão/login próprios (`session_start()` com variáveis de sessão diferentes da raiz);
* layout/tema próprio (CSS/JS/header/footer separados);
* scripts chamados por cron, AJAX, `curl`, `file_get_contents(http...)`, `<iframe>`, `<form action="../outra_pasta/...">`;
* pastas com nomes de backup ou versão (`_old`, `bkp`, `novo`, `v2`, `teste`, datas) — liste-as e classifique como **ativo**, **duplicado** ou **obsoleto**.

### Fontes de dados (dumps SQL)

Os schemas existem como dumps no repositório. Conhecidos até agora:

* `database/precordis2016.sql` → banco do sistema principal (administrador)
* `database/agendaprec.sql` → banco do agendamento (`agendaprecordis`)

**Procure recursivamente todos os arquivos `.sql`** (dumps parciais, seeds, `ALTER TABLE` avulsos, procedures, triggers, views). Liste cada um e informe se foi incorporado à análise.

Use os dumps como **fonte primária e autoritativa** do schema: leia o `CREATE TABLE` de cada tabela (colunas, tipos, tamanhos, `PRIMARY KEY`, `FOREIGN KEY`, `UNIQUE`, `INDEX`, `ENGINE`, `CHARSET/COLLATION`). Use o código PHP como **fonte complementar** para:

* confirmar relacionamentos implícitos (sem FK declarada, mas usados em `JOIN`/`WHERE`);
* descobrir valores reais de colunas `status`/`tipo`/`situacao` armazenadas como `varchar`/`int` livre e tratadas como enum no código;
* identificar tabelas e colunas que nenhum código ativo usa (candidatas a descarte);
* descobrir subsistemas que acessam tabelas de **outro** banco (acoplamento oculto).

Se algum subsistema usar um banco cujo dump não esteja no repositório, sinalize como **lacuna** e infira o schema pelo código, marcando como "inferido".

## OBJETIVO GERAL

1. Produzir um **diagnóstico técnico completo** do legado (código, subsistemas, bancos, regras de negócio, segurança).
2. A partir dele, produzir a **especificação de um novo produto SaaS multi-tenant em Laravel**, com **assinatura (billing recorrente)** e **gestão de permissões ACL**, que substitua todos os subsistemas legados de forma unificada e possa ser vendido para **outras clínicas**, não só para a Precordis.

O documento final deve servir como **especificação de projeto** para a equipe de desenvolvimento. Evite generalidades: cite sempre nomes reais de arquivos, pastas, tabelas, colunas e funções encontradas.

## MODO DE TRABALHO (OBRIGATÓRIO)

O projeto é grande. Para não perder contexto nem inventar informação:

1. **Trabalhe por etapas** e grave a saída de cada etapa em arquivo Markdown na pasta `docs/analise/` (ex.: `01-inventario.md`, `02-fluxos-formularios.md` …). Ao final, gere `00-sumario-executivo.md` com links para todos.
2. **Nunca invente** arquivos, tabelas ou campos. Toda afirmação sobre o legado deve ser rastreável (caminho do arquivo e, quando útil, linha).
3. Classifique cada conclusão como **Confirmado** (visto no código/dump) ou **Inferido** (dedução).
4. Ao encontrar ambiguidade de regra de negócio que o código não resolve, registre em `docs/analise/perguntas-em-aberto.md` em vez de decidir sozinho.
5. [Confirmado] Antes de propor tecnologias, **verifique a versão estável mais recente** de cada uma (Laravel, PHP, MySQL ou MariaDB, pacotes) e registre a versão escolhida e a data da verificação na [fonte única de versões](../fluxos/14-arquitetura-laravel.md#141-stack--fonte-única-de-versões). Não adotar Preview, Beta ou RC como alvo e não manter outra matriz neste documento.

---

## ETAPA 1 — MAPEAMENTO E INVENTÁRIO

1. Percorra recursivamente `precordis-admin-completo/` e gere um inventário com:
   * cada subsistema/módulo (pasta, propósito confirmado, status: ativo/duplicado/obsoleto);
   * quantidade de arquivos e linhas de código por subsistema (PHP, JS, CSS, HTML, SQL);
   * versão de PHP compatível com cada um (sinais: `mysql_*` → PHP ≤ 5.6; `mysqli_*`/PDO; `??`, tipos escalares, `declare(strict_types)` → PHP 7+);
   * bibliotecas e dependências (`composer.json`, `/lib`, `/vendor`, `/includes`, FPDF/TCPDF/mPDF, PHPMailer, jQuery e plugins, Bootstrap, DataTables, bibliotecas de gráfico etc.) com versão e vulnerabilidades conhecidas;
   * arquivos de configuração de banco: quantos bancos distintos, hosts, usuários, nomes de schema (**mascare senhas** no relatório, mas sinalize credenciais expostas no código como risco);
   * integrações externas (SMS, e-mail, WhatsApp, gateways, laboratórios, equipamentos de exame, PACS/DICOM, webservices de convênio/TISS, ViaCEP, Receita etc.);
   * tarefas agendadas (crons) e scripts de linha de comando.
2. Para cada subsistema, liste os **pontos de entrada** (arquivos acessíveis via URL) e trace o fluxo principal de navegação.
3. Monte o **mapa de dependências entre subsistemas**: bancos compartilhados, sessão compartilhada, `include` cruzado, cURL, redirecionamentos, polling AJAX (ex.: TV consultando fila do check-in), tabelas lidas/escritas por mais de um subsistema. Entregue em Mermaid `flowchart`.
4. Liste os **atores/perfis de usuário** que usam cada subsistema (admin, recepção, médico, técnico, empresa cliente, paciente, público anônimo, TV/totem sem login).

## ETAPA 2 — ANÁLISE FUNCIONAL (TELAS, FORMULÁRIOS, REGRAS DE NEGÓCIO)

Para **cada** subsistema (raiz/administrador, `agendaprecordis`, `precordistv`, `pesquisaSatisfacao`, `checkinCliente`, `cadastrar_dados` e todos os descobertos):

1. **Formulários**: campos, tipos esperados, obrigatoriedade, máscaras, validações client-side e server-side, método (GET/POST), proteção CSRF, destino (`action`) e o que o script de destino faz no banco (INSERT/UPDATE/DELETE em quais tabelas).
2. **Telas**: propósito de cada tela relevante (listagens, cadastros, edição, exclusão, relatórios, dashboards, impressão de laudos, telas públicas).
3. **Regras de negócio embutidas**: extraia e documente as regras espalhadas pelo PHP, por exemplo:
   * ciclo de vida do exame (solicitado → agendado → check-in → realizado → laudado → liberado → entregue) e quem pode mudar cada status;
   * regras de agendamento (agendas por médico/sala/equipamento, duração por tipo de exame, encaixes, bloqueios, feriados, limite por convênio/empresa, overbooking);
   * regras de check-in, geração e chamada de senhas, priorização (idosos, gestantes, PCD — Lei 10.048/2000);
   * vínculo empresa ↔ paciente (exames ocupacionais/ASO, convênios, tabelas de preço por empresa);
   * cálculos (valores, descontos, repasse a médicos, faturamento);
   * geração, assinatura e liberação de laudos/resultados (PDF, upload, acesso externo por senha/token);
   * disparo e cálculo da pesquisa de satisfação.
4. **Autenticação e controle de acesso**:
   * como o login é feito em cada subsistema (sessão nativa, cookie, token na URL, sem login);
   * algoritmo de senha (texto puro, MD5, SHA1, `crypt`, `password_hash`);
   * como níveis/perfis são definidos e verificados (tabela, flag numérica, `if` espalhado, menu escondido sem checagem no servidor);
   * se existem logins separados por subsistema e se o mesmo usuário existe em vários bancos.
5. **Regras duplicadas**: identifique lógica repetida entre subsistemas (validação de CPF, formatação de datas, conexão, cálculo de idade, envio de e-mail, montagem de PDF) — insumo para a aplicação de DRY na Etapa 4.

## ETAPA 3 — SEGURANÇA, PRIVACIDADE E CONFORMIDADE

1. Liste cada risco encontrado com **arquivo, trecho, severidade (Crítica/Alta/Média/Baixa) e recomendação**:
   * SQL Injection (concatenação de `$_GET`/`$_POST`/`$_REQUEST`), XSS, CSRF, IDOR (ex.: `laudo.php?id=123` sem checagem de dono), upload inseguro, path traversal, inclusão de arquivos dinâmica, `eval`, sessão fixa, credenciais no código, diretórios listáveis, backups/dumps acessíveis pela web, funções depreciadas/removidas.
2. **LGPD (dados de saúde = dado pessoal sensível, art. 11)**: onde há dado sensível, como é armazenado, quem acessa, se há log de acesso, consentimento, política de retenção, mascaramento, criptografia em repouso/trânsito.
3. **Requisitos setoriais** a observar no novo sistema (indique quais o legado atende ou não): guarda de prontuário (Lei 13.787/2018 e resoluções do CFM), assinatura digital de laudos (ICP-Brasil), padrão TISS da ANS para convênios, se aplicável.

## ETAPA 4 — ANÁLISE DOS BANCOS DE DADOS

Leia integralmente todos os dumps antes de iniciar.

1. Para cada banco: todas as tabelas, colunas, tipos, PKs, FKs (declaradas e implícitas), índices, engine (MyISAM/InnoDB), charset/collation (atenção a `latin1` vs `utf8mb4` e problemas de acentuação), volume estimado de registros (pelos `INSERT`, se houver).
2. Diagrama ER **atual** de cada banco separadamente (Mermaid `erDiagram`).
3. **Cruzamento entre bancos**: entidades que representam o mesmo conceito em sistemas diferentes (paciente/cliente, empresa/convênio, médico/profissional, exame/procedimento, usuário) e como se relacionam hoje (CPF, e-mail, ID copiado manualmente ou nenhuma relação). Avalie a qualidade da chave de deduplicação.
4. **Problemas de modelagem**: duplicidade, falta de normalização, colunas redundantes, campos genéricos (`obs`, `tipo`, `status`), datas em `varchar`, valores monetários em `float`, ausência de soft delete, ausência de auditoria (`created_at`, `updated_at`, `deleted_at`, `created_by`), tabelas sem PK, órfãos.
5. Tabelas/colunas **obsoletas** (não referenciadas por código ativo).

## ETAPA 5 — PROPOSTA DO NOVO PRODUTO SaaS (ENTREGÁVEL PRINCIPAL)

### 5.1 Visão do produto e bounded contexts

* Descreva o produto SaaS em uma página: público-alvo (clínicas de exames, centros de diagnóstico, clínicas ocupacionais), proposta de valor e módulos.
* Divida o domínio em **bounded contexts / módulos**, cobrindo tudo que os subsistemas legados fazem. Sugestão de partida (ajuste à análise): `Tenancy & Billing`, `Identity & Access (ACL)`, `Cadastros` (pacientes, empresas, convênios, profissionais, unidades, salas, equipamentos), `Catálogo de Exames` (procedimentos, preparos, preços), `Agenda`, `Recepção & Check-in`, `Painel de Chamada (TV)`, `Atendimento & Execução`, `Laudos & Resultados`, `Portal do Paciente`, `Portal da Empresa`, `Pesquisa de Satisfação`, `Financeiro/Faturamento`, `Notificações`, `Auditoria`, `Relatórios & BI`.
* Entregue uma **matriz de rastreabilidade**: cada tela/funcionalidade legada → módulo novo → status (migrar igual / redesenhar / descartar / nova).

### 5.2 Estratégia multi-tenant

* Compare, com prós/contras aplicados a este caso (dados de saúde, LGPD, custo, porte das clínicas, backup/restore por cliente, relatórios cross-tenant):
  1. **Banco único com `tenant_id`** + global scopes;
  2. **Banco por tenant**;
  3. **Híbrido** (banco central + bancos de tenant, com opção de banco dedicado para clientes enterprise).
* Recomende uma estratégia e justifique. Defina:
  * identificação do tenant (subdomínio `clinica.dominio.com.br`, domínio próprio, path, header para API);
  * pacote ou implementação própria (avaliar `stancl/tenancy` — verificar compatibilidade com a versão do Laravel escolhida — versus scopes nativos), sempre atrás de **contratos/interfaces** próprios para não acoplar o domínio ao pacote;
  * isolamento em **todas** as camadas: queries, cache, filas/jobs, storage de arquivos (laudos), sessões, logs, broadcasting, busca, e-mails;
  * dados **centrais** (planos, tenants, assinaturas, super-admin) versus dados **do tenant**;
  * suporte a **multiunidade** dentro do mesmo tenant (clínica com várias filiais);
  * testes automatizados que provem que um tenant nunca acessa dados de outro.

### 5.3 Assinatura e billing

* Modelo de planos: por unidade, por profissional, por volume de exames/agendamentos, módulos adicionais (add-ons), trial, período de carência, upgrade/downgrade com pró-rata, cancelamento, inadimplência (bloqueio progressivo: aviso → somente leitura → suspensão), cupons.
* Gateway: avaliar Laravel Cashier (Stripe/Paddle) **e** gateways brasileiros com PIX, boleto e cartão recorrente (ex.: Asaas, Pagar.me, Iugu, Mercado Pago). Recomende e justifique. O código deve depender de uma interface (`BillingGatewayContract`) para permitir troca de provedor (princípios O e D).
* Webhooks idempotentes, conciliação, emissão de nota fiscal de serviço (NFS-e) da assinatura, **feature flags/limites por plano** aplicados via middleware/policy.
* Tabelas: `plans`, `plan_features`, `subscriptions`, `subscription_items`, `invoices`, `payments`, `usage_records`, `coupons` (ajuste conforme o gateway).

### 5.4 Identidade, autenticação e ACL

* Autenticação: escolha e justifique (Fortify/starter kit oficial, Sanctum para SPA/mobile/API, Passport só se houver necessidade real de OAuth2 para terceiros). Inclua 2FA (TOTP) obrigatório para perfis com acesso a dados clínicos, política de senha, bloqueio por tentativas, sessões ativas, SSO como evolução.
* **ACL multi-tenant**: roles e permissions **com escopo por tenant** (ex.: Spatie Laravel-Permission com *teams* = tenant, ou implementação própria com Gates/Policies). Defina:
  * roles padrão do sistema (Super Admin da plataforma, Admin da Clínica, Recepção, Médico/Laudador, Técnico, Financeiro, Usuário de Empresa Cliente, Paciente) e possibilidade de cada clínica criar roles customizadas;
  * catálogo de permissions granular por módulo e ação (`agenda.view`, `agenda.create`, `laudo.sign`, `laudo.release` …);
  * permissões contextuais além de RBAC (ex.: médico só vê laudos que executou; usuário de empresa só vê seus funcionários; restrição por unidade) via Policies;
  * mapeamento explícito dos **níveis de acesso legados → novas roles/permissions**;
  * auditoria de concessão/revogação de permissões e de **todo acesso a dado clínico** (LGPD).
* Usuários de acesso restrito sem login tradicional: TV de chamada e totem de check-in autenticados por **token de dispositivo** vinculado ao tenant/unidade; paciente por login próprio ou link assinado com expiração.

### 5.5 Novo modelo de banco de dados

  * [Confirmado] Schema MySQL/MariaDB conforme a matriz vigente, normalizado (mínimo 3FN; justifique desnormalizações propositais), `utf8mb4`, InnoDB e FKs reais; preservar banco central + banco por tenant e homologar no motor escolhido.
* Todas as tabelas com colunas, tipos, PKs, FKs, índices (incluindo compostos iniciando por `tenant_id`, se for a estratégia), constraints, soft delete e auditoria.
* Valores monetários em `DECIMAL` ou inteiro em centavos; status como enum PHP *backed* + coluna correspondente; IDs públicos não sequenciais (ULID/UUID) em URLs e API.
* Diagrama ER completo (Mermaid `erDiagram`), separado por módulo se ficar grande, mais um diagrama geral.

### 5.6 Plano de migração de dados

* Mapeamento **tabela-a-tabela e coluna-a-coluna** (legado → novo), com transformações.
* A Precordis será o **primeiro tenant**: como os dados legados entram nesse tenant.
* Deduplicação de pacientes/empresas entre os bancos (regra de match: CPF normalizado, nome + data de nascimento etc.), tratamento de órfãos, conversão de charset, migração de senhas (forçar redefinição ou rehash no primeiro login), migração de arquivos (laudos/PDFs) para storage por tenant.
* Ferramenta: comandos Artisan de importação idempotentes, com relatório de inconsistências e possibilidade de reexecução.
* Validação pós-migração (contagens, somatórios, amostragem).

### 5.7 Arquitetura Laravel

* [Confirmado] **Stack**: PHP/Laravel e MySQL ou MariaDB nas versões estáveis da [matriz única](../fluxos/14-arquitetura-laravel.md#141-stack--fonte-única-de-versões); Redis (cache, filas, sessões), Laravel Horizon, Laravel Reverb (painel de TV e fila de recepção em tempo real, substituindo polling), Laravel Scout (busca, se necessário), Pest, Larastan/PHPStan (nível máximo viável), Laravel Pint, Rector, Pulse/Telescope (somente dev). Frontend: Vuexy conforme a orientação do usuário; avaliar a integração técnica sem trocar a referência visual.
* **Estrutura de pastas modular** (por domínio, não só por tipo técnico), por exemplo `app/Domain/<Modulo>/{Models,Actions,DTOs,Enums,Events,Policies,Contracts,Repositories,Services}` e `app/Http/...` fina. Mostre a árvore proposta.
* **Camadas e padrões**:
  * Controllers finos → Form Requests (validação/autorização) → Actions/Services (casos de uso) → Repositories (somente onde houver ganho real; não criar repositório genérico só por formalidade) → Models;
  * DTOs (`readonly` classes) e Value Objects (`Cpf`, `Cnpj`, `Email`, `Money`, `Telefone`, `PeriodoAgenda`) centralizando validação e formatação (DRY);
  * Enums *backed* para status e tipos;
  * Domain Events + Listeners/Jobs para efeitos colaterais (notificações, auditoria, integração);
  * API Resources para respostas; versionamento da API (`/api/v1`);
  * Pipelines ou Strategy onde houver variação de regra por tenant (ex.: regras de agendamento configuráveis).
* **SOLID aplicado com exemplos concretos deste domínio** (não definições genéricas):
  * S — ex.: `AgendarExameAction` separado de `NotificarAgendamentoListener`;
  * O — ex.: novos canais de notificação ou gateways de pagamento sem alterar código existente;
  * L — implementações de `BillingGatewayContract` / `LaudoSignerContract` intercambiáveis;
  * I — contratos pequenos por capacidade (`Schedulable`, `Billable`, `Signable`);
  * D — injeção via container, bindings em Service Providers por módulo.
* **DRY**: liste as regras duplicadas encontradas na Etapa 2 e onde cada uma será centralizada.
* **Clean Code**: convenções de nomenclatura (decidir idioma dos nomes de domínio — português ou inglês — e manter consistência), métodos curtos, sem lógica em views, sem queries em controllers, tipagem estrita (`declare(strict_types=1)`), retorno tipado em tudo.
* Lista de **Models Eloquent** com relacionamentos, lista de **Migrations** na ordem de criação, lista de **Policies**.
* Configurações por tenant (horários, logo/marca, modelos de laudo, textos de e-mail/WhatsApp, regras de agenda).

### 5.8 Qualidade, DevOps e observabilidade

* Estratégia de testes: unitários (Value Objects, regras), feature (fluxos HTTP), testes de isolamento de tenant, testes de permissão por role, testes de arquitetura (Pest Arch: controllers não acessam Models diretamente, domínio não depende de HTTP etc.). Meta de cobertura para regras críticas.
* CI/CD (GitHub Actions ou similar): lint, análise estática, testes, build, deploy com migrations seguras (zero downtime).
* Ambientes (local com Laravel Sail/Herd, staging, produção), Docker, backups com retenção compatível com a guarda de prontuário, restore por tenant, logs estruturados, monitoramento de erros, métricas por tenant.

## ETAPA 6 — NOVAS FUNCIONALIDADES E MELHORIAS

Separadamente das correções estruturais, liste oportunidades de evolução, com valor de negócio e complexidade estimada (P/M/G), por exemplo:

* Portal do paciente (agendar online, preparo do exame, check-in antecipado, baixar resultado/laudo);
* Portal da empresa cliente (exames ocupacionais dos funcionários, ASO, relatórios);
* Notificações automáticas por e-mail/WhatsApp/SMS (confirmação, lembrete, preparo, resultado liberado, pesquisa de satisfação pós-atendimento);
* Painel de TV em tempo real e check-in por QR Code/totem;
* Dashboard de indicadores (exames realizados, ocupação de agenda, no-show, tempo de espera, NPS, faturamento);
* Assinatura digital de laudos ICP-Brasil e verificação de autenticidade por QR Code;
* Integração com equipamentos/PACS (DICOM) e laboratórios de apoio, se aplicável;
* Faturamento de convênios (TISS), repasse médico;
* API pública para integrações e white-label por tenant;
* Auditoria completa e trilha de acesso a dados sensíveis (LGPD);
* Recursos de IA (ex.: rascunho de laudo a partir de modelos, triagem de agendamento, resumo de pesquisas de satisfação) — sinalize riscos clínicos e de privacidade.

## FORMATO DE SAÍDA ESPERADO

Organize a entrega nas seções abaixo, nesta ordem (cada uma em seu arquivo dentro de `docs/analise/`):

0. Sumário executivo (situação atual, principais riscos, recomendação, esforço estimado)
1. Inventário do projeto (subsistemas, versões, dependências, integrações, crons)
2. Mapa de dependências entre subsistemas e atores
3. Fluxos, telas e formulários por subsistema
4. Regras de negócio extraídas (incluindo duplicadas)
5. Riscos de segurança, LGPD e conformidade
6. Bancos legados: dicionário de dados e diagramas ER atuais
7. Inconsistências e problemas de modelagem
8. Visão do produto SaaS, bounded contexts e matriz de rastreabilidade legado → novo
9. Estratégia multi-tenant
10. Assinatura e billing
11. Identidade, autenticação e ACL (incluindo mapeamento de níveis legados)
12. Novo schema unificado (com diagramas ER)
13. Plano de migração de dados
14. Arquitetura Laravel (stack com versões, estrutura de pastas, models, migrations, services/actions, policies, padrões)
15. Qualidade, testes, DevOps e observabilidade
16. Novas funcionalidades e melhorias
17. Roadmap em fases (MVP → fases seguintes), com estimativa de esforço por módulo e estratégia de convivência legado/novo (strangler fig, módulo a módulo)
18. Riscos e pontos de atenção da migração (o que pode quebrar, dependências externas, janela de corte, treinamento de usuários)
19. Perguntas em aberto para o negócio

Seja detalhado e técnico. Este documento é a especificação oficial do projeto: sempre que possível, cite nomes reais de arquivos, tabelas e campos do código analisado e diferencie claramente o que foi **confirmado** do que foi **inferido**.
