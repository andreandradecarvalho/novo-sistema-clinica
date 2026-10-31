# 01 — Inventário do projeto legado

> **Data da análise:** 31/10/2026 · **Fontes:** código em `precordis-admin-completo/` (commit `3585fe3` + alterações locais não commitadas em `agendaprecordis/`), dumps em `database/`, e consulta **somente leitura** aos três bancos de produção.
> **Convenção:** **[C]** = Confirmado (visto no código/dump/banco) · **[I]** = Inferido (dedução).
> **Credenciais:** todas as senhas estão mascaradas (`***`). O fato de existirem no código é tratado como risco em [05-seguranca-lgpd-conformidade.md](05-seguranca-lgpd-conformidade.md).

---

## 1.1 Números gerais

| Métrica | Valor | Status |
|---|---|---|
| Tamanho em disco | 1,5 GB (719 MB só em `private/`) | [C] |
| Arquivos (sem `.git`) | ~5.150 | [C] |
| Arquivos `.php` de aplicação (sem `vendor/`) | 225 (≈ 46.400 linhas) | [C] |
| PDFs | 593 (482 em `private/`, ~99 em `orientacoes/`) | [C] |
| Arquivos `.sql` | 7 (ver 1.6) | [C] |
| `composer.json` de aplicação | **nenhum** (existe `agendaprecordis/vendor/` sem `composer.json`) | [C] |
| Bancos de dados distintos | **3** MySQL (`precordis2016`, `agendaprec`, `samarco`) + **1 Microsoft Access** externo | [C] MySQL / [C] existência do Access via `log_sync` |
| Repositório git | 3 commits (24–28/10/2026), sem remoto configurado | [C] |

## 1.2 Subsistemas

| # | Pasta | Propósito confirmado | Banco | PHP (arquivos/linhas) | Status |
|---|---|---|---|---|---|
| 1 | raiz (`manager.php`, `usuarios*.php`, `empresas*.php`, `agenda*.php`, `acessos.php`, `email*.php`, `send-mail.php`) | **Portal de resultados** (download de laudos em PDF de `private/`) + admin de usuários, empresas, log de acesso e e-mails agendados | `precordis2016` | 41 / 5.659 | **Ativo** — 15.146 logins nos últimos 12 meses (2.535 usuários distintos) [C] |
| 2 | `agendaprecordis/` | **Pré-agendamento de check-up/CMA por dia**, confirmação, desistências, reconvocação (+1 ano), campanhas WhatsApp (via n8n) e convocação por e-mail com anexo; exportação de fichas em PDF/DOCX no navegador | `agendaprec` (+ leitura de `precordis2016`) | 64 / 15.307 (inclui 10 cópias `.php-original`, `-v1`, `--`) | **Ativo** — último registro 30/10/2026 [C] |
| 3 | `formulario/` | **Ficha cadastral + anamnese** preenchida pelo paciente (tablet na recepção) e cadastro de paciente novo; aceite de termos (LGPD) | `precordis2016` (+ leitura de `agendaprec`) | 13 / 4.991 | **Ativo** — última anamnese 30/10/2026 [C] |
| 4 | `form_tab_emerg/` | Cópia de `formulario/` com modo "assistente" (recepção identifica o paciente pelo código) | `precordis2016` | 14 / 3.479 | **Duplicado** (contingência) [C/I] |
| 5 | `formulario_novo/` | Versão **mais antiga** de `formulario/` (não tem perguntas andrológicas, termos LGPD nem regra de matrícula SAMARCO) | `precordis2016` | 11 / 2.533 | **Obsoleto** [C] |
| 6 | `checkupNovo/` | **Cópia da raiz** (41 de 47 arquivos idênticos; difere em `_connection_precordis.php`, `manager.php`, `managerAcesso.php`, `info.php`, `logout.php`, `usuarios-editarTESTE.php`) — não é cópia de `agendaprecordis` | `precordis2016` | 41 / 5.494 | **Duplicado/obsoleto** [C] |
| 7 | `checkinCliente/` | Check-in do dia e **painel de progresso por exame** (19 colunas de exame com cores verde/amarelo/branco/nao) e inclusão de "cliente extra" (encaixe sem agendamento) | `agendaprec` | 15 / 2.952 | **Inativo desde 19/03/2026** (último registro) — substituído na prática pelo `precordistv` [C] |
| 8 | `precordistv/` | **Painel de chamada em TV**, recepção (chamar paciente para sala), salas com PIN, status de sala por celular do profissional, roteiro do paciente | `samarco` | 17 / 3.427 | **Ativo** — último chamado 29/10/2026 [C] |
| 9 | `pesquisaSatisfacao/` | Pesquisa **NPS (0–10)** + satisfação (4 notas 0–10 + observação) anônima | `precordis2016` | 3 / 1.790 | **Ativo** — última resposta 30/10/2026 [C] |
| 10 | `cadastrar_dados/` | Página de entrada (código + empresa) que posta para `novoPaciente.php` — cópia de `formulario/novo.php` | `precordis2016` | 1 / 49 | **Vestigial** — o `action` relativo aponta para arquivo inexistente na pasta [C] |
| 11 | Sincronizador Access → MySQL | Rotina **fora do repositório** que roda ~1×/min copiando dados do Access para `samarco` (`espelho_atendimento`, `relatorio_prec`, `framingham`, `log_sync`) | Access (ODBC) → `samarco` | — | **Ativo**, mas **sem execução desde 29/10/2026 10:07** no momento da análise [C] — verificar |
| 12 | n8n (2 instâncias) | Disparo de WhatsApp/e-mail e gravação dos retornos em `agendamento` | `agendaprec` | — | **Ativo** (fora do repositório) [C chamada / I escrita] |
| 13 | `private/` | 882 arquivos (laudos PDF, imagens TIF/JPG, 5 AVI, `Thumbs.db`) em `private/<Empresa>/<Paciente>/...` **dentro do document root** | filesystem | `size.php` (script avulso de zip) | **Ativo** [C] |
| 14 | `orientacoes/` | ~99 PDFs de orientação ao paciente por especialidade (muitos duplicados com/sem `_`) | filesystem | — | Sem referência no código PHP [C] — uso inferido por link externo/e-mail [I] |
| 15 | `apresentacao.html`, `pages/`, `lib/turn*.js`, `js/magazine.js` | Flipbook institucional (64 JPGs) linkado de `checkup.html` | — | — | Institucional, baixo valor [C] |
| 16 | `BKP_SERV/`, `teste/`, `teste1/`, `teste_lw/`, `testelw/`, `manager_teste.php`, `managerTeste.php`, `manager-search_BKP.php`, `usuarios-editarTESTE.php`, `_menu_top_.php` | Backups e testes | — | — | **Obsoleto**; `teste_lw/Mail.php` é um formulário público de envio de e-mail (risco) [C] |
| 17 | `compactacao/` (5 `.tar` de clientes), `arquivos_zip/` (2 `.zip` gerados por `baixar_zip.php`) | Artefatos de exportação de laudos esquecidos no servidor | — | — | **Lixo com dado sensível** (o `.tar` de um cliente contém 23 PDFs) [C] |
| 18 | `bancoaccess/` | Pasta vazia | — | — | Vestígio do Access [I] |
| 19 | `Stuk/` | JSZip 3.10.1 completo (repositório da lib, 198 arquivos) | — | — | Vendorizado sem uso no PHP [C] |
| 20 | `docs/`, `Claude outputs/` | Análise anterior (20/09/2026) e planos por fase (Laravel 11 single-tenant) | — | — | **Substituídos** por esta análise (ver 00-sumário) |

## 1.3 Versão de PHP

| Subsistema | Sinais encontrados | Compatível com |
|---|---|---|
| raiz, `checkupNovo`, `formulario*`, `form_tab_emerg`, `pesquisaSatisfacao` | `mysqli_*` procedural, `utf8_encode/utf8_decode` (17 arquivos na raiz), sem `??`, sem tipos | PHP 5.6 – 8.1 (quebra em 8.2+ por `utf8_*` depreciado; em 9 será removido) [C] |
| `agendaprecordis` | `mysqli` OO + algumas `prepare`, `??` em 10 arquivos, 1 PDO, `random_bytes` | **PHP ≥ 7.0** [C] |
| `precordistv` | PDO, `declare` de tipos de retorno (`: PDO`, `: ?string`, `: void`), `??`, `password_verify`, `hash_equals` | **PHP ≥ 7.4** [C] |
| Ambiente declarado | `.htaccess`: `AddHandler php73-script .php` (Locaweb, conta `beta59`) | PHP 7.3 [C] |
| Divergência | `agendaprecordis/INSTALAR_FTP.txt` afirma servidor **PHP 5.2.17** | **Contradição**: código com `??` não roda em 5.2 → [perguntas-em-aberto.md](perguntas-em-aberto.md) #1 |

**Nenhuma** função removida do PHP 7 foi encontrada (`mysql_*`, `ereg*`, `each()`, `create_function`) — os acertos de busca eram JavaScript (`.split`, `.each`). [C]

## 1.4 Bibliotecas e dependências

| Biblioteca | Versão | Onde | Observação |
|---|---|---|---|
| jQuery | **1.8.2** (CDN) **e** 3.5.1 (CDN) no mesmo `<head>` | `manager.php:186-189` | 1.8.2 tem CVEs de XSS conhecidas (CVE-2015-9251, CVE-2019-11358, CVE-2020-11022/11023) [C versão / CVEs conhecidas] |
| jQuery | 3.3.1 (CDN, 51 refs), 3.4.1, 3.6.0 locais | `formulario*`, `agendaprecordis/assets`, `checkinCliente/assets` | 3.3.1/3.4.1 afetadas por CVE-2020-11022/11023 |
| jQuery UI | 1.11.4 (CDN) | `agenda-*.php` | CVEs de XSS em datepicker (CVE-2021-41182/41183/41184) |
| jQuery Validation | 1.15.0 | `js/` | ReDoS conhecido em versões < 1.19.3 |
| tableSort.js | — | `js/tableSort.js` | usa `.live()` (removido no jQuery 1.9) |
| CKEditor | 4.x (vendorizado) | `js/ckeditor/` | versão 4 sem suporte gratuito |
| Bootstrap | 4.1.1 (repositório completo ×3) | `formulario/`, `formulario_novo/`, `form_tab_emerg/` | CVE-2018-14040/14042 (XSS) |
| Template | HTML5 UP "Spectral" | `agendaprecordis`, `checkinCliente`, `formulario` | — |
| pdfmake / docx.js | 0.2.20 / — | `agendaprecordis/assets/js/vendor/` | geração de fichas no navegador (commit de 24/10/2026) |
| dompdf 3.1.6, phpword 1.4.0, php-css-parser 9.5, html5 2.11 | Composer (só `vendor/`) | `agendaprecordis/vendor/` | **Não referenciados por nenhum PHP da aplicação** [C] — dependência morta, exposta na web |
| JSZip | 3.10.1 | `Stuk/`, `js/jszip*.js` | sem uso no PHP |
| turn.js, zoom.js, modernizr 2.5.3 | — | `lib/`, `js/` | flipbook |

## 1.5 Configurações de banco (senhas mascaradas)

| Arquivo | Host / schema / usuário | Observação |
|---|---|---|
| `_connection_precordis.php:3` | `precordis2016.mysql.dbaas.com.br` / `precordis2016` / `precordis2016` / `***` | include principal da raiz, `formulario*`, `pesquisaSatisfacao`; **mantém 2 senhas antigas e uma conexão `localhost/root` comentadas** |
| `checkupNovo/_connection_precordis.php:3`, `form_tab_emerg/_connection_precordis.php` | idem, **senha antiga** | senha antiga **já não autentica** (testado em 31/10/2026) [C] |
| `precordistv/_connection_precordis.php` | idem | não é usado pelo `precordistv` (usa `config.php`) |
| `agendaprecordis/db.php:3-9` | `agendaprec.mysql.dbaas.com.br` / `agendaprec` / `agendaprec` / `***` | usado só por ~12 arquivos; **≈ 30 arquivos repetem host/usuário/senha inline** |
| `checkinCliente/*.php` (todos) | idem, **senha antiga** | senha antiga não autentica [C] — **o `checkinCliente` em produção pode estar com outra cópia** ou parado [I] |
| `agendaprecordis/search_cliente.php:6`, `get_cliente_by_id.php:7`, `form_agendamento*.php`, `cadastrar/editar/listar_texto_mensagem_automatica.php` | conexão inline a `precordis2016` | acoplamento cruzado |
| `formulario/index.php:85` | conexão inline a `agendaprec` | acoplamento cruzado |
| `precordistv/config.php:11-15` | `samarco.mysql.dbaas.com.br` / `samarco` / `samarco` / `***` | PDO `utf8mb4` |
| `agendaprecordis/prec.txt` | **credenciais de FTP** do host `beta.precordis.com.br` | arquivo de texto no repositório e no document root |

**Grants verificados em produção** (`SHOW GRANTS`, 31/10/2026) [C]:
- `precordis2016@'%'` → `ALL PRIVILEGES ON precordis2016.* ... WITH GRANT OPTION`
- `agendaprec@'%'` → `ALL PRIVILEGES ON agendaprec.*`
- `samarco@'%'` → `ALL PRIVILEGES ON samarco.*`

Ou seja: o mesmo usuário da aplicação é dono do schema, aceita conexão de **qualquer IP** e pode conceder privilégios a terceiros.

**Versão e charset do servidor** [C]: MySQL **5.7.32-35** (Percona, DBaaS Locaweb), charset padrão `latin1` / `latin1_general_ci`. MySQL 5.7 está fora de suporte desde 10/2023.

## 1.6 Arquivos `.sql`

| Arquivo | Conteúdo | Incorporado? |
|---|---|---|
| `database/precordis2016.sql` | Navicat, estrutura de 12 tabelas + 2 views (28/06/2026) | Sim — **idêntico** à estrutura de produção de 31/10/2026 |
| `database/agendaprec.sql` | Navicat, 9 tabelas | Sim — idêntico |
| `database/samarco.sql` | Navicat, 8 tabelas | Sim — idêntico |
| `database/estrutura-producao/estrutura_*.sql` (3) | `mysqldump --no-data` de 20/09/2026 | Sim — idênticos aos anteriores |
| `precordistv/06_pin_salas.sql` | `ALTER TABLE salas ADD COLUMN pin` (aplicado em 15/07/2026) | Sim |

Não há procedures, triggers, events ou FKs além de duas (ver doc 06). Os scripts `01_` a `05_` sugeridos pelo nome `06_` não existem no repositório (o schema real foi obtido do servidor, então não é lacuna de estrutura).

**Lacuna real:** o **banco Microsoft Access** de origem (tabelas citadas nos erros do sync, ex.: `Clientes_*`) e o **código do sincronizador** não estão no repositório. → [perguntas-em-aberto.md](perguntas-em-aberto.md) #2.

## 1.7 Integrações externas

| Integração | Onde | Como | Status |
|---|---|---|---|
| **n8n — `n8n.aplicativo.precordis.com.br`** | `agendaprecordis/listar_agendados.php:740` | `fetch()` direto do navegador, POST JSON `{selecionados, textoMensagem, teste, tipoTexto}`, **sem token** | Ativo [C] |
| **n8n — `n8n.platform.andreandradecarvalho.site`** (domínio de terceiro) | `listar_emails_lembra_procedimento.php:408`, `listar_mensagens_fim_de_ano.php:426`, `listar-pacientes-com-checkup-a-mais-de-um-ano.php:540` | idem | Ativo [C] — dados de pacientes trafegam por infraestrutura de terceiro (operador LGPD) |
| WhatsApp (via n8n) | colunas `agendamento.enviouMensagem*`, `isWhatsappNumber`, `whatsappPhoto` (1.799 URLs de foto de perfil), `mensagem_fim_de_ano.wuid_whatsapp` | O n8n grava o retorno no banco | [C] colunas preenchidas / [I] autor da escrita |
| E-mail | `mail()` nativo em 9 arquivos (raiz, `teste*`), sempre com cópia fixa para endereços internos hardcoded | Sem SPF/DKIM controlado pela aplicação | [C] |
| Microsoft Access (ODBC) | `samarco.log_sync` | Mensagens de erro no formato `('HY000', '[Microsoft][Driver ODBC Microsoft Access] ...')` → script **Python/pyodbc em máquina Windows** | [C] existência / [I] tecnologia |
| Locaweb (hospedagem + DBaaS) | `.htaccess`, hosts `*.mysql.dbaas.com.br`, path `/home/storage3/.../beta59/public_html` em `baixar_zip.php:45` | — | [C] |
| Monetizze (afiliados) | `info.php:11-13` — dois links de produto de emagrecimento ao final do `phpinfo()` | **Indício de comprometimento ou conteúdo injetado** | [C] |
| Google Fonts / CDNs (googleapis, code.jquery.com) | vários | — | [C] |

Não há integração com laboratórios, equipamentos, PACS/DICOM, TISS, ViaCEP ou Receita. [C]

## 1.8 Tarefas agendadas e scripts CLI

| Tarefa | Evidência | Status |
|---|---|---|
| `send-mail.php` (envio de `mensagens` com `ms_status=0` e `ms_date < NOW()`) | código sem sessão, desenhado para cron | **Morto desde 2022** — último envio (`ms_status=1`) em 29/01/2022; 1.297 mensagens pendentes nunca enviadas [C] |
| Sincronizador Access → `samarco` | `log_sync`: ~710 execuções/dia | Ativo até 29/10/2026 10:07 [C] |
| Liberação automática de salas às 22h | `precordistv/sala_status_api.php` (`auto_liberar_salas_noturno`) — roda **dentro do GET**, não é cron | [C] |
| Fluxos n8n de lembrete / não confirmados | `configuracao_nao_confirmado.ativo=1` e colunas `notificouNaoConfirmou*` | [I] o agendamento roda no n8n |

Não existe crontab, `artisan schedule` ou script CLI versionado. [C]

## 1.9 Volumetria de produção (contagem exata `COUNT(*)` em 31/10/2026)

> A análise anterior (20/09) usou `information_schema.table_rows`, que é **estimativa** no InnoDB — por isso seus números eram menores. Os valores abaixo são exatos.

| Banco | Tabela | Linhas | Período dos dados |
|---|---|---|---|
| `precordis2016` | `usuario` | 10.216 (9.820 pacientes, 382 médicos, 12 admins, 2 admin+médico) | cadastros até 30/10/2026 |
| | `info_usuario` | 10.015 | |
| | `saude_usuario` | 6.780 | 03/01/2021 → 30/10/2026 |
| | `permissao` | 10.539 | |
| | `permissao_diretorioComposto` | 9 | |
| | `mensagens` | 27.937 (9,5 MB) | 2016 → 2023 |
| | `watchdog` | 119.613 | 20/07/2016 → hoje |
| | `empresas` | 110 (99 raiz + 11 filiais) | |
| | `pesquisa_NPS` / `pesquisa_Satisfacao` | 1.914 / 2.014 | 04/05/2023 → 30/10/2026 |
| | `lembrete_resultado_exames` | 13 | só 25/07/2025 |
| | `taxonomy_term_data` | 110 | resíduo Drupal |
| `agendaprec` | `agendamento` | 6.894 | 01/01/2024 → 22/01/2027 (178 futuros) |
| | `checkincliente` | 1.327 | 18/11/2024 → 19/03/2026 |
| | `lembra_procedimento` | 6.604 (31,6 MB) | 20/09/2025 → 06/04/2026 |
| | `mensagem_fim_de_ano` | 2.090 | 22–23/12/2025 |
| | `envio_convocacao_exame` | 27 | 01/04/2026 |
| | `mensagens_envio_automatico` | 6 | |
| | `atendimentos` | 11 | 07–09/06/2026 |
| | `entrada` | 2 | sem uso no código |
| | `configuracao_nao_confirmado` | 1 | |
| `samarco` | `log_sync` | 86.122 | 19/06/2026 → 29/10/2026 |
| | `espelho_atendimento` | 917 (`AUTO_INCREMENT` 674.696) | 19/06 → 29/10/2026 |
| | `chamados_tv` | 512 | 22/06 → 29/10/2026 |
| | `relatorio_prec` | 270 | 06/02 → 30/10/2026 |
| | `framingham` | 87 | |
| | `salas` / `status_salas` | 18 / 19 | |
| | `roteiro_paciente` | **0** | |

**Ordem de grandeza operacional** [C]: ~10–12 pacientes de check-up por dia útil (média 10,1 agendamentos/dia nos últimos 12 meses; `espelho_atendimento` 11–12/dia), praticamente só de segunda a sexta.
