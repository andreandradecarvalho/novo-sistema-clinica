# 05 — Segurança, privacidade (LGPD) e conformidade

> Cada item traz **arquivo:linha**, **severidade** e **recomendação**. Senhas mascaradas. Este documento descreve *o que* está vulnerável e *como corrigir* — não traz prova de conceito de exploração.
> Severidade: **Crítica** (explorável hoje, impacto alto em dado de saúde) · **Alta** · **Média** · **Baixa**.

## 5.1 Resumo executivo de segurança

O legado trata **dado pessoal sensível de saúde** (art. 11 da LGPD) de ~10 mil pacientes, com 719 MB de laudos em PDF. Os controles de acesso, quando existem, rodam no cliente; a autorização do servidor é facilmente contornável; e as credenciais de produção estão no repositório e aceitam conexão de qualquer IP. A combinação eleva vários itens de "má prática" para **risco concreto de vazamento de prontuário**. Os itens Críticos abaixo devem ser tratados **antes e independentemente** da migração, pois são exploráveis na aplicação em produção hoje.

## 5.2 Críticos

| # | Item | Arquivo:linha | Recomendação |
|---|---|---|---|
| C1 | **Autorização ineficaz: `header("Location: ...")` sem `exit`.** O redirecionamento não interrompe o script; o restante (incluindo a query e o JSON de dados) continua executando e é enviado ao cliente. Afeta todo o controle de perfil da raiz. | `manager.php:10`, `usuarios*.php:6-8`, `empresas*.php`, `acessos.php:6`, `agenda*.php`, `detalhePaciente*.php:6` | Adotar middleware/policy no sistema novo; no legado, `exit;` imediatamente após cada `header()` de bloqueio. |
| C2 | **Controle de acesso aos laudos no cliente.** `manager.php` devolve o `scandir` inteiro da pasta em JSON (`?do=list`) e o filtro por pasta/extensão permitida é feito em JavaScript (`renderFileRow`). O download (`?do=download&file=`) e o `?do=list` usam `$_REQUEST['file']` em `realpath`/`readfile`/`scandir` **sem confinar ao diretório do usuário** (as checagens `realpath`/`__DIR__` estão comentadas, linhas 22–30). | `manager.php:22-30,77-137,269-308`, `managerTeste.php`, `manager_teste.php` | Servir arquivo por id com verificação de dono no servidor; armazenar laudos **fora** do document root; nunca listar diretório bruto. |
| C3 | **`baixar_zip.php` — zip de diretório sem autenticação.** Monta o caminho a partir de `$_GET['hashPart']` concatenado ao path físico absoluto e zipa recursivamente; o arquivo não inclui `_check_login.php`. | `baixar_zip.php:22-45` | Exigir autenticação + autorização por tenant/empresa; validar o caminho contra o diretório permitido; gerar zip server-side por id. |
| C4 | **`excluir_pasta.php` — DELETE sem autenticação.** Recebe `$_POST['usuario_id']` (sem escape) e apaga `permissao_diretorioComposto`; sem `_check_login.php`. | `excluir_pasta.php:1-13` | Autenticar, autorizar e parametrizar a query. |
| C5 | **Senhas de paciente em texto plano e enviadas por e-mail.** `usuario.senha` guarda a senha em claro (além de `usuario.password` em md5); `_lembra_senha.php`, `usuarios-cadastrar/editar.php` enviam a senha por e-mail. 10.157 de 10.216 senhas são numéricas de 5 dígitos. | `usuario.senha`; `_lembra_senha.php:19-39`, `usuarios-editar.php:267` | Hash forte (bcrypt/argon2); fluxo de redefinição por link com expiração; nunca enviar senha. |
| C6 | **Autenticação por `md5(senha)` sem salt.** `_login.php` compara `password = md5($_POST['pass'])`; 69 grupos de senha md5 repetida (senhas iguais). | `_login.php:9` | bcrypt/argon2 com rehash no primeiro login. |
| C7 | **SQL Injection por interpolação de entrada do usuário.** Vários pontos concatenam `$_GET`/`$_POST`/`$_SESSION` direto na SQL: `usuarios.php:11` (`$termo`), `empresas.php:11`, `managerAcesso.php:21` (`$_GET['user']`), `managerAcesso.php:45-47` (`$folderName` em `LIKE`), `agendaprecordis/menu.php:34` (insert do formulário), `editaDados.php:41-55`, `deleta_agenda.php:21` (`$_POST['codigo']`), `Desistencia.php:19` (`$_GET`), `listar_agendados_Apoio.php:32`, `listar-pacientes-...-ano.php:21` (`$_GET['nome']` em `LIKE`), `checkinCliente/ExcluirClienteExtra.php:21` (`$_GET['cod']`), `formulario/index.php:90` (`$nomeUsuario` em `LIKE`). | ver coluna | Prepared statements em 100% das queries (Eloquent/PDO parametrizado no sistema novo). |
| C8 | **Coluna de UPDATE escolhida por entrada do usuário.** `salvar_checkin.php`/`gravar_checkin.php`/`atualizar_status.php` montam `UPDATE checkincliente SET $coluna = ?` onde `$coluna`/`$exame` vêm de `$_POST`. Há `switch`/whitelist em alguns, mas `gravar_checkin.php:32` usa `$_POST['campo']` direto. | `checkinCliente/gravar_checkin.php:18-38` | Whitelist estrita server-side; mapear nome→coluna por array fixo. |
| C9 | **Credenciais de produção no repositório, válidas, aceitando qualquer IP.** Senhas em ~45 arquivos + `agendaprecordis/prec.txt` (FTP). `SHOW GRANTS`: os 3 usuários são `@'%'` com `ALL PRIVILEGES` (o `precordis2016` ainda com `GRANT OPTION`). | `_connection_precordis.php:3`, `agendaprecordis/db.php`, `precordistv/config.php:15`, `prec.txt` | **Rotacionar as 3 senhas já**; restringir grant por IP; mover segredos para `.env` fora do git; `.gitignore` + limpeza do histórico; privilégios mínimos (sem `GRANT OPTION`). |

## 5.3 Altos

| # | Item | Arquivo | Recomendação |
|---|---|---|---|
| A1 | **Ausência total de CSRF** em todos os formulários POST (login, cadastro, exclusão, agendamento, check-in). | geral | Token CSRF por sessão (nativo no framework novo). |
| A2 | **XSS armazenado e refletido.** Saída sem `htmlspecialchars` na maioria das listagens da raiz e de `agendaprecordis` (`echo $users_row['nome']`, `$termo` ecoado no `value=`); nome/observação do paciente entram em HTML e em atributos. | `usuarios.php:55`, `manager-search.php`, listagens de `agendaprecordis` | Escapar toda saída; CSP. (`precordistv` já usa `htmlspecialchars`.) |
| A3 | **Laudos servidos de dentro do document root** (`private/`, 719 MB). Além do C2, a URL direta `/private/<empresa>/<nome>/arquivo.pdf` é acessível se adivinhada; `.htaccess` só força HTTPS, não bloqueia `private/`. | `private/`, `.htaccess` | Armazenar fora do webroot; servir por controlador autenticado. |
| A4 | **Arquivos sensíveis expostos na web**: `info.php` (`phpinfo()` público, ainda com 2 links de afiliado Monetizze ao final — indício de injeção), dumps `.sql` em `database/`, `.tar`/`.zip` de laudos em `compactacao/` e `arquivos_zip/`, `prec.txt` (FTP), `teste_lw/Mail.php` (envio de e-mail aberto). | citados | Remover do webroot; `info.php` fora; varrer host por conteúdo injetado. |
| A5 | **Webhooks n8n sem autenticação**, chamados do navegador, um deles em **domínio de terceiro** (`andreandradecarvalho.site`) por onde trafegam nome/telefone/empresa de pacientes. | `agendaprecordis/listar_*` | Token/assinatura no webhook; mover chamada para o servidor; formalizar o terceiro como **operador** (LGPD, art. 39) com contrato. |
| A6 | **`session.save_path` dentro do webroot** (`ini_set('session.save_path','tmp')`), em 7 arquivos da raiz. | `index.php:2` etc. | Usar diretório de sessão fora do webroot / Redis. |
| A7 | **Bibliotecas front-end com CVEs conhecidas** (jQuery 1.8.2/3.3.1, jQuery UI 1.11.4, Bootstrap 4.1.1, CKEditor 4). | ver [01](01-inventario.md) §1.4 | Atualizar/retirar no sistema novo. |
| A8 | **Dependência Composer morta exposta** (`agendaprecordis/vendor/` com dompdf etc., sem uso), aumentando superfície. | `agendaprecordis/vendor/` | Remover do deploy. |

## 5.4 Médios / Baixos

| # | Item | Observação |
|---|---|---|
| M1 | Erros de SQL ecoados ao usuário (`or die("erro SQL".mysqli_error(...))`) em dezenas de arquivos | vaza schema; logar e mostrar genérico |
| M2 | Mensagens de erro do sync com nome de tabela do Access (`log_sync`) legíveis via banco | baixo |
| M3 | `usuario.senha` numérica de 5 dígitos (espaço de 10⁵) | brute-force trivial; ver C5/C6 |
| M4 | `logout.php` da raiz redireciona para `https://precordis.com` (domínio diferente de `precordis.com.br`) | checar posse do domínio |
| M5 | Sem rate-limiting em login (exceto `sleep(1)` no `precordistv`) | adicionar throttling |
| M6 | Cópia oculta fixa de e-mails internos hardcoded | mover para config |

## 5.5 LGPD — dado de saúde é dado sensível (art. 11)

| Requisito | Situação no legado | Ação no sistema novo |
|---|---|---|
| **Base legal / consentimento** | Termos existem em `formulario` (campo `termos_aceita*`), mas só **959 de 10.015** pacientes têm aceite registrado; default do radio é "Sim" (consentimento não é livre) | Consentimento/█base legal explícito, versionado, com data e texto; para dado de saúde, avaliar tutela da saúde (art. 11, II, "a") com apoio jurídico |
| **Minimização** | Coleta anamnese extensa (incl. dados íntimos: andrológico, ginecológico) | Coletar só o necessário por tipo de exame |
| **Log de acesso a dado sensível** | Só `watchdog` de login; **nenhum** log de quem abriu qual laudo/anamnese | Trilha de auditoria de **todo** acesso a dado clínico |
| **Criptografia em repouso/trânsito** | HTTPS forçado (`.htaccess`); laudos e banco sem cripto em repouso; senha em claro | TLS + cripto em repouso (storage/colunas sensíveis); hash de senha |
| **Retenção / descarte** | Sem política; dados desde 2016/2021; `.tar`/`.zip` de laudos esquecidos no servidor | Política de retenção alinhada à guarda de prontuário (abaixo) + descarte seguro |
| **Mascaramento** | Nenhum | Mascarar CPF/dados em telas de listagem |
| **Operadores (terceiros)** | n8n de terceiro processa dados sem contrato visível | Contrato de operador + DPA; registro das operações |
| **Direitos do titular** | Sem fluxo | Portal do titular (acesso, correção, portabilidade) |

## 5.6 Requisitos setoriais (prontuário eletrônico)

> Verificado em fontes oficiais/consolidadas em 31/10/2026. [C]

| Requisito | Norma | O legado atende? | Implicação para o novo |
|---|---|---|---|
| **Guarda do prontuário por no mínimo 20 anos** a partir do último registro | Lei 13.787/2018 (art. 6º) + Res. CFM 1.821/2007 | Parcial — guarda PDFs, sem política formal nem integridade garantida | Retenção ≥ 20 anos, backup com integridade, descarte controlado |
| **Assinatura digital ICP-Brasil** para dispensar o papel / validar laudo | Lei 13.787/2018 (§2º); Res. CFM 1.821/2007 e 2.218/2018; NGS2 da certificação SBIS/CFM | **Não** — laudos são PDFs sem assinatura | Assinar laudos com certificado ICP-Brasil (A3/nuvem) ou CRM Digital; verificação por QR; avaliar certificação SBIS/CFM (NGS1/NGS2) se for eliminar papel |
| **Padrão TISS (ANS)** para faturamento de convênio | RN ANS 501/2022; versão vigente **04.03.00** (obrigatória desde 01/07/2026), Componente Organizacional 202609 | Não se aplica hoje (não há faturamento TISS) | Só se a Precordis passar a faturar convênio pela plataforma; manter a versão vigente, que muda com frequência |
| **LGPD saúde** | Lei 13.709/2018, art. 11 | ver 5.5 | ver 5.5 |

Nada aqui é aconselhamento jurídico; os pontos de conformidade (consentimento para dado de saúde, certificação SBIS/CFM, obrigatoriedade TISS) devem ser validados com assessoria jurídica e, quando aplicável, com o médico responsável técnico.
