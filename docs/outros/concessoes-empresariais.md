# Concessões empresariais — administração e limites

[Confirmado] Entrega da etapa 2 baseada nos contratos de [identidade/ACL](../fluxos/11-identidade-autenticacao-acl.md), [sigilo empresarial](../fluxos/23-saude-ocupacional.md#233-empresa-e-sigilo) e [autorização](../fluxos/24-seguranca-retencao-operacao.md#241-autorização). Status e evidências ficam no [fluxo 20](../fluxos/20-plano-desenvolvimento.md).

## Uso administrativo

[Confirmado] Entre com um administrador dos [acessos locais](acessos-locais.md), conclua o segundo fator e abra Empresas → empresa → Concessões empresariais. A lista preserva o histórico por integrante, categoria, início, fim e revogação. Nova concessão e revogação exigem confirmação recente da senha. A interface segue tabelas básicas e formulário vertical do [mapa Vuexy](../layout/vuexy-assets-e-modelos-por-pagina.md).

[Confirmado] Nova concessão permite selecionar integrante ativo da própria clínica, em lista paginada, e uma destas categorias técnicas:

| Categoria | Valor persistido | Permissão específica exigida na verificação |
|---|---|---|
| [Confirmado] Situação operacional ocupacional | occupational_status | company_portal.status.view |
| [Confirmado] ASO liberado do empregador | released_aso | company_portal.aso.view |

[Confirmado] Informe início e fim opcional no fuso IANA da clínica, indicado na tela. A Action converte para UTC; limites são inclusivos, com precisão de segundos no banco e seleção por minuto no formulário. Fim anterior ao início é recusado. Fim omitido mantém a concessão sem expiração informada, ainda sujeita a revogação e às demais verificações. Permissões e concessões são requisitos independentes: registrar a concessão não atribui papel nem permissão ao destinatário.

[Confirmado] Nova concessão exige empresa ativa; revogação continua permitida após inativação da empresa. Não há edição de proprietário, categoria ou período, exclusão ou reativação de concessão revogada. Para corrigir, revogue a concessão e registre outra. Cada registro é independente; podem coexistir concessões sobrepostas. Revogar uma linha não revoga outras concessões do mesmo usuário/empresa/categoria. Repetir a revogação da mesma linha não muda sua data nem duplica o evento.

## Autorização e integridade

[Confirmado] Administração usa company_access.view/company_access.manage, combinadas com companies.view e users.view; Policies verificam clínica, empresa exata, ator ativo e segundo fator. Somente o administrador provisionado recebe automaticamente as permissões administrativas novas. Catálogo e provisionamento são idempotentes e invalidam o cache de permissões; não concedem automaticamente company_portal.* a administradores, recepção ou usuários de empresa.

[Confirmado] CompanyAccessAuthorization::allows verifica usuário/empresa atuais e ativos, mesma clínica, segundo fator confirmado, permissão específica, categoria exata, ausência de revogação e período vigente. Relê o banco, sem cache de concessões nem bypass administrativo. Portanto, revogação, expiração e inativação são observadas na próxima avaliação. Ausência de contexto lança a exceção padrão; nenhum proprietário enviado pelo cliente substitui o contexto da clínica.

[Confirmado] A [migration](../../database/migrations/2026_11_03_000006_create_company_user_access.php) reproduz company_user_access do [DDL](../schema/schema.sql), com FKs compostas e CHECK de período. Actions validam e bloqueiam os registros envolvidos em transação; gravação da auditoria faz parte da mesma transação. O model impede reescrita histórica e exclusão via save/delete; SQL bruto e operações em massa continuam fronteiras de revisão próprias. Histórico e reversibilidade da migration estão no [CHANGELOG](../schema/CHANGELOG.md).

[Confirmado] Auditoria administrativa registra eventos e IDs de concessão/empresa/ator, sem copiar nomes, e-mails ou conteúdo clínico. A concessão preserva referências ao destinatário, empresa, categoria e período. Erros de validação não criam concessões; falha na auditoria reverte criação/revogação. Não há cache de listagem ou autorização empresarial a invalidar.

## O que esta entrega não conclui

[Confirmado] O serviço de verificação ainda não está conectado a um portal, endpoint de resultado ou download de ASO; essas entregas seguem etapas 6/10. A confirmação da concessão não significa que o destinatário já possa baixar documentos. Futuras Policies deverão combinar o serviço com vínculo empregador/paciente, documento efetivamente liberado, finalidade e categoria permitida. Concessão isolada não autoriza anamnese, diagnóstico, resultados complementares completos ou histórico de outro empregador.

[Confirmado] Não há cadastro de contas, gestão de papéis/permissões empresariais, transmissão eSocial ou revogação em lote nesta interface. Não foram realizados teste de carga concorrente, inspeção visual manual autenticada ou homologação MySQL. [Testes automatizados](../../tests/Feature/CompanyAccessTest.php) cobrem HTTP, vigência, categoria, autorização, referências cruzadas, imutabilidade e reversão transacional em MariaDB local. CI remoto permanece para verificação futura por instrução do usuário.
