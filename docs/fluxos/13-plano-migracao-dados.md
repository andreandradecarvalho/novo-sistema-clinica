# 13 — Plano de migração de dados

> A Precordis é o **primeiro tenant**. A migração não é "3 bancos MySQL → 1" — é **Access + 3 bancos MySQL → 1 banco de tenant**, com o resultado clínico vindo do Access/`relatorio_prec`.

## 13.1 Pré-requisitos (antes de qualquer script)

1. **Localizar e caracterizar o Access** (perguntas-em-aberto #2): arquivo, tamanho, chave real do paciente, histórico anterior a jun/2026 (o `samarco` só tem espelho desde então).
2. **Congelar credenciais novas** e rotacionar as antigas ([05](../analise/05-seguranca-lgpd-conformidade.md) C9).
3. **Snapshot** dos 3 bancos + cópia do Access.
4. **Decidir corte** (perguntas-em-aberto #21).

## 13.2 Estratégia geral

- Comandos **Artisan idempotentes** (`php artisan legacy:import {etapa}`), re-executáveis, com **relatório de inconsistências** por etapa e tabela de-para persistida (`legacy_map`: `source_db`, `source_table`, `source_id`, `target_type`, `target_id`, `match_method`, `confidence`).
- Ordem: empresas → pacientes (dedupe) → anamnese → agendamentos → check-in → resultados/laudos → pesquisa → arquivos.
- **Parser de data e encoding por linha** (não assumir formato único).

## 13.3 Mapeamento tabela-a-tabela (resumo)

| Origem | Destino | Transformação |
|---|---|---|
| `empresas` | `companies` (+`company_units`) | normalizar nome (TRIM/UPPER para dedupe), reconstruir hierarquia `em_parent`, resolver 1 órfão |
| `usuario`+`info_usuario` (paciente) | `patients` | **dedupe** (§13.4); CPF validado; `dt_nascimento` → `DATE` (detecção de formato); sexo normalizado; termos → `anamneses.consent` |
| `usuario` (medico/admin) | `users`+`roles` | mapear flags -1/0 → papéis ([11](11-identidade-autenticacao-acl.md) §11.3); **forçar redefinição de senha** |
| `permissao`/`permissao_diretorioComposto` | vínculo `patient`↔`company` / acesso | caminho Windows → `company` |
| `saude_usuario` | `anamneses`+`anamnesis_answers` | explodir strings `-` em respostas; descartar colunas vazias |
| `agendamento` | `appointments` | **resolver `patient_id`** (§13.4); `status` derivado (§13.5); `Empresa` texto → `company_id` (fuzzy, 43 valores órfãos p/ revisão); `datanasc`/`Especialidade` normalizados; **não migrar** `Status`/`Remarcacoes` |
| colunas WhatsApp do `agendamento` | `notifications` (histórico) | — |
| `checkincliente` (19 colunas) | `checkins`+`checkin_exam_status` (linhas) | só histórico (subsistema inativo); `clienteExtra` → flag |
| `samarco.relatorio_prec` | `exam_results`+`exam_result_items` | 63 colunas → indicadores tipados (`indicators` json) + conclusões; vínculo por nome↔`patient` |
| `samarco.framingham` | `exam_results.framingham` | corrigir negativos (revisão) |
| `samarco.espelho_atendimento` | não migra como tabela | é espelho; serve para cruzar nome↔data |
| `samarco.chamados_tv` | `call_events` (histórico) | — |
| `salas` | `rooms` | `pin` texto → `pin_hash` |
| `pesquisa_NPS`+`pesquisa_Satisfacao` | `surveys` | reconstruir vínculo (ou manter anônimo onde impossível — B5) |
| `mensagens` | `notifications` (histórico) | não migrar como fila ativa |
| `private/**` (719 MB) | storage do tenant (fora do webroot) + `laudos` | mover por paciente/empresa; indexar por `patient_id` |
| `orientacoes/**` | `exam_type_attachments` | de-duplicar (variantes com/sem `_`) |
| `taxonomy_term_data`, `entrada`, backups | — | **descartar** |

## 13.4 Deduplicação de pacientes (regra de match)

Ordem de confiança (gravar `match_method` + `confidence`):

1. **CPF válido** (DV ok, 11 dígitos normalizados) → match forte. Cobre ~60% (6.011 CPFs válidos de 6.171 preenchidos).
2. CPF ausente/ inválido → **nome normalizado (sem acento, upper) + data de nascimento** → match médio.
3. Só nome normalizado → match fraco → **fila de revisão humana** (não funde automático).
4. Sem match → cria paciente.

Casos medidos a tratar: **219 cadastros com CPF duplicado** (fundir), **49 logins duplicados**, **188 nomes repetidos**. Para **agendamento**, ~30% (2.063) não casam com a base → **criar "paciente parcial"** (só nome/telefone/empresa), marcado para completar no próximo atendimento. Isto é fluxo **principal** do ETL, não exceção.

## 13.5 Derivação de `appointments.status`

```
DesistiuCancelou='SIM'            → canceled
FaltouNoDia=1                     → no_show
ConfirmaAgendamento='on'          → confirmed  (+ checked_in/done se houver checkin/espelho na data)
senão                             → scheduled
```
Antes de aplicar o índice único anti-duplicidade, **resolver as 14 duplicatas nome+data** existentes.

## 13.6 Migração de senhas e arquivos

- **Senhas**: não há como preservar (md5/texto + 5 dígitos). Importar usuários com flag `must_reset_password=true`; disparar e-mail de definição no go-live. Pacientes passam a entrar por CPF + senha definida por eles (ou link assinado para baixar laudo sem senha).
- **Arquivos**: mover `private/` para o disco do tenant (S3/compatível ou disco local fora do webroot), preservando `Empresa/Paciente`, e vincular cada PDF a `patient_id`/`exam_result`. Os `.tar`/`.zip` de `compactacao/`/`arquivos_zip/` **não** migram (lixo sensível) e devem ser apagados do servidor.

## 13.7 Validação pós-migração

- **Contagens**: pacientes, agendamentos por ano, laudos por empresa (comparar com [01](../analise/01-inventario.md) §1.9).
- **Somatórios/distribuições**: NPS por nota, agendamentos por status.
- **Amostragem**: 30 pacientes conferidos manualmente (CPF, nascimento, empresa, laudos visíveis).
- **Relatório de inconsistências**: pacientes parciais, matches fracos, empresas órfãs, datas não parseáveis — entregues ao negócio para revisão.
- Nenhum go-live sem o relatório aprovado.
