> [Confirmado] **DOCUMENTO HISTÓRICO — substituído como contrato em 03/11/2026 pelo [fluxo 13](../fluxos/13-plano-migracao-dados.md).** As propostas, nomes de campos/tabelas, estimativas, comandos e ações sobre produção abaixo não são instruções vigentes. A modelagem corrente está nos fluxos 12/22/23 e no schema; o legado permanece somente leitura. Preservado para rastrear a análise original, não para implementar destinos concorrentes.

# Precordis — Processo de Migração dos Bancos Legados

Oct 31, 2026 · @Luiz Guilherme Aurélio

## Resumo executivo

A migração consolida 3 bancos MySQL 5.7 sem chave comum de paciente (29 tabelas e 2 views) em um único schema multi-tenant, com a Precordis como primeiro tenant e a Samarco como empresa cliente dentro dele, não como tenant.

O que os dumps mostram:

- **Não existe identificador único de paciente entre os sistemas.** O admin usa `usuario.PKId`, a agenda usa nome digitado (`agendamento.NomeCliente`) e a Samarco usa nome + data (`espelho_atendimento.Nome_`). O CPF só existe em `info_usuario`. A resolução de identidade é o maior risco do projeto.
- **O catálogo de exames está codificado em colunas fixas**, com três nomenclaturas diferentes (`checkincliente`, `espelho_atendimento`, `saude_usuario`). Precisa virar uma tabela de exames antes de qualquer carga de atendimento.
- **Os resultados clínicos vêm de um sistema externo.** `espelho_atendimento.sincronizado_em`, `CodigoAuxPrincipal` e `log_sync` (cerca de 56 mil execuções) indicam uma sincronização recorrente. Esse sistema de origem precisa ser identificado, porque continua existindo depois da migração.
- **Os laudos não estão no banco.** `permissao.pasta/subpasta` sugere arquivos em diretórios por paciente/empresa. A migração de arquivos é uma frente própria.
- **Senhas e credenciais não devem ser migradas.** `usuario.senha` é exposta como `Registro_Senha` na view `Tablet_DadosFinais`, `entrada.senh` e `salas.pin` aparentam texto puro. Todos os acessos serão recriados com redefinição obrigatória.
- **Há dados de saúde sensíveis** (anamnese em `saude_usuario`, valores clínicos em `relatorio_prec`) sem criptografia, sem log de acesso por registro e em charset `latin1`.

**Recomendação:** migrar em 10 fases (0 a 9) com staging intermediário, tabela de rastreabilidade `legacy_references` e comandos Artisan idempotentes. Rodar pelo menos 2 ensaios completos antes da virada, com carga delta no dia do corte. O histórico transacional de mensagens e logs vai para arquivo frio, sem entrar no modelo novo.

Este documento usa somente a estrutura dos dumps. Itens marcados como **\[validar\]** dependem da leitura do código PHP ou do script de profiling que acompanha este documento.

## Inventário dos bancos legados

Os três bancos rodam em MySQL 5.7.32 na Locaweb (`*.mysql.dbaas.com.br`), todos InnoDB, com charset misto (`latin1`, `utf8`, `utf8mb4_bin`, `utf8mb4_unicode_ci`). Volumes estimados pelo `AUTO_INCREMENT` dos dumps (teto, não contagem real).

### precordis2016 — administrador, pacientes, resultados

| Tabela | Propósito (inferido) | Registros (teto) | Charset | Destino |
| --- | --- | --- | --- | --- |
| `usuario` | Login de equipe **e** de pacientes (flags `administrador`, `medico`, `em_id`) | 10.781 | utf8 | Migrar |
| `info_usuario` | Dados cadastrais do paciente (CPF, RG, nascimento, cargo, matrícula, gestor, aceite de termos) | 10.380 | latin1 | Migrar |
| `saude_usuario` | Anamnese / questionário de saúde (tablet) | 6.730 | latin1 | Migrar |
| `empresas` | Empresas clientes com hierarquia (`em_parent`) | 172 | latin1 | Migrar |
| `permissao` | Acesso de usuário a pasta/subpasta (resultados em arquivo) | 10.728 | utf8 | Transformar |
| `permissao_diretorioComposto` | Acesso a múltiplas pastas (gestores de empresa) | 215 | latin1 | Transformar |
| `mensagens` | Fila de mensagens para usuários (`ms_status` 0/10) | 28.179 | latin1 | Arquivar |
| `lembrete_resultado_exames` | Log de lembretes de resultado por WhatsApp | 13 | latin1 | Arquivar |
| `pesquisa_NPS` | Nota NPS anônima | 1.768 | latin1 | Migrar |
| `pesquisa_Satisfacao` | Notas por critério, ligada ao NPS por `idPesquisa` | 1.750 | latin1 | Migrar |
| `taxonomy_term_data` | Resquício de Drupal 7 (vocabulários/termos) **\[validar uso\]** | 111 | utf8 | Avaliar |
| `watchdog` | Log de eventos/acessos por usuário e IP | 113.846 | latin1 | Arquivar |
| view `Tablet_DadosFinais` | Cadastros do dia + anamnese, inclusive senha | — | — | Substituir |
| view `Tablet_Info_Saude` | Projeção de `saude_usuario` | — | — | Substituir |

### agendaprec — agendamento, check-in, comunicação

| Tabela | Propósito (inferido) | Registros (teto) | Charset | Destino |
| --- | --- | --- | --- | --- |
| `agendamento` | Agendamento por dia, com confirmação, falta, remarcação e disparos de WhatsApp | 5.932 | utf8mb4\_bin + utf8 | Migrar |
| `checkincliente` | Check-in e status por estação de exame (19 colunas) | 1.965 | latin1 | Migrar (pivotar) |
| `entrada` | Login próprio da agenda (`usuario`, `senh`, `perfil`) | 2 | latin1 | Recriar |
| `mensagens_envio_automatico` | Modelos de e-mail de convocação, com anexos e público por sexo | 12 | latin1 | Migrar |
| `envio_convocacao_exame` | Envios de convocação com leitura e liberação | 55 | utf8mb4 | Migrar |
| `configuracao_nao_confirmado` | Configuração única do aviso de não confirmação | 1 | utf8mb4 | Migrar como config |
| `lembra_procedimento` | Lotes de lembrete por e-mail (lista de e-mails em um campo) | 6.613 | latin1 | Arquivar |
| `mensagem_fim_de_ano` | Campanha de WhatsApp de fim de ano | 2.090 | latin1 | Descartar |
| `atendimentos` | Sessões do menu do bot de WhatsApp | 54 | latin1 | Descartar |

### samarco — operação do dia, salas, TV, relatório clínico

| Tabela | Propósito (inferido) | Registros (teto) | Charset | Destino |
| --- | --- | --- | --- | --- |
| `espelho_atendimento` | Cópia sincronizada do atendimento do dia: 18 exames × (previsto, status) + 2 etapas finais | 456.670 \* | utf8mb4 | Migrar (pivotar) |
| `roteiro_paciente` | Ordem das salas por paciente (FK para o espelho, `ON DELETE CASCADE`) | — | utf8mb4 | Migrar |
| `salas` | Salas/estações com PIN de 4 dígitos | 21 | utf8mb4 | Migrar |
| `status_salas` | Estado livre/ocupada em tempo real | — | utf8mb4 | Não migrar (transitório) |
| `chamados_tv` | Chamadas de paciente para a TV (`precordistv`) | 339 | utf8mb4 | Arquivar |
| `relatorio_prec` | Relatório clínico consolidado: pressão, lipídios, glicose, conclusões por especialidade | 253 | utf8mb4 | Migrar |
| `framingham` | Escore de risco de Framingham por matrícula | 87 | utf8mb4 | Migrar |
| `log_sync` | Log da sincronização com o sistema externo | 56.374 | utf8mb4 | Arquivar |

\* O `AUTO_INCREMENT` alto com chave única `(Nome_, DataExame)` indica sincronização por `REPLACE INTO` ou `INSERT … ON DUPLICATE KEY`, que consome IDs a cada execução. O volume real é bem menor **\[validar com COUNT\]**.

**Sobre o banco `samarco`:** o nome indica uma base dedicada à operação da Samarco (exames ocupacionais/preventivos de funcionários). Os conceitos (salas, roteiro, chamada na TV, relatório clínico) são genéricos e passam a servir todas as empresas no sistema novo. A Samarco vira um registro em `companies`.

## Diagrama dos bancos legados

&#91;embedded content: bancos legados · tabelas centrais e ligações\]

Dentro de cada banco há só duas FKs declaradas (`envio_convocacao_exame.texto_id` e `roteiro_paciente.paciente_id`); o resto depende do código. Entre bancos, as quatro ligações usam nome, texto ou ID sem integridade, o que torna a resolução de identidade (seção 8) obrigatória antes de qualquer carga clínica.

## Problemas críticos que impactam a migração

Sete problemas definem a ordem e o esforço da migração. Os dois primeiros bloqueiam todas as cargas de atendimento.

| # | Problema | Onde | Impacto na migração | Tratamento |
| --- | --- | --- | --- | --- |
| 1 | Paciente sem chave comum entre bancos | `usuario.PKId` × `agendamento.NomeCliente` × `espelho_atendimento.Nome_` × `relatorio_prec.Registro`/`MATRICULA` | Duplicidade ou fusão errada de pacientes, com dado clínico no prontuário errado | Índice mestre de pacientes com regras determinísticas e fila de revisão manual (seção 8) |
| 2 | Catálogo de exames em colunas fixas | 19 colunas em `checkincliente`, 18 pares em `espelho_atendimento`, blocos em `saude_usuario` e `relatorio_prec` | Impossível carregar atendimentos sem um de-para validado | Tabela `exam_types` + de-para aprovado pela clínica (seção 7) |
| 3 | Sistema clínico externo como fonte dos resultados | `espelho_atendimento.CodigoAuxPrincipal`, `sincronizado_em`, `log_sync`, colunas no padrão Access/Delphi de `relatorio_prec` | A migração não termina no corte: a sincronização precisa apontar para o sistema novo | Identificar o sistema e criar endpoint/job de integração antes da virada |
| 4 | Senhas inseguras | `usuario.senha`/`password` varchar(64), view `Tablet_DadosFinais` expõe `Registro_Senha`, `entrada.senh`, `salas.pin` | Migrar hashes fracos mantém o risco | Não migrar credenciais: convite com redefinição obrigatória |
| 5 | Datas e flags em texto livre | `agendamento.datanasc` varchar(10), `info_usuario.dt_nascimento` varchar(20), `saude_usuario.data` varchar(20), `DesistiuCancelou` varchar(3), `enviouMensagem` 'F'/'T', `temGinecologico` 'on'/'off', `clienteEspecial` varchar(2) | Conversão falha silenciosamente | Parsers com lista de formatos aceitos e relatório de rejeições |
| 6 | Charset misto e collation binária | `latin1` em 15 tabelas, `utf8mb4_bin` em `agendamento` (busca sensível a maiúsculas) | Acentos corrompidos (mojibake) e falhas no casamento de nomes | Conversão controlada para `utf8mb4_unicode_ci` no staging e detecção de dupla codificação |
| 7 | Laudos fora do banco | `permissao.pasta`/`subpasta` | Perda de documentos clínicos se a frente de arquivos for esquecida | Inventário do diretório, hash SHA-256 por arquivo, cópia para storage por tenant |

Outros problemas que não bloqueiam, mas entram nas transformações:

- `info_usuario` e `saude_usuario` têm PK composta `(id, id_usuario)` sem `UNIQUE` em `id_usuario`: um usuário pode ter várias linhas. Cada linha de `saude_usuario` vira uma resposta de questionário datada; em `info_usuario`, a linha mais recente vence e as anteriores vão para histórico **\[validar\]**.
- `info_usuario.empresa` (texto) convive com `usuario.em_id` (FK implícita para `empresas`). Os dois podem divergir.
- `agendamento` guarda o paciente em texto (`NomeCliente`, `NomeCliente2`, `Empresa`, `unidadeEmpresa`) sem vínculo com `usuario` nem com `empresas`.
- `mensagens_envio_automatico.empresa_id` aponta para `precordis2016.empresas` em outro banco, sem integridade.
- `roteiro_paciente` tem `ON DELETE CASCADE` para `espelho_atendimento`. Se a sincronização usar `REPLACE INTO`, o roteiro é apagado a cada execução **\[validar no código\]**.
- `espelho_atendimento` tem chave única por nome + data: dois homônimos no mesmo dia colidem.
- Valores clínicos em `double` (`relatorio_prec`) e `watchdog.wa_ip` varchar(16), que não comporta IPv6.
- `agendamento.whatsappPhoto` guarda foto de perfil em `longtext`. Não migrar: dado pessoal sem finalidade clínica.
- `empresas.em_nome` tem 3 índices idênticos e `usuario.nome` tem 4.

**Achado ao restaurar os dumps:** `precordis2016.sql` falha na restauração (erro 1146) porque cria a view `Tablet_DadosFinais` antes de `Tablet_Info_Saude`, da qual ela depende. As 12 tabelas carregam; só as views ficam de fora. O `legacy:stage` deve restaurar as tabelas e ignorar as views, que não são migradas. A estrutura dos três dumps foi carregada em MariaDB e o `profiling_legado.sql` rodou sem erro contra ela, inclusive em modo `ONLY_FULL_GROUP_BY`.

## Modelo de destino no escopo da migração

[Confirmado] Quanto ao motor e à versão, o destino passa a seguir a [matriz única de PHP/Laravel e MySQL ou MariaDB](../fluxos/14-arquitetura-laravel.md#141-stack--fonte-única-de-versões). A homologação de SQL e collations no motor escolhido permanece necessária.

[Confirmado] Esta seção descreve um banco único (`utf8mb4_unicode_ci`, InnoDB, FKs reais) com `tenant_id` em toda tabela de tenant. **Em 31/10/2026 o usuário adotou exatamente esse modelo** (banco único + `tenant_id` + global scopes) como a estratégia oficial — os fluxos 09/21 e o `schema.sql` foram atualizados para refletir isso, e a P03 do `PENDENCIAS.md` foi resolvida a favor deste desenho. No mapeamento deste documento, os dados legados entram no tenant **Precordis**; a Samarco e as outras 171 empresas viram `companies` desse tenant.

Regras gerais do schema novo:

- PK `bigint` interna + `public_id` ULID para URLs e API.
- `created_at`, `updated_at`, `deleted_at` (soft delete) e `created_by`/`updated_by` em entidades de negócio.
- Status como enum PHP *backed* gravado em `varchar` curto, com valores fechados.
- Valores clínicos em `DECIMAL` com unidade explícita; dinheiro em centavos (`bigint`).
- Dados sensíveis (anamnese, CPF, conclusões clínicas) com cast `encrypted` do Laravel e coluna `*_hash` (HMAC) para busca exata.
- Índices compostos iniciando por `tenant_id`.

### Entidades de destino e suas origens

| Contexto | Tabela nova | Origem legada |
| --- | --- | --- |
| Plataforma (central) | `tenants`, `tenant_domains`, `plans`, `subscriptions` | Nenhuma (criadas no onboarding da Precordis) |
| Plataforma | `units` (unidades da clínica) | Nenhuma: criar a unidade padrão **\[validar se há mais de uma unidade\]** |
| Cadastros | `companies` (com `parent_id`) | `empresas`; texto de `agendamento.Empresa`/`unidadeEmpresa` e `info_usuario.empresa` casado por nome |
| Cadastros | `patients` | `usuario` (não equipe) + `info_usuario`; pacientes só da agenda ou só da Samarco criados após a resolução de identidade |
| Cadastros | `patient_company_links` (matrícula, cargo, profissão, gestor, vigência) | `info_usuario.matricula`, `cargo`, `profissao`, `gestor`; `framingham.matricula`; `relatorio_prec.MATRICULA`, `ProfCargo` |
| Cadastros | `patient_contacts` (tipo, valor, principal, verificado WhatsApp) | `info_usuario.email/celular/fone_*`; `agendamento.Email/Telefone/isWhatsappNumber` |
| Cadastros | `consents` (LGPD: texto, versão, aceite, data, IP) | `info_usuario.termos_aceita*` |
| Identidade e acesso | `users`, `roles`, `permissions`, `model_has_roles` (escopo por tenant) | `usuario` (equipe e portal), `entrada`; flags `administrador`/`medico`; `entrada.perfil` |
| Identidade e acesso | `company_user_access` (usuário de empresa × empresas visíveis) | `permissao_diretorioComposto` **\[validar semântica\]** |
| Catálogo | `exam_types`, `exam_groups` | Colunas fixas dos três bancos; `taxonomy_term_data` se for o catálogo **\[validar\]** |
| Operação | `rooms`, `room_exam_types`, `devices` (TV/totem/tablet com token) | `salas`; `salas.pin` substituído por token de dispositivo |
| Agenda | `appointments`, `appointment_events` (confirmação, remarcação, cancelamento, falta) | `agendamento` |
| Atendimento | `visits` (atendimento do dia) | `checkincliente`, `espelho_atendimento` |
| Atendimento | `visit_steps` (exame/estação, ordem, status, início, fim) | Colunas de `checkincliente` e `espelho_atendimento` pivotadas; `roteiro_paciente` |
| Atendimento | `room_calls` | `chamados_tv` (opcional, só histórico) |
| Clínico | `questionnaire_templates`, `questionnaire_responses` (respostas em JSON criptografado, versão do modelo) | `saude_usuario` |
| Clínico | `checkup_reports` (conclusões por especialidade) e `clinical_observations` (código, valor, unidade) | `relatorio_prec` |
| Clínico | `risk_scores` (tipo, valor, data) | `framingham`, `relatorio_prec.Framingham`, `PontoCardio` |
| Clínico | `documents` (laudos e anexos, storage por tenant, hash) | Diretórios referenciados em `permissao` |
| Comunicação | `message_templates` | `mensagens_envio_automatico`, `configuracao_nao_confirmado` |
| Comunicação | `campaigns`, `campaign_recipients` | `envio_convocacao_exame` |
| Comunicação | `notifications_log` (canal, destino, status, data) | Campos `enviou*`/`notificou*` de `agendamento`; `mensagens`; `lembrete_resultado_exames` |
| Satisfação | `survey_responses`, `survey_answers` | `pesquisa_NPS` + `pesquisa_Satisfacao` |
| Auditoria | `audit_logs` | `watchdog` (somente arquivo; o novo log começa do zero) |
| Migração | `legacy_references`, `migration_runs`, `migration_issues`, `patient_match_candidates` | Criadas para a migração |

## Mapeamento legado → novo

Toda linha migrada grava em `legacy_references` a origem (`banco.tabela`, PK legada) e o ID novo. Toda linha rejeitada ou ajustada grava em `migration_issues` com o motivo.

### precordis2016.empresas → companies

| Coluna legada | Destino | Transformação |
| --- | --- | --- |
| `em_id` | `legacy_references` | PK legada para rastreio |
| `em_nome` | `companies.name` | `trim`, colapsar espaços; `normalized_name` (sem acento, maiúsculo) para casamento |
| `em_parent` | `companies.parent_id` | 0 = raiz; carregar em duas passadas (raízes, depois filhas); ciclos vão para `migration_issues` |
| `em_created` / `data_modificacao` | `created_at` / `updated_at` | Direto |
| — | `companies.cnpj` | Inexistente no legado: completar depois pela clínica |

### precordis2016.usuario → users / patients

| Coluna legada | Destino | Transformação |
| --- | --- | --- |
| `PKId` | `legacy_references` | Chave de ligação com `info_usuario`, `saude_usuario`, `permissao`, `mensagens`, `watchdog` |
| `administrador`, `medico` | Classificação | Qualquer flag ≠ 0 = equipe → `users` + role; ambas 0 = paciente → `patients` (+ `users` do portal, se houver acesso) **\[validar valores distintos\]** |
| `nome` | `patients.full_name` ou `users.name` | Correção de charset, `trim`, capitalização preservada |
| `login` | `patients.legacy_registration` | É o "Registro" (view `Tablet_DadosFinais`); chave de casamento com `framingham.registro` e `relatorio_prec.Registro` |
| `email` | `users.email` / `patient_contacts` | Minúsculo, validação RFC; duplicados entre usuários vão para revisão |
| `senha`, `password` | **Não migrar** | Usuário criado sem senha + convite de definição |
| `em_id` | `patient_company_links.company_id` | Via `legacy_references` de `empresas`; 0 = sem empresa |
| `lastact` | `users.last_seen_at` | Direto |
| `DataCadastro` / `data_modificacao` | `created_at` / `updated_at` | Direto |

### precordis2016.info\_usuario → patients, patient\_company\_links, patient\_contacts, consents

| Coluna legada | Destino | Transformação |
| --- | --- | --- |
| `id_usuario` | FK para o paciente | Se houver várias linhas, a de `data_modificacao` mais recente vence |
| `cpf` | `patients.cpf` (criptografado) + `cpf_hash` | Só dígitos, validação de DV; inválido → nulo + issue |
| `identidade` | `patients.rg` | `trim` |
| `dt_nascimento` | `patients.birth_date` | Formatos aceitos: `dd/mm/aaaa`, `aaaa-mm-dd`, `dd-mm-aaaa`; outro → nulo + issue |
| `sexo` | `patients.sex` | Normalizar para `F`/`M`/`I` **\[validar valores\]** |
| `estado_civil`, `naturalidade` | `patients.marital_status`, `birthplace` | Estado civil para enum; naturalidade texto |
| `endereco` | `patients.address_line` | Texto livre (não há CEP/número separados) |
| `email`, `celular`, `fone_residencial`, `fone_comercial` | `patient_contacts` | Telefone em E.164 (+55); um registro por contato não vazio |
| `empresa`, `matricula`, `cargo`, `profissao`, `gestor` | `patient_company_links` | `empresa` texto casado com `companies.normalized_name`; conflito com `usuario.em_id` → issue |
| `primeiro_exame`, `primeiro_exameBackup` | `patients.first_visit_flag` | Usar `primeiro_exame`; o backup só para auditoria |
| `verificado`, `Chave_interna` | `patients.legacy_verified`, `legacy_internal_key` | Manter até entender o uso **\[validar\]** |
| `termos_aceita`, `_data`, `_texto` | `consents` | Um registro com texto integral e data; sem data → consentimento não comprovado (issue) |

### precordis2016.saude\_usuario → questionnaire\_responses

| Coluna legada | Destino | Transformação |
| --- | --- | --- |
| `id_usuario` | `patient_id` | Via `legacy_references` |
| `data`, `data_cad_saude` | `answered_at` | `data` (texto) se parseável, senão `data_cad_saude` |
| 39 colunas de anamnese (`refeicao` … `remediodisfuncao`) | `answers` (JSON criptografado) | Cada coluna vira uma chave do modelo `anamnese_precordis_v1`; vazios omitidos |

O modelo `anamnese_precordis_v1` registra cada pergunta com chave, rótulo e tipo, para o formulário novo exibir o histórico sem colunas fixas.

### agendaprec.agendamento → appointments, appointment\_events, notifications\_log

| Coluna legada | Destino | Transformação |
| --- | --- | --- |
| `codigoAgenda` | `legacy_references` | Ligação com `checkincliente.codigoAgenda` |
| `NomeCliente`, `NomeCliente2`, `datanasc`, `sexo`, `Email`, `Telefone` | `patient_id` | Resolução de identidade (seção 8); sem casamento → paciente novo marcado `source=agenda` |
| `Empresa`, `unidadeEmpresa` | `company_id` | Casamento por nome normalizado; sem casamento → fila de revisão |
| `dataAgendamento` | `scheduled_date` | O legado não tem horário: `scheduled_time` fica nulo |
| `Status` | `status` | De-para a partir dos valores distintos **\[profiling\]** |
| `ConfirmaAgendamento`, `enviouMensagemPacienteConfirmou` | `confirmed_at` + evento `confirmed` | Texto/data para timestamp |
| `DesistiuCancelou` | `status=cancelled` + evento | 'Sim'/'S' = cancelado **\[validar valores\]** |
| `FaltouNoDia` | `status=no_show` + evento | Inteiro > 0 |
| `Remarcacoes` | `reschedule_count` | Direto; sem histórico de datas no legado |
| `clienteEspecial` | `priority_flag` | Atendimento prioritário **\[validar significado\]** |
| `primeiravez` | `first_visit` | Para boolean |
| `Especialidade`, `temGinecologico` | `appointment_items` (exames previstos) | Mapear para `exam_types`; 'on' = inclui ginecológico |
| `Observacoes` | `notes` | Direto |
| `enviouMensagem*`, `enviouOrientacaoEmail*`, `notificouLembrete`, `notificouNaoConfirmou*` | `notifications_log` | Um registro por envio com data; 'T'/1 = enviado |
| `isWhatsappNumber` | `patient_contacts.whatsapp_verified` | 'N' = falso |
| `whatsappPhoto` | **Não migrar** | Dado sem finalidade |
| `dataPreenchimento` | `created_at` | Direto |

### agendaprec.checkincliente e samarco.espelho\_atendimento → visits, visit\_steps

| Coluna legada | Destino | Transformação |
| --- | --- | --- |
| `checkincliente.codigoAgenda` | `visits.appointment_id` | Via `legacy_references`; órfão → visita sem agendamento + issue |
| `checkincliente.dataPreenchimento` | `visits.checked_in_at` | Direto |
| `checkincliente.NomeCli`, `clienteExtra` | Validação / `visits.walk_in` | Nome só para conferir com o agendamento; `clienteExtra` = encaixe **\[validar\]** |
| 19 colunas `Lab` … `Res` | `visit_steps` (uma linha por coluna preenchida) | Unpivot + de-para da seção 7; valor varchar(10) → status **\[profiling\]** |
| `espelho_atendimento.Nome_`, `DataExame` | `visits.patient_id`, `visit_date` | Resolução de identidade; empresa = Samarco |
| `espelho_atendimento.CodigoAuxPrincipal` | `visits.external_ref` | Código do sistema clínico externo: chave da integração futura |
| 18 pares `<Exame>` / `<Exame>_Status` | `visit_steps` | Exame = 1 → cria o passo; `_Status` = 1 → `done` **\[validar\]** |
| `OrientacaoFisica_EtapaFinal`, `EntregaFinal` | `visit_steps` (etapas finais) | Etapas do tipo `step` (não exame) |
| `roteiro_paciente.ordem`, `sala_key`, `concluido_em` | `visit_steps.sequence`, `room_id`, `finished_at` | Casar pelo `paciente_id` → espelho → visita |

### samarco.relatorio\_prec → checkup\_reports, clinical\_observations, risk\_scores

| Grupo de colunas | Destino | Transformação |
| --- | --- | --- |
| `Registro`, `MATRICULA`, `Nome`, `Sexo`, `Idade`, `ProfCargo`, `DataExame`, `TipoExame`, `Origem`, `CodigoExamePreventivo` | `checkup_reports` (cabeçalho) | Paciente por `Registro` → `patients.legacy_registration`, depois matrícula; `Idade` descartada (calculável) |
| Medidas numéricas: `P_Art_Sist_Rep`, `P_Art_Diast_Rep`, `Triglicerides`, `HDL_Col`, `LDL_Col`, `Colesterol`, `Glicose`, `Acido_Urico`, `Gama_GT`, `PSA`, `PCR`, `TSH`, `Peso1`, `Altura1`, `AbdomMed1`, `QuadrMed1`, `Fadiga`, `S1`–`S6` | `clinical_observations` | Uma linha por valor: código interno, `DECIMAL`, unidade (mmHg, mg/dL, kg, cm…) **\[validar unidades com a clínica\]** |
| Classificações: `gradeDiabetes`, `GradeHipertenso`, `gradeStress`, `gradeFadiga`, `gradePulm`, `gradeUSom`, `ClassificaPeso`, `Faixa_Etaria`, `HabVidaFisico`, `Fumante`, `Sangue_Oculto` | `clinical_observations` (valor texto) | Texto normalizado |
| Conclusões: `Conclusao`, `ConclusaoCln`, `Concl_Pulm`, `ConclusaoUltrs`, `ConclusaoEco1`, `ConclusaoProcto`, `ConclusaoPostural`, `CC_T_Erg`, `L_ECG1`, `ResultadoAudiom`, `HipoteseDiagnost`, `SugestMetabolica1`, `RelatMed_FatRisco`, `HFamiliarPositivo`, `Alimentacao`, `RelatPeso1`, `PontoCritico1`–3 | `checkup_report_sections` (especialidade, texto criptografado) | Uma seção por coluna não vazia |
| `Framingham`, `PontoCardio`, `Equivale` + `samarco.framingham` | `risk_scores` | Tipo `framingham`; conflito entre as duas fontes → issue |

### Demais tabelas

| Origem | Destino | Regra |
| --- | --- | --- |
| `permissao` | `documents` + política de acesso | Cada `pasta/subpasta` resolve o dono do documento; o acesso passa a ser derivado do paciente/empresa, não de linhas de permissão |
| `permissao_diretorioComposto` | `company_user_access` | Usuário × empresas visíveis **\[validar\]** |
| `entrada` | `users` + role | Recriar os 2 acessos manualmente; `perfil` → role |
| `pesquisa_NPS` + `pesquisa_Satisfacao` | `survey_responses` + `survey_answers` | União por `idPesquisa`; `cliente` (texto) tenta casar com paciente, senão fica anônimo |
| `mensagens_envio_automatico` | `message_templates` | `destino` → público; `empresa_id` via `legacy_references`; anexos copiados para storage |
| `envio_convocacao_exame` | `campaigns` + `campaign_recipients` | Agrupar por `texto_id`; `enviou`/`leu`/`liberado` com datas |
| `configuracao_nao_confirmado` | `tenant_settings` | Chave `appointment.unconfirmed_notice` |
| `salas` | `rooms` | `sala_key` → `code`; `pin` descartado |
| `mensagens`, `lembrete_resultado_exames`, `lembra_procedimento`, `watchdog`, `chamados_tv`, `log_sync` | Arquivo frio | Exportar para Parquet/CSV criptografado em storage, retenção definida pelo DPO |
| `mensagem_fim_de_ano`, `atendimentos`, `status_salas` | Descartar | Sem valor clínico ou histórico |

## Catálogo de exames unificado

O de-para abaixo une as três nomenclaturas em 24 itens (21 exames e 3 etapas de fluxo) e precisa da aprovação da coordenação médica antes da fase 5. A coluna Confiança indica o quanto o casamento depende de inferência.

| Código novo | Nome proposto | Tipo | `checkincliente` | `espelho_atendimento` | Evidência em `relatorio_prec` / outros | Confiança |
| --- | --- | --- | --- | --- | --- | --- |
| `LAB` | Exames laboratoriais | exame | `Lab` | `Laboratorial` | `Glicose`, `HDL_Col`, `LDL_Col`, `TSH`, `PSA`, `PCR` | Alta |
| `CAFE` | Café após coleta em jejum | etapa | `Caf` | — | — | Média |
| `AUD` | Audiometria | exame | `Aud` | `Audiometria` | `ResultadoAudiom` | Alta |
| `CLI` | Consulta clínica | exame | `Cli` | — | `ConclusaoCln`, `HipoteseDiagnost` | Média |
| `ERG` | Teste ergométrico | exame | `Erg` | `Cardio` (provável) | `CC_T_Erg`, `PontoCardio` | Média |
| `ECG` | Eletrocardiograma | exame | — | `Eletro` | `L_ECG1` | Alta |
| `ECO` | Ecodoppler / ecocardiograma | exame | `Eco` | `Ecodopler` | `ConclusaoEco1` | Alta |
| `DER` | Dermatologia | exame | `Der` | `Dermatologico` | Anamnese `dermatologia` | Alta |
| `NUT` | Nutrição / avaliação metabólica | exame | `Nut` | `Metabolismo` | `SugestMetabolica1`, `Alimentacao`, `ClassificaPeso` | Média |
| `ODO` | Odontologia | exame | `Odo` | `Odonto` | — | Alta |
| `OFT` | Oftalmologia | exame | `Oft` | `Oftalmo` | — | Alta |
| `POS` | Avaliação postural | exame | `Pos` | `Postural` | `ConclusaoPostural` | Alta |
| `PUL` | Função pulmonar (espirometria) | exame | `Pul` | `Pulmonar` | `gradePulm`, `Concl_Pulm` | Alta |
| `RXT` | Raio-X de tórax | exame | `RxT` | `Radiologia` | — | Média |
| `STR` | Avaliação de estresse e fadiga | exame | `Str` | `Stress` | `gradeStress`, `gradeFadiga`, `Fadiga`, `S1`–`S6` | Média |
| `TER` | Termografia | exame | `Ter` | `Termografia` | — | Alta |
| `ULT` | Ultrassonografia | exame | `Ult` | `Ultrasom` | `gradeUSom`, `ConclusaoUltrs` | Alta |
| `URO` | Urologia | exame | — | `Urologico` | `PSA`; anamnese `urologico` | Alta |
| `PSI` | Psicologia | exame | — | `Psicologia` | — | Alta |
| `PRO` | Proctologia | exame | — | — | `ConclusaoProcto`, `Sangue_Oculto` | Média |
| `GIN` | Ginecologia | exame | — | — | `agendamento.temGinecologico` | Média |
| `RIS` | Avaliação de fatores de risco | exame | `Res` (?) | `risco` | `RelatMed_FatRisco`, `Framingham` | Baixa |
| `EDF` | Orientação de educação física | etapa | `EdF` | `OrientacaoFisica_EtapaFinal` | `HabVidaFisico` | Média |
| `ENT` | Orientação médica e entrega final | etapa | `OM` (?) | `EntregaFinal` | `Conclusao`, `PontoCritico1`–3 | Baixa |

Campos da tabela `exam_types`: `code`, `name`, `kind` (`exam`/`step`), `specialty`, `default_room_id`, `estimated_minutes`, `requires_fasting`, `sex_restriction` (`F`/`M`/nulo, como em `mensagens_envio_automatico.destino`), `sort_order`, `active`. Uma tabela `exam_type_legacy_codes` guarda cada código antigo (`checkincliente.Aud`, `espelho_atendimento.Audiometria`) para os importadores e para a sincronização com o sistema externo.

Se `taxonomy_term_data` for o catálogo real do admin, os `tid` entram em `exam_type_legacy_codes` como terceira origem.

## Resolução de identidade de pacientes

O cadastro do admin (`usuario` + `info_usuario`) é a base mestre, porque é a única fonte com CPF. As outras fontes são casadas contra ele por regras em ordem de confiança; o que não casa com segurança vai para revisão humana, nunca para fusão automática.

### Fontes e chaves disponíveis

| Fonte | Nome | CPF | Nascimento | Registro / matrícula | Contato | Empresa |
| --- | --- | --- | --- | --- | --- | --- |
| `usuario` + `info_usuario` | Sim | Sim | Sim (texto) | `login`, `matricula` | E-mail, celular | `em_id` e texto |
| `agendamento` | Sim (2 campos) | Não | Sim (texto) | Não | E-mail, telefone | Texto |
| `espelho_atendimento` | Sim | Não | Não | `CodigoAuxPrincipal` (externo) | Não | Samarco (implícito) |
| `relatorio_prec` | Sim | Não | Idade apenas | `Registro`, `MATRICULA` | Não | Samarco (implícito) |
| `framingham` | Sim | Não | Não | `registro`, `matricula` | Não | Samarco (implícito) |
| `envio_convocacao_exame` | Sim | Não | Não | Não | E-mail | Via modelo |
| `pesquisa_Satisfacao.cliente` | Sim | Não | Não | Não | Não | Não |

### Normalização antes de comparar

- **Nome:** corrigir charset, remover acentos, maiúsculas, retirar partículas (DE, DA, DO, DOS, DAS, E), colapsar espaços. Guardar `name_key` e `first_last_key` (primeiro + último nome).
- **CPF:** só dígitos, 11 posições, dígitos verificadores válidos, rejeitar sequências repetidas.
- **Nascimento:** convertido para `DATE`; datas futuras ou anteriores a 1900 viram nulo.
- **Telefone:** E.164 com DDD; **e-mail:** minúsculo e sem espaços.

### Regras de casamento (aplicadas em ordem, a primeira que casa vence)

| Regra | Critério | Decisão |
| --- | --- | --- |
| R1 | CPF válido igual | Automático (deduplica a própria base mestre) |
| R2 | `usuario.login` = `relatorio_prec.Registro` ou `framingham.registro` | Automático |
| R3 | Matrícula igual na mesma empresa, única na empresa | Automático |
| R4 | `name_key` igual + nascimento igual | Automático |
| R5 | E-mail igual + similaridade de nome Jaro-Winkler ≥ 0,92 | Automático |
| R6 | `first_last_key` igual + nascimento igual | Revisão |
| R7 | Telefone igual + primeiro nome igual | Revisão |
| R8 | `name_key` igual + mesma empresa, sem nascimento | Revisão |
| R9 | Jaro-Winkler ≥ 0,92 + mesma empresa | Revisão |
| — | Nenhuma regra | Paciente novo, `identity_quality = low`, origem registrada |

Para `espelho_atendimento`, o casamento usa R8/R9 restritos à Samarco e confirma pelo `relatorio_prec` do mesmo dia quando existir. Homônimos na mesma empresa sempre vão para revisão.

### Fila de revisão

- Tabela `patient_match_candidates`: fonte, ID legado, paciente candidato, regra, escore, status (`pending`, `accepted`, `rejected`), revisor e data.
- Tela no back-office (Filament) com os dois registros lado a lado e ações aceitar, rejeitar (cria paciente novo) ou escolher outro paciente.
- As decisões ficam gravadas e são reaplicadas em toda nova execução. Assim os ensaios e a carga final não pedem revisão duas vezes.
- Meta de aceite antes da virada: 100% dos candidatos com dado clínico revisados; candidatos só de comunicação (convocação, pesquisa) podem ficar anônimos.

O relatório de cada execução mostra, por fonte, o percentual casado automaticamente, em revisão e criado como novo. Uma queda brusca entre ensaios indica erro de normalização.

## Processo de migração em fases

A migração tem 10 fases (0 a 9), cada uma com critério de saída. Nenhuma fase começa sem o critério da anterior cumprido. As fases 3 a 7 são automatizadas e repetidas a cada ensaio.

| Fase | O que é feito | Entregável | Critério de saída |
| --- | --- | --- | --- |
| 0. Preparação e governança | Base legal LGPD com o DPO, ambiente isolado, cópia completa dos 3 bancos com dados, acesso ao código e ao diretório de laudos, congelamento de mudanças de schema no legado | Ambiente de migração e registro de tratamento | Cópias restauradas e acesso restrito a nomeados |
| 1. Descoberta no código | Ler o PHP para fechar as lacunas: sistema externo e mecanismo da sincronização, uso de `taxonomy_term_data`, algoritmo de senha, local dos laudos, semântica de `permissao_diretorioComposto`, `clienteEspecial`, `verificado` | Respostas às perguntas em aberto | Todas as perguntas críticas respondidas |
| 2. Profiling | Rodar `profiling_legado.sql`: contagens reais, valores distintos de status, formatos de data, CPFs inválidos, duplicidades, órfãos, mojibake | Relatório de qualidade + de-para de status preenchido | De-paras de status aprovados pela operação |
| 3. Staging | [Confirmado] Restaurar como `legacy_precordis`, `legacy_agenda`, `legacy_samarco` em MySQL/MariaDB local/staging conforme a matriz do fluxo 14; manter as fontes restauradas somente leitura e gerar tabelas `stg_*` separadas com campos normalizados (nome, CPF, datas, telefones) e conversão de charset | Schemas de staging | Contagem e checksum por tabela iguais à origem; amostra sem mojibake |
| 4. Dados de referência | Criar tenant Precordis, unidade, `companies`, `exam_types` + códigos legados, `rooms`, roles e permissões, modelos de mensagem, modelo `anamnese_precordis_v1` | Cadastros base | Catálogo de exames aprovado pela coordenação médica |
| 5. Pacientes e identidade | Carregar a base mestre, rodar as regras R1–R9 para as demais fontes, abrir a fila de revisão | `patients`, contatos, vínculos, consentimentos, candidatos | 100% dos candidatos com dado clínico revisados |
| 6. Dados transacionais | Na ordem: agendamentos e eventos → visitas e passos → anamneses → relatórios clínicos e observações → escores de risco → pesquisas → campanhas → log de notificações → documentos (arquivos) | Histórico completo no modelo novo | Reconciliação da seção 11 sem divergência crítica |
| 7. Usuários e acessos | Criar usuários de equipe com roles, usuários de empresa com escopo de empresas, portal do paciente sem senha; arquivar tabelas sem destino | Contas e convites prontos (não enviados) | Teste de acesso por perfil aprovado |
| 8. Ensaios e homologação | No mínimo 2 ensaios completos do zero com cópia recente; homologação pelos usuários-chave (recepção, médico, financeiro, gestor de empresa) | Relatório de ensaio com tempos e issues | Ensaio final sem issue crítica e dentro da janela de corte |
| 9. Virada e pós-virada | Congelamento do legado, carga delta, validação, troca de DNS, envio dos convites, legado somente leitura por 90 dias, desativação | Sistema novo em produção | Critérios de go/no-go da seção 12 |

### Ordem de carga e dependências

1. `tenants` → `units` → `companies` (raízes, depois filhas)
2. `exam_types` + `exam_type_legacy_codes` → `rooms` + `room_exam_types`
3. `roles` / `permissions` → `message_templates` → `questionnaire_templates`
4. `patients` → `patient_contacts` → `patient_company_links` → `consents`
5. `appointments` → `appointment_items` → `appointment_events`
6. `visits` → `visit_steps` (depende de agendamentos, pacientes, exames e salas)
7. `questionnaire_responses` → `checkup_reports` → `checkup_report_sections` → `clinical_observations` → `risk_scores`
8. `survey_responses` → `campaigns` → `campaign_recipients` → `notifications_log`
9. `documents` (cópia de arquivos em paralelo, com verificação por hash)
10. `users` + roles + `company_user_access`

### Carga delta no dia da virada

O legado tem marcações de alteração diferentes por tabela, então o delta usa três estratégias:

| Estratégia | Tabelas | Como |
| --- | --- | --- |
| Marca de alteração | `usuario`, `info_usuario`, `saude_usuario`, `empresas`, `espelho_atendimento` | `data_modificacao` / `sincronizado_em` maior que o início do último ensaio |
| PK crescente | `checkincliente`, `pesquisa_*`, `envio_convocacao_exame`, `relatorio_prec`, `framingham`, `roteiro_paciente` | PK maior que a última registrada em `migration_runs` |
| Hash de linha | `agendamento` (sem coluna de alteração; `Status` e confirmações mudam no lugar) | SHA-256 da linha comparado com o hash gravado em `legacy_references` |

Como os importadores são idempotentes, o delta é a mesma execução com filtro: registros já migrados são atualizados, não duplicados.

## Implementação em Laravel

[Confirmado] A migração está prevista como módulo do próprio projeto PHP/Laravel, nas versões da [matriz única](../fluxos/14-arquitetura-laravel.md#141-stack--fonte-única-de-versões), com um importador por entidade atrás de um contrato comum. Cada importador deverá ler do staging, transformar com Value Objects e gravar pelo mesmo Action da aplicação, para evitar duplicação de regras. Isso descreve o desenho proposto, não um importador já implementado; não executar cargas ou DDL/DML em produção neste trabalho.

### Estrutura

```text
app/Domain/Migration/
├── Contracts/
│   ├── LegacyImporter.php            # source(), dependsOn(), import(Chunk): ImportResult
│   └── IdentityResolver.php          # resolve(LegacyPerson): MatchDecision
├── Importers/
│   ├── CompanyImporter.php           # legacy_precordis.empresas
│   ├── PatientImporter.php           # usuario + info_usuario
│   ├── AppointmentImporter.php       # legacy_agenda.agendamento
│   ├── VisitImporter.php             # checkincliente + espelho_atendimento + roteiro_paciente
│   ├── QuestionnaireImporter.php     # saude_usuario
│   ├── CheckupReportImporter.php     # relatorio_prec + framingham
│   ├── SurveyImporter.php            # pesquisa_NPS + pesquisa_Satisfacao
│   ├── CampaignImporter.php          # mensagens_envio_automatico + envio_convocacao_exame
│   └── DocumentImporter.php          # diretórios de laudos (permissao)
├── Transformers/                     # LegacyDateParser, LegacyBooleanParser ('F'/'T', 'on'/'off', 'Sim')
├── Identity/                         # NameNormalizer, MatchRules (R1–R9), RuleBasedIdentityResolver
├── Mappings/                         # exam_codes.php, appointment_status.php, checkin_status.php
├── Models/                           # LegacyReference, MigrationRun, MigrationIssue, PatientMatchCandidate
└── Reports/                          # ReconciliationReport
app/Domain/Shared/ValueObjects/       # Cpf, Email, PhoneNumber, PersonName, BirthDate (reusados pelo sistema)
config/database.php                   # conexões legacy_precordis, legacy_agenda, legacy_samarco (read-only)
```

### Comandos Artisan

| Comando | Função |
| --- | --- |
| `legacy:stage --fresh` | Restaura os dumps no staging, converte charset e gera `stg_*` |
| `legacy:profile` | Executa o profiling e grava o relatório de qualidade |
| `legacy:import {importer?} --tenant=precordis --chunk=1000 --dry-run` | Roda um importador ou todos, na ordem de `dependsOn()` |
| `legacy:import --delta --since=<run_id>` | Carga delta da virada |
| `legacy:match --source=agenda` | Reexecuta a resolução de identidade de uma fonte |
| `legacy:files --verify` | Copia laudos para o storage do tenant e confere SHA-256 |
| `legacy:reconcile` | Gera o relatório de reconciliação (seção 11) |
| `legacy:rollback {run_id}` | Remove o que um run criou, usando `legacy_references` |

### Regras de implementação

- **Idempotência:** `legacy_references` tem chave única `(tenant_id, source, legacy_table, legacy_id)` e guarda `entity_type`, `entity_id`, `row_hash`, `run_id`. Linha já migrada com mesmo hash é ignorada; com hash diferente é atualizada.
- **Transações por lote** de 1.000 linhas; falha em uma linha vai para `migration_issues` e não derruba o lote.
- **Execução em fila** (Horizon) por importador, com progresso em `migration_runs`.
- **Sem eventos de domínio na carga:** os Actions recebem um contexto `ImportContext` que desliga notificações, e-mails e webhooks, para nenhum paciente receber mensagem durante os ensaios.
- **Auditoria:** registros migrados recebem `created_by = system:migration` e `migrated_at`.
- **Testes Pest** por importador com fixtures sintéticas que reproduzem os casos ruins do legado (data inválida, CPF inválido, homônimo, órfão, charset duplo).
- **SOLID na prática:** um importador por fonte (S), novo importador sem alterar o orquestrador (O), qualquer `IdentityResolver` substitui o baseado em regras (L), contratos pequenos (I), tudo injetado pelo container (D).

## Validação e reconciliação

Cada execução fecha com o relatório de `legacy:reconcile` em quatro níveis. A virada só acontece com os níveis 1 a 3 zerados de divergência crítica.

| Nível | Verificação | Regra de aceite |
| --- | --- | --- |
| 1. Contagem | Linhas na origem = migradas + rejeitadas (com motivo) + descartadas por regra, por tabela | Diferença zero; nenhuma linha sem destino explicado |
| 2. Pivot e somatórios | Passos de exame em `visit_steps` = soma de colunas preenchidas em `checkincliente` e de pares `= 1` em `espelho_atendimento`; agendamentos por status e por mês iguais à origem; média de cada medida de `relatorio_prec` igual em `clinical_observations` | Diferença zero |
| 3. Integridade | Nenhuma visita sem paciente; nenhum documento sem dono; todo arquivo com SHA-256 igual ao original; nenhum registro clínico ligado a paciente com candidato pendente | Zero ocorrências |
| 4. Amostragem humana | 50 pacientes sorteados por estrato (só admin, admin + agenda, Samarco, com relatório clínico) conferidos tela a tela contra o legado pela recepção e por um médico | 0 erro clínico; até 2% de erro cadastral corrigível |

Indicadores acompanhados entre ensaios: tempo total de carga, linhas por segundo por importador, percentual de casamento automático por fonte e quantidade de issues por tipo. Variação acima de 5% sem mudança de código deve ser investigada antes de prosseguir.

## Virada e rollback

A virada acontece em um fim de semana sem atendimento, com o legado congelado por cerca de 6 horas. O rollback é simples porque o legado não é alterado: basta reabrir o acesso a ele.

### Roteiro

| Quando | Ação | Responsável |
| --- | --- | --- |
| D-14 | Ensaio final completo com cópia do dia; congelar código dos importadores | Dev |
| D-7 | Treinamento da recepção, médicos e gestores de empresa; aviso às empresas clientes sobre o novo portal | Operação |
| D-2 | Configurar TV, totem e tablets com token de dispositivo no ambiente novo; testar o endpoint da sincronização externa em homologação | Dev + TI da clínica |
| D0, 08h | Página de manutenção em todos os subsistemas legados; desligar crons legados (WhatsApp, e-mail, lembretes, sincronização) | Dev |
| D0, 08h30 | Dump final dos 3 bancos e cópia do diretório de laudos (rsync com checksum) | Dev |
| D0, 09h | `legacy:stage` + `legacy:import --delta` + `legacy:files --verify` | Dev |
| D0, 12h | `legacy:reconcile` e amostragem rápida (10 pacientes) | Dev + usuário-chave |
| D0, 13h | **Go/no-go** | Diretoria + coordenação médica |
| D0, 14h | DNS para o sistema novo; apontar a sincronização externa; ligar filas e agendador | Dev |
| D0, 15h | Enviar convites de acesso para a equipe; portal do paciente e das empresas liberado em ondas | Operação |
| D+1 a D+30 | Acompanhamento diário de erros, fila de revisão residual e chamados | Dev + Operação |
| D+90 | Desativar o legado; manter dumps criptografados pelo prazo de guarda | TI + DPO |

### Critérios de go/no-go

- [ ] Reconciliação níveis 1 a 3 sem divergência crítica
- [ ] Amostra de 10 pacientes conferida sem erro clínico
- [ ] Todos os laudos copiados com hash conferido
- [ ] Login de cada perfil testado (admin, recepção, médico, gestor de empresa, paciente)
- [ ] Sincronização externa recebendo dados no ambiente novo
- [ ] Crons legados confirmados desligados (evita mensagens duplicadas a pacientes)

### Gatilhos de rollback e como executar

Volta-se ao legado se, até D0 18h, houver: erro clínico na amostra, perda de laudo, falha de login generalizada ou sincronização externa sem funcionar.

1. DNS de volta para o legado e remoção da página de manutenção.
2. Religar crons legados e reapontar a sincronização.
3. Não enviar convites; o tenant novo fica congelado para análise.
4. Corrigir, refazer um ensaio e remarcar a virada.

Depois de D0 18h, com dados novos gravados no sistema novo, não há volta ao legado: problemas são corrigidos para frente.

## Riscos e perguntas em aberto

### Riscos

| Risco | Probabilidade | Impacto | Mitigação |
| --- | --- | --- | --- |
| Fusão errada de pacientes (dado clínico no prontuário de outra pessoa) | Média | Crítico | Regras automáticas só com chave forte; revisão obrigatória para dado clínico; amostragem por médico |
| Sistema externo de resultados sem integração pronta na virada | Média | Alto | Identificar na fase 1; construir endpoint antes do ensaio final; critério de go/no-go |
| Laudos não localizados ou corrompidos | Baixa | Crítico | Inventário na fase 1, hash por arquivo, relatório de órfãos |
| De-para de exames ou status errado | Média | Alto | Aprovação formal pela coordenação médica e pela operação; testes com dados reais nos ensaios |
| Mensagens disparadas a pacientes durante ensaios ou em duplicidade na virada | Média | Médio | `ImportContext` sem eventos; crons legados desligados no D0 |
| Exposição de dados de saúde em ambiente de migração | Baixa | Crítico | Ambiente isolado, acesso nominal, dados anonimizados para dev, descarte das cópias ao fim |
| Acentos corrompidos em nomes | Alta | Médio | Detecção de mojibake no staging e correção antes da identidade |
| Resistência dos usuários ao novo fluxo | Média | Médio | Treinamento no D-7, usuários-chave na homologação, suporte reforçado no D+1 a D+30 |

### Perguntas em aberto

Críticas (bloqueiam a fase 4 ou 5):

- [ ] Qual é o sistema clínico externo que alimenta `espelho_atendimento` e `relatorio_prec`, e como a sincronização roda (script, cron, serviço local)?
- [ ] Onde ficam os laudos (servidor, pasta, padrão de nomes) e como `permissao.pasta/subpasta` aponta para eles?
- [ ] `taxonomy_term_data` é usado pelo admin? Para quê (catálogo de exames, categorias de pasta)?
- [ ] Quais valores `administrador` e `medico` assumem em `usuario`, e quais perfis existem em `entrada.perfil`?
- [ ] O de-para da seção 7 está correto, em especial `Cli`, `Erg`/`Cardio`, `Nut`, `Res`/`risco`, `OM`/`EntregaFinal`?
- [ ] Unidades de medida de cada campo numérico de `relatorio_prec` e significado de `S1`–`S6` e `Equivale`.

Importantes (resolver antes dos ensaios):

- [ ] Quais valores aparecem em `agendamento.Status`, `ConfirmaAgendamento`, `DesistiuCancelou` e nas colunas de `checkincliente`? (o profiling responde)
- [ ] O que significam `clienteEspecial`, `clienteExtra`, `info_usuario.verificado` e `Chave_interna`?
- [ ] `permissao_diretorioComposto` dá a gestores de empresa acesso a várias pastas? Quem são esses usuários?
- [ ] A Precordis tem mais de uma unidade física?
- [ ] Existem outros bancos por empresa cliente além de `samarco`?
- [ ] Qual a política de retenção para mensagens, logs de acesso e campanhas arquivadas (definição do DPO)?
