# 20 — Plano de desenvolvimento, PR e controle de execução

[Confirmado] Fonte única de etapas/status. Corrigida em 03/11/2026 para banco único, Vuexy, legado somente leitura e domínio clínico/ocupacional. Versões exclusivamente em [14 §14.1](14-arquitetura-laravel.md). Requisitos funcionais: [22](22-atendimento-clinico.md), [23](23-saude-ocupacional.md), [24](24-seguranca-retencao-operacao.md). Contrato ETL: [13](13-plano-migracao-dados.md).

## 20.1 Workflow e PR

1. [Inferido] Ler AGENTS, pendências e fontes do domínio. Etapa depende do aceite das anteriores; não declarar implementação por existência de documentação.
2. [Inferido] Branch feat/etapa-NN-slug, diff pequeno e revisável: migrations/models/enums → Actions/Policies/Form Requests → endpoints → Vuexy → testes. Uma migration no banco único, não por clínica.
3. [Inferido] CI: Pint, Rector --dry-run, Larastan/PHPStan, Pest e build Vite quando aplicável. Testes unitários de regras, HTTP, permissões, dois tenants e arquitetura. Segurança, migração de banco e recuperação entram desde a fundação.
4. [Inferido] PR informa problema/comportamento, fontes, mudanças de DDL, testes reais, limitações e risco. Revisão + CI verde antes de merge; squash conforme autorização vigente. Não fazer push/merge/deploy automaticamente por esta documentação.
5. [Inferido] Aceite: testes pertinentes, migrations e seeds seguros em banco descartável, dois tenants sintéticos, documentação coerente. Reversibilidade não autoriza apagar dado clínico: usar expand-contract, backup/restore e plano de recuperação para mudanças destrutivas.
6. [Confirmado] Proibido DDL/DML em produção e alterações no legado neste escopo. Comandos previstos só serão executáveis após instalação da aplicação; código em inglês, interface/documentação em português.

[Confirmado] Exceção de aceite da etapa 1, autorizada pelo usuário em 03/11/2026: CI remoto será verificado futuramente; revisão técnica local e validações locais permitem concluir a etapa e liberar suas dependências. A exceção não declara CI remoto verde nem autoriza push/merge/deploy. Para merge, permanece a exigência de revisão/CI do workflow acima.

## 20.2 Quadro de etapas

| # | Etapa | Dependências | Entrega |
|---|---|---|---|
| [Inferido] 0 | Governança e preparação documental | — | Fontes conciliadas, fronteiras de leitura e decisões técnicas registradas; não é patch do legado. |
| [Inferido] 1 | Fundação: tenancy, identidade, ACL e CI | 0 | Laravel/PHP da matriz 14; Vuexy/Blade/Vite, TenantContext, auth, 2FA, ACL, seeds globais/por clínica, CI e staging sintético. |
| [Inferido] 2 | Cadastros, identidade e vínculos | 1 | Pacientes, empresas/unidades, profissionais, vínculos com vigência, busca/merge assistido e concessões empresariais. |
| [Inferido] 3 | Catálogo, protocolos e anamnese | 2 | Catálogo, preparos, questionários versionados/snapshots, consentimentos por finalidade e protocolos ocupacionais aprováveis por tenant. |
| [Inferido] 4 | Pedidos, agenda, recepção e execução | 2,3 | Pedidos/itens/preços, reserva, encaixe, visitas, execuções/tentativas e financeiro clínico mínimo do fluxo 22. |
| [Inferido] 5 | TV, dispositivos, salas e roteiro | 4 | Token revogável, canais Reverb por tenant/unidade, chamadas mínimas e travas transacionais de sala/paciente. |
| [Inferido] 6 | Resultados, documentos e ocupacional | 2,3,4 | Resultado por execução, autoria/revisão/liberação, versões/assinatura, avaliação ocupacional, ASO e disponibilização autorizada. |
| [Inferido] 7 | Migração completa da Precordis | 2,3,4,6 | ETL 13 e fonte Access caracterizada, duas cargas ensaiadas, identidade revisada, reconciliação, arquivos no storage autorizado. |
| [Inferido] 8 | Notificações e pesquisa | 4 | Mensageria em fila com snapshot/idempotência, preferências por finalidade, webhooks e pesquisa validada 0–10. |
| [Inferido] 9 | Assinatura e billing da plataforma | 1 | Planos/limites, gateway escolhido em sandbox, webhooks/conciliacão, política de inadimplência e acesso necessário preservado. |
| [Inferido] 10 | Portais paciente e empresa | 2,6 | Paciente acessa dados próprios; empresa tem acesso por vínculo/documento/finalidade; sem prontuário aberto por padrão. |
| [Inferido] 11 | Homologação, recuperação e go-live | 1–10 | Ensaio de restore, segurança, capacidade, treinamento, go/no-go clínico/operacional e recuperação após primeira escrita. |

[Inferido] **MVP clínico completo = etapas 0–7**, incluindo etapa 6 antes da migração clínica. Não prometer portais self-service ou envio eSocial no MVP. A disponibilização autorizada de laudo/ASO já faz parte da etapa 6. Um piloto só de agenda, sem migração clínica, exige escopo e aceite próprios; não recebe o nome de migração completa.

## 20.3 Testes e aceite específicos

- [Inferido] Etapa 0: links/fontes coerentes, nenhuma dependência de alterar o legado, decisões registradas; não significa contenção de produção concluída.
- [Inferido] Etapa 1: migrate uma vez, tenant:create sem CREATE DATABASE, seeds com contexto, login/reset/2FA e ACL; rejeitar contexto ausente/header falso e troca de tenant em worker; CI verde.
- [Inferido] Etapas 2–4: igualdade de tenant/paciente/empresa nos vínculos; merge humano auditado; snapshots de pergunta; preço histórico; reserva cancelada libera slot; concorrência de agenda; várias execuções/tentativas sem sobrescrita.
- [Inferido] Etapa 5: duas chamadas simultâneas não ocupam a mesma sala nem o mesmo atendimento em salas incompatíveis; PIN usa password_verify para hash, não comparação direta com hash_equals; canais/dispositivos não cruzam tenants.
- [Inferido] Etapa 6: execução → revisão → assinatura/liberação; retificação preserva bytes; link expira/revoga; ASO preserva contexto e autoria; empregador não vê anamnese; critérios do fluxo 23 cumpridos.
- [Inferido] Etapa 7: critérios do fluxo 13, inclusive origem das atualizações/delta, zero associação clínica ambígua sem revisão e reconciliação aprovada pelo negócio.
- [Inferido] Etapas 8–10: retry sem duplicação; escopo de mensagens/logs; limites sem apagar dados; portal respeita vínculo histórico e categoria documental.
- [Inferido] Etapa 11: restore banco/storage/chaves, RPO/RTO medidos, teste de carga, corte/recuperação sem perda após primeira escrita; assinatura e protocolos homologados com responsável técnico.

<a id="204-controle-de-fases-manter-atualizado"></a>

## 20.4 Controle de fases (fonte única)

[Confirmado] Estados: PENDENTE, EM_ANDAMENTO, BLOQUEADA, CONCLUÍDA, REABERTA. O relatório documental e o teste do DDL não concluem etapas de aplicação. Na conciliação, todas as etapas de implementação permanecem pendentes; a governança foi aceita após validação de links/contratos e teste SQL desta sessão.

| # | Etapa | Status | Evidência |
|---|---|---|---|
| [Confirmado] 0 | Governança e preparação documental | CONCLUÍDA | Fontes conciliadas, links conferidos e DDL validado; registro desta sessão e CHANGELOG. |
| [Confirmado] 1 | Fundação: tenancy, identidade, ACL e CI | CONCLUÍDA | Aceite autorizado pelo usuário em 03/11/2026 após revisão técnica local e correções: [revisão](../outros/revisao-etapa-01.md), 38 testes/186 asserções, checks/build e 36 verificações HTTP/Horizon aprovados. CI remoto adiado expressamente para verificação futura; sem merge/deploy presumido. |
| [Confirmado] 2 | Cadastros, identidade e vínculos | EM_ANDAMENTO | Pacientes e empresas clientes/estabelecimentos com CRUD inicial, CPF protegido, CNPJ numérico/alfanumérico, ACL e auditoria. [Pacientes](../outros/cadastro-pacientes.md) / [empresas](../outros/cadastro-empresas.md). [Unidades clínicas e profissionais](../outros/cadastro-unidades-profissionais.md) entregues. [Vínculos com vigência](../outros/vinculos-empregaticios.md) entregues. [Atribuição administrativa de unidade](../outros/atribuicao-unidades.md) entregue. [Gestão de concessões empresariais](../outros/concessoes-empresariais.md) entregue. Restrições operacionais de unidade/empresa nos módulos futuros, busca/merge e aceite da etapa ainda pendentes. |
| [Confirmado] 3 | Catálogo, protocolos e anamnese | PENDENTE | Sem implementação/aceite registrado. |
| [Confirmado] 4 | Pedidos, agenda, recepção e execução | PENDENTE | Sem implementação/aceite registrado. |
| [Confirmado] 5 | TV, dispositivos, salas e roteiro | PENDENTE | Sem implementação/aceite registrado. |
| [Confirmado] 6 | Resultados, documentos e ocupacional | PENDENTE | Sem implementação/aceite registrado. |
| [Confirmado] 7 | Migração completa da Precordis | PENDENTE | Sem implementação/aceite registrado. |
| [Confirmado] 8 | Notificações e pesquisa | PENDENTE | Sem implementação/aceite registrado. |
| [Confirmado] 9 | Assinatura e billing da plataforma | PENDENTE | Sem implementação/aceite registrado. |
| [Confirmado] 10 | Portais paciente e empresa | PENDENTE | Sem implementação/aceite registrado. |
| [Confirmado] 11 | Homologação, recuperação e go-live | PENDENTE | Sem implementação/aceite registrado. |

## 20.5 Registro de execução

### 2026-11-03 — Correção das pendências documentais e do DDL

- [Confirmado] Entregas: fontes conciliadas, contratos 22–24, ETL único, dependências corrigidas e DDL alinhado. Validação SQL e alterações estão no CHANGELOG: 69 tabelas/170 FKs criadas e 25 verificações sintéticas aprovadas em MariaDB 13.0.2. Etapa 0 concluída documentalmente, etapas 1–11 permanecem pendentes. Esta sessão não instala framework nem altera o legado.
- [Confirmado] As decisões clínicas específicas de cada tenant continuam parâmetros de implantação com responsável técnico. Não foram inventados protocolos ou contratos comerciais.
- [Inferido] Próxima execução: fundação conforme etapa 1 após aceite documental; verificar motor/imagens e compatibilidade de dependências antes da instalação.

## 20.6 Perguntas e parametrização

[Confirmado] Perguntas de negócio têm fonte única em [19](19-perguntas-em-aberto-negocio.md); não manter cópia concorrente na análise. Descoberta Access bloqueia ETL clínico, não fundação. O modelo de preços, protocolos e responsabilidades externas é validado antes de ativar a respectiva função, sem prometer funcionalidades ainda não implementadas.

### 2026-11-03 — Fundação iniciada e agente atualizado para skill Laravel

- [Confirmado] Branch feat/etapa-01-fundacao já existente. Esqueleto em docs/laravel, imagem PHP 8.5.12 construída e dependências instaladas com composer.lock, incluindo Laravel 13.39.0. Código inicial e migrations da fundação ainda não executados/homologados; nenhum aceite de aplicação concluído.
- [Confirmado] Por nova instrução do usuário, .codex/agents/precordis.toml e AGENTS.md passam a exigir localizar/ler a skill Laravel 13 Multi-Tenant Expert antes de prosseguir com implementação dependente. O link do plugin está registrado; a configuração não instala o plugin. A busca nesta sessão não encontrou seu SKILL.md nem entrada disponível do plugin. Não foi substituída por skill genérica.
- [Confirmado] Sem alterações no legado, sem migrations em produção e sem testes de aplicação declarados. A etapa 1 permanece EM_ANDAMENTO. Retomar a implementação com a skill disponível, revisar o código parcial e executar os aceites antes de marcá-la concluída.

### 2026-11-03 — Acesso à skill Laravel confirmado

- [Confirmado] Após o usuário informar a habilitação, foi confirmado acesso direto ao plugin Laravel 13 Multi-Tenant Expert, versão 1.0.0, e lido o arquivo interno skills/laravel-multi-tenant/SKILL.md. Nome técnico: laravel-multi-tenant. Ausência na busca genérica não representava bloqueio de acesso direto.
- [Confirmado] AGENTS.md e o agente .codex registram a identidade verificada e o procedimento de localização. O impedimento de acesso à skill foi superado; isso não conclui testes ou aceite da fundação. Etapa 1 permanece EM_ANDAMENTO.

### 2026-11-03 — Aplicação transferida para a raiz

- [Confirmado] Por instrução mais recente do usuário, a aplicação foi movida de docs/laravel/ para a raiz do repositório. Registros anteriores dessa pasta são históricos. Código, manifests, dependências ignoradas, Docker, testes e .env foram preservados; .gitignore foi mesclado, sem alterar staging de mudanças existentes.
- [Confirmado] Fontes atuais de caminhos: AGENTS.md, README da raiz, docs/README.md e arquitetura 14. O validador SQL lê o .env da raiz. Documentação permanece em docs/; somente public/ é webroot.
- [Confirmado] Esta reorganização não executa migrations nem conclui a etapa 1; autenticação, configuração, isolamento e suíte da aplicação continuam sujeitos ao aceite.

- [Confirmado] Verificações após a transferência: Compose válido, composer validate aprovado, composer check-platform-reqs aprovado em PHP 8.5.12 e php artisan --version retornando Laravel Framework 13.39.0. Links locais dos documentos afetados conferidos; .env preservado byte a byte, modo 0600 e ignorado pelo Git, assim como vendor/. Nenhuma migration ou suíte funcional foi executada nesta reorganização.


### 2026-11-03 — Fundação implementada e validada localmente

- [Confirmado] Aplicada a skill Laravel específica já verificada. Entrega na raiz: Fortify com login/reset/2FA TOTP, exigência de troca da senha inicial, usuários ativos, sessões persistidas/listagem/revogação, catálogo de permissões global e papéis Spatie teams=tenant. Auditoria síncrona de login/logout/reset/confirmação 2FA sem credenciais ou payload pessoal.
- [Confirmado] Contexto obrigatório por domínio, header sem poder de seleção, validação de membro ativo, suspensão/read-only, proprietário imutável, guardas para inserções/updates em lote e restauração de modelos com scope. Reset, sessão, throttling, cache, storage e canal privado são separados por clínica. Revisão de SQL bruto/pivôs permanece obrigatória; não há garantia automática para bypass explícito de scopes.
- [Confirmado] Filas carregam tenant_id escalar no envelope, resolvem contexto antes do handler e limpam contexto/time/guard em sucesso, exceção e loop. Um processo Redis real executou A → falha A → B, com o mesmo PID e permissões distintas. Horizon está configurado com timeout 150 < retry_after 180; seu dashboard permanece negado até existir identidade operacional de plataforma.
- [Confirmado] Interface Blade integra somente CSS core/auth Vuexy via Vite, com formulários POST/CSRF, escaping e navegação por autorização. Não carregados scripts de demonstração/analytics. Sanctum, TV/Reverb em execução, cadastro clínico e saúde ocupacional permanecem nas etapas correspondentes; o teste de broadcast valida autorização/assinatura sem servidor WebSocket.
- [Confirmado] composer check aprovado: Pint (63 arquivos de fontes), Rector sem alterações, Larastan nível 5 sem erros e Pest com 29 testes/158 verificações. npm run build aprovado. A verificação externa de senhas vazadas usa mock somente no teste de reset; não foi certificada disponibilidade desse serviço externo. Os testes HTTP usam sessão array e há teste separado do handler persistente MariaDB.
- [Confirmado] Migrations, rollback e reaplicação aprovados em novo_sistema_clinica_testing, MariaDB 13.0.2, com foreign_key_checks=1: 17 tabelas de aplicação/runtime + migrations e 13 FKs. Nenhum DDL do legado/produção foi executado. Histórico de estrutura no CHANGELOG.
- [Confirmado] Após confirmar o banco vazio, migrations da fundação aplicadas somente na nova instância local do Compose, separada da instância anterior do host. Provisionada clínica Precordis/domínio localhost sem administrador/senha padrão. Roteiro nominal/interativo no README.
- [Confirmado] Workflow .github/workflows/ci.yml preparado para ambiente descartável; não executado remotamente. Etapa 1 permanece EM_ANDAMENTO: staging com dois tenants, revisão/PR e aceite ainda não realizados. Nenhum módulo clínico/ocupacional ou importação foi antecipado. Nenhuma mudança no legado ou staging do Git.

- [Confirmado] Smoke no navegador local: login em http://localhost:8080/login renderizado com CSS compilado, formulário POST/CSRF e nenhum script externo; sem overflow horizontal em desktop e viewport de 390 px. Corrigido o comando serve do Compose com --no-reload para preservar hosts DB/Redis injetados no processo web. Não foi criado administrador nominal automaticamente.
- [Confirmado] Varredura da árvore de trabalho elegível ao Git não encontrou .env real, PDFs ou padrões reconhecidos de tokens Mapbox secretos/GitHub/AWS/chave privada. Isso não é auditoria exaustiva do histórico Git; nenhuma reescrita de histórico ou alteração do staging foi realizada.

- [Confirmado] Revisão final: troca de senha e revogação de sessões usam transação; senha inicial/troca e preservação das sessões da outra clínica possuem regressão. Seeder invalida explicitamente o cache de permissões antes/depois da execução. O teste Redis usa o mesmo backend no produtor/worker, invalida somente sua chave de catálogo e verifica cache A/B; nenhum flush global. Checks finais aprovados após essas correções.


### 2026-11-03 — Layout alinhado aos modelos documentados do Vuexy

- [Confirmado] Análise das views anteriores: o layout único carregava core.css, mas usava app-shell/app-header/auth-card próprios, sem shell vertical e sem wrappers de autenticação documentados. Por solicitação do usuário, as telas existentes foram adaptadas à [fonte normativa de layout](../layout/vuexy-assets-e-modelos-por-pagina.md) e às variantes da [cobertura Vuexy](../layout/vuexy-cobertura-completa-144-paginas.md).
- [Confirmado] Agora há base compartilhada, autenticação cover com ilustrações por fluxo e shell layout-wrapper/layout-content-navbar com sidebar, navbar, container-xxl e footer. Dashboard usa grid/cards; equipe usa tabela responsiva e paginação do servidor; perfil e segurança seguem cards/configurações; erro 404 segue a família misc. Não adicionados módulos, gráficos, filtros ou ações sem backend.
- [Confirmado] Public Sans é auto-hospedada pelo Vite; ícones selecionados vêm do asset Tabler documentado; ilustrações são selecionadas no manifest. O shell carrega condicionalmente Popper, Bootstrap, Helpers, Menu e Perfect Scrollbar fornecidos em docs/layout/assets. Auth carrega seu CSS próprio e controle acessível de visibilidade de senha. Sem analytics, customizador, scripts/dados de demonstração ou fontes por CDN.
- [Confirmado] Forms mantêm rotas, CSRF e validação do servidor; campos exibem erros do bag correspondente. Menus/links continuam sob autorização. Nenhuma alteração no schema ou nos dados de negócio do ambiente de trabalho, nas regras de tenancy/permissões ou no legado. Fixtures ficaram no banco de testes. Horários de sessão são apresentados no fuso configurado da clínica.
- [Confirmado] composer check aprovado: Pint, Rector, Larastan e os 29 testes/158 verificações existentes. Build Vite aprovado. Inspeção no navegador em 1440 px e 390 px: autenticação, dashboard, equipe e segurança; menu mobile abre/fecha, menu desktop recolhe, dropdown e visibilidade de senha funcionam, tabelas rolam sem overflow da página. Perfect Scrollbar foi integrado após a inspeção revelar sua necessidade no redimensionamento do Menu fornecido.
- [Confirmado] Preview autenticado usou exclusivamente usuário/clínica sintéticos no banco novo_sistema_clinica_testing, em servidor temporário separado do ambiente de trabalho. Fontes/imagens locais verificadas, sem recursos externos nas telas inspecionadas. Evidências visuais locais em .playwright-mcp/, ignoradas pelo Git; não contêm dados clínicos. O aceite remoto/staging da etapa 1 continua pendente no controle de fases.


### 2026-11-03 — Acessos sintéticos locais solicitados pelo usuário

- [Confirmado] Criadas três contas de teste na clínica local Precordis: administrador, recepção e médico, sem sobrescrever usuários existentes. Senhas aleatórias fornecidas somente na conversa; valores não registrados em documentação ou seeders. Banco armazena hashes.
- [Confirmado] Os três logins foram validados no navegador e as sessões de verificação encerradas. Preservadas troca obrigatória da senha inicial e exigência de 2FA para administrador/médico. Nenhum acesso ao legado ou produção.


### 2026-11-03 — Homologação local isolada com duas clínicas

- [Confirmado] Por escolha explícita do usuário, preparada homologação local isolada da etapa 1. Fonte operacional única: [homologação local](../outros/homologacao-local.md). Compose próprio, porta 8082, banco/Redis/rede/storage/bootstrap/cache separados, código somente leitura e credenciais geradas em .env.homologacao ignorado pelo Git, modo 0600. Desenvolvimento na porta 8080 segue ativo e separado.
- [Confirmado] Migrations aplicadas uma vez no banco de homologação, com duas clínicas sintéticas e quatro atores. Seeder idempotente não sobrescreve senhas/2FA existentes e exige ambiente staging/host db/banco isolado. Administradores de fixture têm TOTP real previamente configurado; recepção A exercita a troca obrigatória da senha inicial. Isso não altera o onboarding dos usuários de desenvolvimento.
- [Confirmado] 36 verificações reais aprovadas por HTTP/Horizon, incluindo mesmo e-mail/senhas diferentes, TOTP obrigatório, sessões SQL criptografadas, CSRF, cookie A em B, contexto/header/binding, ACL, arquivos privados fora do webroot e worker persistente A/falha/B com team/permissões corretos. Resultado: [relatório sanitizado](../outros/homologacao-local-2026-11-03.json). PHP/MariaDB observados no relatório; não homologa MySQL.
- [Confirmado] composer check aprovado: Pint (67 fontes), Rector, Larastan sem erros e 34 testes/169 verificações, incluindo recusa fora do ambiente permitido, DB_URL incompatível, outra conexão padrão e conexão já resolvida para outro banco. Endereços das duas clínicas e assets locais conferidos no navegador. Workflow de CI inclui o mesmo roteiro; execução remota não realizada, nenhum push/merge/deploy automático.
- [Confirmado] Etapa 1 continua EM_ANDAMENTO até revisão/aceite e CI remota. Homologação local escolhida foi entregue; não iniciadas etapas dependentes nem alterado legado. HTTPS/SMTP e implantação externa não foram certificados.


### 2026-11-03 — Revisão da proteção de migrations da homologação

- [Confirmado] Na revisão do roteiro local foi identificada a execução de migrate antes da guarda de destino aplicada aos seeds. Corrigido scripts/homologacao.py para usar [homologation:migrate](../../app/Console/Commands/MigrateHomologation.php), que reaproveita LocalHomologation e recusa ambiente/configuração/conexão resolvida incompatíveis antes de delegar a migrate. Sem mudanças no DDL ou migrations existentes.
- [Confirmado] Regressões verificam recusa fora de staging e com DB_URL, conexão padrão ou conexão resolvida incompatíveis. composer check aprovado: Pint (68 fontes), Rector, Larastan e 35 testes/178 asserções, em PHP 8.5.12 e MariaDB 13.0.2. Roteiro up executado no destino isolado com retorno Nothing to migrate; seeds preservados. Reexecutadas as 36 verificações HTTP/Horizon, aprovadas no [relatório atualizado](../outros/homologacao-local-2026-11-03.json).
- [Confirmado] Fonte operacional atualizada no [roteiro de homologação](../outros/homologacao-local.md). Revisão desta sessão focada no roteiro de migrations e suas guardas; não equivale a auditoria integral nem revisão externa. Etapa 1 permanece EM_ANDAMENTO até CI remota e revisão/aceite. Nenhum push, merge, deploy ou alteração no legado.


### 2026-11-03 — Revisão técnica e aceite autorizado da etapa 1

- [Confirmado] Instrução explícita do usuário: “CI remoto verde = verificar futuramente. Revisão do PR = Execute. Aceite final = Prossiga”. Registrada exceção de aceite em §20.1; CI remoto continua não executado e não bloqueia o avanço local desta etapa.
- [Confirmado] Executada revisão local das alterações destinadas ao PR, com escopo, achados e evidências no [relatório](../outros/revisao-etapa-01.md). Corrigidos consumo concorrente e duração insuficiente da proteção contra replay de TOTP. Preservada a correção anterior que valida o destino antes das migrations de homologação. Sem alterações de schema.
- [Confirmado] composer check aprovado: Pint em 68 fontes, Rector, Larastan e 38 testes/186 asserções. Build Vite aprovado e 36 verificações HTTP/Horizon reais aprovadas nas duas clínicas sintéticas. Testes novos cobrem disputa pelo código, tolerância temporal e recusa sem cache.
- [Confirmado] Etapa 1 marcada CONCLUÍDA com o aceite autorizado e a exceção documentada. Dependência da etapa 2 liberada; etapa 2 ainda PENDENTE, sem cadastros clínicos implementados nesta revisão. Nenhuma aprovação/merge de PR remoto, push, deploy ou alteração do legado/produção.


### 2026-11-03 — Etapa 2 iniciada: cadastro de pacientes

- [Confirmado] Primeira entrega vertical: listagem paginada, criação, consulta e edição de pacientes com Form Request, Actions, Policy, autorização no servidor e telas Vuexy. Fonte operacional: [cadastro de pacientes](../outros/cadastro-pacientes.md). CPF normalizado/validado, ciphertext e HMAC com chave separada/derivada por tenant; sem CPF, o cadastro permanece parcial. Auditoria não copia conteúdo pessoal; CPF e demais entradas pessoais não são armazenados no flash de validação.
- [Confirmado] Migration aditiva reproduz companies/company_units/patients do DDL, com FKs e índices preservados; empresas/unidades são dependências estruturais nesta entrega. Histórico e rollback/reaplicação em banco testing vazio no [CHANGELOG](../schema/CHANGELOG.md). Aplicada somente nos bancos locais do Compose: desenvolvimento, testes e homologação isolada. Nenhuma alteração no legado/produção.
- [Confirmado] Sete testes de pacientes em duas clínicas, incluindo unicidade no banco, CPF mascarado normalizado, acesso 403/binding 404, contexto ausente, cadastro parcial, atualização e recusa sem chave HMAC. composer check aprovado: Pint em 80 fontes, Rector, Larastan e **45 testes/244 asserções**. Build Vite aprovado; 36 checks reais HTTP/Horizon da fundação continuam aprovados após a integração. As novas telas foram renderizadas pelos testes HTTP; inspeção visual manual autenticada nova não foi concluída nesta sessão.
- [Confirmado] Preparadores geram CPF_SEARCH_KEY apenas no arquivo privado, sem substituir a chave existente. Contas/senhas preservadas; papéis administrativos locais atualizados pelo provisionamento idempotente. Não há cache de resultados de pacientes a invalidar nesta entrega.
- [Confirmado] Etapa 2 EM_ANDAMENTO: busca/merge humano, empresas/unidades/profissionais, vínculos com vigência e concessões empresariais ainda pendentes. Não iniciados exames, prontuário ou ocupacional. Alterações previamente preparadas no index pelo usuário foram preservadas; nenhum commit/push/merge ou troca automática de branch foi realizado. A organização da branch específica da etapa 2 depende da consolidação da base já preparada, sem promover esta entrega a aceite integral.


### 2026-11-03 — Etapa 2: empresas clientes e estabelecimentos

- [Confirmado] Entregues listagem, criação, consulta, edição e inativação de empresas/estabelecimentos, com telas Vuexy, Form Requests, Actions e Policies. [Uso e limites](../outros/cadastro-empresas.md). Estabelecimentos de empregadores (company_units) não são unidades de atendimento da clínica (units).
- [Confirmado] CNPJ numérico/alfanumérico normalizado e validado pelo VO compartilhado conforme manual técnico oficial referenciado no documento de uso. Unicidade no banco por tenant; CNPJ omitido numa edição preserva a identificação. Empresa/tenant do estabelecimento vêm da rota/contexto; binding, Action e Policy rejeitam associação a outro empregador, inclusive dentro da mesma clínica. UF usa enum compartilhado.
- [Confirmado] Novas permissões companies.view/companies.manage no catálogo global e no papel administrativo por tenant. RegistryAudit centraliza a gravação de eventos/IDs, e TenantPermission centraliza a verificação comum de usuário ativo/contexto/segundo fator; pacientes preservam o comportamento e suas regressões. Nenhuma leitura clínica é concedida ao cadastrar empresa.
- [Confirmado] Oito testes de empresas, cobrindo formatos de CNPJ, validação, unicidade, preservação de identificação, ACL, binding cruzado, empresa errada na mesma clínica, FK cruzada e formulários. composer check aprovado: Pint em 98 fontes, Rector, Larastan e **53 testes/332 asserções**; npm run build aprovado. Os 36 checks reais HTTP/Horizon da fundação continuam aprovados. Novas views renderizadas pelos testes HTTP; inspeção visual manual autenticada não foi executada nesta entrega.
- [Confirmado] Sem nova migration/DDL: tabelas já criadas na entrega anterior. Provisionamento idempotente atualizou permissões somente nos ambientes locais de desenvolvimento/homologação, sem substituir usuários/senhas. CI remoto continua para verificação futura por orientação do usuário; sem commit/push/merge/deploy, alterações no index preservadas e legado/produção sem alterações.
- [Confirmado] Etapa 2 permanece EM_ANDAMENTO. Próximas entregas: unidades clínicas e profissionais, vínculos empregatícios com vigência e concessões empresariais, busca/merge humano auditado de pacientes e aceite final da etapa.


### 2026-11-03 — Etapa 2: unidades clínicas e profissionais

- [Confirmado] Entregues listagem, criação, consulta, edição e inativação de unidades clínicas; units é a tabela existente da fundação, distinta de company_units. Fonte operacional: [unidades clínicas e profissionais](../outros/cadastro-unidades-profissionais.md). Componente e validação de localização foram compartilhados com os estabelecimentos empresariais, preservando as regressões anteriores.
- [Confirmado] Cadastro profissional vinculado a integrante ativo da clínica, com seleção paginada e dados de conselho/especialidade. Vínculo único/imutável, validação contextual, FK composta e auditoria por IDs. Não cria contas nem modifica senha/papel/permissão/habilitação. Não foi introduzida situação própria ou vínculo paralelo com unidade: essas informações pertencem ao usuário conforme schema.
- [Confirmado] Adicionada somente a migration de professionals, reproduzindo o DDL de referência. Aplicada nos bancos locais de testes, homologação isolada e desenvolvimento; rollback e reaplicação apenas em testing vazio, com FKs habilitadas. Mudança/reversibilidade em [CHANGELOG](../schema/CHANGELOG.md). Migrations anteriores intactas.
- [Confirmado] Sete testes novos em duas clínicas: CRUD de unidades, usuário local ativo, grupo de campos do conselho, imutabilidade, FK cruzada, ACL e ausência de concessão clínica automática. composer check aprovado: Pint em 116 fontes, Rector, Larastan e **60 testes/425 asserções**. Build Vite aprovado. As 36 verificações reais HTTP/Horizon da fundação continuam aprovadas após a integração. Views novas renderizadas pelos testes HTTP; inspeção visual manual autenticada não executada nesta entrega. CI remoto permanece para verificação futura conforme orientação do usuário.
- [Confirmado] Permissões units.view/units.manage/professionals.view/professionals.manage concedidas somente ao papel administrativo no provisionamento local idempotente. Contas/senhas preservadas; nenhuma escrita no legado/produção, operação de commit/push/merge/deploy ou manipulação do index pelo agente.
- [Confirmado] Etapa 2 permanece EM_ANDAMENTO: atribuição administrativa de usuários a unidades e seus efeitos operacionais, vínculos empregatícios com vigência, concessões empresariais, busca/revisão/merge humano de pacientes e aceite final ainda pendentes.

### 2026-11-03 — Etapa 2: vínculos empregatícios com histórico

- [Confirmado] Entregues seleção de empresa, criação de vínculos simultâneos, histórico paginado, versionamento de função/estabelecimento e desligamento explícito. Fonte de uso: [vínculos empregatícios](../outros/vinculos-empregaticios.md); convenção de períodos inclusivos registrada no fluxo 23. Preservados paciente/empresa, versões anteriores e nomes históricos em contexto criptografado. Resumo legado do paciente e permissões de usuários não são alterados por esse cadastro.
- [Confirmado] Policies exigem contexto, paciente exato, usuário ativo, segundo fator e permissões; Form Requests recusam propriedade/contexto enviados pelo cliente. Actions validam estabelecimento da empresa e tenant, usam transação/bloqueio e revalidam versão atual. Auditoria registra IDs/eventos e referência à versão anterior, sem copiar conteúdo pessoal. Falha de auditoria reverte ambas as escritas do versionamento.
- [Confirmado] Migration aditiva reproduz somente patient_company_links do DDL. Aplicada nos bancos locais de desenvolvimento, homologação e testes; rollback/reaplicação apenas em testing vazio, com FKs habilitadas. Justificativas e limites no [CHANGELOG](../schema/CHANGELOG.md). Provisionamento atualizou permissões administrativas locais idempotentemente, preservando contas/senhas.
- [Confirmado] Onze testes novos exercitam dois tenants, empresa/estabelecimento incompatíveis, paciente errado na mesma clínica, intervalos, ACL, requisição obsoleta, preservação de snapshot após renomeação e falha de persistência da auditoria. composer check aprovado: Pint em 129 fontes, Rector, Larastan e **71 testes/529 asserções**; npm run build aprovado. As 36 verificações reais HTTP/Horizon da fundação também passaram após a integração da migration; não constituem homologação funcional das novas telas. Views renderizadas nos testes HTTP. Não executados inspeção visual manual autenticada nem ensaio de escritores paralelos.
- [Confirmado] Etapa 2 permanece EM_ANDAMENTO: atribuição de usuários a unidades e seus efeitos operacionais, concessões empresariais, busca/revisão/merge humano de pacientes e aceite final ainda pendentes. Avaliações, protocolos, exames e ASO permanecem nas etapas próprias. CI remoto segue para verificação futura por orientação do usuário. Sem commit/push/merge/deploy, manipulação do index pelo agente ou escrita no legado/produção.

### 2026-11-03 — Etapa 2: atribuição administrativa de unidade

- [Confirmado] Entregues consulta da unidade no integrante e formulário Equipe → integrante → Alterar unidade, seguindo Vuexy. [Uso e limites](../outros/atribuicao-unidades.md). Reutiliza users.unit_id e sua FK composta da fundação; nenhum DDL/migration ou papel novo. Catálogo e permissões administrativas existentes foram preservados.
- [Confirmado] Policy exige users.view/users.manage/units.view, ator ativo, tenant e segundo fator; rotas exigem confirmação recente de senha. Action aceita somente integrante ativo e unidade ativa da mesma clínica, ou remoção explícita, em transação com bloqueio. Não altera senha, papéis ou perfil profissional. Mudança efetiva renova remember_token e remove sessões persistidas do integrante; autoalteração encerra a sessão corrente. Operação sem mudança preserva sessões/token.
- [Confirmado] Auditoria centralizada grava ator/integrante e IDs de unidade anterior/nova, sem conteúdo pessoal. Teste de falha da auditoria confirma rollback da atribuição, token e exclusão de sessões. Sete testes novos cobrem também dois tenants, contexto ausente, binding/FK cruzados, entradas proibidas, unidades indisponíveis, ACL, segundo fator, confirmação de senha, remoção e autoalteração.
- [Confirmado] composer check aprovado: Pint em 133 fontes, Rector, Larastan e **78 testes/609 asserções**, em PHP 8.5.12/MariaDB 13.0.2 locais. npm run build aprovado. Reexecutadas as 36 verificações reais HTTP/Horizon da fundação, aprovadas no [relatório sanitizado](../outros/homologacao-local-2026-11-03.json). Essas verificações não homologam funcionalmente a nova atribuição; sua renderização/operação HTTP foi validada pela suíte automatizada. Não executados inspeção visual manual autenticada, teste de carga concorrente ou MySQL. CI remoto continua para verificação futura.
- [Confirmado] Etapa 2 permanece EM_ANDAMENTO: busca/revisão/merge humano, concessões empresariais e aceite final pendentes. Policies operacionais por unidade serão integradas aos módulos de agenda/atendimento; atribuir unidade não implementa essas telas nem autoriza acesso global quando unit_id é nulo. Sem alterações no legado/produção, commit/push/merge/deploy ou manipulação do index pelo agente.

### 2026-11-03 — Etapa 2: gestão de concessões empresariais

- [Confirmado] Entregues histórico paginado por empresa, criação por integrante/categoria/vigência e revogação sem exclusão. Telas Vuexy e confirmação recente de senha nas escritas. [Uso e limites](../outros/concessoes-empresariais.md). Policies exigem tenant, empresa exata, usuário ativo, segundo fator e permissões administrativas específicas; nenhuma concessão atribui automaticamente papéis ou acesso clínico.
- [Confirmado] CompanyAccessAuthorization relê usuário/empresa/concessões e exige permissão específica, categoria, período inclusivo e ausência de revogação, sem cache nem bypass administrativo. Futuras Policies de portal/documento deverão combinar esse requisito com vínculo/contexto do empregador e documento liberado; portal, ASO e downloads não são antecipados por esta entrega.
- [Confirmado] Migration aditiva reproduz company_user_access do schema.sql. Aplicada em desenvolvimento, homologação isolada e testes; rollback/reaplicação somente em testing vazio, com FKs habilitadas. Histórico no [CHANGELOG](../schema/CHANGELOG.md). Catálogo e permissões administrativas locais atualizados idempotentemente, preservando contas/senhas. Permissões company_portal.* cadastradas, sem concessão automática.
- [Confirmado] Oito testes novos cobrem HTTP em dois tenants, binding e FK cruzados, empresa errada na mesma clínica, categorias, períodos/fuso, limites inclusivos, usuário/empresa inativos, segundo fator, ACL, confirmação de senha, revogação idempotente, imutabilidade e rollback quando a auditoria falha. Corrigida durante a validação uma diretiva Blade adjacente que impedia renderizar a listagem. composer check final aprovado: Pint em 143 fontes, Rector, Larastan e **86 testes/701 asserções**, em PHP 8.5.12/MariaDB 13.0.2. npm run build aprovado.
- [Confirmado] Reexecutadas e aprovadas as 36 verificações HTTP/Horizon da fundação nas duas clínicas sintéticas, com [relatório atualizado](../outros/homologacao-local-2026-11-03.json). Esse roteiro não certifica funcionalmente as novas concessões, exercitadas pela suíte HTTP automatizada.
- [Confirmado] Etapa 2 permanece EM_ANDAMENTO: busca/revisão/merge humano de pacientes e aceite final pendentes; restrições operacionais de empresa/unidade serão integradas aos módulos correspondentes. Inspeção visual manual autenticada, teste de carga concorrente, CI remoto e MySQL não executados. CI remoto continua para verificação futura por orientação do usuário. Sem commit/push/merge/deploy, manipulação do index pelo agente ou escrita no legado/produção.
