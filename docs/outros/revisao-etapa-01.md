# Revisão técnica da etapa 1 — 03/11/2026

[Confirmado] Revisão local das alterações destinadas ao PR, na branch `feat/etapa-01-fundacao`, sobre a revisão `e21900372982e19b0e88402ee3639bc810ac7be2` e alterações ainda não commitadas. Não foi publicada aprovação em PR no GitHub. Aplicada a skill Laravel 13 Multi-Tenant Expert. O status e o aceite são mantidos exclusivamente no [fluxo 20](../fluxos/20-plano-desenvolvimento.md#204-controle-de-fases-manter-atualizado).

## Achados e correções

| Estado | Prioridade | Cenário e impacto | Correção / regressão |
|---|---|---|---|
| [Confirmado] Corrigido | P1 | Duas verificações TOTP podiam ler a chave de uso ausente antes de ambas gravarem com put, aceitando o mesmo código. | [Provider](../../app/Domain/Identity/Auth/TenantTwoFactorProvider.php): consumo com cache add atômico; recusa sem cache. [Teste](../../tests/Feature/FoundationTest.php): uma requisição concorrente vence entre a leitura e a gravação; a perdedora é rejeitada. |
| [Confirmado] Corrigido | P1 | O TTL anterior de 60 segundos podia terminar enquanto um código adiantado ainda era válido na janela de tolerância. | TTL cobre `(2 × window + 1) × período`. Regressão com relógio do gerador controlado e avanço do cache em 61 segundos rejeita o replay. |
| [Confirmado] Corrigido na revisão anterior e incluído nesta entrega | P1 | O roteiro executava migrate antes de validar o destino permitido para os seeds. | [Comando protegido](../../app/Console/Commands/MigrateHomologation.php) reutiliza LocalHomologation antes das migrations; testes rejeitam ambiente e conexão incompatíveis. |

## Escopo revisado

- [Confirmado] Tenancy: ResolveTenant/EnsureTenantMember, TenantContext, BelongsToTenant/TenantBuilder, propriedade imutável e bindings. Models globais Tenant/Domain têm uso explícito no provisionamento e resolução. SQL direto atual de sessões e auditoria inclui tenant do contexto; versões/FKs do comando de homologação não consultam dados clínicos.
- [Confirmado] Identidade: Fortify, reset por clínica, senha inicial, sessões, TOTP e políticas de membros; Spatie teams, limpeza das relações e do registrar na troca de contexto. Auditoria atual cobre os eventos de autenticação implementados; CRUD futuro de concessões deverá incluir auditoria própria.
- [Confirmado] Filas/cache/storage/broadcast: hooks de limpeza, payload escalar dos jobs existentes, namespace de tenant, paths privados e autorização do canal. Horizon web permanece negado; teste real exercita A → falha A → B no mesmo processo.
- [Confirmado] Migrations/runtime, seeds e operação: FKs compostas de usuário/sessão/unidade, catálogo global, provisionamento sem criar banco, fixtures isoladas e CI declarada. Histórico do rollback/reaplicação em banco descartável está no fluxo 20/CHANGELOG; não foi repetido nesta revisão, pois o DDL não mudou.
- [Confirmado] Interface: revisão estática de rotas, componentes Blade, escaping e POST/CSRF, integração Vite e assets Vuexy selecionados. O SVG de QR code é gerado pelo Fortify; não recebe HTML enviado pelo usuário. Evidências visuais anteriores permanecem no fluxo 20; não houve nova alteração de layout nesta revisão.

## Verificação e limites

- [Confirmado] Executados nesta revisão: composer check — Pint em 68 fontes, Rector sem alterações, Larastan sem erros e **38 testes/186 asserções**; npm run build aprovado.
- [Confirmado] **36 verificações HTTP/Horizon** aprovadas em duas clínicas: [relatório sanitizado](homologacao-local-2026-11-03.json), PHP 8.5.12, Laravel 13.39.0 e MariaDB 13.0.2. As regressões de corrida TOTP usam interleaving controlado; não representam teste de carga HTTP concorrente.
- [Confirmado] Não foram identificados achados bloqueantes remanescentes no escopo revisado após as correções. Isso não certifica ausência de vulnerabilidades fora desse escopo.
- [Confirmado] CI remota foi adiada expressamente pelo usuário. MySQL/PostgreSQL, HTTPS/SMTP, revisão humana independente, implantação e funcionalidades clínicas futuras não foram certificados nesta revisão. Nenhum push, merge, deploy ou acesso ao legado/produção foi realizado.
