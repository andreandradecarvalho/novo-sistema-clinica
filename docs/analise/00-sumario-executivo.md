# 00 — Sumário executivo

> Análise do legado **Precordis** (`precordis-admin-completo/`) e base para a especificação do novo SaaS multi-tenant em Laravel. Realizada em **31/10/2026** sobre o código, os dumps e os **três bancos de produção** (consulta somente leitura). Senhas sempre mascaradas.

## Situação atual

A Precordis opera um sistema de clínica de check-up montado como **5 subsistemas PHP desconectados** (portal de resultados, agendamento, anamnese em tablet, check-in, painel de TV) sobre **3 bancos MySQL 5.7** (`precordis2016`, `agendaprec`, `samarco`) — e, por trás, um **banco Microsoft Access** que é a fonte real do resultado clínico, espelhado para o MySQL por uma rotina externa ~1×/min. São ~10 mil pacientes, ~900 atendimentos recentes espelhados e **719 MB de laudos em PDF**. A operação real é de ~10–12 check-ups por dia útil.

O código tem ilhas de qualidade (o módulo `precordistv` é moderno e bem escrito) sobre uma base majoritariamente PHP 5 escrita em PHP 7.3, sem framework, sem Composer de aplicação, com HTML/SQL/conexão no mesmo arquivo e muita duplicação (6 listagens quase iguais, 4 cópias do formulário, credenciais repetidas em ~45 arquivos).

## Principais riscos (detalhe em [05](05-seguranca-lgpd-conformidade.md))

1. **Credenciais de produção no repositório, válidas e aceitando conexão de qualquer IP** (os 3 usuários são `@'%'` com `ALL PRIVILEGES`). É o item mais urgente.
2. **Controle de acesso aos laudos roda no navegador**; o servidor devolve o diretório inteiro, e as proteções de caminho estão comentadas. Há endpoints de download/exclusão **sem autenticação**.
3. **Autorização do painel admin é contornável** — `header("Location")` sem `exit`, então o script continua.
4. **Senhas de paciente em texto plano** (e md5 sem salt), enviadas por e-mail; senhas são códigos de 5 dígitos.
5. **SQL Injection, XSS e ausência de CSRF** em vários pontos.
6. **LGPD/dado de saúde**: sem log de acesso a laudo/anamnese, consentimento registrado em só ~10% dos pacientes, laudos no document root, operador terceiro (n8n) sem contrato visível, dumps e `.tar` de laudos esquecidos no servidor.
7. **Conformidade de prontuário**: laudos sem assinatura digital ICP-Brasil (Lei 13.787/2018); sem política de guarda de 20 anos.

**Bugs ativos achados nos dados**: trava de agendamento duplicado inoperante desde sempre; `roteiro_paciente` zerado por FK em cascata contra o sync; sala fantasma ocupada desde agosto; fila de e-mail morta desde 2022 (1.297 pendentes); NPS e satisfação sem vínculo; 298 registros com sexo gravado como nome.

**Qualidade de dados que dita a migração**: 38% dos pacientes sem CPF; datas de nascimento em formatos mistos; ~30% dos agendamentos sem paciente identificável; o vínculo entre os sistemas é feito por **nome de pessoa**, não por chave.

## Recomendação

Reescrever como **SaaS multi-tenant em Laravel 13 / PHP 8.4 / MySQL 8.4 LTS**, com **um banco por clínica** (isolamento físico, melhor para LGPD e backup/restore), **ACL por tenant**, **assinatura recorrente**, identidade única do paciente, laudo com assinatura ICP-Brasil e auditoria de acesso. A Precordis é o primeiro tenant.

**Antes da reescrita**, executar a **Fase 0 de contenção** no legado (rotacionar segredos, tirar arquivos sensíveis do webroot, fechar os endpoints abertos) — são falhas exploráveis hoje. Em paralelo, **localizar e mapear o Access**, que amplia o escopo e é a fonte do laudo.

## Esforço

Projeto em 10 fases (0 a 9), estratégia **strangler** (módulo a módulo), com corte no fim de semana (a operação é quase só em dias úteis). MVP = fundação + cadastros + agenda + check-in + TV + ETL parcial. As estimativas por fase estão em [17](../proposta-novo-sistema/17-roadmap-fases.md); são faixas de ordenação, a fechar com o time e com as respostas de [perguntas-em-aberto.md](perguntas-em-aberto.md).

## Índice da análise

| Doc | Conteúdo |
|---|---|
| [01 — Inventário](01-inventario.md) | subsistemas, versões, dependências, integrações, crons, volumetria |
| [02 — Dependências e atores](02-mapa-dependencias-atores.md) | mapa (Mermaid), sessões, chamadas cruzadas, perfis |
| [03 — Fluxos, telas, formulários](03-fluxos-telas-formularios.md) | por subsistema, com regras embutidas |
| [04 — Regras de negócio](04-regras-de-negocio.md) | ciclo do exame, agenda, bugs confirmados |
| [05 — Segurança, LGPD, conformidade](05-seguranca-lgpd-conformidade.md) | riscos com arquivo/linha/severidade/correção |
| [06 — Bancos: dicionário e ER](06-bancos-legados-dicionario-er.md) | 3 bancos, ER atual, cruzamento |
| [07 — Inconsistências/modelagem](07-inconsistencias-modelagem.md) | identidade, tipos, órfãos, tabelas mortas |
| [perguntas-em-aberto](perguntas-em-aberto.md) | o que o negócio precisa decidir |

A **especificação do novo produto** está em [`../proposta-novo-sistema/`](../proposta-novo-sistema/00-indice.md) (seções 8 a 19).
