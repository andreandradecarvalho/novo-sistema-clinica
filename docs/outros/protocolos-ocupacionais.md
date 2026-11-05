# Protocolos ocupacionais: versões e aprovação

[Confirmado] Entrega técnica da etapa 3. Fonte única do contrato de regras/autorizações: [fluxo 23, §23.1.1](../fluxos/23-saude-ocupacional.md#2311-contrato-técnico-inicial-dos-protocolos). DDL: occupational_protocols no [schema](../schema/schema.sql). Evidências, limites de teste e aceite no [fluxo 20](../fluxos/20-plano-desenvolvimento.md); migration/recuperação no [CHANGELOG](../schema/CHANGELOG.md).

## Uso

1. [Confirmado] Abra Empresas → empresa → Programas ocupacionais → versão do programa → **Protocolos deste programa**. A listagem mostra todas as versões e a aprovação registrada de cada uma.
2. [Confirmado] Use **Novo protocolo**, informando código, vigência, fonte e contexto de riscos recebidos da clínica, função opcional e códigos dos exames do catálogo, um por linha. O sistema preserva identificação/nome dos exames escolhidos. Nenhum conteúdo médico ou prazo é sugerido automaticamente.
3. [Confirmado] Consulte a versão pendente. **Cadastrar nova versão** parte dos dados da origem e preserva a anterior. Alterações sempre geram nova versão, inclusive antes da primeira aprovação. Código/programa não são transferidos e a aprovação anterior nunca é herdada.
4. [Confirmado] Para aprovar, o profissional autorizado consulta o conteúdo, marca a declaração de revisão e usa **Confirmar aprovação**. O endpoint pede senha recente e identifica o profissional pelo próprio usuário da sessão; não há escolha de outro aprovador no formulário.

[Confirmado] Somente a versão mais recente e pendente pode receber nova aprovação. Se houver mudança do nome/código de exame ou inativação desde o cadastro, a aprovação é recusada e exige nova versão revisada. A aprovação de uma versão antiga já registrada continua no histórico; não é sobrescrita nem transportada para a revisão nova.

## Autorizações e evidência

- [Confirmado] Catálogo global idempotente inclui protocols.view/manage/approve. Provisionamento concede somente view/manage ao clinic_admin; **approve não é atribuída automaticamente a administrador, médico ou outro papel**. A clínica precisa conceder explicitamente essa permissão, além das condições do fluxo 23. O cadastro de responsável de um programa não concede aprovação.
- [Confirmado] Esta entrega não cria interface administrativa de concessão de permissões. As fixtures sintéticas dos testes concedem approve explicitamente dentro do tenant e não alteram usuários de desenvolvimento/homologação ou produção. Sem concessão explícita, o botão permanece oculto e a Action recusa a operação.
- [Confirmado] aprovar não exige permissão de editar protocolos; exige as permissões de consulta/ato clínico documentadas e cadastro profissional próprio com CRM/UF/número local. Há revalidação do usuário ativo e do contexto na Action. Não há consulta automática a conselho ou comprovação externa de habilitação.
- [Confirmado] approved_by e approved_at preservam profissional e instante UTC da aprovação. A tela consulta o nome atual do integrante associado àquele profissional; não representa um documento assinado com identificação materializada no instante histórico. A auditoria preserva o ator e o evento sem copiar fonte/contexto/exames para logs.
- [Confirmado] Conteúdo/identidade são imutáveis pelos Models. A transição de aprovação usa um único UPDATE revisado, condicionado por tenant/id e ambos os campos de aprovação nulos, após locks e autorização; altera apenas approved_by/approved_at/updated_at. A auditoria participa da transação, revertendo a aprovação em falha. Outros raw/bulk writes continuam fronteiras de revisão, sem trigger universal de imutabilidade.
- [Confirmado] Bindings aninhados e FKs impedem atravessar programa/clínica/profissional. Listagem é paginada e não carrega rules; consultas usam private/no-store. Textos sensíveis de formulário ficam fora de old input. Não existem cache próprio, jobs ou endpoints de exclusão.

## Limites

[Confirmado] Aprovação nesta interface é o registro de revisão de uma configuração. Não assina PCMSO/laudo/ASO, não determina aptidão, não emite documentos e não ativa agenda nem seleciona automaticamente protocolo vigente. O uso clínico futuro deve revalidar versão, programa, empresa, vínculo, data e permissões antes de aplicar e preservar snapshots na avaliação.

[Confirmado] O contrato atual admite lista explícita de exames e contexto textual, sem motor de condicionais, frequência/periodicidade automática ou catálogo estruturado de agentes. Regras de outro formato/versionamento são recusadas; importações futuras precisam de mapeamento explícito. Não incluir dados de pacientes na configuração.

[Confirmado] Pendentes: validação de conteúdo real pelo responsável da clínica, gerenciamento de concessões clínicas na interface, revogação/substituição operacional de aprovações, seleção no atendimento e avaliação/ASO. Não existe botão para desfazer uma aprovação; novo conteúdo usa nova versão. Antes de disponibilizar seleção operacional, definir tratamento de retirada de aprovação e impedir uso de versão retirada.

[Confirmado] Implementação em [OccupationalProtocol](../../app/Domain/Occupational/Models/OccupationalProtocol.php), [Actions](../../app/Domain/Occupational/Actions/) e [testes sintéticos](../../tests/Feature/OccupationalProtocolTest.php). Nenhuma aprovação de conteúdo real ou escrita no legado foi executada pelo agente.
