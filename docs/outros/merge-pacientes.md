# Merge cadastral de pacientes — revisão humana

[Confirmado] Entrega da etapa 2 baseada na [busca e comparação](busca-revisao-pacientes.md), no risco de associação indevida do [fluxo 18](../fluxos/18-riscos-migracao.md) e no requisito de merge humano auditado do [fluxo 20](../fluxos/20-plano-desenvolvimento.md). Suporta origem sem dependências ou com vínculos empregatícios preservados. Não consolida prontuários/documentos nem registros legados importados.

## Como usar

[Confirmado] Entre como administrador dos [acessos locais](acessos-locais.md), conclua o segundo fator e abra Pacientes → paciente → Revisar homônimos → Comparar. Escolha o cadastro sobrevivente, registre motivo/evidência da conferência e confirme a identidade por fonte autorizada. A escrita exige senha recente. Nome/nascimento semelhantes continuam insuficientes para uma decisão automática.

[Confirmado] A comparação mostra a quantidade de versões de vínculos de cada cadastro/grupo e acesso para conferi-los, quando o operador possui autorização de leitura. O merge mantém os campos do sobrevivente, sem copiar campos da origem. A origem é arquivada logicamente e deixa de aparecer nas buscas/rotas normais. O histórico do sobrevivente preserva origem, contato, motivo, data e operador. Layout segue formulário vertical/cards do [mapa Vuexy](../layout/vuexy-assets-e-modelos-por-pagina.md).

[Confirmado] A decisão positiva fica em patient_merges. Não há decisão negativa/pendência formal, associação automática por nome ou workflow de candidatos do ETL nesta interface.

## Vínculos empregatícios preservados

[Confirmado] A tela Vínculos empregatícios do sobrevivente reúne suas versões e as das origens arquivadas diretamente relacionadas por patient_merges. PatientIdentityGroup resolve essa composição com contexto de tenant, sem cache. As FKs patient_id/company_id/company_unit_id e os IDs das versões não são movidos, renumerados ou copiados pelo merge. A interface sinaliza o cadastro de origem dos vínculos consolidados.

[Confirmado] Binding, Policy e Actions conferem pertencimento ao grupo exato da mesma clínica. Pelas rotas do sobrevivente, o operador autorizado pode versionar ou encerrar vínculos da origem arquivada. Nova versão mantém patient_id e empregador do vínculo anterior; apenas o fechamento/versionamento normal ocorre. Novo vínculo independente criado após o merge pertence ao sobrevivente. Rotas da origem arquivada continuam retornando 404; ela não pode receber novo vínculo direto nem ser restaurada genericamente.

[Confirmado] Um sobrevivente pode receber várias origens independentes. Não se consolidam grupos existentes como origem: a dependência de merges anteriores continua recusada. Não há reparenting de decisões, cadeias recursivas ou desfazer automático. A relação Patient::employments continua representando propriedade física; consultas consolidadas usam explicitamente PatientIdentityGroup. Módulos futuros deverão integrar a composição e suas Policies de forma explícita.

[Confirmado] Concessões empresariais permanecem independentes e exigem empresa/categoria/vigência exatas. Consolidar identidade não concede acesso a outro empregador, não amplia permissões e não abre anamnese/resultados. Futuras Policies de documento/portal deverão preservar esse limite; não há portal clínico entregue nesta etapa.

## Recusas e autorização

- [Confirmado] Origem com CPF/hash/ciphertext é recusada: mantenha o cadastro identificado como sobrevivente. Dois cadastros identificados não são fundidos por esta versão.
- [Confirmado] Nascimentos conhecidos divergentes exigem conferência/correção autorizada antes de nova comparação. Não alterar dados apenas para contornar a recusa.
- [Confirmado] Conta, associação cadastral de empresa/estabelecimento, origem legada, indicação de duplicidade, termos/consentimento cadastral e histórico de portal na origem continuam bloqueados.
- [Confirmado] Dependências por FK são recusadas, com exceção explícita de patient_company_links.patient_id. Referências polimórficas/sem FK não são descobertas automaticamente; revisar a guarda antes de introduzir novos módulos/ETL.
- [Confirmado] Cadastros iguais, de outra clínica, já arquivados, confirmação alterada/expirada ou dados/grupo/vínculos modificados após a comparação impedem a operação.

[Confirmado] Merge exige patients.view/manage/review/merge, ator ativo, segundo fator e autorização de ambos os pacientes. Se qualquer cadastro/grupo tiver vínculos, exige também employments.view. Gerenciar vínculos após o merge continua exigindo suas permissões próprias. A Action revalida a Policy; tenant, operador e snapshots vêm do servidor.

## Evidências e transação

[Confirmado] A confirmação cifrada de versão 2 inclui fingerprints dos atributos dos pacientes, membros dos grupos e versões de vínculos, com tenant/operador e prazo de 15 minutos. Alterar, adicionar ou encerrar vínculo invalida a confirmação. Tokens da versão anterior exigem reabrir a comparação. A Action relê e bloqueia pacientes por ordem de ID, composição e versões para revalidar a decisão.

[Confirmado] Decisão, snapshots, arquivamento e auditoria pertencem à mesma transação. Falha da auditoria reverte tudo. Motivo usa cast encrypted em TEXT. payload_snapshot permanece JSON válido: versão 2/estratégia preserve_employment_ownership contém snapshots cifrados com paciente, membros e vínculos no momento da decisão. Evidências de versão 1 permanecem intactas; não há backfill destrutivo.

[Confirmado] Motivo/snapshots são ocultos da serialização do model; motivo só aparece na tela autorizada. Auditoria operacional guarda IDs/eventos, sem motivo, CPF ou contatos. Consulta consolidada registra também IDs das identidades componentes. reason/review_token não entram no flash de validação. Não há cache de identidade, merge ou vínculos a invalidar.

[Confirmado] Model events impedem edição/exclusão da decisão e alteração/restauração da origem arquivada por save/restore; SQL bruto e operações em massa exigem revisão própria. Edição cadastral e operações de vínculo usam leitura com bloqueio para recusar objetos obsoletos. Essas proteções não equivalem a RLS ou garantia universal para futuros importadores.

[Confirmado] Preservar APP_KEY, chaves anteriores e backups é necessário para ler as evidências. Snapshots não constituem reversão operacional homologada. Não há endpoint de desfazer; correções exigem procedimento específico, autorizado e testado.

## Estrutura e validação

[Confirmado] A [migration 000007](../../database/migrations/2026_11_03_000007_create_patient_merges.php) reproduz patient_merges do [DDL](../schema/schema.sql). A integração com vínculos não altera DDL/migrations; evolução do envelope está no [CHANGELOG](../schema/CHANGELOG.md).

[Confirmado] [Testes de merge](../../tests/Feature/PatientMergeTest.php) e [integração com vínculos](../../tests/Feature/PatientEmploymentMergeTest.php) cobrem decisão/autoria, cifragem, conflitos, stale/replay, permissões, dois tenants, dependências recusadas, FKs preservadas, versões/desligamento pelo sobrevivente, novas origens e concessões por empresa. Não foram realizados ensaio de escritores paralelos, inspeção visual manual autenticada ou restauração operacional. Aceite da etapa 2 e limites dos módulos futuros permanecem no fluxo 20; CI remoto continua para verificação futura conforme orientação do usuário.
