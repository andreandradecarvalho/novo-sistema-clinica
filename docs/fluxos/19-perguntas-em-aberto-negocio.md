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
