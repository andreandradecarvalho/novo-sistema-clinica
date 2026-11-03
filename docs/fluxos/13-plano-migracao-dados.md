# 13 — Contrato único de migração e convivência

[Confirmado] Fonte normativa do ETL. Concilia P01/P10 em 03/11/2026 e substitui os contratos concorrentes do [processo histórico](<../outros/Precordis — Processo de Migração dos Bancos Legados.md>). Precordis é tenant; Samarco é empresa. Legado é somente leitura. SQL e transformação somente em local/staging autorizado, sem copiar dados de saúde para o repositório.

## 13.1 Destinos e pré-requisitos

- [Inferido] MVP completo depende das etapas 1–6 de [20](20-plano-desenvolvimento.md); etapa 7 importa cadastros, agenda, anamnese, execução, resultados e documentos. Não há migração parcial presumida que dispense resultados. Fundação para novos tenants independe da descoberta do Access.
- [Inferido] Descobrir estrutura/chaves do Access e sincronizador por leitura autorizada antes do ETL clínico. Preferir dumps de estrutura; dados reais só em ambiente restrito de migração. Nenhuma alteração na árvore ou banco legado nesta tarefa.
- [Inferido] Fontes restauradas são read-only; normalização usa stg_* separado. Nunca executar DDL/DML remoto. Arquivos clínicos vão para storage autorizado fora do Git; não indexar/copiar private/, compactacao/, arquivos_zip/ no trabalho de conhecimento do legado.

## 13.2 Estratégia geral

- [Inferido] Nome canônico: **legacy_references**. Não criar legacy_map em paralelo. Preservar o conjunto de requisitos dos dois contratos: tenant_id, source_db, source_table, source_id (string para PK composta), target_type, target_id, match_method, confidence, row_hash e run_id.
- [Inferido] Chave de idempotência `(tenant_id, source_db, source_table, source_id, target_type)` permite uma origem gerar entidades de tipos diferentes; unpivot 1:N do mesmo tipo usa source_id composto determinístico, por exemplo PK + código de coluna. target_type usa enum/morph map permitido, nunca nome de classe recebido externamente.
- [Inferido] migration_runs identifica execução, versão do importador, watermark/hash e resumo; migration_issues registra rejeições sem copiar dado clínico bruto; patient_match_candidates preserva candidatos e decisões humanas. Mapear alterações e decisões por origem, sem sobrescrever evidência anterior de execução.
- [Inferido] Importar de forma idempotente por lotes e transação; falha isolada usa savepoint ou transação própria, sem confirmar metade de uma unidade clínica. Reexecução com hash igual ignora; hash alterado atualiza somente registro ainda elegível, preservando versão de documento clínico liberado.
- [Inferido] Comandos previstos, ainda não implementados: legacy:stage, legacy:profile, legacy:import --tenant --dry-run, legacy:reconcile, legacy:files --verify. Rollback por execução requer verificar alterações posteriores; não é DELETE indiscriminado de prontuários.

## 13.3 Mapeamento canônico

| Fonte | Destino | Regra |
|---|---|---|
| [Inferido] empresas | companies/company_units | Hierarquia validada, órfãos e ciclos em issues. |
| [Inferido] usuario + info_usuario | patients; users/professionals para equipe | Identidade e CPF normalizado; senha nunca importada. Contas sem hash fraco, convite de definição só no go-live autorizado. |
| [Inferido] empresa/cargo/matrícula do cadastro | patient_company_links | Histórico conhecido com origem; não inventar datas de admissão/desligamento. Campos atuais no paciente são resumo, sem concessão de acesso. |
| [Inferido] termos_aceita* | patient_consents | Manifestação/origem conhecida, sem inventar versão ou base legal retroativa. |
| [Inferido] saude_usuario | health_records/health_answers | Perguntas versionadas; ciphertext pela aplicação e snapshot do modelo. |
| [Inferido] agendamento | appointments/appointment_events | Descartar Status e Remarcacoes; derivar estado de flags conforme §13.5. Sem paciente inequívoco: parcial + revisão. |
| [Inferido] checkincliente/espelho_atendimento | visits/exam_executions + appointment_exams quando houver reserva | Unpivot por de-para aprovado; preservar histórico e origem. Espelho não é fonte de nova identidade clínica por nome isolado. |
| [Inferido] relatorio_prec/framingham e Access validado | exam_results/exam_measurements/exam_conclusions | Unidades e interpretação aprovadas pelo responsável técnico; preservar erros de origem como issues, sem corrigir valores clínicos por suposição. |
| [Inferido] arquivos autorizados | documents/document_deliveries quando comprovada | Hash, dono inequívoco, storage privado. Não inventar assinatura ou prova de entrega. |
| [Inferido] salas/chamados | rooms/tv_calls | Não importar PIN em claro nem sessão de dispositivo. Estado transitório de sala começa vazio. |
| [Inferido] notificações/pesquisas | messages/surveys ou arquivo frio | Nunca importar como fila ativa; manter anonimato onde vínculo não é comprovável. |

## 13.4 Identidade e delta

- [Inferido] CPF válido + ausência de conflito documental é candidato forte; divergência de dados relevantes vai para revisão. Login/registro só pode casar automaticamente quando unicidade contextual estiver provada. Nome+nascimento pode gerar candidato, não fusão automática de prontuário em caso ambíguo. E-mail compartilhado/similaridade de nome não é identidade suficiente para carga clínica automática.
- [Inferido] Toda associação clínica ambígua exige revisão nominal registrada. Homônimos, duplicados e registros parciais não são descartados para satisfazer índice único. Retomar decisões humanas na reexecução.
- [Inferido] Delta cobre inserts, updates e exclusões lógicas detectáveis. Watermark de PK somente em fonte comprovadamente append-only; demais fontes por timestamp confiável ou comparação de hash com snapshot. Ausência na extração não autoriza exclusão clínica automática.

## 13.5 Status de agendamento

[Inferido] Precedência de transformação: DesistiuCancelou='SIM' → cancelado; FaltouNoDia=1 → no_show; evidência de chegada → checked_in; ConfirmaAgendamento='on' → confirmado; senão → pre_agendado. Registrar conflitos entre flags como issue. Execução/resultado não deriva apenas dessa coluna; vive nas entidades de fluxo 22.

## 13.6 Convivência, corte e reconciliação

- [Inferido] Access/sincronizador pode permanecer como fonte durante convivência explicitamente limitada. Nenhuma integração contínua é presumida: caso necessária antes do corte, especificar conector monitorado e homologado sem alterar o legado neste trabalho.
- [Inferido] Corte completo só ocorre quando resultados e autoria estiverem homologados no novo sistema. Desligamento do sincronizador/legado é ação operacional futura sob autorização própria, fora da correção documental. Não tratar seu desligamento como entregue.
- [Inferido] Ensaiar pelo menos duas cargas completas: origem = importado + rejeitado/descartado com motivo; conferir hashes e zero órfão clínico; todos os candidatos clínicos revisados. Amostra estratificada mínima de 50 pacientes ou toda população se menor, validada por médico e operação. Na virada, repetir verificações integrais automatizadas e amostra humana mínima de 10.
- [Inferido] Rollback por retorno ao legado só antes da primeira escrita operacional no novo. Após nova escrita: congelar, reconciliar e executar recuperação aprovada; horário do relógio não garante segurança. Registrar go/no-go, origem/destino das escritas e plano de continuidade.
