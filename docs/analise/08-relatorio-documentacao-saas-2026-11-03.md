# Revisão da documentação — SaaS para clínicas de exames e saúde ocupacional

[Confirmado] **Relatório histórico anterior às correções.** Resultado da conciliação posterior em [PENDENCIAS](../outros/PENDENCIAS.md); DDL e fontes atuais no [mapa central](../README.md). As contagens e ausências abaixo descrevem o snapshot analisado, não o estado após correção.

[Confirmado] Data: **03/11/2026**. Escopo: documentação presente no estado local do repositório, incluindo alterações ainda não commitadas. Foram revisados os 31 arquivos Markdown/SQL das pastas `analise`, `fluxos`, `layout`, `outros` e `schema`, além de `AGENTS.md` e `README.md` da raiz. A avaliação do template se baseia nos seus dois documentos de referência; não representa nova auditoria dos assets ou das 144 páginas HTML.

[Confirmado] Este relatório confirma o conteúdo observado nos documentos e no DDL, não o funcionamento do legado ou da aplicação planejada. Não foram acessados bancos de produção, dados de pacientes ou PDFs de laudos. Não foram executados DDL/DML, testes de aplicação ou homologação SQL. O legado permaneceu sem alterações.

## Parecer

[Inferido] A documentação oferece uma boa base de diagnóstico do legado e de organização técnica do SaaS, mas **ainda não constitui uma especificação coerente e suficiente para implementar todo o produto**, especialmente saúde ocupacional. O maior risco é reproduzir o fluxo de check-up da primeira clínica como se ele cobrisse os processos de todas as clínicas clientes.

[Confirmado] A visão do produto já inclui clínicas ocupacionais, diferentes exames e portal empresarial. Entretanto, o modelo físico concentra-se em pacientes, agenda, salas, anamnese, resultados e documentos. O arquivo atual é `docs/schema/schema.sql`: contém **50 tabelas e 100 declarações de FK, nenhuma composta**. Os antigos `central.sql` e `tenant.sql` estão ausentes no estado local analisado. Fontes: [produto, §8.1–8.2](../fluxos/08-visao-produto-bounded-contexts.md), [DDL](../schema/schema.sql), [changelog](../schema/CHANGELOG.md).

[Inferido] Recomendo preservar o trabalho de análise e revisar os contratos de domínio antes de transformar o DDL inteiro em migrations. A fundação pode avançar após conciliar isolamento, autenticação, interface e critérios de aceite; os módulos clínicos e ocupacionais precisam das definições abaixo antes da implementação correspondente.

## O que está bem encaminhado

| Evidência documental | Avaliação |
|---|---|
| [Confirmado] O diagnóstico separa os subsistemas, explica a dependência do Access e identifica vínculos frágeis por nome, duplicidades, datas e encoding. Fontes: análise 01–07. | [Inferido] É um ponto de partida útil para migração; evita tratar a importação como simples cópia de tabelas. |
| [Confirmado] A proposta inclui identidade por paciente, catálogo em linhas, autorização no servidor, storage privado, auditoria e testes entre tenants. Fontes: fluxos 08–15. | [Inferido] São fundamentos adequados ao produto, desde que convertidos em contratos verificáveis. |
| [Confirmado] O fluxo 20 define entregáveis, dependências, testes e aceite; o fluxo 14 propõe organização modular com Actions, Policies e contratos. | [Inferido] A disciplina de execução é aproveitável após remover as contradições. Não há motivo documental para iniciar com microsserviços. |
| [Confirmado] O schema usa InnoDB, identificadores públicos, FKs, valores monetários em DECIMAL e separa catálogo de medidas dos valores coletados. | [Inferido] Há evolução estrutural em relação ao legado, embora ainda faltem invariantes e histórico clínico. |

## Divergências que afetam a implementação

### Arquitetura de isolamento e operação

[Confirmado] `AGENTS.md`, linhas 13–16, e `README.md`, linha 13, prescrevem banco central e banco por clínica. O fluxo 09, o fluxo 21 e `schema.sql` prescrevem banco único com `tenant_id`. O índice dos fluxos contém as duas descrições. `PENDENCIAS.md` registra P03 como resolvida a favor do banco único, mas a resolução não foi propagada a todas as fontes. **Este relatório não escolhe uma alternativa nem altera o DDL.**

[Confirmado] Mesmo dentro do fluxo 20, a etapa 1 descreve migrations executadas uma vez, enquanto a definição de pronto exige `tenants:migrate` em dois tenants e o aceite manda provisionar banco. O fluxo 15 também mantém migrations por tenant. Fontes: [20, §20.1 e etapa 1](../fluxos/20-plano-desenvolvimento.md), [15, §15.2](../fluxos/15-qualidade-testes-devops.md).

[Inferido] A decisão consolidada precisa definir o significado de tenant, seu cadastro, autenticação, migração, backup, restauração, desligamento e eventual transferência para banco dedicado. Não basta trocar o nome dos arquivos SQL. Quantidade prevista de clínicas, volume, custo operacional, requisitos contratuais e capacidade de suporte devem fundamentar a escolha; a pergunta 16 do fluxo 19 ainda solicita esses dados.

### Interface, versões e fontes concorrentes

[Confirmado] O `AGENTS.md` e o README apontam Vuexy como escolha do usuário. O fluxo 14 mantém Filament e Inertia/React como propostas, enquanto o fluxo 20 repete “front-end a definir”. Seu cabeçalho e a etapa 1 ainda repetem versões antigas que diferem da matriz §14.1. A matriz é um registro de consulta datada, não evidência de instalação ou de compatibilidade de todos os pacotes.

[Inferido] Definir uma estratégia de integração com Vuexy e remover a ambiguidade de execução. A análise não recomenda trocar a referência visual nem escolher pacotes sem uma instalação e um teste de compatibilidade reproduzíveis. Centralizar versões no fluxo 14 e referenciá-lo nos demais documentos.

[Confirmado] Não existem `docs/README.md` nem `docs/00-mapa.md`, `docs/outros/banco-legado.md` ou `docs/outros/indice-legado.md`, previstos na preparação inicial. Há conteúdo equivalente parcial espalhado em `AGENTS.md` e análise 01–06. A checagem de destinos Markdown encontrou **10 ocorrências de links para caminhos ausentes**, incluindo duas referências históricas a `tenant.sql` em pendências. Quatro links `schema/schema.sql` em `docs/fluxos/` resolvem para uma pasta inexistente; os links ao DDL atual devem apontar para `../schema/schema.sql`.

[Inferido] Criar o índice central e os documentos pendentes como mapas de referências, evitando repetir o dicionário e as regras existentes. Identificar claramente material histórico, especificação vigente e proposta ainda não aprovada.

### Plano de trabalho e limite sobre o legado

[Confirmado] A etapa 1 depende da etapa 0, cujo aceite exige alterações e verificações em produção no legado. Isso conflita com a orientação vigente do usuário e o `AGENTS.md`: legado somente leitura. Fontes: [20, §20.2–20.3](../fluxos/20-plano-desenvolvimento.md), [AGENTS, Segurança](../../AGENTS.md).

[Inferido] Separar a contenção operacional do legado, sob responsabilidade e autorização próprias, da construção do novo produto. Registrar as necessidades observadas sem executá-las neste escopo. Redefinir o bloqueio da fundação de forma explícita; não marcar a etapa 0 concluída para contornar a dependência.

[Confirmado] P01 continua aberta: `legacy_map` e `legacy_references` diferem também em campos e recursos de idempotência. P02 continua aberta: o MVP exclui a etapa 6, mas inclui a etapa 7 que depende dela. O roadmap 17 e o plano 20 usam numerações de fases diferentes. Fonte: [pendências existentes](../outros/PENDENCIAS.md).

[Inferido] Declarar se haverá migração parcial de cadastros/agenda antes dos resultados. Diferenciar fase de produto, etapa de implementação e fase de ETL para impedir aceites associados ao número errado.

## Cobertura do atendimento de diversos exames

[Confirmado] O catálogo contempla preparo, duração e preço básico. `appointment_exams` permite vários tipos de exame no agendamento, mas impõe unicidade de `(appointment_id, exam_type_id)`. `exam_results` referencia agendamento/paciente/tipo, sem vínculo direto com um item específico de execução. Não há entidade própria de pedido nem de atendimento efetivamente realizado. Fontes: [schema, linhas 504, 625, 685 e 775](../schema/schema.sql), [12](../fluxos/12-novo-schema-unificado.md).

[Inferido] Distinguir conceitualmente **pedido → agendamento → atendimento → execução de cada exame → resultado → documento liberado**. A implementação pode reunir entidades quando a operação justificar, mas deve explicar encaixe sem agenda, atendimento em vários dias, exame repetido, exame recusado/incompleto, execução externa e liberação parcial. Um agendamento cancelado não equivale a um prontuário apagado.

[Inferido] Exemplo: um paciente realiza audiometria e coleta hoje, repete uma medição e recebe um exame de imagem depois. O fluxo precisa preservar cada execução e seus responsáveis, enquanto o pedido e a cobrança continuam rastreáveis. A unicidade atual por tipo exige decidir como representar essa repetição, sem criar um novo paciente ou sobrescrever o primeiro resultado.

[Confirmado] A documentação pergunta se haverá agenda por horário e capacidade, mas o DDL já bloqueia dois agendamentos não excluídos do mesmo paciente na mesma data, independentemente de horário/unidade. A coluna `active_flag` considera somente `deleted_at`, não `status` ou `canceled_at`. Fonte: [schema, linha 625](../schema/schema.sql), [21, decisões mantidas](../fluxos/21-schema-e-reaproveitamento.md).

[Inferido] Definir antes de consolidar o índice: reagendamento após cancelamento, consultas separadas no mesmo dia, unidade e recursos concorrentes. O índice atual corrige a falha de NULL do desenho anterior, mas não resolve sozinho essas regras de negócio.

[Inferido] Laboratório de apoio, amostras, equipamentos, imagens e protocolos específicos devem entrar conforme o portfólio real das clínicas. DICOM/PACS, integrações laboratoriais e TISS não precisam ser impostos universalmente no MVP; o modelo deve evitar impedir sua evolução. Fluxo, capacidade e responsabilidade profissional importam mais do que carregar todas as bibliotecas do template.

## Saúde ocupacional: lacuna principal de domínio

[Confirmado] A visão do produto e o portal empresarial mencionam ocupacional/ASO. A análise 04, §4.4, informa que o legado não possui ASO ou exame ocupacional estruturado. O documento de migração propõe `patient_company_links` com vigência, mas essa entidade não está no DDL atual; empresa, matrícula e cargo permanecem no cadastro do paciente. Não há tabelas específicas para PCMSO, exposição ocupacional ou avaliação/ASO estruturados. Fontes: [04](04-regras-de-negocio.md), [processo de migração, entidades de destino](<../outros/Precordis — Processo de Migração dos Bancos Legados.md>), [schema, linha 426](../schema/schema.sql).

[Inferido] Para atender o requisito do usuário, ocupacional deve ser um domínio explícito, compartilhando pacientes, agenda e resultados com os demais exames. A tabela abaixo propõe conceitos, não nomes de tabelas aprovados nem um módulo já implementado.

| Conceito proposto | Informação e comportamento a especificar |
|---|---|
| [Inferido] Vínculo de trabalho | Paciente, empregador, estabelecimento, matrícula, setor, função e períodos; múltiplos vínculos e mudanças com histórico. |
| [Inferido] Contexto ocupacional | Riscos/exposições e protocolo aplicável, com vigência e origem; referência ao PCMSO/PGR quando aplicável. Não pressupor que a clínica fará toda a gestão de segurança do trabalho do empregador. |
| [Inferido] Avaliação ocupacional | Motivo da avaliação, função avaliada, exames previstos e realizados, pendências, médico responsável e conclusão. |
| [Inferido] ASO | Dados estruturados vinculados à avaliação; fotografia dos dados usados na emissão, versão do documento, autoria e registro de disponibilização. O PDF é uma representação desse registro. |
| [Inferido] Convocação | Vencimentos por protocolo e contexto, pendências e justificativas; não reutilizar automaticamente a campanha de check-up “+1 ano”. |
| [Inferido] Integração SST | Escopo contratado, origem dos dados, exportação ou transmissão, validação, retorno, retificação e rastreabilidade. |

[Confirmado] A NR-7 distingue exames admissional, periódico, retorno ao trabalho, mudança de riscos ocupacionais e demissional (§7.5.6). Prevê conteúdo mínimo do ASO com identificação da organização/trabalhador/função, riscos pertinentes, exames e datas, conclusão de aptidão e identificação médica (§7.5.19.1). A periodicidade varia; não é anual para todos. Fonte: [NR-7, texto consolidado disponibilizado pelo MTE](https://www.gov.br/trabalho-e-emprego/pt-br/acesso-a-informacao/participacao-social/conselhos-e-orgaos-colegiados/comissao-tripartite-partitaria-permanente/arquivos/normas-regulamentadoras/nr-07-atualizada-2022.pdf).

[Inferido] Fluxo recomendado para validação com a clínica: identificar vínculo/função e motivo → selecionar protocolo vigente → solicitar/agendar exames → registrar execuções e resultados → avaliação médica → conclusão e ASO → disponibilização autorizada → acompanhamento das próximas obrigações. Aptidão depende da avaliação médica contextualizada; não deve ser calculada automaticamente por um valor de laboratório.

[Inferido] Exemplo de aceite: mudar a função ou o empregador hoje não altera o conteúdo de um ASO emitido anteriormente. A nova empresa não recebe automaticamente acesso ao histórico clínico produzido para outra relação de trabalho. Esse caso precisa aparecer no desenho e nos testes.

[Confirmado] No eSocial, S-2220 trata do monitoramento da saúde do trabalhador e S-2240 das condições ambientais/agentes nocivos. A responsabilidade pelo envio é da empresa, que pode delegá-lo por procuração eletrônica. Fontes: [manual oficial SST](https://www.gov.br/esocial/pt-br/empresas/manual-web-geral/manual-web-geral/), [FAQ oficial, item 11.1](https://www.gov.br/esocial/pt-br/empresas/perguntas-frequentes/perguntas-frequentes-producao-empresas-e-ambiente-de-testes/).

[Inferido] Decidir se o SaaS somente entrega informações para terceiros, exporta arquivos ou transmite eventos. Não pressupor que emitir ASO obriga a clínica a implementar toda a gestão de SST, nem que S-2240 pode ser montado apenas com resultados de exames. Se houver transmissão: prever versão do leiaute, autorização, recibos, erros, reenvio idempotente e retificações.

## Segurança, sigilo e isolamento

### Banco compartilhado e referências entre clínicas

[Confirmado] O fluxo 09 reconhece isolamento lógico, mas chama global scopes de equivalente prático ao RLS. A documentação oficial do Laravel permite remover scopes com `withoutGlobalScope(s)`. Portanto, um filtro do ORM não é uma política obrigatória do servidor de banco. Fonte: [Laravel, remoção de global scopes](https://laravel.com/framework/docs/13.x/eloquent#removing-global-scopes).

[Confirmado] No DDL, `patients.tenant_id` referencia `tenants.id`, e `patients.company_id` referencia somente `companies.id`; não há FK composta que exija igualdade entre os tenants dessas linhas. O mesmo padrão aparece em relações clínicas e operacionais. Fonte: [schema, linha 426](../schema/schema.sql).

[Inferido] Exemplo estrutural: um paciente marcado como clínica A pode referenciar uma empresa existente da clínica B sem violar essas duas FKs simples. Isso não comprova vazamento na aplicação, ainda inexistente; mostra uma relação que o banco não rejeita. Avaliar FKs compostas `(tenant_id, parent_id)` com chaves compatíveis nos pais, além da autorização da aplicação. Tratar especialmente paciente, atendimento, resultado, documento, unidade e empregador.

[Inferido] O contrato de contexto deve negar operação quando o tenant estiver ausente, validar o vínculo do usuário ao tenant resolvido e impedir que um header escolhido pelo cliente conceda acesso. Definir comportamento de consultas cruas, joins, operações em lote, jobs, scheduler, workers persistentes, cache de permissões, busca e exportações. Identificar tenant é diferente de autorizar acesso.

[Confirmado] O cabeçalho do schema e o fluxo 09 afirmam que tabelas centrais não possuem `tenant_id`, mas `domains`, `subscriptions`, `invoices` e `usage_records` o possuem. Também afirmam `tenant_id NOT NULL` em toda tabela da clínica, mas `roles`/`activity_log` permitem NULL e `role_has_permissions` não tem essa coluna. Fonte: [schema, linhas 67, 107, 141, 179 e 247–360](../schema/schema.sql).

[Inferido] Classificar cada tabela como global, pertencente a tenant ou pertencente indiretamente por pai. Definir exceções e regras de acesso sem aplicar um trait universal que quebre ACL ou ignore dados associados indiretamente. Na ACL, testar também associação de papel de outra clínica, não apenas alteração direta de `tenant_id`.

### Empresa, paciente e equipe clínica

[Confirmado] O fluxo 11 restringe a empresa aos “seus funcionários”, sem definir acesso por categoria de dado ou por vínculo histórico. O art. 76 do Código de Ética Médica protege informações confidenciais obtidas em exames de trabalhadores, inclusive diante de exigências empresariais, com a exceção prevista no próprio artigo. Fontes: [11, §11.2](../fluxos/11-identidade-autenticacao-acl.md), [CFM, Sigilo Profissional](https://transparencia.cfm.org.br/codigo-de-etica-medica/).

[Inferido] Criar uma matriz de autorização por **papel × ação × categoria de informação × vínculo × finalidade**. Acesso administrativo do empregador ao ASO e à situação operacional não deve liberar automaticamente anamnese, hipóteses diagnósticas ou todos os resultados. Da mesma forma, administrador da plataforma e administrador comercial da clínica não precisam receber leitura clínica irrestrita por padrão. Prever acesso excepcional com motivo, prazo e auditoria.

[Confirmado] O schema não inclui tabela própria de usuários autorizados por empresa, dispositivos ou concessões/revogação de links. Há metadados de acesso a documentos e tabelas polimórficas de ACL, mas isso não define sozinho as relações exigidas pelos portais e pela TV. Fontes: [schema](../schema/schema.sql), [11, §11.1–11.2](../fluxos/11-identidade-autenticacao-acl.md).

[Inferido] Especificar guards do paciente/equipe/empresa, vínculos com mais de uma unidade, revogação de dispositivos e links, autenticação e autorização no download. Link assinado protege integridade/validade da URL; definir também quem pode usá-lo, possibilidade de encaminhamento e revogação. Para TV, definir identificação mínima exibida e evitar diagnóstico ou informação ocupacional na chamada pública.

## Histórico clínico, documentos e retenção

[Confirmado] `health_questions` tem `version`, mas sua chave única é `(tenant_id, code)` e `health_answers` aponta para uma pergunta sem snapshot do texto/opções. `measurement_types` contém unidade e limites de referência, enquanto `exam_measurements` guarda valores sem snapshot desses parâmetros. Fontes: [schema, linhas 567, 609, 760 e 801](../schema/schema.sql).

[Inferido] A presença de uma coluna `version` não garante histórico imutável. Definir versões preservadas de questionários, protocolos, unidades, intervalos e modelos. Um resultado antigo deve continuar interpretável após alteração do catálogo. Quando aplicável ao exame, intervalos dependem de método, população e contexto, não de um único mínimo/máximo editável.

[Confirmado] `documents` guarda hash, status e metadados de assinatura, sem entidade explícita para versões, substituição/retificação, múltiplas assinaturas ou evidência de validação. `appointment_exams` e `exam_results` não registram diretamente o profissional executor. Fontes: [schema, linhas 685, 775 e 831](../schema/schema.sql).

[Inferido] Definir autoria de execução, revisão, assinatura e liberação; impedir sobrescrita silenciosa do documento liberado; preservar versão anterior e motivo de retificação. Hash do arquivo e QR de consulta não substituem a validação da assinatura. Revisar a Policy “médico só assina o que executou” para acomodar os papéis profissionais efetivamente permitidos em cada exame.

[Confirmado] A análise 05 cita tutela da saúde como art. 11, II, “a”; na LGPD essa hipótese está na alínea **“f”**, enquanto “a” trata de obrigação legal/regulatória. Fonte: [análise 05, §5.5](05-seguranca-lgpd-conformidade.md), [LGPD, texto atualizado na Câmara](https://www2.camara.leg.br/legin/fed/lei/2018/lei-13709-14-agosto-2018-787077-normaatualizada-pl.pdf).

[Inferido] Separar base legal por finalidade de aceite de termos e de autorização para campanhas. Não usar um aceite genérico como condição universal para todo tratamento clínico ou ocupacional. O cadastro atual de termos no paciente não descreve sozinho versões, finalidades, revogação e histórico dessas escolhas.

[Confirmado] A NR-7 prevê guarda mínima do prontuário do empregado por 20 anos **após desligamento**, ressalvados seus anexos (§7.6.1.1). A Lei 13.787 usa **último registro** como marco para seu prazo mínimo. São marcos diferentes. Fontes: [NR-7](https://www.gov.br/trabalho-e-emprego/pt-br/acesso-a-informacao/participacao-social/conselhos-e-orgaos-colegiados/comissao-tripartite-partitaria-permanente/arquivos/normas-regulamentadoras/nr-07-atualizada-2022.pdf), [Lei 13.787, repositório oficial do Ministério da Justiça](https://bibliotecadigital.mj.gov.br/bitstream/1/1641/2/LEI_2018_13787.html).

[Inferido] Modelar política de retenção por categoria, marco inicial, exceção e impedimento de descarte. Cancelamento da assinatura, exclusão solicitada e fim do contrato comercial não autorizam automaticamente apagar prontuários. O fluxo 09 propõe exclusão em cascata do tenant, mas essa operação não deve ser o mecanismo padrão de desligamento; a própria descrição de “todas” as tabelas em cascata não corresponde às exceções do DDL.

[Confirmado] A Lei 13.787, art. 2º, §2º, trata do processo de digitalização com certificado ICP-Brasil **ou outro padrão legalmente aceito**. A análise 05 resume isso como exigência geral para validar laudo. Fonte: [Lei 13.787](https://bibliotecadigital.mj.gov.br/bitstream/1/1641/2/LEI_2018_13787.html).

[Inferido] Revisar a matriz regulatória por documento e processo com o responsável técnico: documento originalmente digital, digitalização, ASO, laudo e descarte de papel não são automaticamente a mesma situação. Manter ICP-Brasil como opção técnica prevista sem apresentar o resumo documental como prova universal de conformidade.

## Schema: correções e validações a planejar

| Constatação estática | Consequência e encaminhamento proposto |
|---|---|
| [Confirmado] Diagramas e migrations planejadas em 12/14 usam `patient_company`, `anamneses`, `checkins`, `laudos` e outras entidades ausentes no SQL, que usa `health_records`, `documents` etc. | [Inferido] Conciliar conceitos e relacionamentos; uma renomeação não explica perdas de histórico ou mudanças de cardinalidade. |
| [Confirmado] O processo de migração prevê CPF criptografado + HMAC; o DDL usa `cpf CHAR(11)` com índice direto, sem campo de hash. | [Inferido] Decidir criptografia e busca conjuntamente antes das migrations. Não aplicar cast criptografado sobre o campo atual presumindo que o ciphertext terá 11 caracteres. |
| [Confirmado] `room_states` tem PK por sala, sem unicidade de atendimento entre salas. | [Inferido] A regra de concorrência do fluxo 20 exige transação/travas e teste de duas chamadas simultâneas; não está comprovada pela PK da sala. |
| [Confirmado] `surveys` usa TINYINT UNSIGNED sem CHECK de 0–10; medições permitem valor numérico e textual simultaneamente ou ambos nulos. | [Inferido] Definir invariantes de entrada e constraints adequadas aos casos válidos. Não transformar ausência de medição em resultado “normal” por default. |
| [Confirmado] `messages` não guarda snapshot do conteúdo enviado nem chave própria única de idempotência. | [Inferido] Definir histórico e prevenção de duplicidade, incluindo retries/webhooks; limitar dados clínicos em mensagens e logs. |
| [Confirmado] `companies.cnpj` é CHAR(14), tipo que pode armazenar letras. Não há regra documentada específica para o formato alfanumérico. | [Inferido] Atualizar VO, máscaras, normalização, validação e integrações sem remover letras; não é necessário aumentar a coluna apenas por esse motivo. |

[Confirmado] A Receita Federal já iniciou emissão de CNPJ alfanumérico em 2026. Fonte: [comunicado oficial de 31/07/2026](https://www.gov.br/fazenda/pt-br/assuntos/noticias/2026/julho/receita-federal-gera-o-primeiro-cnpj-em-formato-alfanumerico).

[Confirmado] O changelog declara validação apenas estrutural, sem execução do SQL em servidor. O script desliga e religa `FOREIGN_KEY_CHECKS`. Não há homologação registrada do DDL atual. Fonte: [CHANGELOG](../schema/CHANGELOG.md), [observações anteriores de integridade](../outros/PENDENCIAS.md).

[Inferido] Homologar em banco descartável no motor e versão escolhidos: criação limpa, inspeção das FKs, inserções sintéticas válidas e inválidas, referência entre tenants, cancelamento/reagendamento, exclusões, rollback e concorrência. Confirmar suporte a MySQL e MariaDB separadamente se o produto anunciar ambos. Criar tabelas sem erro é necessário, mas não prova segurança de isolamento ou correção clínica.

## Financeiro e operação do SaaS

[Confirmado] O fluxo 10 e as tabelas `plans/subscriptions/invoices/payments` tratam da mensalidade paga pela clínica à plataforma. O catálogo tem preço básico por exame, mas não há modelo de cobrança clínica por pedido/item, contrato empresarial, pacote, pagador, recebimento ou repasse. Fontes: [10](../fluxos/10-assinatura-billing.md), [schema](../schema/schema.sql).

[Inferido] Separar **financeiro da plataforma** de **financeiro da clínica**. Validar qual mínimo é necessário: particular, cobrança por empresa, orçamento, tabela contratada, preço preservado no pedido, execução parcial, desconto e cancelamento. TISS e repasse podem ser posteriores se não fizerem parte da operação inicial; a ausência de financeiro mínimo deve ser uma exclusão expressa, não uma cobertura presumida pelas tabelas de assinatura.

[Confirmado] O fluxo 15 cita backup e restore por tenant, mas não define RPO/RTO, procedimento de restauração seletiva, gestão das chaves de criptografia ou critérios de recuperação de banco e storage em conjunto. O fluxo 09 simplifica extração de tenant para um filtro, embora haja dados de propriedade indireta, como pagamentos e pivôs de permissões.

[Inferido] Definir recuperação com consistência entre registros, documentos, versões e chaves; testar restauração sem sobrescrever dados de outras clínicas. Planejar limites de requisições, filas, uso de storage e tarefas pesadas para uma clínica não consumir todos os recursos compartilhados. Incluir timezone por clínica e regra explícita para datas locais, timestamps e vencimentos.

[Inferido] A inadimplência exige uma política operacional que preserve continuidade e acesso necessário aos registros: especificar ações permitidas em somente leitura/suspensão, exportação e responsabilidades de guarda. Não colocar disponibilidade clínica e recuperação de desastres apenas na última fase de hardening.

## Migração: riscos adicionais encontrados

[Confirmado] O processo de migração apresenta regras automáticas por login/registro (R2) e e-mail com similaridade (R5). A análise 07 registra logins duplicados. O mesmo processo usa PK crescente para delta de resultados e framingham, sem demonstrar que essas linhas são imutáveis. Fontes: [processo, resolução de identidade e carga delta](<../outros/Precordis — Processo de Migração dos Bancos Legados.md>), [07, §7.1](07-inconsistencias-modelagem.md).

[Inferido] Exigir unicidade contextual e evidência suficiente antes de vincular resultado clínico automaticamente. E-mail pode ser compartilhado por família ou empresa; similaridade não demonstra identidade. Na carga delta, detectar também atualizações/exclusões de linhas existentes; PK crescente só basta quando a natureza append-only estiver confirmada.

[Confirmado] O processo de migração manda mapear `agendamento.Status`, enquanto análise 04/07 e fluxo 13 determinam descartá-lo e derivar o estado de outras flags. Há ainda divergência entre manter a sincronização externa após o corte e descartá-la após ETL. Fontes: [processo, mapeamento e virada](<../outros/Precordis — Processo de Migração dos Bancos Legados.md>), [13](../fluxos/13-plano-migracao-dados.md), [08, matriz](../fluxos/08-visao-produto-bounded-contexts.md).

[Inferido] Conciliar o de-para clínico e a estratégia de convivência. Arquivos de pacientes devem ir apenas para storage autorizado, fora do repositório. A descoberta do Access continua importante para a migração; não deve definir toda a arquitetura do SaaS ou bloquear módulos independentes para uma clínica nova.

[Inferido] O plano de rollback deve considerar o momento da primeira escrita no novo sistema, não só um horário de corte. Reabrir o legado após novos atendimentos sem reconciliação perde continuidade do histórico. Definir congelamento, evidência de dados novos e responsabilidades de recuperação, mantendo ações no legado fora deste trabalho.

## Prioridades e critérios de aceite propostos

[Inferido] **P0** abaixo significa decisão/contrato necessário antes da implementação afetada; **P1**, capacidade a entregar antes do uso correspondente em produção; **P2**, evolução condicionada ao negócio. Não representa autorização para modificar o legado ou produção.

| Prioridade | Entrega proposta | Evidência de aceite |
|---|---|---|
| [Inferido] P0 | Conciliar arquitetura e fontes: AGENTS, README, 09/12/14/15/20/21, migração e DDL. | Uma decisão rastreável; comandos, diagramas, DDL e critérios de pronto compatíveis; links vigentes. |
| [Inferido] P0 | Definir núcleo clínico e ocupacional com a operação e responsável técnico. | Exemplos aprovados de check-up, exame avulso e avaliação ocupacional, incluindo repetição, pendência, vínculo histórico e ASO. |
| [Inferido] P0 | Fixar matriz de acesso e invariantes de isolamento. | Empresa sem acesso automático ao prontuário; contexto obrigatório; relações entre tenants rejeitadas; políticas de unidade e histórico. |
| [Inferido] P0 | Corrigir dependências e escopo do MVP; separar contenção do legado. | Etapas executáveis sem exigir alteração não autorizada; migração parcial/completa definida; P01/P02 decididas. |
| [Inferido] P1 | Fundação homologada e primeiro fluxo vertical clínico. | Aplicação reproduzível, motor fixado, CI, dois tenants sintéticos e fluxo pedido/agenda → execução → resultado → liberação. |
| [Inferido] P1 | Fluxo ocupacional, histórico e documentos. | Avaliação e ASO rastreáveis; mudança de cadastro não reescreve passado; autoria e retificação preservadas. |
| [Inferido] P1 | Recuperação, retenção, monitoramento e financeiro mínimo aprovado. | Restore ensaiado, RPO/RTO definidos, política de guarda e desligamento, cobrança clínica conforme escopo. |
| [Inferido] P2 | Integrações avançadas, BI, TISS, PACS, transmissão eSocial e IA. | Escopo confirmado por clínica/contrato e integração homologada; antecipar se indispensável à operação inicial. |

[Inferido] Testes de aceite essenciais: tentativa de referência A→B; header de tenant incompatível com usuário; job A seguido de job B no mesmo worker; exportação e download de outra clínica; mudança de empregador sem liberação retroativa; reemissão de documento com histórico; alteração de pergunta sem mudar resposta antiga; duas chamadas simultâneas; retry de mensagem sem duplicação; restore de uma clínica preservando as demais.

## Decisões de negócio ainda necessárias

1. [Inferido] Quais exames serão realizados no lançamento, por unidade, e quais serão terceirizados? Quais precisam de amostra, imagem ou medição específica?
2. [Inferido] A clínica executa apenas exames ocupacionais ou também elabora/gerencia PCMSO e recebe inventário de riscos? Quem fornece e valida esse contexto?
3. [Inferido] Quais documentos, estados e relatórios a empresa poderá consultar? Quais permanecem exclusivos da equipe clínica e do paciente?
4. [Inferido] Como tratar trabalhador com vários empregadores, mudança de função, retorno e exames complementares pendentes?
5. [Inferido] Haverá exportação ou transmissão eSocial? Quais eventos e responsabilidades estão contratados?
6. [Inferido] Como se cobra exame particular/empresa: tabela, pacote, contrato, faturamento mensal, execução parcial? Qual mínimo financeiro entra no MVP?
7. [Inferido] Qual volume esperado de clínicas e exames, requisitos de disponibilidade e recuperação, e exigência de banco dedicado?
8. [Inferido] O primeiro lançamento será operação completa da Precordis ou agenda/recepção com convivência do sistema externo? Qual é o limite explícito dessa convivência?

[Inferido] Essas decisões devem alimentar suas fontes normativas nos fluxos existentes, não virar uma segunda especificação permanente neste relatório. Este arquivo registra a revisão; as divergências que impedem decisões técnicas ficam referenciadas em [PENDENCIAS.md](../outros/PENDENCIAS.md).
