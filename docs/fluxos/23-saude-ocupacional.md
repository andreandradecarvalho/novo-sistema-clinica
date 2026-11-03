# 23 — Saúde ocupacional e ASO

[Confirmado] Fonte funcional do núcleo ocupacional do SaaS, criada na correção de pendências de 03/11/2026. Compartilha [atendimento e exames](22-atendimento-clinico.md); autorização e guarda seguem [24](24-seguranca-retencao-operacao.md). Modelagem proposta é marcada [Inferido]; a existência de DDL não comprova aplicação nem conformidade homologada.

## 23.1 Fronteiras

- [Inferido] Tenant é a clínica; empresa cliente é empregador/pagador dentro da clínica. Unidade da clínica (`units`) e estabelecimento do empregador (`company_units`) são conceitos diferentes.
- [Inferido] `patient_company_links` registra vínculo e seus períodos, matrícula, estabelecimento, setor e função. Mudança de função/estabelecimento encerra uma versão e cria outra; múltiplos vínculos simultâneos são permitidos. Campos de empresa/cargo no paciente são somente resumo legado, nunca fonte do ASO nem regra exclusiva de acesso.
- [Inferido] `occupational_programs` registra referência/versionamento do PCMSO, responsável e contexto de riscos proveniente do PGR quando aplicável. Não pressupõe que toda organização esteja obrigada a PCMSO nem que o SaaS produza PGR/LTCAT. Origem, dispensa quando aplicável e responsável pela informação são explícitos.
- [Inferido] `occupational_protocols` guarda versão dos exames/regras aplicáveis ao contexto. Exposições, função, vigência e fonte são preservadas no snapshot da avaliação. A estrutura inicial JSON versionada admite evolução de riscos; não é substituto de validação médica nem de um módulo completo de segurança do trabalho.

## 23.2 Avaliação e ASO

[Confirmado] A NR-7 distingue admissional, periódico, retorno ao trabalho, mudança de riscos ocupacionais e demissional (§7.5.6), prevê conteúdo mínimo do ASO (§7.5.19.1) e periodicidades dependentes do contexto. Fonte: [NR-7 oficial](https://www.gov.br/trabalho-e-emprego/pt-br/acesso-a-informacao/participacao-social/conselhos-e-orgaos-colegiados/comissao-tripartite-partitaria-permanente/arquivos/normas-regulamentadoras/nr-07-atualizada-2022.pdf).

1. [Inferido] Identificar paciente e versão de vínculo; registrar motivo da avaliação, data, estabelecimento/função e contexto de riscos.
2. [Inferido] Selecionar protocolo vigente, preservando snapshot; gerar itens de pedido e controlar exames necessários, realizados, dispensados com justificativa e pendentes.
3. [Inferido] Registrar execução e resultados conforme fluxo 22; avaliação pode atravessar vários atendimentos. Não reutilizar automaticamente prazo de check-up “+1 ano”.
4. [Inferido] Médico registra avaliação clínica e conclusão; sistema não determina aptidão por algoritmo de laboratório. Fechamento verifica autoria, contexto e justificativas das pendências.
5. [Inferido] `aso_records` materializa versão estruturada vinculada à avaliação e à versão em `documents`. Snapshot contém identificação de empregador/trabalhador/função, riscos pertinentes, exames/datas, conclusão e identificação médica. Validar campos obrigatórios antes de emitir; não tratar todo exame complementar isolado como ASO.
6. [Inferido] Disponibilizar ao trabalhador por canal autorizado, registrando entrega/disponibilização em `document_deliveries`. Retificação preserva todas as versões e vincula o documento substituído.

- [Inferido] Estados da avaliação: `draft → awaiting_exams → ready_for_review → completed`; `canceled` exige motivo. Conclusão clínica permanece nula enquanto pendente; valores iniciais `fit`/`unfit` são registrados pelo médico, com observações clínicas em área restrita e campos específicos quando exigidos pelo contexto. Não converter pendente em inapto automaticamente.
- [Inferido] Identificações e conclusões clínicas sensíveis dos snapshots usam ciphertext em campo próprio; metadados operacionais mínimos ficam separados. Snapshot nunca deriva do cadastro atual durante reimpressão de documento antigo.

## 23.3 Empresa e sigilo

- [Inferido] Acesso empresarial exige `company_user_access`, empresa exata, vigência e permissão específica. Por padrão, permite situação operacional e ASO liberado daquele empregador/contexto; não permite anamnese, hipóteses diagnósticas ou resultados complementares completos. Exceções exigem finalidade/base e autorização específicas, avaliadas pela clínica.
- [Inferido] Novo empregador não herda acesso a histórico de outro vínculo. Encerramento de vínculo não destrói prontuário; prazo de visibilidade empresarial e guarda são políticas distintas. Trocar `patients.company_id` não concede acesso retroativo.
- [Confirmado] O sigilo dos exames de trabalhadores é tratado no art. 76 do [Código de Ética Médica](https://transparencia.cfm.org.br/codigo-de-etica-medica/), inclusive perante exigências empresariais, com a exceção prevista no artigo.

## 23.4 eSocial e integrações

- [Confirmado] S-2220 corresponde ao monitoramento de saúde; S-2240 às condições ambientais/agentes nocivos. Fonte: [manual oficial SST](https://www.gov.br/esocial/pt-br/empresas/manual-web-geral/manual-web-geral/).
- [Inferido] Escopo inicial conservador: disponibilizar ASO e relatório autorizado; **não há transmissão automática eSocial prometida no MVP**. Exportação específica ou transmissão exige contrato, leiaute vigente, fontes de riscos, autorização do empregador e homologação. Não gerar S-2240 a partir apenas de resultados clínicos.
- [Inferido] Integração futura deve versionar payload, recibo, retificação, erros e idempotência; certificados e credenciais ficam em cofre/ambiente autorizado. Catálogo de eventos e prazos regulatórios não deve ser hardcoded neste documento.

## 23.5 Aceite e configuração da clínica

- [Inferido] Testar cada motivo de avaliação, vínculo simultâneo, alteração de função sem reescrever passado, exame pendente/dispensado, emissão e retificação com médico, disponibilização registrada, bloqueio de outro empregador/tenant e ausência de acesso empresarial à anamnese.
- [Inferido] Antes de ativar ocupacional em uma clínica, o responsável técnico deve aprovar seus protocolos, periodicidades, modelos, responsabilidades de PCMSO e regras de acesso. São parâmetros de implantação, não pré-requisito para inventar protocolos clínicos durante o desenvolvimento.
