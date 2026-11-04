# Cadastro inicial de pacientes — etapa 2

[Confirmado] Primeira entrega da etapa 2: listagem paginada, criação, consulta e edição de identificação/contato em `/patients`, com o layout Vuexy de tabelas básicas e formulário vertical definido no [mapa visual](../layout/vuexy-assets-e-modelos-por-pagina.md). O andamento oficial permanece no [fluxo 20](../fluxos/20-plano-desenvolvimento.md).

## Uso local

[Confirmado] Na homologação isolada, entre como administrador da clínica desejada, usando os [acessos documentados](acessos-locais.md#homologação-local-isolada--duas-clínicas), conclua o desafio TOTP e abra:

- [Confirmado] Clínica A: http://clinica-a.localhost:8082/patients
- [Confirmado] Clínica B: http://clinica-b.localhost:8082/patients

[Confirmado] Administradores recebem `patients.view` e `patients.manage` no provisionamento idempotente. Listar/consultar exige a primeira; criar/editar exige ambas, usuário ativo, mesma clínica e segundo fator confirmado. Os demais papéis não recebem concessão automática nesta entrega; acesso de recepção por unidade e concessões empresariais ainda dependem dos respectivos vínculos e Policies.

[Confirmado] Campos editáveis: nome, nascimento, CPF opcional, e-mail e celular. É possível registrar sem CPF; o estado parcial atual significa identificação sem CPF. Na edição, CPF vazio preserva o valor existente. Não há exclusão/merge automático. Um CPF duplicado na mesma clínica é recusado; coincidência em outra clínica não compartilha cadastro.

## Proteção de identidade

[Confirmado] Implementação: [Cpf](../../app/Domain/Shared/ValueObjects/Cpf.php), [PatientIdentity](../../app/Domain/Patients/Services/PatientIdentity.php), [SavePatient](../../app/Domain/Patients/Actions/SavePatient.php). CPF aceita 11 dígitos ou máscara canônica, valida verificadores e normaliza. Ciphertext usa o encrypter Laravel/APP_KEY; unicidade usa HMAC-SHA256 com chave derivada por tenant, separada da chave de criptografia. A versão atual de identidade é 1.

[Confirmado] `CPF_SEARCH_KEY` deve conter 32 bytes aleatórios em hexadecimal (64 caracteres), somente no `.env` privado correspondente. Os preparadores locais geram a chave sem publicá-la e preservam chaves existentes. Sem chave válida, a gravação de CPF falha antes do commit. Os testes geram chave e identidades sintéticas próprias.

[Confirmado] Não regenerar `APP_KEY` ou `CPF_SEARCH_KEY` de uma instalação com pacientes. A versão 1 não oferece comando de rotação: uma rotação futura deve manter chaves anteriores, recriptografar/recalcular índices com verificação de colisões e restaurabilidade antes de trocar a versão. Essa operação não foi implementada nem executada nesta entrega.

[Confirmado] CPF/hash não são exibidos nas páginas nem serializados pelo model. Dados pessoais do formulário não são copiados para old input em erro. Auditoria registra criação, edição, consulta e listagem com IDs de tenant/ator/recurso, sem CPF, nome ou conteúdo clínico. Não há cache de consultas de pacientes nesta entrega.

## Estrutura e limites

[Confirmado] A migration cria `companies`, `company_units` e `patients`, com os campos/índices/FKs do [schema de referência](../schema/schema.sql). As duas primeiras foram introduzidas como dependências estruturais; sua interface agora é descrita em [empresas e estabelecimentos](cadastro-empresas.md). Mudança e validações em [CHANGELOG](../schema/CHANGELOG.md).

[Confirmado] Busca e comparação de homônimos estão disponíveis conforme [uso e limites](busca-revisao-pacientes.md). Permanecem para continuação da etapa 2: integração de identidade com os módulos clínicos futuros, além das restrições operacionais por unidade/empresa nos módulos futuros. Não há prontuário, exames ou saúde ocupacional implementados por este cadastro. Testes e status desta entrega estão no fluxo 20; CI remoto continua para verificação futura conforme a exceção autorizada.

[Confirmado] Cadastros relacionados disponíveis: [unidades clínicas e profissionais](cadastro-unidades-profissionais.md).

[Confirmado] Histórico de vínculos com vigência disponível em [vínculos empregatícios](vinculos-empregaticios.md).

[Confirmado] Gestão administrativa de concessões disponível em [concessões empresariais](concessoes-empresariais.md); integração dos portais permanece futura.

[Confirmado] Decisão humana positiva e merge com vínculos empregatícios preservados: [uso e limites](merge-pacientes.md).
