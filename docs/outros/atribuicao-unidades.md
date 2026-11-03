# Atribuição de unidade aos integrantes

[Confirmado] Entrega administrativa da etapa 2, baseada no [contrato de identidade/ACL](../fluxos/11-identidade-autenticacao-acl.md#112-acl-multi-tenant). Usa users.unit_id já existente, com FK composta de tenant/unidade; não cria tabela ou migration. Unidades clínicas são distintas dos estabelecimentos de empregadores. Status e evidências ficam no [fluxo 20](../fluxos/20-plano-desenvolvimento.md).

## Uso

[Confirmado] Entre com um administrador dos [acessos locais](acessos-locais.md), conclua o segundo fator e abra Equipe → integrante ativo → Alterar unidade. O fluxo pede confirmação recente da senha. Selecione uma unidade ativa da clínica ou Sem unidade atribuída e salve. A tela segue o formulário vertical e cards do [mapa Vuexy](../layout/vuexy-assets-e-modelos-por-pagina.md).

[Confirmado] A consulta do integrante mostra sua unidade atual, inclusive se posteriormente inativada. O formulário oferece apenas unidades ativas; para mudar uma atribuição cuja unidade foi inativada, escolha outra ou remova explicitamente. Inativar unidade não transfere integrantes nem remove seus vínculos. Usuários inativos não podem ser alterados neste fluxo.

## Autorização e efeitos

[Confirmado] A Policy exige users.view, users.manage e units.view, ator ativo, clínica do integrante e segundo fator confirmado. Essas permissões já pertencem ao administrador provisionado; não foram criados novos papéis ou permissões. Tenant e integrante vêm do contexto/binding, sem possibilidade de escolher proprietários pelo corpo da requisição. Unidade de outra clínica, inexistente, inativa ou excluída é recusada pela Action.

[Confirmado] Mudança efetiva encerra as sessões SQL existentes do integrante e renova seu remember_token, exigindo novo login. Alteração da própria unidade encerra também a sessão HTTP corrente do administrador. Salvar a mesma unidade não renova token, não apaga sessões nem gera evento de mudança. A operação não altera senha, papéis, permissões, perfil profissional ou outros integrantes.

[Confirmado] A Action relê e bloqueia o integrante e verifica a unidade dentro da transação. A auditoria registra ator, integrante, clínica e IDs de unidade anterior/nova; nomes, e-mails, tokens e senhas não entram nas propriedades. Falha na auditoria reverte atribuição, token e exclusão de sessões. A abertura do formulário também gera evento. Não há cache de atribuições a invalidar.

[Confirmado] A atribuição é única por integrante, conforme users.unit_id; não foi criada relação paralela de múltiplas unidades. Ausência de unidade não autoriza acesso global. Os módulos de agenda/check-in/chamada ainda não foram implementados: suas Policies deverão exigir a unidade autorizada conforme fluxos 11/24. Este cadastro não conclui a restrição operacional de recepção, nem modifica históricos de atendimento ou vínculos empregatícios.

## Validação e limites

[Confirmado] Os [testes de atribuição](../../tests/Feature/MemberUnitTest.php) verificam criação/troca/remoção, referências cruzadas por binding/Action/FK, contexto ausente, unidades/integrantes indisponíveis, permissões, segundo fator, confirmação de senha, revogação seletiva de sessões, autoalteração e reversão quando a auditoria falha. Somente fixtures sintéticas no MariaDB de testes.

[Confirmado] Não houve teste de carga com requisições concorrentes nem inspeção visual manual autenticada desta tela. Revogação remove sessões persistidas no momento da transação; não interrompe uma requisição que já estava em execução. Autorizações operacionais futuras devem consultar o vínculo vigente em cada operação. CI remoto continua para verificação futura, por instrução do usuário.
