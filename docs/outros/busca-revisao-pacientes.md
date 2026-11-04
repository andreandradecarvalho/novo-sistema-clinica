# Busca e comparação de pacientes

[Confirmado] Entrega da etapa 2, vinculada ao [cadastro de pacientes](cadastro-pacientes.md) e ao [plano de execução](../fluxos/20-plano-desenvolvimento.md). Telas usam formulário vertical, cards e tabelas básicas do [mapa Vuexy](../layout/vuexy-assets-e-modelos-por-pagina.md). Busca e comparação isoladas auxiliam a revisão humana, sem mutações. O [merge cadastral autorizado](merge-pacientes.md) tem formulário e histórico próprios.

## Uso

[Confirmado] Abra Pacientes → Buscar pacientes. Escolha um dos formulários: CPF exato, com ou sem máscara canônica, ou início do nome, com pelo menos três caracteres e nascimento opcional. Nome usa a mesma normalização do cadastro: espaços consolidados, transliteração ASCII e minúsculas. A busca é por prefixo do nome completo, sem aproximação fonética ou fragmento no meio do nome; nascimento, quando informado, exige a mesma data.

[Confirmado] O resultado mostra pacientes não excluídos da clínica atual, com nome, nascimento e indicação de CPF cadastrado, em páginas de 20 registros. Caracteres SQL %, _ e ! são tratados literalmente no nome. CPF é validado e comparado pelo HMAC existente, sem descriptografar ou exibir o número. Cadastro excluído logicamente não aparece. Não há compartilhamento de identidade entre clínicas.

[Confirmado] Para comparar, abra um paciente → Revisar homônimos → Comparar. A lista apresenta outros cadastros com o mesmo nome normalizado, inclusive quando os nascimentos divergem ou estão ausentes. Isso é um critério de consulta, não uma confirmação de duplicidade. A comparação mostra identificação e contato lado a lado; CPF permanece sem exibição. As duas identidades são autorizadas separadamente no servidor.

## Segurança e estado da busca

[Confirmado] Busca exige patients.view, ator ativo, clínica correta e segundo fator confirmado. Revisão/comparação exige também patients.review, nova permissão concedida somente ao administrador no provisionamento idempotente. Não exige nem concede permissão de merge. Sem contexto, as consultas tenant-aware recusam a operação; binding de identidade de outra clínica retorna 404.

[Confirmado] Os formulários enviam critérios por POST com CSRF. URLs de resultado/paginação recebem somente o número da página; não incluem CPF, hash, nome ou nascimento. A sessão mantém um único conjunto de critérios: HMAC para CPF, ou nome normalizado/nascimento, associado a usuário/clínica e prazo de 15 minutos. Nova busca substitui os critérios anteriores e afeta abas da mesma sessão. Resultado sem critérios válidos redireciona ao formulário e remove o estado inválido/expirado.

[Confirmado] Manter SESSION_ENCRYPT=true conforme o ambiente local/homologação documentado é necessário para cifrar o payload persistido da sessão. Nome normalizado, nascimento e HMAC continuam dados pessoais; não exportar sessão, logs de corpo da requisição ou dumps com seus valores. O prazo impede reutilização na aplicação; a remoção física de uma sessão inativa segue sua expiração/coleta, não um job novo desta entrega.

[Confirmado] Rotas novas retornam Cache-Control privado/no-store. Dados pessoais do formulário não entram no flash de validação. Auditoria registra eventos de busca/listagem de homônimos e IDs das duas identidades consultadas na comparação, sem critérios ou conteúdo pessoal. Não há cache de resultados a invalidar; cada consulta reavalia autorização e banco.

## Limites

[Confirmado] Nenhum cadastro é unido, alterado, excluído ou marcado automaticamente. Homônimos não comprovam mesma pessoa; contatos podem ser compartilhados. A comparação não registra aprovação/rejeição nem substitui a verificação humana por fonte autorizada. A [decisão positiva e o merge cadastral](merge-pacientes.md) agora possuem formulário e histórico próprios, com vínculos empregatícios preservados. A consulta/comparação isolada continua sem mutações. Consolidação de outros históricos dependentes segue os limites do documento de merge.

[Confirmado] Não foi utilizada patient_match_candidates: essa tabela pertence ao ETL e exige migration_runs/origem legada, conforme [contrato 13](../fluxos/13-plano-migracao-dados.md). Nenhuma execução de migração fictícia foi criada para viabilizar esta consulta. Não houve mudança de DDL/migration, importação ou acesso ao legado.

[Confirmado] Os [testes](../../tests/Feature/PatientSearchTest.php) cobrem dois tenants, CPF/HMAC, normalização, filtro por nascimento, escape de curingas, paginação sem critérios na URL, prazo/proprietário da sessão, validação sem flash pessoal, ACL, binding, comparação auditada e ausência de alteração cadastral. Inspeção visual manual autenticada e ensaio de carga não foram realizados. CI remoto permanece para verificação futura por instrução do usuário; evidências locais e status ficam no fluxo 20.
