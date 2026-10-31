# 08 — Visão do produto SaaS, bounded contexts e matriz de rastreabilidade

## 8.1 O produto em uma página

**Nome de trabalho:** plataforma de gestão para clínicas de exames / centros de diagnóstico (nome comercial a definir).

**Público-alvo:** clínicas de check-up, centros de diagnóstico e clínicas ocupacionais de pequeno e médio porte — organizações que hoje vivem de planilhas, um Access na recepção e um portal PHP artesanal como a Precordis.

**Proposta de valor:** um único sistema, vendido por assinatura, que cobre o paciente da **agenda** à **entrega do laudo assinado digitalmente**, com **painel de chamada em TV**, **portal do paciente e da empresa**, **pesquisa de satisfação** e **trilha de auditoria LGPD** — substituindo os cinco subsistemas desconectados do legado e o banco Access. Cada clínica é um **tenant** isolado; a Precordis é o primeiro.

**Diferencial sobre o legado:** identidade única do paciente (hoje espalhada por nome em 3 bancos), autorização de verdade no servidor, laudo com assinatura ICP-Brasil, e tudo auditável.

## 8.2 Bounded contexts / módulos

Ajustados ao que a análise encontrou (não é a lista genérica — cada módulo mapeia subsistemas reais do legado):

| Módulo | Responsabilidade | Nasce de |
|---|---|---|
| **Tenancy & Billing** | Clínicas (tenants), planos, assinatura, limites por plano, cobrança recorrente | novo |
| **Identity & Access (ACL)** | Usuários, papéis e permissões **por tenant**, 2FA, auditoria de acesso | 3 logins fragmentados da raiz/agenda/TV |
| **Cadastros** | Paciente (identidade única), empresa/convênio, profissional, unidade, sala, equipamento | `usuario`,`info_usuario`,`empresas`,`salas` |
| **Catálogo de Exames** | Tipos de exame, preparos, orientações (os PDFs de `orientacoes/`) | `orientacoes/`, colunas de exame do espelho/checkin |
| **Anamnese** | Ficha clínica preenchida pelo paciente/recepção | `formulario*`, `saude_usuario` |
| **Agenda** | Agendamento (dia ou horário), confirmação, cancelamento, falta, reconvocação | `agendaprecordis`, `agendamento` |
| **Recepção & Check-in** | Chegada, progresso por exame | `checkinCliente`, `checkincliente` |
| **Painel de Chamada (TV)** | Chamada por sala, roteiro, status de sala, PIN | `precordistv`, `samarco.*` |
| **Resultados & Laudos** | Resultado clínico estruturado, laudo PDF, assinatura ICP-Brasil, liberação | `relatorio_prec`, `private/`, `mensagens` |
| **Portal do Paciente** | Agendar, preparo, baixar laudo, responder pesquisa | novo (hoje só download no `manager.php`) |
| **Portal da Empresa** | Exames dos funcionários, ASO, relatórios | novo |
| **Pesquisa de Satisfação** | NPS + satisfação | `pesquisaSatisfacao` |
| **Notificações** | E-mail/WhatsApp/SMS, campanhas, lembretes | `mensagens`, `lembra_procedimento`, n8n |
| **Auditoria** | Trilha de todo acesso a dado clínico (LGPD) | `watchdog` (ampliado) |
| **Relatórios & BI / Dashboard** | Indicadores operacionais e clínicos | novo |
| **Integração Legado (ETL)** | Importar Access + 3 bancos para o primeiro tenant | novo, temporário |

## 8.3 Matriz de rastreabilidade (legado → novo)

Status: **migrar-igual** / **redesenhar** / **descartar** / **nova**.

| Funcionalidade legada | Arquivo(s) | Módulo novo | Status |
|---|---|---|---|
| Login paciente/médico/admin | `_login.php` | Identity & Access | redesenhar (hash forte, 2FA p/ clínico) |
| Login agenda (setor) | `valida.php` | Identity & Access | redesenhar (vira usuário por pessoa) |
| Login TV (compartilhado) | `auth.php` | Identity & Access | redesenhar (token de dispositivo) |
| Gerenciador de laudos | `manager.php` | Resultados & Laudos + Portal do Paciente | redesenhar (acesso no servidor, storage fora do webroot) |
| Download em massa | `baixar_zip.php` | Resultados & Laudos | redesenhar (autorizado, por id) |
| CRUD usuários | `usuarios*.php` | Identity & Access | migrar-igual (conceito) |
| CRUD empresas | `empresas*.php` | Cadastros | migrar-igual |
| Log de acesso | `acessos.php`,`watchdog` | Auditoria | redesenhar (amplia p/ acesso a dado clínico) |
| E-mails agendados | `agenda*.php`,`send-mail.php` | Notificações | redesenhar (fila/jobs) |
| Agendamento | `agendaprecordis` | Agenda | redesenhar (identidade, trava real, horário opcional) |
| Confirmação/campanha WhatsApp | `listar_*`, n8n | Notificações | redesenhar (fila + operador formalizado) |
| Convocação de exame | `*_envio_convocacao_exame` | Notificações | migrar-igual |
| Reconvocação +1 ano | `listar-...-ano.php` | Agenda/Notificações | migrar-igual |
| Check-in / progresso | `checkinCliente` | Recepção & Check-in | redesenhar (tabela de exames, tempo real) |
| Painel TV / chamada | `precordistv` | Painel de Chamada | migrar-igual (é a referência) + tempo real (Reverb) |
| Salas / PIN / roteiro | `salas*`,`roteiro_api` | Painel de Chamada | redesenhar (corrige B2/B3) |
| Anamnese | `formulario*`,`saude_usuario` | Anamnese | redesenhar (estrutura, só o necessário) |
| Resultado clínico | `relatorio_prec` (Access) | Resultados & Laudos | **nova** (deixa de ser espelho; vira entidade 1ª classe) |
| Escore Framingham | `framingham` | Resultados & Laudos | migrar-igual (campo derivado) |
| NPS + satisfação | `pesquisaSatisfacao` | Pesquisa de Satisfação | redesenhar (corrige vínculo B5) |
| Orientações (PDFs) | `orientacoes/` | Catálogo de Exames | migrar-igual (anexos por exame) |
| Flipbook institucional | `apresentacao.html` | — | descartar |
| Backups/testes/zips | `BKP_SERV`,`teste*`,`compactacao`,`arquivos_zip` | — | descartar |
| Sincronizador Access | externo | Integração Legado | descartar após ETL |
| Resíduo Drupal | `taxonomy_term_data` | — | descartar |
| Portal do paciente (self-service) | — | Portal do Paciente | **nova** |
| Portal da empresa | — | Portal da Empresa | **nova** |
| Assinatura/billing | — | Tenancy & Billing | **nova** |
| Dashboard/BI | — | Relatórios & BI | **nova** |
