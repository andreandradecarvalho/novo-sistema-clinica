# 21 — DDL e reaproveitamento de referências

## 21.1 Fonte executável

[Confirmado] Um arquivo [schema.sql](../schema/schema.sql), no banco único conforme [09](09-estrategia-multi-tenant.md). Contagem, alterações e resultado de validação estão no [CHANGELOG](../schema/CHANGELOG.md). Não manter outra lista de contagem suscetível a divergência.

[Confirmado] Modelo e relações: [12](12-novo-schema-unificado.md); atendimento: [22](22-atendimento-clinico.md); ocupacional: [23](23-saude-ocupacional.md); segurança/guarda: [24](24-seguranca-retencao-operacao.md); contrato ETL: [13](13-plano-migracao-dados.md). As migrations futuras devem materializar o mesmo contrato; ainda não há aplicação instalada.

[Inferido] Aplicar somente em **banco descartável local/staging**, nunca no banco de trabalho existente ou produção. Selecionar motor/versão de [14](14-arquitetura-laravel.md); validar separadamente antes de anunciar suporte a ambos. Rodar migration uma vez; onboarding cria tenant/domínio e seeds com contexto.

[Inferido] Validar criação com FKs ligadas, catálogo de constraints e DML sintético: referência entre tenants rejeitada; reserva cancelada libera slot; tentativa duplicada rejeitada; ASO/documento e contexto preservados. Sucesso SQL não comprova cast criptografado, autorização HTTP, assinatura ou isolamento de filas/storage.

## 21.2 Reaproveitamento

[Inferido] Tailadmin e legado são referências somente leitura; não são base arquitetural do SaaS. Reaproveitar conceitos e validar contra DDL/fluxos atuais: VOs de CPF/telefone/nome/datas, enums sem dependência visual, catálogos e de-paras sintéticos. Não copiar credenciais, pacientes, documentos clínicos ou arquitetura single-tenant/cPanel.

[Inferido] Seed de catálogo não presume que as salas/protocolos da Precordis sejam padrão de toda clínica. Parametrizar por tenant e validar conteúdo clínico com seu responsável técnico. Vuexy é a referência visual; estilos de Sneat/Filament não substituem essa escolha.
