# 16 — Novas funcionalidades e melhorias

> Separadas das correções estruturais. Valor de negócio e complexidade **P/M/G**. Priorização sugerida; decisão do negócio.

## Alta prioridade

| Funcionalidade | Valor | Complexidade |
|---|---|---|
| **Portal do paciente** — agendar online, ver preparo do exame, check-in antecipado, **baixar laudo por link assinado** (substitui o `manager.php` inseguro) | Alto — reduz telefone e resolve a falha de acesso a laudo | M |
| **Notificações automáticas** (e-mail/WhatsApp/SMS): confirmação, lembrete, preparo, **resultado liberado**, pesquisa pós-atendimento — em fila, com operador formalizado (hoje é n8n de terceiro sem contrato) | Alto — já é o que mais usam | M |
| **Painel de TV em tempo real** (Reverb, sem polling) + **chamada por QR/totem** | Alto — já existe, fica robusto e corrige B2/B3 | M |
| **Assinatura digital de laudo (ICP-Brasil)** + verificação por QR | Alto — conformidade (Lei 13.787/2018) e permite dispensar papel | G |
| **Dashboard operacional** (exames/dia, ocupação de agenda, no-show, tempo de espera por sala, NPS, pacientes a reconvocar) | Alto — hoje não existe visão nenhuma | M |
| **Identidade única do paciente** com busca e merge assistido | Alto — base de tudo | M |

## Média prioridade

| Funcionalidade | Valor | Complexidade |
|---|---|---|
| **Portal da empresa cliente** — exames ocupacionais dos funcionários, ASO, relatórios | Médio/Alto p/ ocupacional | G |
| **Priorização legal na chamada** (idoso/gestante/PCD — Lei 10.048/2000) | Médio — conformidade + experiência | P |
| **Agenda por horário/sala/equipamento** (além do "por dia" atual), com capacidade e bloqueios | Médio — depende do fluxo da clínica (pergunta #8) | M |
| **Importador do Access como conector monitorado** (enquanto o Access existir) | Médio — evita o ETL quebrar silencioso | M |
| **Modelos de laudo configuráveis por tenant** | Médio | M |

## Baixa prioridade / futuro

| Funcionalidade | Valor | Complexidade |
|---|---|---|
| **Faturamento de convênio (TISS)** + repasse médico | depende de pergunta #12; TISS 04.03.00 vigente | G |
| **Integração com equipamentos/PACS (DICOM)** e laboratórios de apoio | se a clínica tiver imagem/lab próprio | G |
| **API pública + white-label por tenant** | produto | M |
| **SSO (Google Workspace)** para a equipe | conveniência | P |
| **IA (com cautela)**: rascunho de laudo a partir de modelos, triagem de agendamento, resumo das pesquisas de satisfação | Médio | M–G |

**Riscos de IA a sinalizar explicitamente**: rascunho de laudo é **apoio**, nunca decisão clínica — exige revisão e **assinatura humana** do médico; dado de saúde não pode ser enviado a serviço de IA sem base legal, contrato de operador e, idealmente, processamento que não treine modelos com o dado. Triagem de agendamento não pode negar atendimento automaticamente. Toda saída de IA sobre dado clínico entra na trilha de auditoria.
