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

[Confirmado] Transições iniciais de pedidos particulares implementadas: draft → authorized e draft/authorized → canceled, com motivo cifrado, ator/data UTC, itens atualizados em transação e auditoria. [Uso, autorizações e limites](../outros/pedidos-exames.md#autorizar-ou-cancelar). Não implementam ainda execução, estornos ou cancelamento parcial; estados posteriores e dependências devem ser integrados antes do uso correspondente. Pedidos com recebível aberto/baixado recusam cancelamento; recebíveis cancelados com evidência válida permitem o cancelamento explícito do pedido.

[Confirmado] Seleção explícita de empresa pagadora implementada nos pedidos, com identificação cifrada histórica e preço preservado do catálogo. [Operação e limites](../outros/pedidos-exames.md#empresa-pagadora). A escolha não altera vínculo empregatício ou acesso empresarial. Tabelas/contratos comerciais e descontos permanecem parâmetros a definir antes de sua implementação, conforme fluxo 19; não há reprificação retroativa ou emissão automática de cobrança.

[Confirmado] Criação de pedidos particulares/empresariais protegida por chave de envio obrigatória e escopo de clínica/ator/operação. Mesmo conteúdo recupera o pedido; reutilização com conteúdo diferente é recusada, preservando snapshots e autorização atual. [Contrato operacional e limites](../outros/pedidos-exames.md#proteção-contra-reenvio-da-criação), com colunas/índice no schema e migration 000016. Não representa idempotência de recebíveis, notificações ou integrações futuras.

[Confirmado] Registro integral interno de contas a receber implementado para pedido autorizado, com valor histórico, vencimento, permissões financeiras, reenvio idempotente e bloqueio de cancelamento do pedido quando há recebível. [Operação e limites](../outros/contas-a-receber.md). Baixa e cancelamento internos auditados também implementados, com permissões e idempotência próprias, motivo cifrado e instante UTC. Cobrança externa, estorno e parcelas continuam fora do incremento; não constitui financeiro completo.

[Confirmado] Primeiro incremento da agenda implementado: [reservas diárias por paciente/unidade](../outros/reservas-diarias.md), com autorização por unidade, consulta, criação e cancelamento auditados/idempotentes. O escopo original desse incremento não incluía horário/recurso/capacidade ou check-in; a ligação ao pedido foi entregue no incremento seguinte de visits.

[Confirmado] [Confirmação manual e check-in/atendimento](../outros/check-in-atendimento.md) implementados: pré-agendada → confirmada → checked_in, com evidência cifrada, autoria/UTC e idempotência. Um atendimento open por reserva não nula, com pedido autorizado do paciente e unidade coerente; vários atendimentos do mesmo pedido podem vir de reservas distintas. Confirmação manual é da equipe, sem presumir confirmação autenticada do paciente. Cancelamento de reserva confirmada preserva a confirmação; após check-in, reserva/pedido e novo cancelamento de recebível ficam bloqueados até revisão específica. Não inicia exames nem coleta respostas; escopo/evidências no fluxo 20.

[Confirmado] Cancelamento de atendimento antes da execução implementado como `open → canceled`, com permissão `visits.cancel`, motivo cifrado, autoria/UTC, idempotência e auditoria transacional. Preserva chegada e bloqueios sobre reserva/pedido/recebível. [Contrato e limites](../outros/check-in-atendimento.md#cancelar-atendimento-antes-da-execução); não antecipa cancelamento após execução, estorno ou conclusão clínica.

[Confirmado] Pré-requisito de execuções materializado: [cadastro de salas por unidade](../outros/salas-unidades.md), migration 000021, com FKs compostas já previstas no DDL. Trata configuração administrativa; não inicia execução, não configura capacidade ou habilitação profissional e não altera cancelamento de atendimentos.

[Confirmado] [Preparação/cancelamento de tentativas pendentes](../outros/tentativas-exames.md) implementados por item autorizado, com reenvio protegido e histórico. Preparação não inicia visita/pedido/itens; cancelar atendimento exige pendências explicitamente canceladas e evidências íntegras. Na preparação inicial, sala/executor e início/fim são nulos; a sala pode ser atribuída no fluxo administrativo posterior.

[Confirmado] [Atribuição administrativa de sala](../outros/atribuicao-salas-tentativas.md) implementada para pendência sem sala, com unidade exata, catálogo ativo na nova atribuição, snapshot cifrado e reenvio. Cancelamento preserva essa atribuição. Não atribui profissional nem inicia visita/pedido/exame.

[Confirmado] Usuário definiu autorização explícita por profissional/tipo. [Concessões e revogações](../outros/concessoes-profissional-exame.md) e verificador por tipo implementados; não substituem Policy do atendimento nem iniciam exames automaticamente.

[Confirmado] Incremento da etapa 4: [assumir e iniciar exame](../outros/tentativas-exames.md#assumir-e-iniciar-exame), com concessão profissional específica, sala válida e transição atômica de tentativa/item/atendimento/pedido. Leitores e financeiro integral adaptados; não representa conclusão clínica, ocupação exclusiva de sala ou coleta de respostas. Evidências/status somente no fluxo 20.

[Confirmado] [Coleta individual de respostas](../outros/respostas-clinicas.md) materializada na etapa 4: uma versão de pergunta escolhida por envio, snapshot no servidor, cifra, autoria/UTC/origem e vínculos a paciente/reserva/atendimento, com idempotência e histórico sem sobrescrita. Não é questionário completo, retificação clínica ou aprovação de conteúdo real. Evidências/status somente no fluxo 20.

[Confirmado] [Conclusão regular da execução](../outros/tentativas-exames.md#concluir-execução-e-itens-do-pedido) implementada pelo executor atribuído, com concessão atual, permissão própria, término UTC, evidência cifrada e reenvio. Item acompanha a conclusão; pedido termina somente com todos os itens concluídos. Atendimento permanece em execução até o fechamento explícito separado; resultados/documentos/ASO seguem seus próprios estados. Financeiro e respostas preservam leitura/operação no atendimento ativo com pedido concluído. Evidências/aceite no fluxo 20.

[Confirmado] [Encerramento regular do atendimento](../outros/check-in-atendimento.md#encerrar-atendimento-após-execução) entregue com permissão própria, conferência das tentativas locais, autoria/UTC/evidência cifrada, reenvio e bloqueio de novas execuções/coletas. Preserva consultas, pedido com vários atendimentos e financeiro independente. Desfechos excepcionais e liberação clínica não são derivados do fechamento. Evidências/status somente no fluxo 20.

[Confirmado] Por decisão do usuário, política financeira para recusa/incompletude é configurável por clínica. [Configuração e histórico](../outros/politica-financeira-excecoes.md) materializados, inicialmente sem modo selecionado, com permissões próprias. Configurar não registra desfechos nem ajusta cobranças existentes; o registro após início consome a revisão configurada pelo fluxo próprio. Ajustes/revisão explícita de contas permanecem no incremento seguinte.

[Confirmado] [Desfechos excepcionais após início](../outros/desfechos-excepcionais-exames.md) materializados com concessão atual, motivo cifrado, autoria/UTC e política financeira capturada. Itens restantes continuam executáveis; pedido/visita não são concluídos automaticamente. Repetição e revisão do preço histórico após conclusão estão implementadas conforme os contratos abaixo; ajustes de contas continuam pendentes.


[Confirmado] Repetição de item refused/incomplete no atendimento ativo segue o [contrato de sucessão](../outros/desfechos-excepcionais-exames.md#repetir-no-mesmo-item), mantendo o predecessor e preparando nova tentativa. A decisão comercial é uma cobrança por item e revisão financeira explícita antes de nova cobrança/baixa; conclusão da repetição não libera o financeiro nem encerra automaticamente as pendências da visita.


[Confirmado] [Revisão financeira após repetição concluída](../outros/contas-a-receber.md#revisão-explícita-após-repetição-concluída) confirma explicitamente uma cobrança por item pelo preço histórico, vinculada às tentativas e à conta. Não altera valores, não baixa/cria conta automaticamente e não encerra pendências clínicas; itens ainda excepcionais e ajustes seguem bloqueados.

[Confirmado] Encerramento explícito com tentativas históricas excepcionais resolvidas por sucessora concluída segue o [contrato do atendimento](../outros/check-in-atendimento.md#encerrar-atendimento-após-execução): preserva predecessores, exige cadeia íntegra na mesma visita e independe da revisão financeira. Não há reabertura nem liberação clínica derivada do encerramento.
