# 11 — Identidade, autenticação e ACL (com mapeamento dos níveis legados)

## 11.1 Autenticação

| Decisão | Escolha | Justificativa |
|---|---|---|
| Base | **Fortify** (starter kit oficial) | login, reset, verificação de e-mail prontos |
| SPA/mobile/API | **Sanctum** | tokens somente quando API/dispositivo necessitar; UI inicial Blade; token p/ API `/v1` |
| OAuth2 p/ terceiros | **Passport** só se houver necessidade real | não há hoje |
| **2FA (TOTP)** | **obrigatório** para perfis com acesso a dado clínico (médico, laudador, admin) | dado sensível |
| Política de senha | mínimo 10 chars, verificação contra listas de vazamento | corrige senha de 5 dígitos do legado |
| Bloqueio por tentativas | throttling nativo | corrige ausência de rate-limit |
| Sessões ativas | listagem/invalidação | — |
| SSO | evolução (Google Workspace p/ a clínica) | fase posterior |
| Acesso sem login tradicional | **TV e totem por token de dispositivo** vinculado a tenant/unidade; **paciente** por login próprio ou **link assinado com expiração** p/ baixar laudo | substitui o login compartilhado e o PDF de URL adivinhável do legado |

Senhas migram como **"forçar redefinição no primeiro acesso"** (não dá para converter md5/texto em bcrypt mantendo a senha — e as senhas atuais são fracas demais para preservar). Ver [13](13-plano-migracao-dados.md).

## 11.2 ACL multi-tenant

**Spatie laravel-permission** com a feature **teams = tenant** (cada tenant tem seus papéis; catálogo de permissões é global); verificado em 31/10/2026 que a linha atual suporta Laravel 13 e tem suporte nativo a teams. Concessões sempre **com escopo de tenant**, com exceções de papéis globais controladas; permissões contextuais (além de RBAC) via **Policies**.

**Papéis padrão do sistema** (cada clínica pode criar os seus):

| Papel | Escopo |
|---|---|
| Super Admin da Plataforma | plataforma comercial/técnica; sem prontuário automático |
| Admin da Clínica | tenant inteiro |
| Recepção | agenda, check-in, chamada |
| Médico / Laudador | execução/revisão/liberação conforme habilitação e vínculo autorizado |
| Técnico | execução de exame, status de sala |
| Financeiro | billing da clínica, relatórios |
| Usuário de Empresa Cliente | categorias/documentos autorizados por empresa e vínculo histórico, conforme fluxo 23 |
| Paciente | seus próprios dados (Portal do Paciente) |

**Catálogo de permissões granular por módulo.ação** (exemplos):
`agenda.view`, `agenda.create`, `agenda.cancel`, `checkin.update`, `chamada.call`, `sala.manage`, `laudo.view`, `laudo.sign`, `laudo.release`, `paciente.view`, `paciente.edit`, `empresa.view`, `pesquisa.view`, `billing.manage`, `audit.view`, `usuarios.manage`.

**Permissões contextuais (Policies), que o RBAC puro não cobre:**
- profissional só executa/revisa/assina/libera conforme habilitação, atribuição e Policy do tipo documental;
- usuário de empresa só vê **categorias autorizadas do vínculo empregador**, sem prontuário completo por padrão;
- recepção restrita à **sua unidade**;
- paciente só os **próprios** registros.

## 11.3 Mapeamento dos níveis legados → novos papéis

| Legado | Evidência | Novo papel |
|---|---|---|
| `usuario.administrador = -1` | 14 contas | Admin da Clínica |
| `usuario.medico = -1` | 382 contas | Médico / Laudador |
| `administrador=-1 AND medico=-1` | 2 contas | Admin + papel Médico |
| `usuario` comum (paciente) | 9.820 | Paciente |
| login agenda `agendamentos@`/`latif.laila@` | `valida.php` | Recepção (viram usuários nominais) |
| login agenda `apoio` | `valida.php` | Técnico/Apoio |
| login TV `precordis` | `auth.php` | Recepção/Técnico (token de dispositivo p/ a TV em si) |
| PIN de sala (4 díg., texto plano) | `salas.pin` | credencial de dispositivo da sala (hash) |

**Auditoria obrigatória** (LGPD): concessão/revogação de papel e permissão, e **todo acesso a dado clínico** (abrir laudo, abrir anamnese, exportar) gravados em `activity_log`/`document_accesses` por tenant — amplia o `watchdog`, que só registrava login.

## 11.4 Diferença essencial do legado

No legado a autorização rodava no cliente e o `header()` sem `exit` não bloqueava. No novo, **nada** chega ao controlador sem passar por middleware de tenant + `auth` + `can:`; e cada ação sensível tem Policy. Os testes de permissão por papel e de isolamento por tenant ([09](09-estrategia-multi-tenant.md) §9.4, [15](15-qualidade-testes-devops.md)) são parte do "pronto".

[Confirmado] Matriz normativa e concessões excepcionais: [24 §24.1](24-seguranca-retencao-operacao.md). Acesso empresarial: [23 §23.3](23-saude-ocupacional.md). Estruturas de concessão/dispositivos/download em schema.sql. RBAC isolado não substitui essas Policies.
