# Homologação local isolada

[Confirmado] Ambiente escolhido pelo usuário para validar a etapa 1 com duas clínicas sintéticas. Usa [compose.homologacao.yaml](../../compose.homologacao.yaml) e [scripts/homologacao.py](../../scripts/homologacao.py). Estratégia de dados e versões seguem os fluxos [09](../fluxos/09-estrategia-multi-tenant.md) e [14](../fluxos/14-arquitetura-laravel.md). Status/aceite ficam exclusivamente no [fluxo 20](../fluxos/20-plano-desenvolvimento.md).

## Preparação e operação

[Confirmado] Requer dependências PHP instaladas e assets compilados conforme o [README](../../README.md#ambiente-local). O código e os assets são compartilhados em modo somente leitura. Banco, rede, Redis, storage, bootstrap/cache, APP_KEY e cookie de sessão pertencem ao projeto Compose precordis-clinic-homologacao, separado do desenvolvimento. O servidor publica somente a porta de loopback 8082; banco/Redis não têm portas publicadas.

```bash
# Na raiz. Gera somente valores ausentes na primeira criação do arquivo privado.
python3 scripts/homologacao.py prepare
python3 scripts/homologacao.py up
python3 scripts/homologacao.py check
# Inclui os cadastros e a identidade da etapa 2, com fixtures temporárias.
python3 scripts/homologacao.py check --registries
# Inclui também catálogo, questionários, manifestações e protocolos da etapa 3.
python3 scripts/homologacao.py check --stage3
python3 scripts/homologacao.py status

# Encerra somente esta homologação, preservando seus volumes.
python3 scripts/homologacao.py down
```

[Confirmado] O preparador preserva um .env.homologacao já existente. O comando up executa homologation:migrate, que valida ambiente, configuração e conexão resolvida antes de chamar as migrations; migra somente o banco novo_sistema_clinica_homologacao e aplica fixtures protegidas por ambiente/host/nome de banco; não limpa tabelas nem sobrescreve usuários existentes. A suíte testa a recusa do seeder e do comando de verificação fora desse contexto, incluindo DB_URL, conexão padrão e conexão já resolvida incompatíveis. Nenhuma alteração no legado ou nas contas locais da porta 8080.

[Confirmado] O comando check é executado no container e chama o servidor HTTP real. O relatório público, sem credenciais, fica em docs/outros/homologacao-local-AAAA-MM-DD.json; o snapshot validado desta entrega é [homologacao-local-2026-11-03.json](homologacao-local-2026-11-03.json). O resultado inclui data, versões reais, FKs habilitadas, verificações e revisão de origem; working_tree_dirty registra alterações locais ainda não commitadas.

## Cadastros e identidade — etapa 2

[Confirmado] `check --registries` mantém as verificações da fundação e acrescenta operações reais de cadastro nas duas clínicas. O relatório fica em `docs/outros/homologacao-etapa-02-AAAA-MM-DD.json`, com data UTC. A [execução de 04/11/2026 UTC](homologacao-etapa-02-2026-11-04.json) aprovou **81 verificações**: 36 da fundação, 44 de cadastros/identidade/isolamento e uma de limpeza. O código desse complemento está em [RegistryHomologation](../../app/Support/RegistryHomologation.php); a análise e os limites ficam na [revisão da etapa 2](revisao-etapa-02.md).

- [Confirmado] Cobertura adicional: unidade clínica, atribuição de integrante, profissional, empresa/estabelecimento, pacientes, busca por CPF/HMAC e nome, vínculo empregatício, comparação/merge humano, consulta consolidada, versionamento/desligamento e criação/revogação de concessão empresarial. Verifica preservação das FKs/histórico, ausência de papéis automáticos e recusa de paciente/empresa/vínculo de outra clínica.
- [Confirmado] Usa os administradores sintéticos existentes com senha/TOTP privados. Cria integrante sem permissões e registros sintéticos com identificador aleatório próprio da execução; usa o mesmo CPF sintético nas duas clínicas para exercitar seu isolamento. Não modifica senhas, TOTP ou a atribuição de unidade dos quatro atores persistentes documentados.
- [Confirmado] O destino é validado antes da execução e novamente antes da limpeza. Em `finally`, remove somente registros temporários rastreados por IDs e tenant, em transação e ordem de FKs; mantém a auditoria operacional sem conteúdo cadastral. Não faz truncamento ou limpeza global. Interrupção abrupta do processo/container pode impedir esse `finally`; nesse caso, examinar as fixtures do ambiente isolado antes de repetir, sem apagar registros pelo nome genericamente.
- [Confirmado] `--registries` só é aceito na ação `check` do script. A suíte verifica que o comando Artisan, com e sem as opções ampliadas, recusa ambiente diferente da homologação isolada antes de qualquer requisição ou fixture. A CI declarada agora executa `--stage3`, que inclui estes cadastros; execução remota continua não verificada.

## Catálogo e configuração clínica — etapa 3

[Confirmado] `check --stage3` inclui fundação e cadastros. A [execução de 04/11/2026 UTC](homologacao-etapa-03-2026-11-04.json) aprovou **168 verificações**: as 81 anteriores e 87 adicionais, incluindo a limpeza da etapa 3. Implementação em [StageThreeHomologation](../../app/Support/StageThreeHomologation.php); escopo e limites na [revisão da etapa 3](revisao-etapa-03.md). O relatório específico usa o prefixo `homologacao-etapa-03-AAAA-MM-DD.json`, sem substituir a evidência da etapa 2.

- [Confirmado] HTTP real: cadastro/edição de exame e preparo, upload multipart de PNG sintético, download com bytes exatos, ausência de URL pública, publicação de perguntas e suas versões, concessão/retirada de manifestações, referências de programas, protocolos pendentes, aprovação e nova versão sem herança de aprovação. Verifica negações de objetos das duas clínicas em ambos os sentidos. A cifra do storage/evidências é inspecionada diretamente; o snapshot readonly da definição é construído pela Action, sem endpoint ou coleta de respostas.
- [Confirmado] Cada clínica recebe um aprovador temporário sem papéis: senha e segredo TOTP aleatórios ficam somente na memória. A fixture concede explicitamente permissões mínimas de leitura/aprovação; login, TOTP e confirmação recente de senha passam pelo servidor real. O roteiro verifica a recusa do administrador sem concessão, da aprovação repetida e da aprovação após retirada da permissão temporária. Nenhuma concessão clínica é atribuída às contas persistentes.
- [Confirmado] Antes das fixtures e novamente antes da limpeza, o destino isolado é validado. O `finally` remove somente IDs/tenant rastreados, concessões e sessões do aprovador temporário, em transação e ordem de FKs; apaga somente os caminhos privados exatos dos anexos sintéticos e confere sua ausência. Auditoria operacional permanece. Storage e transação SQL não são atômicos; interrupção abrupta pode impedir a limpeza e exige inspeção restrita da execução antes de repetir.
- [Confirmado] `--stage3` só funciona com `check`; inclui `--registries` implicitamente. Testes de guarda cobrem as quatro combinações das opções e a recusa direta do helper fora da homologação antes de executar callbacks. O roteiro usa somente conteúdo fictício sem efeito médico/jurídico e não substitui aprovação de conteúdo pela clínica, revisão visual ou aceite final.

## Clínicas e acessos sintéticos

[Confirmado] A fonte única de URLs, perfis, e-mails, variáveis de senha e instruções do segundo fator é [Acessos locais — homologação isolada](acessos-locais.md#homologação-local-isolada--duas-clínicas). O mesmo documento mantém os acessos de desenvolvimento da porta 8080 em seção separada.

[Confirmado] O verificador usa TOTP real, sem desabilitar o segundo fator; se o código da janela atual já foi consumido, aguarda a próxima janela. O check espera os estados iniciais das fixtures documentados nos acessos.

## Cobertura e limites

- [Confirmado] HTTP real: login por domínio, senha da outra clínica rejeitada para o mesmo e-mail, desafio TOTP, sessão SQL criptografada, CSRF sem token rejeitado, cookie A reapresentado em B, binding 404, header falso, domínio desconhecido, ACL 403, troca inicial de senha e logout.
- [Confirmado] Arquivos .env/docs/vendor indisponíveis pelo webroot; Horizon web negado aos atores clínicos. Fontes e ilustrações locais conferidas no navegador nas duas clínicas.
- [Confirmado] Horizon ativo em staging com um worker: executa A → falha A → B no mesmo PID, preservando contexto, team e diferenças de autorização. A falha sintética intermediária é intencional e aparece em failed_jobs; o teste remove somente sua chave Redis de resultado, sem flush global.
- [Confirmado] APP_DEBUG=false, SESSION_ENCRYPT=true, Redis para cache/fila e MAIL_MAILER=array. HTTP local sem TLS e sem envio de e-mail externo; não homologa HTTPS, SMTP ou implantação externa. A CI foi ampliada para executar este roteiro após os checks/build, mas não foi executada remotamente nesta sessão.
