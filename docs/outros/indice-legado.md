# Índice do código legado — somente leitura

[Confirmado] Referência em /home/somvibe/Projetos/pessoal/precordis/precordis-admin-completo. Nesta indexação foram considerados somente arquivos PHP da árvore permitida; diretórios private/, compactacao/, arquivos_zip/, vendor/, Stuk/, node_modules/ e .git/ foram podados antes da travessia. Symlinks e PDFs não foram abertos. Nenhum arquivo legado foi alterado.

[Confirmado] Subsistemas, dependências e atores têm fonte única no [inventário 01](../analise/01-inventario.md) e no [mapa 02](../analise/02-mapa-dependencias-atores.md). Campos/telas: [03](../analise/03-fluxos-telas-formularios.md); regras: [04](../analise/04-regras-de-negocio.md); estrutura: [06](../analise/06-bancos-legados-dicionario-er.md). Não duplicar esses levantamentos.

## Pontos de entrada e localização de regras

| Estado | Subsistema/assunto | Arquivo/linha observado | Responsabilidade |
|---|---|---|---|
| [Confirmado] | Portal e acesso | [_login.php:9](/home/somvibe/Projetos/pessoal/precordis/precordis-admin-completo/_login.php:9) | Ver análise funcional/regras; referência somente leitura. |
| [Confirmado] | Laudos e listagem | [manager.php:81](/home/somvibe/Projetos/pessoal/precordis/precordis-admin-completo/manager.php:81) | Ver análise funcional/regras; referência somente leitura. |
| [Confirmado] | Agenda | [agendaprecordis/gravaagenda.php:54](/home/somvibe/Projetos/pessoal/precordis/precordis-admin-completo/agendaprecordis/gravaagenda.php:54) | Ver análise funcional/regras; referência somente leitura. |
| [Confirmado] | Anamnese | [formulario/gravaSaude.php:95](/home/somvibe/Projetos/pessoal/precordis/precordis-admin-completo/formulario/gravaSaude.php:95) | Ver análise funcional/regras; referência somente leitura. |
| [Confirmado] | Dados cadastrais e regra empresarial | [formulario/gravaInfo.php:27](/home/somvibe/Projetos/pessoal/precordis/precordis-admin-completo/formulario/gravaInfo.php:27) | Ver análise funcional/regras; referência somente leitura. |
| [Confirmado] | Check-in histórico | [checkinCliente/checkin.php:28](/home/somvibe/Projetos/pessoal/precordis/precordis-admin-completo/checkinCliente/checkin.php:28) | Ver análise funcional/regras; referência somente leitura. |
| [Confirmado] | TV/chamada | [precordistv/chamar.php:27](/home/somvibe/Projetos/pessoal/precordis/precordis-admin-completo/precordistv/chamar.php:27) | Ver análise funcional/regras; referência somente leitura. |
| [Confirmado] | Roteiro | [precordistv/roteiro_api.php:18](/home/somvibe/Projetos/pessoal/precordis/precordis-admin-completo/precordistv/roteiro_api.php:18) | Ver análise funcional/regras; referência somente leitura. |
| [Confirmado] | Salas | [precordistv/sala_status_api.php:36](/home/somvibe/Projetos/pessoal/precordis/precordis-admin-completo/precordistv/sala_status_api.php:36) | Ver análise funcional/regras; referência somente leitura. |
| [Confirmado] | Pesquisa | [pesquisaSatisfacao/pesquisaAgradecimentoEnvio.php:16](/home/somvibe/Projetos/pessoal/precordis/precordis-admin-completo/pesquisaSatisfacao/pesquisaAgradecimentoEnvio.php:16) | Ver análise funcional/regras; referência somente leitura. |
| [Confirmado] | Fila de e-mail | [send-mail.php:21](/home/somvibe/Projetos/pessoal/precordis/precordis-admin-completo/send-mail.php:21) | Ver análise funcional/regras; referência somente leitura. |

[Confirmado] As linhas foram localizadas por leitura estática de identificadores, sem reproduzir queries, credenciais ou valores de pacientes. Existência de código não comprova atividade em produção. Subpastas de contingência e cópias antigas estão classificadas no inventário 01; não são novas fontes normativas do SaaS.

[Confirmado] Access e sincronizador não têm código identificado nesta árvore, conforme análise existente. A migração depende de descoberta autorizada, como define o [fluxo 13](../fluxos/13-plano-migracao-dados.md). Não corrigir problemas encontrados no legado durante implementação do novo produto.
