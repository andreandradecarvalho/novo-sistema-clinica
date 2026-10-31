# 02 — Mapa de dependências entre subsistemas e atores

> Fonte: código + confirmação em produção. [C]/[I] como em [01](01-inventario.md).

## 2.1 Mapa de dependências

```mermaid
flowchart TB
  subgraph PUB["Acesso público (sem login)"]
    FORMI["formulario/ · form_tab_emerg/<br/>(login do paciente: senha + CPF/email)"]
    PESQ["pesquisaSatisfacao/<br/>(anônimo)"]
    TV["precordistv: tv.php · status.php<br/>sala.php · pacientes_status.php"]
    ZIP["baixar_zip.php"]
    EXCL["excluir_pasta.php"]
    INFO["info.php (phpinfo)"]
    MAILLW["teste_lw/Mail.php"]
  end

  subgraph PRINC["Portal de resultados — precordis2016"]
    LOGIN["_login.php (md5)"] --> MGR["manager.php"]
    MGR --> MENU["_menu_top.php"]
    MGR --> USR["usuarios* / empresas* / acessos / agenda*"]
    AG["agenda-cadastrar.php"] --> SM["send-mail.php (cron morto)"]
    MGR -. lê .-> PRIV[("private/ — 719 MB PDFs")]
  end

  subgraph AGD["agendaprecordis — agendaprec"]
    AIDX["index.php → valida.php<br/>(credenciais fixas no código)"] --> AMENU["menu.php"]
    AMENU --> FAG["form_agendamento.php"] --> GRA["gravaagenda.php"]
    AMENU --> LIS["listar_agendados*.php (6 variantes)"]
    AMENU --> CONV["convocacao / textos automaticos"]
  end

  subgraph CKI["checkinCliente — agendaprec (inativo)"]
    CIN["checkin.php"] --> SALV["salvar_checkin.php / gravar_checkin.php"]
  end

  subgraph TVS["precordistv — samarco"]
    TLOG["login.php (1 senha compartilhada)"] --> GER["index.php · rapido.php · gerencial.php · salas_admin.php"]
    GER --> CHAM["chamar.php"] --> CF[("chamado_atual.json + chamados_tv")]
    TV -. lê .-> CF
  end

  ACC[("Microsoft Access<br/>(sistema clínico, fora do repo)")] -->|sync ~1x/min| SAM[("samarco:<br/>espelho_atendimento,<br/>relatorio_prec, framingham")]
  SAM --> TVS

  FAG -. conexão cross-DB .-> PRINC
  SC["search_cliente.php / get_cliente_by_id.php"] -. cross-DB .-> PRINC
  FORMI -. conexão cross-DB .-> AGD
  AGD -->|fetch sem token| N8N1["n8n.aplicativo.precordis.com.br"]
  AGD -->|fetch sem token| N8N2["n8n.platform.andreandradecarvalho.site<br/>(terceiro)"]
  N8N1 & N8N2 -->|grava retorno WhatsApp| AGD
```

## 2.2 O que é e o que não é compartilhado

**Bancos compartilhados**
- `precordis2016`: raiz, `checkupNovo`, `formulario`, `formulario_novo`, `form_tab_emerg`, `pesquisaSatisfacao` (escrevem em `usuario`/`info_usuario`/`saude_usuario`/`permissao`). [C]
- `agendaprec`: `agendaprecordis` e `checkinCliente`. [C]
- `samarco`: só `precordistv`. [C]

**Sessão/login — três universos independentes, nenhum integrado** [C]:
| Subsistema | Variável de sessão | Fonte da credencial |
|---|---|---|
| raiz / portal | `$_SESSION['us_id']`, `['administrador']`, `['medico']` | tabela `usuario` (md5) |
| `agendaprecordis` | `$_SESSION['logged_in']` | **2 pares usuário/senha fixos no `valida.php`** + `apoio/precordis` |
| `precordistv` | `$_SESSION['precordis_auth']` | **1 usuário `precordis` + 1 hash fixo** em `auth.php` |
| `checkinCliente` | (nenhuma — `valida.php` não existe na pasta) | formulário aponta para arquivo ausente |
| `formulario` | nenhuma guarda de sessão real; paciente "entra" por senha+CPF | `usuario`/`info_usuario` |

O mesmo médico tem um registro em `usuario` (portal), mas a agenda e a TV não o conhecem — são logins de setor, não de pessoa.

**Chamadas cruzadas entre bancos (sem API, conexão MySQL direta ao outro schema)** [C]:
- `agendaprecordis/form_agendamento.php` e variantes abrem conexão a `precordis2016` para popular o `<select>` de empresa a partir de `empresas`.
- `agendaprecordis/search_cliente.php` e `get_cliente_by_id.php` fazem autocomplete em `precordis2016.usuario JOIN info_usuario`.
- `formulario/index.php:85` abre conexão a `agendaprec` e busca o agendamento do dia do paciente por `NomeCliente LIKE '%nome%'`.

**Tabela escrita por mais de um subsistema**: `agendamento` (agendaprecordis grava; checkinCliente marca `FaltouNoDia`; n8n grava colunas de WhatsApp). `checkincliente` (checkinCliente grava; não há FK com `agendamento`). [C]

**Acoplamento oculto por nome de pessoa** (não por chave): o vínculo paciente ↔ agendamento ↔ exame clínico é feito por **string de nome**, nunca por id — detalhado em [06](06-bancos-legados-dicionario-er.md) e [07](07-inconsistencias-modelagem.md).

## 2.3 Atores / perfis por subsistema

| Ator | Onde atua | Como se autentica | Observação |
|---|---|---|---|
| **Paciente** | portal (vê/baixa seus laudos), `formulario` (anamnese), `pesquisaSatisfacao` | login = senha numérica de 5 dígitos (md5) + CPF/e-mail; pesquisa é anônima | 9.820 pacientes; senha é na prática um "código" de 5 dígitos [C] |
| **Médico / laudador** | portal (`manager.php`, `manager-search.php` — busca pacientes da própria empresa) | `usuario.medico = -1` | 382 médicos; 92 logaram nos últimos 12 meses [C] |
| **Admin Precordis** | portal completo (usuários, empresas, agenda de e-mails, log) | `usuario.administrador = -1` | 14 contas; 7 ativas [C] |
| **Recepção / agendamento** | `agendaprecordis` | 2 e-mails + senha fixos | login de setor [C] |
| **Apoio** | `agendaprecordis` (listagens de impressão) | `apoio/precordis` fixo | [C] |
| **Recepção/sala (TV)** | `precordistv` | 1 login `precordis` compartilhado; sala libera com PIN de 4 dígitos | [C] |
| **Profissional na sala** | `precordistv/sala.php` pelo celular | token na URL `?sala=KEY` + PIN opcional | público, sem login [C] |
| **TV da sala de espera** | `precordistv/tv.php` | nenhuma (público) | [C] |
| **Empresa cliente (PJ/convênio)** | — | **não tem acesso próprio** hoje | `empresas` é só agrupador de pasta de laudo [C] |
| **Sincronizador Access** | grava em `samarco` | credencial `samarco` | robô [C] |
| **n8n** | grava em `agendamento` | credencial `agendaprec` + webhook sem token | robô de terceiro [C] |
