# 22 — Atendimento, execução de exames e financeiro clínico

[Confirmado] Fonte normativa funcional do atendimento do novo SaaS, criada na correção de pendências de 03/11/2026. Requisito do usuário: clínicas realizam diversos exames, inclusive ocupacionais. DDL: [schema](../schema/schema.sql). Ocupacional: [23](23-saude-ocupacional.md). Segurança e retenção: [24](24-seguranca-retencao-operacao.md). A especificação não comprova código implementado.

## 22.1 Identidade e sequência

- [Inferido] `patients` identifica uma pessoa **dentro da clínica**. Não compartilhar prontuários entre tenants por coincidência de CPF. CPF normalizado tem hash HMAC para busca exata e ciphertext em campo próprio; pacientes parciais podem não ter CPF.
- [Inferido] `exam_orders` é o pedido: paciente, solicitante quando conhecido, empregador/pagador, itens, preços e contexto preservados. `exam_order_items` identifica o exame solicitado e seu preço contratado. Criar novo pedido não exige agendamento prévio.
- [Inferido] `appointments` é a reserva; `visits` é o atendimento efetivo, inclusive encaixe sem reserva; `exam_executions` registra cada execução/tentativa por exame, vinculada ao item do pedido e ao atendimento. Atendimento sem pedido pode existir para histórico legado, com origem documentada; operações novas exigem pedido quando houver cobrança de exames.
- [Inferido] `exam_results` referencia a execução quando conhecida; históricos incompletos preservam vínculos disponíveis e a qualidade da origem. Ausência de resultado não significa exame normal ou concluído.
- [Inferido] `documents` contém cada versão materializada de laudo/anexo/ASO. Versões liberadas são imutáveis; retificação cria nova versão com referência à anterior, motivo e autoria. Nunca atualizar bytes de arquivo já liberado no mesmo path.

## 22.2 Estados e transições

| Entidade | Estados de referência | Responsável / condição |
|---|---|---|
| [Inferido] Pedido | `draft → authorized → in_progress → completed`; `canceled` | Recepção autoriza; conclusão deriva dos itens; cancelamento exige motivo e preserva execução já realizada. |
| [Inferido] Agendamento | `pre_agendado → confirmado → checked_in`; `cancelado`, `no_show` | Recepção; confirmação do paciente autenticada por canal autorizado. Cancelar não exclui atendimento ou resultado. |
| [Inferido] Atendimento | `open → in_progress → completed`; `canceled` antes de execução | Recepção abre; equipe executa; fechamento verifica pendências. |
| [Inferido] Execução | `pending → in_progress → completed`; `refused`, `incomplete`, `canceled` | Profissional autorizado; guardar executor, início/fim e motivo em desfechos excepcionais. Nova tentativa mantém a anterior. |
| [Inferido] Resultado | `draft → reviewed → released`; `amended` | Autor/revisor/liberador conforme tipo de exame e Policy; transição guarda autoria e data. |
| [Inferido] Documento | `draft → signed → released`; `superseded`, `revoked` | Médico/profissional habilitado conforme documento; retificação preserva original e trilha. |

- [Inferido] Uma Action por transição valida estado anterior, autorização, tenant e vínculos. Usar transação e controle de concorrência; eventos externos somente após commit, com idempotência. Não aceitar alteração arbitrária de status pelo formulário.
- [Inferido] Regras de capacidade são configuráveis por clínica/unidade/recurso. Padrão inicial: no máximo uma reserva não cancelada por paciente/unidade/dia/horário. Horário nulo representa reserva diária e usa slot próprio; não equivale a todos os horários. Regras mais restritas podem impedir outras reservas no dia. Conflitos entre reservas diárias e horários dependem da configuração validada com a operação.
- [Inferido] Repetição de exame cria `exam_executions.attempt_no` adicional; não duplica cadastro de paciente e não sobrescreve resultado. Disponibilidade parcial de laudos não encerra automaticamente pedido nem avaliação ocupacional.

## 22.3 Questionários e resultados históricos

- [Inferido] Perguntas identificadas por código + versão são imutáveis após uso. Respostas preservam texto/tipo/opções da pergunta e conteúdo criptografado; alteração publica nova versão. Registro deve indicar autor, data e origem.
- [Inferido] Cada medição preserva unidade, método e referência utilizados na coleta/interpretação. Não recalcular sinalização de resultado histórico ao editar o catálogo. Referências e protocolos são configurados pelo responsável técnico; não inferir diagnóstico de limites genéricos.
- [Inferido] Campos de resultado numérico e textual são mutuamente exclusivos quando há valor; ambos nulos só representam dado explicitamente ausente, sem flag normal automática. Exames complexos podem exigir estruturas adicionais específicas; anexos não substituem metadados de autoria.

[Confirmado] Sequência decidida pelo usuário em 04/11/2026: etapa 3 prepara modelos/versionamento e snapshots da definição das perguntas; a coleta de respostas, sua cifra e persistência em health_records/health_answers serão implementadas na etapa 4, com appointments disponível. Decisão e dependências na [P13](../outros/PENDENCIAS.md#p13-sequencia-questionarios) e no [fluxo 20](20-plano-desenvolvimento.md). Não criar tabelas de agenda antecipadamente nem remover FKs para simular a entrega.

## 22.4 Financeiro da clínica

- [Confirmado] [Billing SaaS](10-assinatura-billing.md) cobra mensalidade da clínica; não representa venda de exames.
- [Inferido] Escopo clínico inicial: preço por item preservado no pedido, pagador particular ou empresa e contas a receber (`clinical_receivables`) com valor, vencimento, status e referência externa de liquidação. Desconto não pode gerar total negativo; ajustes têm motivo e trilha. Não armazenar dados de cartão.
- [Inferido] Pedidos preservam snapshot da tabela/contrato e identificação do pagador. Alterar preço no catálogo ou empresa do paciente não altera cobrança anterior. Execução parcial exige decidir itens cobrados antes do fechamento.
- [Inferido] TISS, contabilidade completa, caixa, repasse médico, parcelamento e integração fiscal clínica estão fora desse mínimo; só entram mediante escopo específico. Não anunciar financeiro completo a partir de uma conta a receber.

## 22.5 Aceite

- [Inferido] Cobrir pedido com vários exames/dias; encaixe; recusa; repetição sem perda da tentativa anterior; liberação parcial; cancelamento e novo agendamento no mesmo slot; duas unidades/horários; preço histórico; questionário alterado sem mudar resposta anterior; resultado retificado sem sobrescrever documento; rejeição de paciente/resultado/pedido de tenant diferente.

[Confirmado] Primeiro incremento da etapa 4: [rascunhos de pedidos particulares](../outros/pedidos-exames.md), com vários exames e snapshots de preço/descrição, sem agendamento prévio. Não implementa ainda as transições/financeiro do contrato acima. Escopo, testes e continuidade no fluxo 20.

[Confirmado] Transições iniciais de pedidos particulares implementadas: draft → authorized e draft/authorized → canceled, com motivo cifrado, ator/data UTC, itens atualizados em transação e auditoria. [Uso, autorizações e limites](../outros/pedidos-exames.md#autorizar-ou-cancelar). Não implementam ainda execução, recebíveis, estornos ou cancelamento parcial; estados posteriores e dependências devem ser integrados antes do uso correspondente.
