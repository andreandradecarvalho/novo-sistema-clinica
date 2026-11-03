# Vínculos empregatícios — uso e limites

[Confirmado] Entrega da etapa 2 baseada no [contrato ocupacional §23.1](../fluxos/23-saude-ocupacional.md#231-fronteiras), com telas Blade seguindo os cards e formulários do [mapa Vuexy](../layout/vuexy-assets-e-modelos-por-pagina.md). Status e evidências ficam no [fluxo 20](../fluxos/20-plano-desenvolvimento.md).

## Acesso

[Confirmado] Entre como administrador nos [acessos locais](acessos-locais.md), conclua o segundo fator e abra Pacientes → paciente → Vínculos empregatícios. Novo vínculo apresenta empresas ativas da clínica; selecione uma e informe início, estabelecimento opcional, matrícula, função e setor. Empresas e estabelecimentos são mantidos no [cadastro empresarial](cadastro-empresas.md).

[Confirmado] O provisionamento idempotente concede employments.view/employments.manage ao administrador da clínica. Consulta exige também patients.view; criação, versionamento e desligamento exigem gestão do paciente e companies.view. Policies verificam usuário ativo, clínica, paciente exato e segundo fator. Cadastrar um vínculo não concede acesso empresarial ou clínico a nenhum usuário.

## Histórico e operações

[Confirmado] O início é obrigatório no cadastro manual. Períodos têm datas inclusivas; Nova versão de função / estabelecimento exige início posterior ao da versão anterior e fecha essa versão no dia imediatamente anterior. Empresa e paciente são imutáveis; trocar de empregador exige outro vínculo. Múltiplos vínculos simultâneos são permitidos. A versão anterior continua disponível e seu fechamento por versionamento não registra desligamento.

[Confirmado] Registrar desligamento exige data igual ou posterior ao início da versão e preenche ends_on e termination_date. Datas futuras explicitamente informadas são aceitas e exibidas literalmente. Qualquer versão com encerramento preenchido fica bloqueada para novo versionamento/desligamento, inclusive com encerramento futuro. Registros legados sem início conhecido permanecem consultáveis, mas não podem sofrer essas operações automaticamente.

[Confirmado] Novos vínculos e versões exigem empresa/estabelecimento ativos da própria clínica; o estabelecimento deve pertencer à empresa escolhida. Inativação posterior preserva a consulta e permite desligamento do vínculo existente. Não há exclusão, reabertura ou correção retroativa nesta interface; esse fluxo exige definição e entrega próprias.

[Confirmado] Cada versão guarda os nomes da empresa/estabelecimento no campo context_encrypted existente, usando cast encrypted:array e payload de versão 1 construído no servidor. Renomear o cadastro não muda esses rótulos históricos. Registros anteriores sem contexto usam o cadastro relacionado como fallback. Matrícula/função/setor permanecem nos campos previstos no schema, protegidos pela autorização; não há alegação de criptografia integral da tabela. Preservar APP_KEY e backups é necessário para ler os snapshots.

## Integridade, auditoria e validação

[Confirmado] Criação, versionamento e desligamento usam transação e bloqueio das linhas do paciente/vínculo. Versionamento relê o registro no banco e rejeita requisição baseada em versão já encerrada; falha ao persistir a auditoria reverte o fechamento e a nova versão. RegistryAudit registra eventos e IDs, incluindo previous_link_id no evento de nova versão, sem copiar nomes, matrícula ou função. Leitura também gera evento. Entradas pessoais do formulário não ficam no flash de validação.

[Confirmado] O model impede reescrever campos históricos ou excluir via save/delete; FKs compostas protegem referências entre clínicas. A igualdade entre estabelecimento e empresa é validada na Action. SQL bruto e operações em massa exigem revisão própria; não estão universalmente protegidos pelos eventos do model. Não há cache de consultas de vínculos nesta entrega; o provisionamento invalida o cache de permissões.

[Confirmado] A [migration](../../database/migrations/2026_11_03_000005_create_patient_company_links.php) reproduz patient_company_links do DDL de referência; validação e reversibilidade estão no [CHANGELOG](../schema/CHANGELOG.md). Os [testes](../../tests/Feature/EmploymentLinkTest.php) cobrem dois tenants, paciente errado na mesma clínica, empresa/estabelecimento incompatíveis, períodos, ACL, snapshots, requisição obsoleta e falha transacional. Não foi executado teste de carga com escritores paralelos nem inspeção visual manual autenticada das novas telas; renderização foi exercitada por HTTP nos testes.

[Confirmado] Este cadastro não implementa avaliação ocupacional, protocolos, ASO, transmissão eSocial ou o portal empresarial. A [gestão administrativa de concessões](concessoes-empresariais.md) possui entrega própria. Também não altera patients.company_id como resumo legado. Aceite da etapa 2 permanece pendente, conforme o fluxo 20; CI remoto segue para verificação futura por instrução do usuário.
