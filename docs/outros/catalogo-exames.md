# Catálogo de exames e preparos — etapa 3

[Confirmado] Cadastro administrativo por clínica na rota `/exam-types`, com entrada no menu **Catálogo de exames**. Contratos de domínio: [produto](../fluxos/08-visao-produto-bounded-contexts.md), [atendimento](../fluxos/22-atendimento-clinico.md) e [parametrização pelo responsável técnico](../fluxos/19-perguntas-em-aberto-negocio.md#191-encaminhamento-após-conciliação--03112026). Status e evidências de execução somente no [fluxo 20](../fluxos/20-plano-desenvolvimento.md).

## Operação e campos

- [Confirmado] Permite listar, consultar, cadastrar, editar e inativar um tipo de exame. Não oferece exclusão física. Listagem paginada em 20 itens, ordenada por ordem de exibição, nome e ID; mostra ativos e inativos para gestão do catálogo.
- [Confirmado] Código obrigatório de até 20 caracteres, normalizado para maiúsculas e sem espaços nas extremidades. Unicidade garantida por `(tenant_id, code)` no banco; duas clínicas podem usar o mesmo código. O ULID público permanece estável ao editar o código ou nome.
- [Confirmado] Nome obrigatório até 120 caracteres. Duração prevista opcional, inteira entre 1 e 65.535 minutos. Preço de referência opcional, não negativo, com até duas casas decimais e armazenado como DECIMAL; zero e ausência de preço são valores distintos. Ordem de exibição entre 0 e 65.535; situação ativa/inativa.
- [Confirmado] O formulário exige selecionar explicitamente se há jejum no cadastro novo. O sistema não sugere exames, períodos de jejum ou protocolos clínicos. Conteúdo deve ser configurado com aprovação do responsável técnico; a indicação cadastral não substitui orientação de preparo individual.
- [Confirmado] O campo técnico `scope` não é editável pela interface e preserva o default `exame` do DDL nos novos registros; não foi inventada uma enumeração de categorias. Mantido `ordem` por compatibilidade com o schema documentado.

## Preparos textuais

[Confirmado] No detalhe do exame, **Preparos do exame** abre a listagem de orientações daquele exame. Permite cadastrar, consultar e editar título e instruções, com paginação de 20 títulos por página e texto completo somente no detalhe/formulário. A interface não oferece exclusão física nem transferência de preparo para outro exame. Exame inativo continua disponível para administração de seu catálogo/preparos; isso não disponibiliza orientação a pacientes nem reativa o exame.

[Confirmado] Título obrigatório até 150 caracteres e instruções obrigatórias até 16.000 caracteres. O limite de instruções mantém até 64.000 bytes em utf8mb4, dentro da coluna TEXT. Texto é escapado em Blade e preserva quebras de linha; HTML e scripts cadastrados são exibidos literalmente. O formulário textual não gera conteúdo clínico nem recebe arquivos; os anexos são tratados na seção seguinte. Documentos do legado não são copiados.

[Confirmado] Usa as permissões catalog.view/manage já existentes e segundo fator, com Policy específica de recurso que também autoriza o exame pai. Rotas aninhadas com binding escopado recusam preparo de outra clínica ou de outro exame da mesma clínica. Action relê e bloqueia pai/preparo, revalida autorização e grava na mesma transação da auditoria. FK composta protege tenant/exame; Model impede mudança de exam_type_id em save. IDs e tenant vêm do servidor, e campos de propriedade enviados no formulário são recusados. Não há cache específico para invalidar.

[Confirmado] Eventos de criação/edição/consulta/listagem registram apenas referências/eventos na auditoria operacional, sem copiar título ou instruções. O schema atual não possui versões, timestamps ou aprovação clínica formal de preparos: editar atualiza o texto cadastral vigente. Aprovação de conteúdo continua responsabilidade de implantação descrita no fluxo 19; salvar o cadastro não certifica essa aprovação. Integrações futuras com pedidos/orientações enviadas precisam preservar o conteúdo histórico utilizado, sem reler o texto atual para reconstruí-lo.

[Confirmado] A [migration 000009](../../database/migrations/2026_11_03_000009_create_exam_type_preparations.php) reproduz exatamente exam_type_preparations do DDL; motivo, validações e recuperação no [CHANGELOG](../schema/CHANGELOG.md). [Testes de preparos](../../tests/Feature/ExamPreparationTest.php) exercitam dois tenants, exame errado na mesma clínica, FK, propriedade imutável, ACL/2FA, limites Unicode, escaping/paginação e rollback da criação/edição quando a auditoria falha.

## Anexos de orientação

[Confirmado] O botão Anexos de orientação no detalhe do exame permite listar arquivos e enviar uma nova orientação geral da clínica. Descrição obrigatória até 150 caracteres; PDF, PNG ou JPEG até 2.048 KiB. Validação no servidor confere MIME a partir do conteúdo, extensão e tamanho. Não importar laudos ou arquivos do legado; o cadastro destina-se a orientação geral, sem dados de pacientes. A interface usa formulário multipart simples do padrão Vuexy, sem bibliotecas de upload adicionais.

[Confirmado] Metadados ficam em exam_type_attachments; bytes são cifrados com Crypt e gravados por TenantStorage no disco privado local, sob prefixo de tenant e caminho gerado por exame/ULID. O nome original não é armazenado nem utilizado no download. O campo disk é explicitamente local, correspondente ao adaptador privado já existente; o default tenant do DDL não cria um disco Laravel. path/disk são ocultos da serialização e não podem ser fornecidos no formulário.

[Confirmado] Listagem/upload/download usam Policy própria, delegando às permissões catalog.view/manage e ao exame pai com segundo fator. Binding aninhado e FK composta recusam exame/clínica incompatíveis. Download relê o registro, revalida autorização, confere disco/caminho e decifra os bytes; devolve attachment, filename gerado, nosniff e Cache-Control private/no-store. Caminho inválido, disco inesperado, arquivo ausente ou ciphertext corrompido retornam 404. Não há URL pública, renderização inline, link externo compartilhável ou portal nesta entrega.

[Confirmado] Os registros de anexos não podem ser alterados/excluídos por save/delete do Model; a interface não substitui bytes nem oferece exclusão. Novos envios criam registros independentes, sem workflow formal de versões/aprovação/revogação. Operações SQL em massa continuam fronteira de revisão. Auditoria guarda apenas ator/tenant/IDs/eventos, sem arquivo, descrição, caminho ou nome original.

[Confirmado] Upload autoriza e bloqueia o pai antes de gravar; falha de escrita não confirma a linha. Falha de banco/auditoria reverte a linha e tenta remover somente o caminho novo. Falha na remoção é registrada genericamente, sem conteúdo. Banco e filesystem não têm commit atômico conjunto: interrupção abrupta ou rollback de uma transação externa após o retorno da Action podem deixar arquivo órfão; não foi implementada reconciliação automática nem ensaiado corte de processo. Recuperação requer banco, storage e APP_KEY/chaves anteriores; não apagar arquivos por varredura genérica.

[Confirmado] A [migration 000010](../../database/migrations/2026_11_03_000010_create_exam_type_attachments.php) reproduz exatamente o DDL existente. [Testes de anexos](../../tests/Feature/ExamAttachmentTest.php) usam arquivos sintéticos temporários e storage isolado, verificando cifragem, bytes do download, autorização, referências cruzadas, entradas inválidas, compensação e arquivo indisponível/corrompido. Validação de tipo/tamanho não certifica conteúdo médico, assinatura, antivírus, leitor PDF ou implantação com TLS/backups; os requisitos operacionais completos permanecem no fluxo 24.

## Autorização e isolamento

[Confirmado] Leitura exige `catalog.view`; criação/edição exige também `catalog.manage`, usuário ativo da mesma clínica e segundo fator confirmado. Backend usa Policy e Form Request; Actions de escrita revalidam a autorização e releem o registro com bloqueio dentro da transação. Binding usa o ULID e o contexto existente; IDs de outra clínica retornam 404. `tenant_id`, `id`, `public_id` e `scope` enviados no formulário são recusados.

[Confirmado] Model reutiliza BelongsToTenant e impede mudança do identificador público em save. SQL bruto e operações em massa continuam sujeitos a revisão própria; essas proteções não equivalem a RLS. Eventos de criação/alteração e leitura registram tenant, ator, IDs e nomes fixos de evento, sem copiar campos cadastrais. Falha da auditoria reverte a escrita. Não há cache próprio de catálogo para invalidar.

[Confirmado] O seeder global adiciona as duas permissões de forma idempotente. Provisionamento configura essas permissões para `clinic_admin` no tenant correto, sem conceder gestão a recepção, médico, técnico ou usuário de empresa. Nenhuma conta/senha fixa foi criada. Em instalação local existente, reaplicar `tenant:create` com os mesmos parâmetros já documentados no [README](../../README.md#ambiente-local) atualiza os papéis previstos pelo provisionamento.

## Estrutura, layout e limites

[Confirmado] A [migration 000008](../../database/migrations/2026_11_03_000008_create_exam_types.php) reproduz exatamente `exam_types` do [schema.sql](../schema/schema.sql), preservando índices, FK e chave composta para referências futuras. O DDL de referência não mudou. Validação de banco e recuperação registradas no [CHANGELOG](../schema/CHANGELOG.md).

[Confirmado] Listagem responsiva, formulário vertical e detalhe usam cards, tabela e componentes Blade do [padrão Vuexy](../layout/vuexy-assets-e-modelos-por-pagina.md), com os assets core existentes via Vite. Sem bibliotecas novas, analytics ou recursos de demonstração.

[Confirmado] Esta entrega não inclui questionários versionados, consentimentos por finalidade, protocolos ocupacionais, pedidos/agenda, disponibilidade por unidade, contratos comerciais ou cobrança. Preço e duração são referências atuais do catálogo; pedidos e cobranças futuros deverão preservar snapshots conforme fluxo 22. Inativar ou editar um exame não representa cancelamento de atendimento nem recálculo de histórico.

[Confirmado] [Testes do catálogo](../../tests/Feature/ExamCatalogTest.php) cobrem isolamento entre clínicas, constraints de código, autorização/2FA, valores decimais e limites, validação de propriedade, identidade pública estável, paginação/escaping e rollback diante de falha da auditoria. Homologação visual manual e CI remota não foram certificadas nesta entrega. O roteiro HTTP/Horizon de 81 checks continua cobrindo fundação/etapa 2; não passa a certificar funcionalmente o catálogo apenas por sua execução.
