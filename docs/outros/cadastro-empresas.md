# Empresas clientes e estabelecimentos — etapa 2

[Confirmado] Entrega de listagem paginada, criação, consulta, edição e inativação de empresas clientes e de seus estabelecimentos. O layout segue tabelas básicas e formulário vertical do [mapa Vuexy](../layout/vuexy-assets-e-modelos-por-pagina.md). Status e evidências de execução ficam no [fluxo 20](../fluxos/20-plano-desenvolvimento.md).

## Acesso e uso

[Confirmado] Entre com um dos administradores dos [acessos de homologação](acessos-locais.md#homologação-local-isolada--duas-clínicas), conclua o segundo fator e abra:

- [Confirmado] Clínica A: http://clinica-a.localhost:8082/companies
- [Confirmado] Clínica B: http://clinica-b.localhost:8082/companies

[Confirmado] Abra Nova empresa para informar nome, CNPJ opcional e situação. Na consulta da empresa, Novo estabelecimento informa nome, cidade/UF opcionais e situação. A edição permite inativar sem apagar o cadastro. A inativação é uma marca cadastral; não destrói registros ou vínculos e não constitui, por si só, um mecanismo de revogação de acesso.

[Confirmado] `companies` representa empregadores/clientes da clínica. `company_units` representa estabelecimentos desses empregadores; não equivale a `units`, que representa unidades de atendimento da própria clínica. Essa separação segue [23 §23.1](../fluxos/23-saude-ocupacional.md#231-fronteiras). As tabelas utilizadas já existem na migration inicial de pacientes; não há novo DDL nesta entrega.

## Validação e autorização

[Confirmado] O [VO Cnpj](../../app/Domain/Shared/ValueObjects/Cnpj.php) aceita o formato numérico e o alfanumérico, sem pontuação ou com máscara canônica, normalizando letras para maiúsculas. Verifica os dois dígitos segundo o [manual técnico da Receita Federal](https://www.gov.br/receitafederal/pt-br/centrais-de-conteudo/publicacoes/documentos-tecnicos/cnpj/manual-dv-cnpj.pdf): caracteres convertidos por ASCII menos 48 e módulo 11. Isso valida a estrutura; não consulta inscrição, situação fiscal ou emissão real na Receita.

[Confirmado] O índice UNIQUE de CNPJ é por clínica; o mesmo CNPJ pode ter cadastros independentes em duas clínicas. O identificador interno da empresa permanece estável quando o nome muda. Em uma atualização, CNPJ omitido preserva o valor; vazio explicitamente enviado remove a identificação opcional. UF aceita as siglas brasileiras do enum compartilhado, incluindo DF.

[Confirmado] `companies.view` permite listar/consultar. `companies.manage`, junto da permissão de leitura, permite criar/editar empresas e estabelecimentos. Policies também exigem usuário ativo, mesma clínica e segundo fator confirmado. Apenas o papel administrativo recebe essas permissões automaticamente no provisionamento idempotente; não há concessão automática a empresa cliente, recepção ou profissional.

[Confirmado] O estabelecimento é escolhido sob a empresa da rota; vínculo com outra empresa, mesmo da mesma clínica, é rejeitado. A entrada HTTP não aceita `tenant_id`, `company_id`, hierarquia, slug ou caminhos de storage para sobrescrever propriedade. O model e a Action de estabelecimentos impedem transferir um estabelecimento existente a outro empregador. Atualizações em SQL bruto continuam sujeitas à revisão explícita descrita no fluxo 09.

[Confirmado] Auditoria de cadastros foi centralizada em RegistryAudit e mantém somente eventos e IDs, sem nome, CNPJ ou conteúdo clínico. A verificação comum de permissão/contexto/segundo fator foi centralizada em TenantPermission, preservando o comportamento anterior de pacientes. Não há cache de consultas empresariais a invalidar nesta entrega; o provisionamento invalida o cache de permissões.

## Limites da entrega

[Confirmado] Cadastro empresarial não concede acesso a prontuário, paciente, resultado ou ASO. Permanecem para continuação da etapa 2: restrições operacionais por unidade/empresa nos módulos futuros, além da integração de identidade com os módulos clínicos futuros. Não há exclusão definitiva, gestão de matriz/filial por parent_id, CNPJ próprio em company_units ou integração fiscal nesta interface. Testes e verificações não executadas estão no fluxo 20.

[Confirmado] Cadastros relacionados disponíveis: [unidades clínicas e profissionais](cadastro-unidades-profissionais.md).

[Confirmado] Histórico de vínculos com vigência disponível em [vínculos empregatícios](vinculos-empregaticios.md).

[Confirmado] Gestão administrativa de concessões disponível em [concessões empresariais](concessoes-empresariais.md); integração dos portais permanece futura.
