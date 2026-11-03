# Homologação local isolada da fundação

[Confirmado] Ambiente escolhido pelo usuário para validar a etapa 1 com duas clínicas sintéticas. Usa [compose.homologacao.yaml](../../compose.homologacao.yaml) e [scripts/homologacao.py](../../scripts/homologacao.py). Estratégia de dados e versões seguem os fluxos [09](../fluxos/09-estrategia-multi-tenant.md) e [14](../fluxos/14-arquitetura-laravel.md). Status/aceite ficam exclusivamente no [fluxo 20](../fluxos/20-plano-desenvolvimento.md).

## Preparação e operação

[Confirmado] Requer dependências PHP instaladas e assets compilados conforme o [README](../../README.md#ambiente-local). O código e os assets são compartilhados em modo somente leitura. Banco, rede, Redis, storage, bootstrap/cache, APP_KEY e cookie de sessão pertencem ao projeto Compose precordis-clinic-homologacao, separado do desenvolvimento. O servidor publica somente a porta de loopback 8082; banco/Redis não têm portas publicadas.

```bash
# Na raiz. Gera somente valores ausentes na primeira criação do arquivo privado.
python3 scripts/homologacao.py prepare
python3 scripts/homologacao.py up
python3 scripts/homologacao.py check
python3 scripts/homologacao.py status

# Encerra somente esta homologação, preservando seus volumes.
python3 scripts/homologacao.py down
```

[Confirmado] O preparador preserva um .env.homologacao já existente. O comando up executa homologation:migrate, que valida ambiente, configuração e conexão resolvida antes de chamar as migrations; migra somente o banco novo_sistema_clinica_homologacao e aplica fixtures protegidas por ambiente/host/nome de banco; não limpa tabelas nem sobrescreve usuários existentes. A suíte testa a recusa do seeder e do comando de verificação fora desse contexto, incluindo DB_URL, conexão padrão e conexão já resolvida incompatíveis. Nenhuma alteração no legado ou nas contas locais da porta 8080.

[Confirmado] O comando check é executado no container e chama o servidor HTTP real. O relatório público, sem credenciais, fica em docs/outros/homologacao-local-AAAA-MM-DD.json; o snapshot validado desta entrega é [homologacao-local-2026-11-03.json](homologacao-local-2026-11-03.json). O resultado inclui data, versões reais, FKs habilitadas, verificações e revisão de origem; working_tree_dirty registra alterações locais ainda não commitadas.

## Clínicas e acessos sintéticos

[Confirmado] A fonte única de URLs, perfis, e-mails, variáveis de senha e instruções do segundo fator é [Acessos locais — homologação isolada](acessos-locais.md#homologação-local-isolada--duas-clínicas). O mesmo documento mantém os acessos de desenvolvimento da porta 8080 em seção separada.

[Confirmado] O verificador usa TOTP real, sem desabilitar o segundo fator; se o código da janela atual já foi consumido, aguarda a próxima janela. O check espera os estados iniciais das fixtures documentados nos acessos.

## Cobertura e limites

- [Confirmado] HTTP real: login por domínio, senha da outra clínica rejeitada para o mesmo e-mail, desafio TOTP, sessão SQL criptografada, CSRF sem token rejeitado, cookie A reapresentado em B, binding 404, header falso, domínio desconhecido, ACL 403, troca inicial de senha e logout.
- [Confirmado] Arquivos .env/docs/vendor indisponíveis pelo webroot; Horizon web negado aos atores clínicos. Fontes e ilustrações locais conferidas no navegador nas duas clínicas.
- [Confirmado] Horizon ativo em staging com um worker: executa A → falha A → B no mesmo PID, preservando contexto, team e diferenças de autorização. A falha sintética intermediária é intencional e aparece em failed_jobs; o teste remove somente sua chave Redis de resultado, sem flush global.
- [Confirmado] APP_DEBUG=false, SESSION_ENCRYPT=true, Redis para cache/fila e MAIL_MAILER=array. HTTP local sem TLS e sem envio de e-mail externo; não homologa HTTPS, SMTP ou implantação externa. A CI foi ampliada para executar este roteiro após os checks/build, mas não foi executada remotamente nesta sessão.
