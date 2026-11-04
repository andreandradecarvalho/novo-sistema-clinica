# Mapa da documentação

[Confirmado] Aplicação na [raiz do repositório](../), com [composer.json](../composer.json) e [artisan](../artisan); documentação nas pastas analise, fluxos, layout, outros e schema. Orquestração: [AGENTS](../AGENTS.md). Não confundir documentação/DDL com aplicação instalada. Correções atuais em [pendências](outros/PENDENCIAS.md).

## Fontes únicas por assunto

| Estado | Assunto | Fonte normativa |
|---|---|---|
| [Confirmado] | Produto e fronteiras | [08](fluxos/08-visao-produto-bounded-contexts.md) |
| [Confirmado] | Isolamento e propriedade | [09](fluxos/09-estrategia-multi-tenant.md) |
| [Confirmado] | Assinatura SaaS | [10](fluxos/10-assinatura-billing.md) |
| [Confirmado] | Identidade e papéis | [11](fluxos/11-identidade-autenticacao-acl.md), complementado pela matriz 24 |
| [Confirmado] | Modelo/DDL | [12](fluxos/12-novo-schema-unificado.md), [schema.sql](schema/schema.sql); aplicação/validação em [21](fluxos/21-schema-e-reaproveitamento.md) e [CHANGELOG](schema/CHANGELOG.md) |
| [Confirmado] | ETL, identidade de origem e corte | [13](fluxos/13-plano-migracao-dados.md) |
| [Confirmado] | Versões e estrutura da aplicação | [14](fluxos/14-arquitetura-laravel.md) |
| [Confirmado] | Qualidade/CI | [15](fluxos/15-qualidade-testes-devops.md) |
| [Confirmado] | Etapas, dependências, PR e status | [20](fluxos/20-plano-desenvolvimento.md); 17 é somente visão de roadmap |
| [Confirmado] | Atendimento, exames e financeiro clínico | [22](fluxos/22-atendimento-clinico.md) |
| [Confirmado] | Ocupacional e ASO | [23](fluxos/23-saude-ocupacional.md) |
| [Confirmado] | Segurança, sigilo, guarda e operação | [24](fluxos/24-seguranca-retencao-operacao.md) |
| [Confirmado] | Interface/CSS/JS/imagens | [Vuexy assets](layout/vuexy-assets-e-modelos-por-pagina.md), [cobertura](layout/vuexy-cobertura-completa-144-paginas.md); integração técnica no 14 |
| [Confirmado] | Estrutura/conexões legadas | [banco legado](outros/banco-legado.md), que referencia o dicionário 06 |
| [Confirmado] | Código legado por subsistema | [índice legado](outros/indice-legado.md) |
| [Confirmado] | Questões de negócio/implantação | [19](fluxos/19-perguntas-em-aberto-negocio.md) |

[Confirmado] analise/01–07 são evidências históricas do legado; o relatório 08 é o snapshot anterior às correções. O processo de migração antigo e o prompt em outros são históricos, não contratos concorrentes. Requisitos do novo sistema prevalecem nos fluxos normativos; instrução explícita do usuário prevalece sobre documentos.

## Catálogo de documentos

| Estado | Documento | Classificação |
|---|---|---|
| [Confirmado] | [00 — Sumário executivo](<analise/00-sumario-executivo.md>) | Documento do tema; escopo e status no próprio arquivo. |
| [Confirmado] | [01 — Inventário do projeto legado](<analise/01-inventario.md>) | Documento do tema; escopo e status no próprio arquivo. |
| [Confirmado] | [02 — Mapa de dependências entre subsistemas e atores](<analise/02-mapa-dependencias-atores.md>) | Documento do tema; escopo e status no próprio arquivo. |
| [Confirmado] | [03 — Fluxos, telas e formulários por subsistema](<analise/03-fluxos-telas-formularios.md>) | Documento do tema; escopo e status no próprio arquivo. |
| [Confirmado] | [04 — Regras de negócio extraídas](<analise/04-regras-de-negocio.md>) | Documento do tema; escopo e status no próprio arquivo. |
| [Confirmado] | [05 — Segurança, privacidade (LGPD) e conformidade](<analise/05-seguranca-lgpd-conformidade.md>) | Documento do tema; escopo e status no próprio arquivo. |
| [Confirmado] | [06 — Bancos legados: dicionário de dados e diagramas ER (estado atual)](<analise/06-bancos-legados-dicionario-er.md>) | Documento do tema; escopo e status no próprio arquivo. |
| [Confirmado] | [07 — Inconsistências e problemas de modelagem](<analise/07-inconsistencias-modelagem.md>) | Documento do tema; escopo e status no próprio arquivo. |
| [Confirmado] | [Revisão da documentação — SaaS para clínicas de exames e saúde ocupacional](<analise/08-relatorio-documentacao-saas-2026-11-03.md>) | Histórico; consultar fontes vigentes abaixo. |
| [Confirmado] | [Perguntas e decisões do projeto](<analise/perguntas-em-aberto.md>) | Documento do tema; escopo e status no próprio arquivo. |
| [Confirmado] | [Proposta — Novo sistema (SaaS multi-tenant em Laravel)](<fluxos/00-indice.md>) | Documento do tema; escopo e status no próprio arquivo. |
| [Confirmado] | [08 — Visão do produto SaaS, bounded contexts e matriz de rastreabilidade](<fluxos/08-visao-produto-bounded-contexts.md>) | Documento do tema; escopo e status no próprio arquivo. |
| [Confirmado] | [09 — Estratégia multi-tenant](<fluxos/09-estrategia-multi-tenant.md>) | Documento do tema; escopo e status no próprio arquivo. |
| [Confirmado] | [10 — Assinatura e billing](<fluxos/10-assinatura-billing.md>) | Documento do tema; escopo e status no próprio arquivo. |
| [Confirmado] | [11 — Identidade, autenticação e ACL (com mapeamento dos níveis legados)](<fluxos/11-identidade-autenticacao-acl.md>) | Documento do tema; escopo e status no próprio arquivo. |
| [Confirmado] | [12 — Modelo de dados e correspondência executável](<fluxos/12-novo-schema-unificado.md>) | Documento do tema; escopo e status no próprio arquivo. |
| [Confirmado] | [13 — Contrato único de migração e convivência](<fluxos/13-plano-migracao-dados.md>) | Documento do tema; escopo e status no próprio arquivo. |
| [Confirmado] | [14 — Arquitetura Laravel](<fluxos/14-arquitetura-laravel.md>) | Documento do tema; escopo e status no próprio arquivo. |
| [Confirmado] | [15 — Qualidade, testes, DevOps e observabilidade](<fluxos/15-qualidade-testes-devops.md>) | Documento do tema; escopo e status no próprio arquivo. |
| [Confirmado] | [16 — Novas funcionalidades e melhorias](<fluxos/16-novas-funcionalidades.md>) | Documento do tema; escopo e status no próprio arquivo. |
| [Confirmado] | [17 — Roadmap do produto](<fluxos/17-roadmap-fases.md>) | Documento do tema; escopo e status no próprio arquivo. |
| [Confirmado] | [18 — Riscos e pontos de atenção da migração](<fluxos/18-riscos-migracao.md>) | Documento do tema; escopo e status no próprio arquivo. |
| [Confirmado] | [19 — Perguntas em aberto para o negócio](<fluxos/19-perguntas-em-aberto-negocio.md>) | Documento do tema; escopo e status no próprio arquivo. |
| [Confirmado] | [20 — Plano de desenvolvimento, PR e controle de execução](<fluxos/20-plano-desenvolvimento.md>) | Documento do tema; escopo e status no próprio arquivo. |
| [Confirmado] | [21 — DDL e reaproveitamento de referências](<fluxos/21-schema-e-reaproveitamento.md>) | Documento do tema; escopo e status no próprio arquivo. |
| [Confirmado] | [22 — Atendimento, execução de exames e financeiro clínico](<fluxos/22-atendimento-clinico.md>) | Documento do tema; escopo e status no próprio arquivo. |
| [Confirmado] | [23 — Saúde ocupacional e ASO](<fluxos/23-saude-ocupacional.md>) | Documento do tema; escopo e status no próprio arquivo. |
| [Confirmado] | [24 — Segurança, retenção e operação do novo SaaS](<fluxos/24-seguranca-retencao-operacao.md>) | Documento do tema; escopo e status no próprio arquivo. |
| [Confirmado] | [Vuexy — Mapa de Assets (CSS/JS/JSON/i18n) e Modelos de Página](<layout/vuexy-assets-e-modelos-por-pagina.md>) | Documento do tema; escopo e status no próprio arquivo. |
| [Confirmado] | [Vuexy — Cobertura Completa das 144 Páginas do Menu Lateral](<layout/vuexy-cobertura-completa-144-paginas.md>) | Documento do tema; escopo e status no próprio arquivo. |
| [Confirmado] | [Pendências de conciliação documental](<outros/PENDENCIAS.md>) | Documento do tema; escopo e status no próprio arquivo. |
| [Confirmado] | [Precordis — Processo de Migração dos Bancos Legados](<outros/Precordis — Processo de Migração dos Bancos Legados.md>) | Histórico; consultar fontes vigentes abaixo. |
| [Confirmado] | [Prompt: Análise Profunda do Legado Precordis (PHP 5/7) e Especificação de um SaaS Multi-tenant em Laravel](<outros/prompt-analise-precordis-saas.md>) | Histórico; consultar fontes vigentes abaixo. |
| [Confirmado] | [Histórico do schema](<schema/CHANGELOG.md>) | Documento do tema; escopo e status no próprio arquivo. |

[Confirmado] Revisão técnica da fundação: [relatório da etapa 1](outros/revisao-etapa-01.md). Status e exceção de aceite permanecem no fluxo 20.

[Confirmado] Primeira entrega da etapa 2: [cadastro de pacientes — uso e limites](outros/cadastro-pacientes.md).

[Confirmado] Cadastros empresariais da etapa 2: [empresas clientes e estabelecimentos](outros/cadastro-empresas.md).

[Confirmado] Cadastros administrativos da clínica: [unidades clínicas e profissionais](outros/cadastro-unidades-profissionais.md).

## SQL e verificação

- [Confirmado] [schema.sql](schema/schema.sql) é o único DDL atual; antigos central.sql/tenant.sql foram substituídos. O split é de responsabilidades lógicas no banco compartilhado.
- [Confirmado] [Validador sintético](../scripts/validate_schema.py) cria e remove banco local descartável; execução/resultado e limitações no CHANGELOG. Não aponta para produção nem modifica o banco de trabalho.

[Confirmado] Operação da aplicação na raiz (instalação local, migrations, seeds e checks): [README do projeto](../README.md#ambiente-local). Entrega e evidências da fundação: [fluxo 20](fluxos/20-plano-desenvolvimento.md); estrutura executável: [migrations](../database/migrations/).

[Confirmado] Homologação local isolada da fundação e dos cadastros: [roteiro, clínicas e evidências](outros/homologacao-local.md). Credenciais ficam no arquivo de ambiente privado indicado no roteiro.

[Confirmado] Vínculos paciente–empregador, períodos, versões e desligamento: [uso e limites](outros/vinculos-empregaticios.md), com contrato no fluxo 23.

[Confirmado] Gestão do vínculo integrante–unidade: [atribuição de unidades](outros/atribuicao-unidades.md), conforme identidade/ACL do fluxo 11.

[Confirmado] Gestão administrativa do acesso por empregador: [concessões empresariais](outros/concessoes-empresariais.md), conforme fluxos 11/23/24.

[Confirmado] Identificação e apoio à revisão humana: [busca e comparação de pacientes](outros/busca-revisao-pacientes.md); identidade nos módulos clínicos futuros segue o planejamento do fluxo 20.

[Confirmado] Decisão positiva de identidade, arquivamento de duplicata e vínculos empregatícios preservados: [merge cadastral de pacientes](outros/merge-pacientes.md).

[Confirmado] Revisão técnica e homologação ampliada dos cadastros: [relatório da etapa 2](outros/revisao-etapa-02.md). O aceite e o avanço de etapas permanecem exclusivamente no fluxo 20.
