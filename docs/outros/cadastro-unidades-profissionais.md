# Unidades clínicas e profissionais — etapa 2

[Confirmado] Entrega de cadastros administrativos para unidades de atendimento da clínica e perfis profissionais da equipe. Telas seguem tabelas básicas e formulários verticais do [mapa Vuexy](../layout/vuexy-assets-e-modelos-por-pagina.md). Status e evidências permanecem exclusivamente no [fluxo 20](../fluxos/20-plano-desenvolvimento.md).

## Acesso local

[Confirmado] Entre com um administrador dos [acessos de homologação](acessos-locais.md#homologação-local-isolada--duas-clínicas), conclua o segundo fator e abra os menus Unidades clínicas ou Profissionais:

| Clínica | Unidades clínicas | Profissionais |
|---|---|---|
| [Confirmado] A | http://clinica-a.localhost:8082/units | http://clinica-a.localhost:8082/professionals |
| [Confirmado] B | http://clinica-b.localhost:8082/units | http://clinica-b.localhost:8082/professionals |

[Confirmado] O provisionamento idempotente concede units.view/units.manage e professionals.view/professionals.manage ao papel administrativo da clínica. Consulta exige a permissão de leitura; criação/edição também exige a de gestão. Policies conferem usuário ativo, contexto da clínica e segundo fator confirmado. Outros papéis não recebem concessão automática nesta entrega.

## Unidades clínicas

[Confirmado] `/units` lista, cadastra, consulta e edita nome, cidade/UF opcionais e situação. Inativação preserva o cadastro e seu public_id. A tabela units já existia na fundação; não foi recriada nem alterada. As unidades pertencem à clínica e são distintas dos estabelecimentos de empregadores (company_units), conforme [23 §23.1](../fluxos/23-saude-ocupacional.md#231-fronteiras).

[Confirmado] A entrada HTTP não pode escolher tenant ou empresa. Cidade/UF usam as mesmas regras e componente visual dos estabelecimentos empresariais. Cadastrar ou inativar uma unidade não altera vínculos de integrantes, não transfere atendimentos e não implementa sozinho a restrição operacional de recepção por unidade. A [atribuição administrativa](atribuicao-unidades.md) está disponível; as restrições operacionais dos módulos futuros permanecem pendentes.

## Profissionais

[Confirmado] Novo profissional abre uma lista paginada de integrantes ativos da própria clínica, sem perfil profissional existente. Selecione um integrante e informe os dados cadastrais. É necessário que o usuário já exista; este fluxo não cria conta, não altera senha e não concede papel, permissão ou habilitação clínica.

[Confirmado] O perfil permite consultar/editar conselho, UF, número do registro e especialidade. Quando algum dado do registro é informado, conselho/UF/número devem ser preenchidos em conjunto. A validação é estrutural, com limites do schema e UF brasileira; não consulta conselhos profissionais nem certifica registro, especialidade, RQE ou regularidade. A conferência de habilitação e as Policies de execução/assinatura continuam sendo requisitos das etapas clínicas correspondentes.

[Confirmado] O vínculo com o usuário é imutável e único, protegido na Action/model, por índice UNIQUE e por FK composta de tenant/usuário. Usuário de outra clínica, inativo ou excluído logicamente não pode receber um perfil novo. Dados de perfis existentes são preservados mesmo se o integrante ficar inativo; a situação exibida vem do usuário, pois professionals não possui coluna is_active. Não há exclusão nem troca de usuário nesta interface.

[Confirmado] Associação de profissional a uma unidade clínica não é um campo independente de professionals neste schema; o vínculo de unidade do integrante pertence a users.unit_id. Não foi criada uma associação paralela nem uma regra automática de transferência nesta entrega.

## Auditoria, estrutura e limites

[Confirmado] RegistryAudit grava eventos de criação, edição, listagem e consulta com IDs de recurso/tenant/ator; nomes, números de registro e conteúdo clínico não são copiados para properties. Nome, cidade, conselho, número do registro e especialidade são excluídos do flash de validação. Não há cache de consultas de unidades/profissionais a invalidar; o provisionamento invalida o cache de permissões.

[Confirmado] A nova [migration de professionals](../../database/migrations/2026_11_03_000004_create_professionals.php) reproduz a tabela prevista no [schema](../schema/schema.sql). Alteração, teste do motor e reversibilidade estão no [CHANGELOG](../schema/CHANGELOG.md). As antigas migrations e o DDL de referência permanecem intactos.

[Confirmado] A etapa 2 ainda depende das restrições operacionais por unidade/empresa nos módulos futuros e busca/revisão/merge humano auditado de pacientes. Nenhum exame, prontuário, laudo ou ASO foi implementado por estes cadastros. CI remoto permanece para verificação futura conforme a exceção autorizada no fluxo 20.

[Confirmado] Histórico de vínculos com vigência disponível em [vínculos empregatícios](vinculos-empregaticios.md).

[Confirmado] Gestão administrativa de concessões disponível em [concessões empresariais](concessoes-empresariais.md); integração dos portais permanece futura.
