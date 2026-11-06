# 19 — Perguntas em aberto para o negócio

> Pontos que o código **não resolve** e que precisam de decisão humana antes/durante o projeto. Numeradas para referência.

## Sobre o sistema atual

1. **Versão real de PHP em produção.** O `.htaccess` diz `php73-script`, mas `agendaprecordis/INSTALAR_FTP.txt` fala em PHP 5.2.17, e há código com `??` (PHP 7+). Qual é a versão efetiva do host `beta.precordis.com.br`? (Decide se há como rodar os patches de segurança no legado durante a convivência.)
2. **O Microsoft Access.** Onde fica o arquivo, qual seu tamanho, desde quando existe, quem o alimenta e quem mantém o script de sincronização (`log_sync` mostra ~710 execuções/dia)? É a **fonte real do resultado clínico** — a migração depende de mapeá-lo. O sync está **parado desde 29/10/2026 10:07** no momento da análise: foi intencional?
3. **Dois domínios de produção**: `precordis.com`, `precordis.com.br`, `beta.precordis.com.br`. Qual é o ambiente de produção real? O prefixo `beta.` sugere que o "sistema de produção" roda num subdomínio de homologação.
4. **n8n de terceiro** (`n8n.platform.andreandradecarvalho.site`): há contrato de operador de dados? Pode ser internalizado?
5. **`info.php` com links de afiliado Monetizze**: foi colocado intencionalmente ou é conteúdo injetado (indício de comprometimento)? Convém varrer o host.
6. **`send-mail.php` morto desde 2022 com 1.297 mensagens pendentes**: essas mensagens ainda precisam ser enviadas ou podem ser descartadas?
7. **`checkinCliente` inativo desde 03/2026**: foi substituído pelo `precordistv` ou ainda é usado em algum ponto?

## Sobre regras de negócio

8. **Agenda por dia, sem horário**: a clínica quer manter a lógica de "capacidade diária" ou passar a agenda por horário/sala/equipamento no sistema novo?
9. **Capacidade**: existe um limite diário de check-ups? Por sexo? Há regra de quantos ginecológicos/dia?
10. **Priorização legal** (idoso/gestante/PCD — Lei 10.048/2000): deve ser implementada na chamada/TV?
11. **Perfis/papéis**: hoje há admin, médico, recepção (setor), apoio (setor), TV. Quais papéis a clínica quer de fato, e quem pode mudar cada status do exame?
12. **Empresas/convênios**: haverá faturamento de convênio (TISS) pela plataforma, ou a empresa é só agrupador de laudo? Preço por empresa existe?
13. **"Cliente especial" e "primeira vez"**: o que significam operacionalmente (prioridade? fluxo diferente?)?
14. **Regra SAMARCO (matrícula obrigatória)**: é a única exceção por cliente ou há outras regras por empresa não escritas no código?
15. **Laudo**: continuará sendo produzido no Access e publicado como PDF, ou o novo sistema vai gerar/assinar o laudo? Há exigência de assinatura digital ICP-Brasil / certificação SBIS-CFM?

## Sobre o produto SaaS

16. **Multi-tenant**: além da Precordis, quantas clínicas no primeiro ano? Alguma exige banco dedicado (enterprise)?
17. **Multiunidade**: a Precordis tem filiais físicas? (`empresas` é de clientes, não de unidades da clínica.)
18. **Billing**: qual gateway o negócio prefere (Asaas/Pagar.me/Iugu/Stripe)? Precisa de NFS-e da assinatura? Modelo de plano (por unidade? por volume de exames? por profissional?).
19. **Portal do paciente/empresa**: é escopo do MVP ou fase posterior?
20. **Idioma do código de domínio**: português ou inglês? (Decisão de Clean Code; recomendação em [14](14-arquitetura-laravel.md).)
21. **Janela de corte**: a clínica tolera parar o sistema por algumas horas num fim de semana para a virada, ou precisa de convivência longa (strangler)?
22. **Retenção de 20 anos**: onde os laudos históricos ficarão (storage por tenant)? Há orçamento para storage + backup de longo prazo?

## 19.1 Encaminhamento após conciliação — 03/11/2026

- [Confirmado] Convenções técnicas resolvidas: banco único (09), Vuexy/Blade/Vite e inglês no código (14), ETL legacy_references (13), MVP completo com resultados antes de migração (20). A lista histórica acima não reabre essas decisões.
- [Inferido] #1/#3/#5: levantamento operacional externo; nenhuma intervenção no legado neste trabalho. #2 Access bloqueia migração clínica, não fundação. #8/#9: padrão de slot inicial no fluxo 22; capacidade e conflitos entre agenda diária/horário são configuração a homologar. #18: gateway permanece escolha comercial antes da etapa 9; Asaas é proposta, não conta/provedor já contratado.
- [Inferido] Parâmetros clínicos antes de ativar um tenant: portfólio de exames, terceirização/coleta/imagens, protocolos/riscos e periodicidade ocupacional, responsável por PCMSO, modelo de ASO, profissionais habilitados, regras de acesso da empresa e histórico de vínculo. Não inventar respostas clínicas nesta conciliação.
- [Inferido] Parâmetros comerciais antes da etapa afetada: tabela/contrato e pagadores, financeiro mínimo, nº de unidades/usuários/volume, SLA e necessidade de banco dedicado; eSocial fora da transmissão automática inicial, conforme fluxo 23.
- [Inferido] Produção só após homologação dos fluxos por responsável técnico/operação, assinatura conforme documento e recuperação ensaiada. Pendência de parametrização não deve ser marcada como resposta já fornecida pelo negócio.

<a id="autorização-para-início-real-de-exames--decisão-pendente"></a>

## Autorização para início real de exames — decisão do usuário

- [Confirmado] Cadastro profissional não comprova habilitação; o fluxo 11 exige autorização conforme habilitação/atribuição/tipo e o fluxo 22 exige profissional autorizado. Preparação e atribuição administrativa de sala não implementam essa autorização.
- [Confirmado] O usuário decidiu: **“Concessão explícita por profissional e tipo de exame”**. A decisão define o contrato de autorização do sistema; não equivale à concessão para qualquer profissional real nem à aprovação clínica do conteúdo/P14. Cadastro, permissões gerais ou sala atribuída não substituem a concessão específica.

## Desfechos excepcionais e financeiro — decisão do usuário

- [Confirmado] O [fluxo 22 §22.2/22.4](22-atendimento-clinico.md#224-financeiro-da-clínica) prevê `refused`/`incomplete`, preservação das tentativas e decisão dos itens cobrados em execução parcial. Quando a decisão foi solicitada, o [financeiro](../outros/contas-a-receber.md) registrava somente valor integral do pedido; não implementa ajuste por item, estorno ou parcelamento. Cadastro dos estados no schema não materializa uma política comercial.
- [Confirmado] Na retomada de 05/11/2026 foi solicitada ao usuário a política inicial para exame recusado/incompleto: manter valores com revisão financeira explícita, cobrar somente concluídos mediante ajuste explícito, ou política por clínica. O usuário escolheu: **“Definir uma política configurável por clínica”**. Não foi escolhido um modo padrão para todas as clínicas.
- [Inferido] Com a política configurável definida, especificar como pendências/repetições e fechamento do pedido se relacionam com recebíveis já abertos/baixados. A continuidade técnica independente pode reforçar integridade e leitores existentes; transições excepcionais devem usar a política explicitamente configurada e preservar sua versão, sem alterar preços/baixas existentes automaticamente.

[Confirmado] [Configuração e histórico por clínica](../outros/politica-financeira-excecoes.md) implementados na etapa 4, sem aplicar um modo padrão ou modificar cobranças existentes. Integração ao registro após início entregue; ajustes explícitos de contas/revisão financeira seguem no próximo incremento.


<a id="repetição-após-desfecho-excepcional--decisão-pendente"></a>

## Repetição após desfecho excepcional — decisão do usuário

- [Confirmado] A implementação atual mantém uma conta agregada por pedido e uma tentativa excepcional vinculada ao item, com política financeira capturada. Preparação regular não repete itens refused/incomplete; evidências e limites estão em [desfechos excepcionais](../outros/desfechos-excepcionais-exames.md).
- [Confirmado] Em 06/11/2026 foi solicitada ao usuário a regra para repetir um exame após recusa/incompletude: manter uma cobrança por item, exigindo revisão financeira explícita antes de liberar nova cobrança/baixa após repetição; ou exigir outro pedido e cobrança independente para cada nova tentativa. O usuário respondeu: **“Uma cobrança por item; revisão financeira explícita antes de liberar nova cobrança/baixa após repetição”**. Essa é a regra escolhida; não criar cobrança por tentativa nem outro pedido automaticamente.
- [Confirmado] A repetição prepara outra tentativa no mesmo item, preservando anterior/preço/motivo/política/respostas e contas existentes. Nova cobrança ou baixa exige revisão financeira explícita mesmo se o novo exame concluir ou a política vigente permitir cobrança. A implementação progressiva e seus limites ficam em [desfechos excepcionais](../outros/desfechos-excepcionais-exames.md#repetir-no-mesmo-item).
