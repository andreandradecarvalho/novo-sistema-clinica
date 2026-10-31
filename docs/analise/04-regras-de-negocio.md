# 04 — Regras de negócio extraídas

> Regras reconstruídas a partir do código e confirmadas, quando possível, contra os dados de produção. [C]/[I].

## 4.1 Ciclo de vida do exame (como é hoje, fragmentado em 3 sistemas)

Não existe uma máquina de estados única. O "estado" de um paciente está espalhado:

```
[agendaprec.agendamento]                    [agendaprec.checkincliente]        [samarco]                    [precordis2016]
pré-agendado  ──confirma──►  confirmado  ──►  check-in (bolinhas por exame) ──► espelho_atendimento  ──►  laudo PDF em private/
(ConfirmaAgendamento='on')   (dia do exame)    (verde/amarelo/branco)           (status por sala)          + mensagens de aviso
        │                                                                        relatorio_prec             (portal)
        ├─ desistiu/cancelou (DesistiuCancelou='SIM')                            (resultado clínico)
        └─ faltou (FaltouNoDia=1)
```

| Estado | Onde vive | Quem muda | [C]/[I] |
|---|---|---|---|
| Pré-agendado | `agendamento` (insert) | recepção (`gravaagenda`) | [C] |
| Confirmado | `agendamento.ConfirmaAgendamento='on'` | recepção/apoio (`editaDados*`) ou paciente via WhatsApp (n8n grava `enviouMensagemPacienteConfirmou`) | [C] |
| Cancelado | `DesistiuCancelou='SIM'` | recepção (`deleta_agenda`) | [C] |
| Faltou | `FaltouNoDia=1` | check-in (`ExcluirClienteExtra`) | [C] |
| Em atendimento (por sala) | `samarco.status_salas` / `espelho_atendimento.*_Status` | recepção TV + sala + **sync do Access** | [C] |
| Laudado | `samarco.relatorio_prec` (preenchido no Access) → PDF em `private/` | médico, fora do sistema web | [I] — o PDF é produzido externamente |
| Entregue | `espelho_atendimento.EntregaFinal` / `chamados_tv` sala `ENTREGA` | recepção | [C] |
| Resultado liberado ao paciente | PDF aparece em `private/<empresa>/<nome>`; e-mail por `mensagens` | admin | [C] |

**Não há transição controlada**: qualquer um com acesso ao setor muda qualquer coisa. O `espelho_atendimento` é só uma cópia do Access; o status real de laudo vive no Access. [I]

## 4.2 Agendamento

- **Granularidade por dia, não por horário**: `agendamento` não tem hora; a agenda é "quantos cabem no dia". `consultar_agendamento_ajax.php` mostra, para a data escolhida, total + contagem por sexo + "clientes especiais". **Não há limite, sala, equipamento, duração nem bloqueio de feriado** no código — o controle é visual/humano. [C]
- **Sem overbooking controlado**: a única trava (duplicidade por nome+data) está quebrada (§4.6). Média 10/dia, pico 194/dia (dia de mutirão). [C]
- **"Cliente especial"** (`clienteEspecial='on'`) e **"primeira vez"** (`primeiravez='on'`) e **"tem ginecológico"** (`temGinecologico='on'`) são flags livres usadas na contagem e na priorização visual. [C]
- **CMA vs Checkup**: `Especialidade` (`Checkup` 4.357, `Cma` 187, vazio 2.349). O texto de WhatsApp muda conforme `tipoTexto` (`cma`/`checkup`). [C]
- **Reconvocação +1 ano**: `listar-pacientes-com-checkup-a-mais-de-um-ano.php` lista quem agendou há > 1 ano e **não** tem agendamento no último ano (subquery `NOT EXISTS`), para campanha de retorno. [C]

## 4.3 Check-in e chamada

- **Check-in** (`checkinCliente`, inativo): grade de 19 exames por paciente, cada um `verde`/`amarelo`/`branco`/`nao`. "Cliente extra" = encaixe sem agendamento prévio (`codigoAgenda` sorteado 7000–9000). [C]
- **Chamada de senha/sala** (`precordistv`, ativo): não há senha numérica; chama-se **pelo nome**. Trava de concorrência: um paciente não pode estar ocupado em duas salas; uma sala não aceita dois pacientes (checado em `chamar.php`). Recepção e Entrega Final não têm trava. PIN de 4 dígitos por sala para liberar. [C]
- **Priorização de idoso/gestante/PCD (Lei 10.048/2000)**: **não existe** no código. [C] — oportunidade (ver [16](../proposta-novo-sistema/16-novas-funcionalidades.md)).

## 4.4 Vínculo empresa ↔ paciente

- `empresas` é hierárquica (`em_parent`): empresa-mãe e filiais/unidades. Serve para **duas coisas**: agrupar a pasta de laudos (`permissao.pasta` = caminho da empresa) e popular o `<select>` do agendamento. [C]
- **Não há** tabela de preço por empresa, convênio, ASO ou exame ocupacional estruturado — "ocupacional" existe só como texto de empresa (SAMARCO, Vale etc.). [C]
- O campo `info_usuario.empresa` (texto) e `usuario.em_id` (FK lógica) coexistem e divergem (ver [07](07-inconsistencias-modelagem.md)). [C]

## 4.5 Laudos, mensagens e pesquisa

- **Laudo**: produzido no Access/externamente, publicado como PDF em `private/<Empresa>/<Paciente>/`. O acesso é por `permissao.pasta` (string). Sem token, sem expiração, sem assinatura digital. [C]
- **Mensagens de resultado** (`mensagens`): admin agenda um e-mail por paciente (`ms_date`); `send-mail.php` enviaria os pendentes — **mas o cron está morto desde 2022** (último `ms_status=1` em 29/01/2022; 1.297 pendentes). Hoje o aviso de resultado é feito por WhatsApp via n8n (`lembra_procedimento`, `lembrete_resultado_exames`). [C]
- **NPS/Satisfação**: anônimos; NPS 0–10 (média alta: 10 é a nota modal, 1.295 de 1.914), satisfação 4 escalas com média ~8,3. Vínculo entre NPS e satisfação está bugado (§4.6). [C]
- **Framingham**: escore de risco cardiovascular por paciente, calculado no Access, espelhado em `samarco.framingham` (valores -7 a 95; o negativo é provável erro de origem). [C]

## 4.6 Bugs de regra confirmados em produção

| # | Bug | Evidência | Impacto |
|---|---|---|---|
| B1 | **Trava de agendamento duplicado nunca funciona** | `WHERE ... DesistiuCancelou IS NULL`, mas a coluna é `NOT NULL DEFAULT ''` (0 linhas `NULL`) | agendamentos duplicados entram sem aviso |
| B2 | **`roteiro_paciente` sempre vazio (0 linhas)** | FK `ON DELETE CASCADE` para `espelho_atendimento.id` + o sync recria a linha do paciente a cada ciclo (`AUTO_INCREMENT` 674.696 para 917 linhas) → o roteiro é apagado em cascata a cada ~1 min | funcionalidade de roteiro inutilizável |
| B3 | **Sala fantasma `CLINICO` ocupada desde 13/08/2026** | `status_salas` tem a chave `CLINICO`, que **não existe** em `salas` (a sala real é `UROL` = "Clínico Urologia"); sem FK, a linha órfã nunca limpa | painel mostra sala ocupada permanentemente |
| B4 | **`send-mail.php` morto** | nenhum envio desde 01/2022; 1.297 `mensagens` pendentes | avisos de resultado migraram para WhatsApp, mas a fila antiga ficou |
| B5 | **NPS ↔ satisfação mal vinculados** | `pesquisaAgradecimentoEnvio.php` usa `max(idPesquisa)` de `pesquisa_NPS` e passa o array do `fetch` como `idPesquisa`; 2.013 de 2.014 linhas têm `idPesquisa=0` | impossível cruzar NPS com satisfação |
| B6 | **Sexo gravado como nome** | 298 linhas de `agendamento.sexo` não são `Masc`/`Fem`; em 182 delas `sexo` = início do nome do cliente (campos trocados num formulário antigo, 05/2024–07/2025) | relatórios por sexo distorcidos |
| B7 | **`_lembra_senha.php` envia para `$to` indefinido** | variável `$to` nunca é atribuída antes do `mail($to,...)` | recuperação de senha provavelmente falha |
| B8 | **`get_cliente_by_id.php` com `var_dump` e sem `WHERE`** | `var_dump($user)` no loop; `$query_search` montado mas não concatenado na SQL (`LIMIT 10` sem filtro) | endpoint quebrado/verboso |
| B9 | **datas de nascimento em formatos mistos** | `info_usuario.dt_nascimento`: 5.748 `Y-m-d`, 1.259 `d/m/Y`, 3.006 vazio; o PHP assume `d/m/Y` | idade errada/vazia para a maioria |

Esses bugs são insumo direto do plano de migração ([13](../proposta-novo-sistema/13-plano-migracao-dados.md)) e do roadmap ([17](../proposta-novo-sistema/17-roadmap-fases.md)).
