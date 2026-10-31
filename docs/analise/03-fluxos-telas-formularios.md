# 03 — Fluxos, telas e formulários por subsistema

> Cada formulário lista: método, campos, validação server-side, proteção CSRF e efeito no banco. Salvo indicação, **nenhum** formulário do legado tem token CSRF. [C]

## 3.1 Portal de resultados (raiz — `precordis2016`)

| Tela | Arquivo | Propósito |
|---|---|---|
| Home | `index.php` | links Check-up / Fisioterapia |
| Login | `checkup.php` → `_login.php` | autentica paciente/médico/admin |
| Recuperar senha | `esqueci_senha.php` → `_lembra_senha.php` | **envia a senha em texto plano por e-mail** |
| Gerenciador de arquivos | `manager.php` | lista/baixa PDFs de `private/<pasta do usuário>` |
| Busca de paciente (médico) | `manager-search.php` | busca por nome dentro da própria `em_id` |
| Admin unidades do usuário | `managerAcesso.php` | grava `permissao_diretorioComposto` |
| Download em massa | `baixar_zip.php` | zipa a pasta |
| Usuários | `usuarios.php` + `-cadastrar/-editar/-excluir` | CRUD `usuario` + `permissao` |
| Empresas | `empresas.php` + `-cadastrar/-editar` | CRUD `empresas` (hierárquico `em_parent`) |
| Log de acessos | `acessos.php` | `watchdog` (evento `login`) |
| E-mails agendados | `agenda.php`, `agenda-*`, `email_agendado.php`, `enviar_pendente.php` | CRUD `mensagens` + disparo manual |
| Detalhe do paciente | `detalhePaciente.php`, `detalhePacienteSaude.php` | anamnese (não linkados no menu) |

**Formulários (server-side):**

| Form | Arquivo | Método | Validação server | Efeito no banco |
|---|---|---|---|---|
| Login | `_login.php` | POST | nenhuma; SQL com `md5($_POST['pass'])` interpolado | `SELECT usuario`; grava `watchdog` |
| Esqueci senha | `_lembra_senha.php` | POST | nenhuma | lê `usuario.senha` e envia por e-mail (bug: `mail($to,...)` com `$to` indefinido) |
| Cadastrar usuário | `usuarios-cadastrar.php` | POST | só checa duplicidade de `login`; campos escapados com `real_escape_string` | `INSERT usuario` (senha em claro **e** md5) + `INSERT permissao`; `mail()` boas-vindas |
| Editar usuário | `usuarios-editar.php` / `...TESTE.php` | POST | nenhuma além de `isset` | `UPDATE usuario`/`permissao`; reenvio opcional de e-mail com senha em claro |
| Excluir usuário | `usuarios-excluir.php` | POST | checkbox `confirma=1` | `DELETE usuario` + `DELETE permissao` |
| Cadastrar/editar empresa | `empresas-*` | POST | nenhuma | `INSERT/UPDATE empresas` |
| Mensagem agendada | `agenda-cadastrar.php` / `-editar` / `-excluir` | POST | `pattern` de data no HTML | `INSERT/UPDATE/DELETE mensagens` |
| Incluir unidade | `managerAcesso.php` | POST + `?user=` no GET | nenhuma | `INSERT permissao_diretorioComposto` (usa `$_GET['user']` direto) |

**Regras de negócio embutidas** [C]:
1. **Perfil por flag invertida** herdada do Access: `administrador`/`medico` = `-1` (sim) ou `0` (não). Verificação `if($_SESSION['administrador']==0) header("Location: manager.php");` repetida em ~15 arquivos, com variantes (`==0`, `==0 || !isset`, `==-1`). **Nunca há `exit;` após o `header()`** → o script continua (ver [05](05-seguranca-lgpd-conformidade.md)).
2. **Permissão de laudo por string de caminho Windows**: `permissao.pasta` guarda `Empresa\Subpasta`; `manager.php` normaliza `\`→`/` e, se houver `#`/`,`/`;`, faz `preg_split` em várias empresas. O filtro de acesso roda **no JavaScript do cliente** (`renderFileRow`: só desenha a linha se `data.path.lastIndexOf(allowedDir) >= 0`); o servidor devolve o `scandir` inteiro em JSON.
3. **Whitelist de extensão também no cliente**: `['dir','pdf','jpg','jpeg','png','ppt','tif']`.
4. **`mensagens.ms_status`**: `0` pendente, `1` enviado, `2` erro (o `COMMENT` da coluna diz "10 - enviado", divergente).
5. **Cópia oculta fixa** em todo e-mail de resultado: endereços internos hardcoded em `send-mail.php`/`enviar_pendente.php`.
6. **Template**: único "engine" é `str_replace("[NOME]", ...)`.
7. **Assunto condicional**: título `'Precordis - Dúvida sobre exame preventivo'` vira assunto `'Exame Preventivo Realizado'`.
8. **Sessão expira em 3600 s** (`_check_login.php`).

## 3.2 `agendaprecordis` (`agendaprec`)

| Tela | Arquivo |
|---|---|
| Login | `index.php` → `valida.php` (credenciais fixas) |
| Menu | `menu.php` |
| Agendar | `form_agendamento.php` → `gravaagenda.php` |
| Editar (3 variantes por perfil) | `form_agendamentoEdicao.php` / `...APOIO` / `...DataCalendario` → `editaDados.php` / `editaDadosApoio.php` |
| Cancelar/desistir | `deleta_agenda.php`, `Desistencia.php`, `gravaagendaDesistencia.php` (soft delete `DesistiuCancelou='SIM'`) |
| Listagens (6 quase idênticas) | `listar_agendados*.php`, `listar_confirmados.php`, `listar_agendados_Apoio*.php` |
| Calendário | `calendario.php` (PDO) |
| Reconvocação +1 ano | `listar-pacientes-com-checkup-a-mais-de-um-ano.php` |
| Convocação de exame (c/ anexo) | `cadastrar/editar/listar/view/delete_envio_convocacao_exame.php` |
| Textos automáticos | `*_texto_mensagem_automatica.php` → `mensagens_envio_automatico` |
| Campanhas WhatsApp | `listar_mensagens_fim_de_ano.php`, `listar_emails_lembra_procedimento.php` |
| Config "não confirmados" | `configuracao-nao-confirmado.php` |

**Formulário de agendamento** (`form_agendamento.php` → `gravaagenda.php`), POST:
- Campos: `buscaCliente` (autocomplete via `search_cliente.php`), `especialidade` (`Checkup`/`CMA`), `selected_date`, `name`, `sexo` (`Masc`/`Fem`), `datanasc` (máscara JS `dd/mm/aaaa`), `email`, `empresa` (`<select>` populado de `precordis2016.empresas`), `empresaUnidade`, `telefone`, checkboxes `frequencia`/`clienteEspecial`/`temGinecologico`, `message`.
- Validação server: `santize()` faz uppercase + remoção de acentos no nome; **demais campos entram na SQL por interpolação** (ver [05](05-seguranca-lgpd-conformidade.md)).
- Regra: antes do `INSERT agendamento`, checa duplicata com `... AND DesistiuCancelou IS NULL`. **Esse filtro nunca casa** — a coluna é `NOT NULL DEFAULT ''`, nunca `NULL` (confirmado: 6.145 linhas `''`, 749 `'SIM'`, zero `NULL`). **A trava de duplicidade está inativa desde sempre.** [C]
- Faz `SHOW COLUMNS FROM agendamento LIKE 'temGinecologico'` a cada submit (resquício de migração defensiva).

**Edição** (`editaDados.php`): `UPDATE agendamento SET ... DesistiuCancelou=''` — editar um registro **reativa** um agendamento cancelado. [C]

**Calendário**: para cada dia do mês roda uma query — N+1 por natureza, mas volume baixo. [C]

**Confirmação/WhatsApp**: a listagem lê `ConfirmaAgendamento` (`on`/`off`/`NULL`), `whatsappPhoto` (URL da foto de perfil), `enviouMensagem*`, `notificouNaoConfirmou*`. O envio é um `fetch()` para o n8n; o n8n grava os retornos de volta.

**Convocação de exame** (`cadastrar_envio_convocacao_exame.php`): upload com whitelist server `['pdf','jpg','jpeg','png','doc','docx']`, nome aleatório `bin2hex(random_bytes(3))`, salva em `uploads/mensagens_automaticas/`. Fluxo `enviou → leu → liberado`. **É o único upload do legado com validação de extensão no servidor.** [C]

**Exportação de fichas (24/10/2026)**: PDF e DOCX gerados **no navegador** (pdfmake/docx.js) a partir dos pacientes selecionados, 13 especialidades, grades em branco para preenchimento manual. Pacote `dist/` para "PHP 5.2" (ver contradição em [01](01-inventario.md) §1.3).

## 3.3 `checkinCliente` (`agendaprec`, inativo desde 03/2026)

- `checkin.php`: UNION de (agendamentos confirmados do dia **sem falta**) + (clientes extra do dia); desenha 19 "bolinhas" de exame coloridas; **sem guarda de sessão**.
- `salvar_checkin.php` / `gravar_checkin.php` / `atualizar_status.php`: AJAX que faz `INSERT/UPDATE checkincliente` setando a coluna do exame. **Vulnerável** — ver [05](05-seguranca-lgpd-conformidade.md) (coluna dinâmica via `$_POST`).
- `gravaClienteNovoCheckin.php`: cliente extra recebe `codigoAgenda = rand(7000,9000)` (colisão possível — [07](07-inconsistencias-modelagem.md)).
- `ExcluirClienteExtra.php`: `?cod=` no GET → `DELETE checkincliente` + `UPDATE agendamento SET FaltouNoDia=1`.

Domínios reais das colunas de exame [C]: `verde` (feito), `amarelo`, `branco`, `nao`, `NULL`.

## 3.4 `formulario` (anamnese, `precordis2016`) e forks

- `index.php`: paciente entra com `login` (= `usuario.senha`, 5 dígitos) **e** CPF **ou** e-mail; se encontrado, pré-preenche a ficha e busca o agendamento do dia em `agendaprec` por `LIKE '%nome%'` (`$permiteAlterar` só libera edição se houver agendamento hoje).
- `novoPaciente.php` → `gravaNovo.php`: cadastra paciente novo. `INSERT usuario` com `senha = password = md5(codigo)` (login e senha iguais ao "código"), `INSERT permissao` (pasta vazia), `INSERT info_usuario` com termos LGPD.
- `gravaInfo.php`: `INSERT`/`UPDATE info_usuario` (dados cadastrais + termos).
- `gravaSaude.php`: monta a anamnese; campos multi-valor (`cabeca[]`, `respiratorio[]`, `digestivo[]`...) viram string separada por `-`; `INSERT`/`UPDATE saude_usuario` (40+ colunas).
- **Regra SAMARCO**: `if (stripos($nomeEmpresa,'SAMARCO')!==false && trim($matricula)==='') die(...)` em `gravaNovo.php` e `gravaInfo.php` — matrícula obrigatória só para esse cliente, por comparação de string, em 2 lugares. [C]
- **Termos de uso (LGPD)**: `novoPaciente.php` tem radio "Aceito os termos" (default **Sim**) e um `<input hidden>` com o texto do termo; grava `termos_aceita`, `termos_aceita_data`, `termos_aceita_texto`. Em produção: 959 de 10.015 têm aceite (ver [07](07-inconsistencias-modelagem.md)). [C]
- `form_tab_emerg`: idem com modo "assistente" (recepção digita o código no lugar do paciente). `formulario_novo`: versão antiga (sem termos, sem andrológico, sem regra SAMARCO).

## 3.5 `precordistv` (`samarco`) — o módulo mais bem escrito

| Tela | Arquivo | Acesso |
|---|---|---|
| Login | `login.php` → `auth.php` | usuário `precordis` + hash fixo; `sleep(1)` anti-brute-force; `session_regenerate_id` |
| Recepção (chamar) | `index.php`, `rapido.php` | requer login |
| Roteiro do paciente | `gerencial.php` → `roteiro_api.php` | requer login |
| Admin de salas | `salas_admin.php` → `salas_api.php` | requer login |
| TV | `tv.php` + `status.php` + `pacientes_status.php` | **público** |
| Sala (celular do profissional) | `sala.php` + `sala_status_api.php` | **público** + PIN de 4 dígitos por sala |

Fluxo: recepção chama paciente para a sala → `chamar.php` grava `chamado_atual.json` (lido pela TV a cada 3 s) + `INSERT chamados_tv` + marca `status_salas` como ocupada. A sala do profissional confirma pela `sala_status_api.php` (ação `ocupada`/`livre`, PIN validado com `hash_equals`). Às 22h, o próximo GET em `sala_status_api.php` libera as salas ainda ocupadas. Pacientes do dia vêm de `espelho_atendimento` filtrado por `DataExame = CURDATE()`. [C]

Pontos bons: PDO com prepared statements, tipos de retorno, `password_verify`/`hash_equals`, `htmlspecialchars` na saída, `session_regenerate_id`. É a referência de qualidade para a reescrita.

## 3.6 `pesquisaSatisfacao` (`precordis2016`)

- `pesquisaSatisfacao.php`: NPS 0–10 (radio `NOTA`) → `pesquisaSatisfacaoFinal.php` → `INSERT pesquisa_NPS(NotaPesquisa)`.
- Em seguida, satisfação (4 escalas `pontualidade`/`ATENDIMENTO`/`AMBIENTE`/`TEMPO`, `responsavel`, `message`) → `pesquisaAgradecimentoEnvio.php` → `INSERT pesquisa_Satisfacao`, vinculando ao `max(idPesquisa)` **da tabela errada** (`pesquisa_NPS` em vez de `pesquisa_Satisfacao`) e passando o array inteiro do `fetch` como valor (bug — [07](07-inconsistencias-modelagem.md)). Anônimo (sem id de paciente). [C]

## 3.7 Regras/funções duplicadas (insumo para DRY)

| Lógica | Onde se repete |
|---|---|
| Conexão ao banco (host/user/senha inline) | ~45 arquivos |
| `santize()` (uppercase + tira acento) | `gravaagenda`, `editaDados`, `editaDadosApoio`, `gravaagendaDesistencia` (idêntica) |
| Perfil `== 0 / == -1` | ~15 arquivos da raiz, 3 variantes |
| `quebra_linha` por `PATH_SEPARATOR` + montagem de header de e-mail | `_lembra_senha`, `usuarios-cadastrar`, `usuarios-editar`, `contato`, `teste_lw/Mail` |
| Normalização de `pasta` Windows (`\\`→`/`, `utf8_encode`) | `manager`, `managerAcesso`, `usuarios-editar`, `manager_teste` |
| Máscara de telefone/data/CPF em JS | `form_agendamento`, `listar_agendados`, `checkin`, `calendario` |
| Formatação de data `d/m/Y` | praticamente todas as listagens |
| Listagem de agendados (quase igual) | 6 arquivos `listar_agendados*` |
