# Acessos locais de teste — Precordis

[Confirmado] Contas sintéticas criadas e com login validado em 03/11/2026, na clínica Precordis do ambiente de desenvolvimento local. Registro da criação no [plano de execução](../fluxos/20-plano-desenvolvimento.md). Este documento não descreve acessos ao legado ou à produção.

[Confirmado] **Endereço:** http://localhost:8080/login.

[Confirmado] As senhas iniciais estão no [arquivo privado de acessos](.env.acessos-locais), nesta pasta. Ele é ignorado pelo Git e possui permissão 0600. A tabela indica a variável que contém cada senha; os valores não são reproduzidos neste documento versionável.

| Perfil | E-mail | Variável da senha inicial |
|---|---|---|
| [Confirmado] Administrador | `admin.teste@example.test` | `LOCAL_TEST_ADMIN_PASSWORD` |
| [Confirmado] Recepção | `recepcao.teste@example.test` | `LOCAL_TEST_RECEPCAO_PASSWORD` |
| [Confirmado] Médico | `medico.teste@example.test` | `LOCAL_TEST_MEDICO_PASSWORD` |

## Primeiro acesso

1. [Confirmado] Abra o endereço acima e entre com o e-mail e a senha inicial correspondentes.
2. [Confirmado] Confirme a senha atual quando solicitado para abrir as configurações de segurança.
3. [Confirmado] Troque a senha inicial antes de acessar a área da clínica.
4. [Confirmado] Administrador e médico também devem configurar e confirmar o segundo fator no aplicativo autenticador antes de acessar a área clínica. Guarde os códigos de recuperação em local seguro.

[Confirmado] O arquivo privado registra as senhas iniciais fornecidas nesta sessão; ele não é atualizado automaticamente quando uma senha é alterada pela interface. As contas são de teste, sem credenciais padrão em seeders; o legado não foi alterado.

## Homologação local isolada — duas clínicas

[Confirmado] As contas abaixo pertencem ao ambiente isolado da porta 8082. Para preparar, iniciar ou verificar esse ambiente, consulte o [roteiro de homologação](homologacao-local.md). Os acessos da porta 8080 permanecem separados.

[Confirmado] As senhas e chaves TOTP estão no [.env.homologacao](../../.env.homologacao), na raiz, ignorado pelo Git e com permissão 0600. Cada variável abaixo contém o valor correspondente; nenhum segredo é reproduzido neste documento.

| Clínica | Endereço de login | Perfil | E-mail | Variável da senha |
|---|---|---|---|---|
| [Confirmado] A | http://clinica-a.localhost:8082/login | Administrador | `admin.homologacao@example.test` | `HOMOLOG_A_ADMIN_PASSWORD` |
| [Confirmado] B | http://clinica-b.localhost:8082/login | Administrador | `admin.homologacao@example.test` | `HOMOLOG_B_ADMIN_PASSWORD` |
| [Confirmado] A | http://clinica-a.localhost:8082/login | Recepção | `recepcao.homologacao@example.test` | `HOMOLOG_A_RECEPTION_PASSWORD` |
| [Confirmado] B | http://clinica-b.localhost:8082/login | Recepção | `recepcao.homologacao@example.test` | `HOMOLOG_B_RECEPTION_PASSWORD` |

[Confirmado] O mesmo e-mail existe nas duas clínicas, com senhas distintas. Use o endereço e a variável da clínica escolhida.

[Confirmado] Os administradores sintéticos têm onboarding concluído e segundo fator habilitado. Cadastre a chave `HOMOLOG_A_TOTP_SECRET` ou `HOMOLOG_B_TOTP_SECRET` do arquivo privado em um aplicativo autenticador e informe o código temporário após a senha. A recepção A exige troca da senha inicial; a recepção B permite acessar o dashboard e não possui permissão para consultar a equipe.

[Confirmado] O seeder preserva alterações manuais de senha e segundo fator; o arquivo privado não acompanha mudanças feitas pela interface. A verificação automatizada espera o estado inicial das contas sintéticas descrito acima.
