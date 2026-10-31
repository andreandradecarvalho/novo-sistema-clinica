# 07 — Inconsistências e problemas de modelagem

> Medições em produção de 31/10/2026. [C].

## 7.1 Identidade e duplicidade

| Problema | Medição | Consequência |
|---|---|---|
| `usuario.login` sem `UNIQUE` | **49 grupos** de login duplicado (404 linhas) | login ambíguo; `_login.php` pega o primeiro |
| Senha md5 repetida | 69 grupos (152 linhas) | senhas iguais entre pacientes (código fraco) |
| CPF duplicado em `info_usuario` | **80 grupos / 219 cadastros** | mesmo paciente cadastrado várias vezes |
| CPF ausente | **3.803 de 10.015 (38%)** | CPF não serve como chave única para 1/3 da base |
| CPF inválido (dígito verificador) | 160 de 6.171 preenchidos | precisa validação |
| `agendamento` sem `patient_id` | 29,9% não casam com a base por nome | histórico de agenda difícil de atribuir |
| Nome como única chave entre bancos | acentuação inconsistente (ver 7.3) | deduplicação dependente de normalização |

## 7.2 Normalização

- **PK composta artificial** em `info_usuario` e `saude_usuario` (`(id, id_usuario)`) — `id` já é `AUTO_INCREMENT` único; o `id_usuario` na PK é desnecessário e atrapalha FK. [C]
- **Pares coluna/status repetidos** em `espelho_atendimento` (~20 pares `<Exame>`/`<Exame>_Status`) e **19 colunas de exame** em `checkincliente` — deveriam ser linhas de uma tabela `exame_do_atendimento`. [C]
- **`relatorio_prec` com 63 colunas** achatando resultado clínico, risco e conclusões num só registro — mistura laboratório, antropometria, cardiologia, conclusões textuais. [C]
- **`empresas` com 3 índices idênticos** em `em_nome` (`em_nome`, `em_nome_2`, `em_nome_3`). [C]
- Dados de WhatsApp (15+ colunas) embutidos em `agendamento` em vez de tabela de notificação. [C]

## 7.3 Domínios, tipos e codificação

| Problema | Medição |
|---|---|
| **Datas em `varchar`**: `info_usuario.dt_nascimento` | 5.748 `Y-m-d`, 1.259 `d/m/Y`, 3.006 vazio, 2 outros |
| `agendamento.datanasc` (varchar) | 6.569 `d/m/Y`, 282 "outro", 42 vazio, 1 `Y-m-d` |
| `saude_usuario.data` em varchar | — |
| **`agendamento.Status` é lixo** | só `''`(3.673), `off`(3.220), `' '`(1) — o estado real vive em `ConfirmaAgendamento`+`DesistiuCancelou`+`FaltouNoDia`. **Não migrar** |
| **`DesistiuCancelou` nunca `NULL`** | `''`(6.145)/`SIM`(749) → quebra a trava de duplicidade (B1) |
| **`sexo` poluído** | `agendamento.sexo`: `Masc`(5.306), `Fem`(1.134), vazio(156), **298 com nome de pessoa** (B6) |
| **`estado_civil` sem padrão** | `Casado`/`Casado(a)`/`Casada` etc. — ~10 variantes para 3 conceitos |
| **`fumante` com mojibake** | `N�o`(6.420)/`Sim`(348) — `latin1` vs utf8 |
| **`Empresa` texto livre no agendamento** | 121 valores distintos; 43 não existem em `empresas` (186 linhas); 14 são números (`em_id` gravado como nome); `ANGLO GOLD` vs `ANGLO GOLD ASHANTI`; `vale`/`Vale` |
| **`em_parent` órfão** | 1 filial aponta para `em_id` inexistente |
| Valores monetários | inexistentes no legado (não há faturamento) — mas no novo usar `DECIMAL`/centavos |
| `watchdog.wa_ip varchar(16)` | não cabe IPv6 |
| `framingham` negativo | mínimo -7 (erro de origem no Access) |

**Observação de charset**: apesar de `precordis2016` ser `latin1`, os **nomes em `usuario` estão corretos** (0 com `Ã`/`Â`) — a dupla codificação aparece em `saude_usuario.fumante` e em `agendamento.Empresa`/`mensagens.ms_subject`. A migração precisa detectar e corrigir por coluna, não assumir um encoding único. [C]

## 7.4 Integridade e auditoria

| Problema | Medição |
|---|---|
| Só 2 FKs declaradas nos 3 bancos | integridade referencial no código (frágil) |
| **Órfãos**: `info_usuario` sem `usuario` | 40 |
| `saude_usuario` órfãos | 6 |
| `permissao` órfãos | 331 |
| `mensagens` órfãs (sem `usuario`) | 4.328 |
| `usuarios sem permissao` | 9 |
| FK `ON DELETE CASCADE` perigosa | `roteiro_paciente`→`espelho_atendimento` (B2) |
| Sem `created_at/updated_at/deleted_at/created_by` padronizados | a maioria só tem `DataCadastro`/`data_modificacao` em algumas tabelas |
| Sem soft delete | exclusões são `DELETE` físico (usuários, mensagens, permissões) |
| `status_salas` sem FK → `salas` | sala fantasma `CLINICO` ocupada desde 13/08 (B3) |

## 7.5 Tabelas/colunas obsoletas ou mortas

| Objeto | Evidência | Recomendação |
|---|---|---|
| `taxonomy_term_data` | resíduo Drupal, nenhum código ativo usa | descartar |
| `entrada` (agendaprec) | login antigo, sem uso no código | descartar |
| `mensagens` (fila) | morta desde 2022 | arquivar histórico, não migrar como fila |
| `agendamento.Status` | lixo | não migrar |
| `agendamento.Remarcacoes` | sempre 0 | descartar |
| `CodigoAuxPrincipal` (espelho) | sempre 0 | descartar |
| `permissao_diretorioComposto` | 9 linhas, 3 usuários | reavaliar no modelo novo |
| `checkincliente` | subsistema inativo desde 03/2026 | migrar como histórico, não como feature viva |
| Pastas `BKP_SERV/`, `teste*`, `*TESTE.php`, `*_BKP.php`, `manager_teste.php` | duplicatas | remover |
| `compactacao/*.tar`, `arquivos_zip/*.zip` | laudos exportados esquecidos | remover do servidor (dado sensível) |
| `Stuk/`, `agendaprecordis/vendor/` | libs não usadas no PHP | remover do deploy |

## 7.6 Síntese do impacto na migração

1. **Deduplicação de pacientes** é o maior risco: CPF cobre 62%, o resto cai em nome+nascimento com revisão humana (219 CPFs duplicados, 49 logins duplicados).
2. **~30% dos agendamentos viram "paciente parcial"** (sem cadastro na base) — fluxo principal do ETL, não exceção.
3. **Resultado clínico real está no `relatorio_prec`/Access**, não no portal — precisa entrar no schema novo como entidade de resultado.
4. **Datas e encoding** exigem parser por linha/coluna.
5. **Índices únicos novos (ex.: login, CPF, nome+data)** vão rejeitar dados legados — tratar duplicatas **antes** de aplicar constraints.
