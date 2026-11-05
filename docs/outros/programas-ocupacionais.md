# Referências de programas ocupacionais

[Confirmado] Entrega administrativa da etapa 3. Fonte funcional: [fluxo 23, §23.1](../fluxos/23-saude-ocupacional.md#231-fronteiras); autorização e proteção em [24](../fluxos/24-seguranca-retencao-operacao.md). Estrutura occupational_programs no [schema](../schema/schema.sql); migration e recuperação no [CHANGELOG](../schema/CHANGELOG.md). Execução e aceite exclusivamente no [fluxo 20](../fluxos/20-plano-desenvolvimento.md).

## Operação

1. [Confirmado] Em Empresas → empresa → **Programas ocupacionais**, consulte versões previamente registradas. São exigidos companies.view, programs.view e segundo fator confirmado.
2. [Confirmado] Com programs.manage e professionals.view adicionais, use **Registrar versão**, selecione o profissional informado como responsável e preencha versão do documento de origem, início da vigência, fim opcional, referência da fonte e contexto fornecido pela clínica. Empresa e integrante do profissional precisam estar ativos para um novo registro.
3. [Confirmado] A versão é identificada pelo texto informado na fonte, único por clínica/empresa. Registrar outra versão cria uma linha nova, preservando a anterior. Não existem edição, exclusão, transferência de empresa, encerramento automático ou seleção automática de versão vigente. O cadastro inicial é da empresa como um todo; company_unit_id permanece nulo e não é aceito do formulário.

[Inferido] Convenção inicial da interface: início, origem, contexto e profissional são obrigatórios para reduzir registros sem referência, embora o DDL admita alguns desses campos nulos. A versão não é um contador automático e o sistema não presume ausência de sobreposição de vigências. Não representa aprovação clínica, assinatura ou comprovação automática de habilitação do profissional.

## Preservação e isolamento

- [Confirmado] O contexto cifrado preserva o texto informado, nome da empresa e identificação do profissional/conselho no momento do registro; mudança posterior nos cadastros não recompõe essas informações históricas. O envelope identifica clínica, empresa, profissional e versão. Dados de conselho ausentes permanecem ausentes, sem inventar registro profissional.
- [Confirmado] TenantContext e BelongsToTenant existentes, Policy por empresa, binding de programa subordinado à empresa e FKs compostas. A escolha de profissional é independente da empresa, mas exige a mesma clínica e autorização; a Action relê/bloqueia empresa, profissional e integrante antes de gravar.
- [Confirmado] Auditoria e escrita são transacionais; falha de auditoria reverte o novo programa. Consultas e seleção de responsável também são auditadas sem copiar o contexto. Listagem paginada não carrega ciphertext e respostas HTTP usam private/no-store. Não há cache próprio ou novas filas.
- [Confirmado] programs.view/manage integram o catálogo global idempotente; provisionamento concede ao clinic_admin dentro do tenant. Demais papéis não recebem concessão automática. Leitura do contexto exige programs.view e visibilidade da empresa; isso não concede acesso a prontuários de pacientes.
- [Confirmado] Origem/contexto não são enviados para old input em falha de validação, e context_encrypted fica oculto na serialização do Model. Leitura recusa envelope ausente, corrompido, inválido ou com vínculo/versão divergente. Models recusam alterações/exclusões por instância; Query Builder, bulk writes e raw SQL continuam fronteiras de revisão, sem trigger universal de imutabilidade.

## Limites

[Confirmado] Este cadastro armazena **referências informadas pela clínica**. Não gera PCMSO/PGR/LTCAT, não decide obrigação ou dispensa, não cria protocolos/exames/periodicidades, não aprova conteúdo e não emite ASO. A clínica informa a fonte e o contexto aplicável; a aprovação e os protocolos seguem o fluxo 23. Nenhum conteúdo médico ou dado de paciente foi pré-carregado.

[Confirmado] Programas por estabelecimento, seleção de contexto vigente no pedido/avaliação e snapshots clínicos do atendimento continuam pendentes. Versões de regras e aprovação têm entrega própria em [protocolos ocupacionais](protocolos-ocupacionais.md). Campos approved_by/approved_at pertencem a occupational_protocols, não transformam o responsável desta referência em aprovador automático. Leitura de histórico de empresa inativa é permitida; cadastro novo exige empresa ativa.

[Confirmado] Implementação em [OccupationalProgram](../../app/Domain/Occupational/Models/OccupationalProgram.php), [registro](../../app/Domain/Occupational/Actions/RegisterOccupationalProgram.php) e [testes sintéticos](../../tests/Feature/OccupationalProgramTest.php). Nenhum arquivo ou banco do legado foi modificado.
