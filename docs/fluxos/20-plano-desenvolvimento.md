# 20 — Plano de desenvolvimento, PR e controle de execução

[Confirmado] Fonte única de etapas/status. Corrigida em 03/11/2026 para banco único, Vuexy, legado somente leitura e domínio clínico/ocupacional. Versões exclusivamente em [14 §14.1](14-arquitetura-laravel.md). Requisitos funcionais: [22](22-atendimento-clinico.md), [23](23-saude-ocupacional.md), [24](24-seguranca-retencao-operacao.md). Contrato ETL: [13](13-plano-migracao-dados.md).

## 20.1 Workflow e PR

1. [Inferido] Ler AGENTS, pendências e fontes do domínio. Etapa depende do aceite das anteriores; não declarar implementação por existência de documentação.
2. [Inferido] Branch feat/etapa-NN-slug, diff pequeno e revisável: migrations/models/enums → Actions/Policies/Form Requests → endpoints → Vuexy → testes. Uma migration no banco único, não por clínica.
3. [Inferido] CI: Pint, Rector --dry-run, Larastan/PHPStan, Pest e build Vite quando aplicável. Testes unitários de regras, HTTP, permissões, dois tenants e arquitetura. Segurança, migração de banco e recuperação entram desde a fundação.
4. [Inferido] PR informa problema/comportamento, fontes, mudanças de DDL, testes reais, limitações e risco. Revisão + CI verde antes de merge; squash conforme autorização vigente. Não fazer push/merge/deploy automaticamente por esta documentação.
5. [Inferido] Aceite: testes pertinentes, migrations e seeds seguros em banco descartável, dois tenants sintéticos, documentação coerente. Reversibilidade não autoriza apagar dado clínico: usar expand-contract, backup/restore e plano de recuperação para mudanças destrutivas.
6. [Confirmado] Proibido DDL/DML em produção e alterações no legado neste escopo. Comandos previstos só serão executáveis após instalação da aplicação; código em inglês, interface/documentação em português.

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
| [Confirmado] 1 | Fundação: tenancy, identidade, ACL e CI | EM_ANDAMENTO | Fundação na raiz implementada e validada localmente: composer check e build Vite aprovados. Workflow de CI preparado; execução remota, staging e revisão/aceite ainda pendentes. Evidências na sessão de validação abaixo. |
| [Confirmado] 2 | Cadastros, identidade e vínculos | PENDENTE | Sem implementação/aceite registrado. |
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
