# Bancos legados — acesso e estrutura

[Confirmado] Inventário fornecido pelo usuário e registrado na análise 01; nenhuma conexão remota foi realizada nesta correção. Legado somente leitura. Senhas mascaradas, sem valores reais neste documento. Credenciais ficam no ambiente autorizado, nunca no Git.

| Estado | Uso | Host | Banco | Usuário | Senha | Prefixo de ambiente |
|---|---|---|---|---|---|---|
| [Confirmado] | Principal | precordis2016.mysql.dbaas.com.br | precordis2016 | precordis2016 | *** | LEGACY_MAIN |
| [Confirmado] | Agenda/check-in | agendaprec.mysql.dbaas.com.br | agendaprec | agendaprec | *** | LEGACY_AGENDA |
| [Confirmado] | TV/resultados | samarco.mysql.dbaas.com.br | samarco | samarco | *** | LEGACY_TV |

[Inferido] Para cada prefixo, configurar somente no .env/cofre autorizado: _HOST, _PORT, _DATABASE, _USERNAME, _PASSWORD e _CHARSET. Exemplos de nomes: LEGACY_MAIN_PASSWORD, LEGACY_AGENDA_PASSWORD, LEGACY_TV_PASSWORD. Não preencher valores em documentação ou .env.example. Para ETL, apontar essas conexões a cópias read-only de staging, sem presumir autorização de acesso remoto.

## Dicionário e relações

- [Confirmado] Fonte estrutural única: [análise 06, dicionário e ER](../analise/06-bancos-legados-dicionario-er.md), com tabelas de precordis2016, agendaprec e samarco. [Inventário 01, §1.6](../analise/01-inventario.md) localiza os dumps de estrutura no legado.
- [Confirmado] Qualidade/inconsistências: [07](../analise/07-inconsistencias-modelagem.md); código/regras por subsistema: [índice legado](indice-legado.md). Este documento referencia essas fontes, sem copiar pacientes ou duplicar o dicionário.
- [Inferido] Microsoft Access e sincronizador externos exigem descoberta autorizada antes da migração clínica; não inferir tabela/chave real além da evidência registrada. Contrato de destino no [fluxo 13](../fluxos/13-plano-migracao-dados.md).

[Confirmado] Banco local de trabalho e parâmetros DB_* estão no [AGENTS](../../AGENTS.md). Dados online não devem ser confundidos com configuração do novo SaaS ou da validação descartável.
