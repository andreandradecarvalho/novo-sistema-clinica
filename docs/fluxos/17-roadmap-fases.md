# 17 — Roadmap do produto

[Confirmado] A numeração, dependências, estados e aceite têm fonte única no [fluxo 20](20-plano-desenvolvimento.md). Não manter fases concorrentes neste arquivo. As fases do ETL em materiais históricos não substituem etapas do produto.

- [Inferido] Primeiro: governança de leitura do legado e fundação; depois cadastros/vínculos, exames/anamnese, agenda/atendimento, TV e resultados/ocupacional. Implementar slices verticais testáveis dentro dessas etapas.
- [Inferido] MVP clínico completo: etapas 0–7, incluindo resultados/laudos/ocupacional antes da migração clínica completa. Portais e automações ampliadas seguem depois; entrega de documentos ao trabalhador/paciente já tem mecanismo autorizado na etapa 6.
- [Inferido] Billing comercial, portais e BI seguem etapas 8–11. Integrações como TISS, PACS e transmissão eSocial dependem de escopo específico. Descoberta do Access condiciona migração, não instalação da fundação.
- [Confirmado] Legado somente leitura; contenção operacional externa não é etapa de modificação autorizada por este roadmap.
