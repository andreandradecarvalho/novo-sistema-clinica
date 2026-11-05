# Cadastro de perguntas versionadas

[Confirmado] Entrega administrativa da etapa 3, conforme o [plano de desenvolvimento](../fluxos/20-plano-desenvolvimento.md). Contrato clínico de perguntas/respostas e snapshots: [fluxo 22, §22.3](../fluxos/22-atendimento-clinico.md#223-questionários-e-resultados-históricos). Estrutura: health_question_groups e health_questions no [schema de referência](../schema/schema.sql); implementação em [Catalog](../../app/Domain/Catalog/), com migration registrada no [CHANGELOG](../schema/CHANGELOG.md).

## Uso

1. [Confirmado] Acesse **Questionários** no menu. Com catalog.view e segundo fator confirmado, consulte os grupos da clínica e todas as versões das perguntas, em listas paginadas.
2. [Confirmado] Com catalog.manage adicionalmente, cadastre um grupo por código, nome e ordem. Abra o grupo e cadastre uma pergunta por código, enunciado e ordem. Nenhuma pergunta médica é pré-carregada.
3. [Confirmado] Abra a versão mais recente da pergunta e use **Cadastrar nova versão**. O código, grupo e ordem são preservados; o enunciado recebe novo registro e versão incrementada pelo servidor. A versão anterior continua consultável. Uma tentativa baseada numa versão antiga é recusada; reabra o grupo para localizar a versão mais recente.

[Confirmado] Nesta entrega, somente texto livre: type=text, options=null e applies_to=all, correspondendo aos defaults do DDL. Campos técnicos, propriedade e versão não são aceitos dos formulários. Configurações diferentes eventualmente inseridas por outra integração não podem ser convertidas silenciosamente por esta Action. Os códigos aceitam letras ASCII, números, hífen e sublinhado; unicidade segue a collation do schema, dentro da clínica (perguntas compartilham o espaço de códigos entre grupos).

[Inferido] Como medida de preservação antecipada do histórico, a implementação cria nova versão a cada alteração, mesmo antes de haver respostas. Grupos também são imutáveis nesta interface. Não existem edição destrutiva, transferência de grupo ou exclusão; a evolução desse cadastro deve preservar as referências históricas.

## Isolamento e limites

- [Confirmado] Contexto obrigatório de clínica, Policies, autorização revalidada nas Actions e bindings aninhados. A FK composta impede vincular pergunta a grupo de outra clínica. As permissões administrativas do catálogo são reutilizadas, sem conceder acesso clínico adicional a outros perfis.
- [Confirmado] Criação/versão e auditoria fazem parte da mesma transação. O grupo é bloqueado para serializar versões criadas pelas Actions; unicidade tenant/código/versão permanece garantida pelo banco. A auditoria registra ator, data, evento e referência ao registro, sem copiar o enunciado. Não há cache específico de questionários.
- [Confirmado] As versões anteriores mantêm os valores originais, inclusive is_active do DDL. Esse campo não representa aprovação, seleção ou ativação clínica nesta entrega. A listagem apresenta **todas** as versões; não existe questionário pronto para aplicação ou seleção automática de uma versão vigente.
- [Confirmado] Ainda não implementados: seleção de perguntas por exame/protocolo, opções e outros tipos, condicionais, aprovação clínica formal, questionários completos e aplicação automática durante atendimento. A [coleta individual cifrada](respostas-clinicas.md) foi entregue separadamente na etapa 4, e consentimentos têm [fluxo próprio](consentimentos-pacientes.md). O cadastro não habilita atendimento clínico. Conteúdo real exige aprovação do responsável técnico conforme [questões de negócio](../fluxos/19-perguntas-em-aberto-negocio.md).
- [Confirmado] Proteções de imutabilidade dos Models cobrem save/update/delete por instância. Query Builder, bulk update, raw SQL e escritores externos continuam fronteiras obrigatórias de revisão; não há trigger de imutabilidade universal no banco. Nenhum SQL recebido do usuário é concatenado.

[Confirmado] Testes automatizados: [HealthQuestionTest.php](../../tests/Feature/HealthQuestionTest.php), com fixtures sintéticas em banco exclusivo MariaDB. Evidências executadas e lacunas ficam somente no fluxo 20; sem certificação de MySQL, concorrência real ou CI remota por existência da suíte.

## Snapshot da definição da pergunta

[Confirmado] Decisão do usuário em 04/11/2026: modelos e snapshots de perguntas são preparados na etapa 3; coleta/cifra/persistência de respostas ficam na etapa 4, com appointments disponível. Conciliação registrada na [P13](PENDENCIAS.md#p13-sequencia-questionarios); sequência normativa no fluxo 20 e contrato clínico no fluxo 22.

[Confirmado] [BuildHealthQuestionSnapshot](../../app/Domain/Catalog/Actions/BuildHealthQuestionSnapshot.php) relê a pergunta pela clínica/grupo, autoriza a consulta e retorna [HealthQuestionSnapshot](../../app/Domain/Catalog/ValueObjects/HealthQuestionSnapshot.php), um objeto readonly separado do Model. Preserva tenant, ID, código, versão, enunciado, tipo e opções; answerColumns fornece os campos da definição previstos em health_answers. A consulta individual já usa esse snapshot para o enunciado exibido.

[Confirmado] A versão é explícita: construir o snapshot de v1 não passa a apontar para v2 após cadastrar uma nova versão. Somente texto livre/all/options=null tem suporte atual; outras configurações são recusadas. A preparação da etapa 3 não criou tabelas health_records/health_answers ou de agenda; elas são materializadas nas entregas próprias da etapa 4.

[Confirmado] O objeto não contém resposta de paciente, não grava prontuário e não é autorização clínica. A Action de coleta da etapa 4 revalida tenant, paciente, versão escolhida e permissão clínica própria, obtém a definição no servidor, cifra a resposta e persiste atomicamente com autoria/data/origem e referências de atendimento. Nunca aceitar o snapshot ou ciphertext enviado pelo cliente como fonte confiável.

[Confirmado] Integração na etapa 4 disponível em [coleta individual de respostas clínicas](respostas-clinicas.md): versão explícita, snapshot no servidor, resposta/evidência cifradas, autoria/data/origem e reenvio. Não equivale a seleção automática ou aprovação de conteúdo real.
