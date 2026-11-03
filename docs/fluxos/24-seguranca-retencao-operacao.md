# 24 — Segurança, retenção e operação do novo SaaS

[Confirmado] Esta é a fonte normativa de requisitos de segurança do novo produto. [Análise 05](../analise/05-seguranca-lgpd-conformidade.md) descreve achados históricos do legado, não autorização para modificá-lo. Legado somente leitura e nenhum DDL/DML de produção neste trabalho. Políticas abaixo são propostas técnicas [Inferido] a implementar e homologar.

## 24.1 Autorização

| Papel/contexto | Acesso padrão proposto |
|---|---|
| [Inferido] Plataforma | Operação comercial/técnica e métricas agregadas; sem leitura clínica automática. Acesso excepcional com motivo, prazo, aprovação da clínica e auditoria. |
| [Inferido] Administração da clínica | Gestão conforme permissões; título de administrador não concede todo prontuário. |
| [Inferido] Recepção | Identificação, pedido, agenda e andamento da unidade autorizada; conteúdo clínico somente se atribuição específica exigir. |
| [Inferido] Profissional clínico | Execução, revisão, assinatura e liberação são permissões distintas, condicionadas a habilitação e vínculo com o atendimento. |
| [Inferido] Empresa | Concessão por empresa/vigência; situação operacional e ASO autorizado segundo fluxo 23. Sem anamnese/diagnóstico por padrão. |
| [Inferido] Paciente | Seus dados/documentos autorizados; conferir identidade e representação quando aplicável. |
| [Inferido] Dispositivo | Token revogável por tenant/unidade e finalidade; TV recebe identificação mínima para chamada, sem dados clínicos. |

- [Inferido] Resolver tenant não concede autorização. Validar vínculo do principal ao domínio/header/dispositivo, negar contexto ausente e impedir sobrescrita de tenant. Sessões, tokens, busca, cache, ACL, filas e broadcast têm escopo explícito. Workers limpam contexto antes e depois de cada job, inclusive em falha.
- [Inferido] Global scopes auxiliam consultas Eloquent; não equivalem a RLS no servidor. SQL cru/bulk/importação exige contrato de contexto. O DDL usa FKs compostas onde aplicável para impedir pai de outra clínica; vínculos polimórficos e papéis globais exigem Policies/testes específicos.
- [Inferido] Links de download são concessões com token armazenado apenas como hash, destinatário/escopo, expiração e revogação. Validar tenant, documento liberado e concessão em todo acesso; URL assinada isoladamente não define identidade. Registrar leitura/exportação clínica e mudanças de autorização, sem copiar conteúdo clínico para logs.

## 24.2 Criptografia e bases de tratamento

- [Inferido] TLS, storage privado criptografado, backups criptografados e controle de chaves são obrigatórios na implantação. CPF usa ciphertext e HMAC por tenant/chave para unicidade/busca exata; normalização e derivação de chave ficam centralizadas. Chaves de busca e criptografia separadas, com versão e roteiro de rotação. HMAC continua dado pessoal pseudonimizado.
- [Inferido] Campos `*_encrypted` recebem ciphertext pela aplicação; DDL não implementa criptografia sozinho. Incluem CPF, conteúdo de resposta, conclusões e snapshots clínicos. Não aplicar cast criptografado sobre CHAR de tamanho original. Busca textual em conteúdo clínico criptografado não é prometida.
- [Confirmado] Tutela da saúde está no art. 11, II, “f” da [LGPD](https://www2.camara.leg.br/legin/fed/lei/2018/lei-13709-14-agosto-2018-787077-normaatualizada-pl.pdf); obrigação legal/regulatória está na alínea “a”.
- [Inferido] Registrar finalidade/base por operação; consentimento não é base universal. `patient_consents` registra eventos por finalidade, versão, manifestação e data, sem sobrescrever escolhas antigas. Marketing e ciência de termos são separados do atendimento. A retirada de consentimento não apaga automaticamente informação sob obrigação de guarda.

## 24.3 Guarda, desligamento e documentos

- [Confirmado] A [NR-7, §7.6.1.1](https://www.gov.br/trabalho-e-emprego/pt-br/acesso-a-informacao/participacao-social/conselhos-e-orgaos-colegiados/comissao-tripartite-partitaria-permanente/arquivos/normas-regulamentadoras/nr-07-atualizada-2022.pdf) prevê prontuário do empregado por no mínimo 20 anos após desligamento, com ressalvas nos anexos. A [Lei 13.787, art. 6º](https://bibliotecadigital.mj.gov.br/bitstream/1/1641/2/LEI_2018_13787.html) usa último registro como marco de seu prazo mínimo.
- [Inferido] Aplicar a política pertinente por categoria e preservar o maior prazo aplicável quando houver sobreposição. Sem marco conhecido, não autorizar descarte automático. Registrar impedimentos de descarte, responsável e evidência de aprovação. Manter registros necessários de desligamento e acesso sob finalidade definida; não presumir 20 anos para todo log operacional.
- [Inferido] Desligar tenant significa suspender novas operações comerciais conforme contrato, garantir exportação e continuidade dos acessos necessários, preservar guarda e cancelar jobs/dispositivos. **Não há endpoint/rotina padrão de DELETE em cascata de prontuários.** FKs de tenant são RESTRICT no DDL; descarte futuro exige procedimento próprio aprovado e testado.
- [Inferido] Arquivo liberado é imutável: nova versão, autoria, motivo, hash e evidência de assinatura. Hash/QR não comprovam assinatura por si sós; verificação deve validar artefato e provedor. Definir política por tipo documental antes de eliminar papel.
- [Confirmado] A Lei 13.787, art. 2º, §2º, prevê ICP-Brasil ou outro padrão legalmente aceito no processo de digitalização; isso não é uma definição universal de assinatura de todo laudo originalmente digital. Fonte: [texto oficial](https://bibliotecadigital.mj.gov.br/bitstream/1/1641/2/LEI_2018_13787.html).

## 24.4 Recuperação e capacidade

- [Inferido] Meta técnica inicial a homologar: RPO ≤ 1 hora e RTO ≤ 4 horas para recuperação completa do serviço. Ajustar por contrato antes de prometer SLA. Backup completo + recuperação incremental/PITR no motor escolhido, storage com versionamento e cópia independente das chaves.
- [Inferido] Restore seletivo: restaurar snapshot em instância isolada, extrair tenant com dependências diretas e indiretas, conferir IDs, hashes e referências de storage, reconciliar gravações posteriores e só então importar por procedimento autorizado. Não restaurar o dump completo sobre banco compartilhado em uso.
- [Inferido] Ensaio verifica dados e arquivos em conjunto e ausência de alteração nos outros tenants; registrar duração, ponto recuperado e falhas. Recuperação seletiva não é descrita como trivial nem reduzida a filtrar toda tabela por `tenant_id`.
- [Inferido] Limites de fila, requests, uploads, storage e concorrência por tenant; alertas de indisponibilidade, erros, backlog, falha de assinatura e anomalias de acesso. Rotação e acesso a segredos auditados. Timezone IANA por clínica; timestamps em UTC, datas de agenda e prazos interpretados no timezone da clínica.

## 24.5 Aceite

- [Inferido] Testes de autorização por categoria/vínculo e dois tenants, referências cruzadas, contexto ausente/falso, troca de contexto no mesmo worker, download expirado/revogado, concorrência e restore. Os testes SQL de estrutura não substituem testes HTTP/filas/storage, que exigem aplicação implementada.
