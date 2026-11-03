# 18 — Riscos e pontos de atenção da migração

## 18.1 O que pode quebrar

| Risco | Probabilidade | Impacto | Mitigação |
|---|---|---|---|
| **Access desconhecido** é a fonte real do laudo e não está mapeado | Alta | Alto | Descoberta na Fase 0/7 (pergunta #2); não cortar o Access antes da Fase 5 validada |
| **Sync do Access parado** (sem alarme desde 29/10) passa despercebido | Alta | Médio | Monitorar o sync enquanto existir; alarme em `log_sync` |
| **Dedupe de paciente** funde ou separa errado (38% sem CPF, 219 CPFs duplicados) | Alta | Alto | Match por confiança + fila de revisão humana; nunca fundir automático em match fraco |
| **~30% dos agendamentos sem paciente** viram paciente parcial | Certa | Médio | Fluxo previsto no ETL; completar no próximo atendimento |
| **Encoding/datas** quebram na importação | Alta | Médio | Parser por linha/coluna; amostragem pós-ETL |
| Índice único novo **rejeita dados legados** | Alta | Médio | Resolver duplicatas antes de aplicar constraints |
| **Senhas não migram** (md5/texto/5 dígitos) | Certa | Baixo | Forçar redefinição no go-live; comunicar pacientes |
| **Laudos (719 MB)** com paths/nomes inconsistentes | Média | Médio | Mover por `Empresa/Paciente`, vincular a `patient_id`, conferir contagem |
| Regra oculta por empresa (além de SAMARCO) | Média | Médio | Levantar com o negócio (pergunta #14) antes da Fase 3 |
| Ambiente de produção ambíguo (`beta.`/2 domínios) | Média | Médio | Confirmar produção real (pergunta #3) antes do corte |

## 18.2 Dependências externas

- **Microsoft Access + script de sync** (fora do repositório, máquina Windows).
- **n8n** (2 instâncias, uma de terceiro) para WhatsApp — precisa virar operador formal ou ser internalizado.
- **Locaweb** (hospedagem + DBaaS MySQL 5.7 EOL + FTP).
- **Gateway de pagamento** (a definir) e **autoridade certificadora ICP-Brasil** (se assinar laudo).
- **WhatsApp Business API** por trás do n8n.

## 18.3 Janela de corte

- A operação é concentrada em dias úteis, ~10–12 pacientes/dia, praticamente nada no fim de semana ([01](../analise/01-inventario.md) §1.9) — há **janela natural de corte no fim de semana**.
- Preferir **strangler** (módulo a módulo) com corte final curto: congelar escrita no legado numa sexta à noite, rodar ETL incremental, validar sábado, abrir o novo na segunda.
- Confirmar tolerância do negócio (pergunta #21).

## 18.4 Treinamento de usuários

- Perfis a treinar: recepção/agenda, apoio, médico/laudador, admin. Hoje usam logins de setor; passam a login nominal com 2FA — comunicar e treinar.
- Pacientes: comunicar o novo acesso (CPF + senha definida / link de laudo).
- Material curto por papel + acompanhamento na primeira semana pós-corte.

## 18.5 Rollback

- Manter o legado **somente leitura** por um período após o corte.
- ETL idempotente + `legacy_references` permitem reimportar; snapshots antes de cada etapa.
- Critérios objetivos de go/no-go: relatório de inconsistências aprovado, contagens batendo, amostra de 30 pacientes conferida, testes de isolamento verdes.

## 18.6 Contrato vigente de corte e recuperação

- [Confirmado] Este inventário histórico não autoriza ações no legado nem fixa janela de corte. O [fluxo 13](13-plano-migracao-dados.md) define o contrato vigente, revisão de identidade e delta; [24](24-seguranca-retencao-operacao.md) define recuperação. Retorno ao legado após primeira escrita nova exige reconciliação, nunca simples troca de DNS por horário.
