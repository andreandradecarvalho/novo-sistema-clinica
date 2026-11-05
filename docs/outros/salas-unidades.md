# Salas das unidades clínicas

[Confirmado] Cadastro administrativo da etapa 4, fonte executável em `Room`/`SaveRoom`/`RoomPolicy` e migration `2026_11_05_000021_create_rooms`. Materializa a estrutura já prevista em [schema.sql](../schema/schema.sql); atende a dependência de sala de `exam_executions`, sem remover FKs ou antecipar início/conclusão de exames. Execução/aceite e continuidade somente no [fluxo 20](../fluxos/20-plano-desenvolvimento.md#2041-ponto-de-retomada--04112026).

## Operação e acesso

- [Confirmado] Acessar **Unidades clínicas → Consultar → Salas da unidade**. A lista é paginada de 20 em 20, ordenada por ordem de exibição/nome/ID. Permite cadastrar, consultar, editar e ativar/inativar salas. Interface segue [modelos Vuexy](../layout/vuexy-assets-e-modelos-por-pagina.md), com formulários e tabelas básicos, sem novas bibliotecas.
- [Confirmado] Consulta exige `rooms.view` e consulta da unidade (`units.view`); criação/edição exigem também `rooms.manage`. Integrante ativo, 2FA confirmado e tenant atual são obrigatórios. São permissões administrativas do catálogo da clínica: abrangem suas unidades mesmo sem unidade atribuída ao integrante, como o cadastro de unidades. Isso não concede agenda, check-in, acesso clínico, operação de sala ou execução de exames; suas Policies continuam independentes.
- [Confirmado] Catálogo global/provisionamento incluem as permissões de salas para novos provisionamentos de clinic_admin. Seed de catálogo não amplia papéis existentes. Conceder explicitamente os direitos aos responsáveis pelo cadastro. Nenhum PIN é criado, mostrado ou alterado neste fluxo.
- [Confirmado] Endpoints `units/{unit:public_id}/rooms`: GET lista, GET `/create`, POST cadastra; GET `/{room}`, GET `/{room}/edit` e PUT `/{room}`. Binding composto e Actions exigem a unidade exata, além do tenant; conhecer o ID numérico de uma sala não autoriza consultá-la por outra unidade. Escritas têm CSRF e consultas são privadas/sem cache. Não há exclusão.

## Dados, validação e integridade

- [Confirmado] Campos: `room_key` (até 20 caracteres), nome (até 100), ordem de exibição (inteiro 0–65.535) e situação ativa/inativa. Nome/chave recebem trim; chave convertida em maiúsculas e limitada a letras ASCII, números, hífen e sublinhado, começando por letra/número. São regras do cadastro novo; não representam transformação automática de dados legados.
- [Confirmado] Chave é única por clínica, inclusive em unidades diferentes e salas inativas. Outra clínica pode usar a mesma chave. Chave/nome podem ser corrigidos; referências futuras usam ID estável, e histórico de operação deverá preservar seus próprios snapshots. Inativar conserva o registro e sua chave. Não reaproveitar inativação como prova de ausência de execução ou liberação de documentos.
- [Confirmado] Unidade é atribuída pela rota e não pode ser transferida pelo cadastro. Unidade ativa/não arquivada é obrigatória para criar ou ativar sala. Em unidade inativa, salas existentes podem ser consultadas e editadas se permanecerem/ficarem inativas. Unidade arquivada não é resolvida pelas rotas. Registro nullable de unidade no schema é reservado à compatibilidade histórica; o fluxo novo sempre exige unidade.
- [Confirmado] IDs, tenant, unidade, PIN/hash e datas enviados no corpo são proibidos. `pin_hash` fica oculto na serialização, e PIN/hash não são reapresentados como old input em erro. Models recusam mudança de proprietário/unidade/hash e exclusão; SQL bruto continua fronteira de revisão.
- [Confirmado] Action normaliza/valida inclusive em chamadas diretas, relê e bloqueia unidade/ator/sala, reautoriza com ator fresco e grava auditoria na mesma transação. Unique do banco também protege disputa entre unidades; violação é traduzida em erro do campo sem detalhes SQL. Auditoria registra referências/eventos (`room_created`, `room_updated`, consultas), sem conteúdo/PIN. Falha na auditoria reverte criação/edição.

## Limites e recuperação

- [Confirmado] Este cadastro não implementa capacidade, disponibilidade, horários, equipamentos, agenda de sala, chamada/TV ou autenticação por PIN. Esses fluxos dependem de configuração operacional conforme [19](../fluxos/19-perguntas-em-aberto-negocio.md) e [22](../fluxos/22-atendimento-clinico.md). Ativar uma sala não habilita automaticamente um profissional.
- [Confirmado] Migration reproduz rooms do schema de referência, incluindo FKs compostas, unique por chave/tenant e chave composta referenciável por futuras execuções. Não modifica schema.sql nem cria tabelas de execução vazias para simular entrega. Evidências de up/down e recuperação no [CHANGELOG](../schema/CHANGELOG.md).
[Confirmado] A dependência de salas é usada pela tabela de [tentativas pendentes](tentativas-exames.md), agora materializada. Estado e sequência da implementação ficam somente no fluxo 20; habilitação profissional e conteúdo real continuam sujeitos aos contratos clínicos/P14.

[Confirmado] Salas ativas podem ser selecionadas na [atribuição administrativa a tentativas pendentes](atribuicao-salas-tentativas.md), que preserva nome/chave históricos e não cria reserva de horário ou ocupação de sala.
