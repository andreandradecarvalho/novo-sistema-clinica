# Histórico do schema

## 2026-10-31 — Referência de versões de destino

- [Confirmado] Em `central.sql` e `tenant.sql`, somente os comentários do cabeçalho foram alterados: a indicação fixa de MySQL 8.4 LTS foi substituída por referência à [matriz única no fluxo 14 §14.1](../fluxos/14-arquitetura-laravel.md#141-stack--fonte-única-de-versões), conforme solicitação do usuário para atualizar PHP/Laravel e MySQL ou MariaDB.
- [Confirmado] Os cabeçalhos agora distinguem o DDL existente da homologação pendente nas versões de destino. Não houve alteração de instruções SQL, tabelas, colunas, índices, collations ou FKs; o split central/tenant foi preservado.
- [Confirmado] A instância MariaDB 10.5 local e o banco vazio criado anteriormente não comprovam compatibilidade dos DDLs. Nenhum SQL foi executado nesta atualização documental; a validação deve ocorrer em bancos descartáveis local/staging, no motor/versão de destino, antes de declarar suporte.
