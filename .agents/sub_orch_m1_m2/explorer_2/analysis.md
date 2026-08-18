# Especificação Técnica de Banco de Dados, Modelos Eloquent e Migrações (Milestone M2)
## Plataforma CONECTA EGRESSO — SEJUS / Governo do Estado do Espírito Santo

**Agente:** Explorer 2 (`sub_orch_m1_m2/explorer_2`)  
**Data:** 17 de Agosto de 2026  
**Status:** Especificação Técnica Aprovada para Implementação  
**Escopo:** 12 Migrações PostgreSQL 16, 12 Modelos Eloquent, Base dos 78 Municípios do ES, Serviços Criptográficos LGPD e Trilha de Auditoria Imutável

---

## 1. Visão Geral da Arquitetura de Dados

A camada de persistência da plataforma CONECTA EGRESSO foi projetada para atender aos requisitos de alta disponibilidade, rastreabilidade indelével, proteção de dados pessoais sensíveis (LGPD Art. 6º) e suporte geoespacial (PostGIS) cobrindo todos os 78 municípios do Estado do Espírito Santo.

```
                                 ┌─────────────────────────┐
                                 │         perfis          │
                                 │ (Gestor, Técnico, etc.) │
                                 └────────────┬────────────┘
                                              │ 1:N
                                              ▼
┌─────────────────────────┐      1:N     ┌─────────────────────────┐      1:1      ┌─────────────────────────┐
│      municipios_es      ├─────────────►│          users          ├──────────────►│        egressos         │
│ (78 Municípios PostGIS) │              │  (Auth, CPF Hash LGPD)  │               │ (Dados Pessoais Cripto) │
└────────────┬────────────┘              └────────────┬────────────┘               └────────────┬────────────┘
             │                                        │                                         │
             ├───────────────────────┐                │ 1:N (Responsável / Técnico)             │ 1:1
             │ 1:N                   │ 1:N            ▼                                         ▼
             ▼                       ▼   ┌─────────────────────────┐               ┌─────────────────────────┐
┌─────────────────────────┐ ┌────────┴───┤   prontuario_timeline   │◄──────────────┤       prontuarios       │
│      vagas_emprego      │ │ cursos_    │  (Histórico de Eventos) │      1:N      │     (Registro Único)    │
│ (Vagas Afirmativas ES)  │ │ capacitacao│ └─────────────────────────┘               └────────────┬────────────┘
└─────────────────────────┘ └────────────┘                                                      │ 1:N
             │                                                                                  ▼
             │ 1:N                                                                 ┌─────────────────────────┐
             ▼                                                                     │ prontuario_audit_logs   │
┌─────────────────────────┐                                                        │ (RULE DO INSTEAD NOTHING│
│       rede_apoio        │                                                        │   SHA-256 Hash Chain)   │
│ (CRAS, CREAS, SINE,CAPS)│                                                        └─────────────────────────┘
└─────────────────────────┘
             │
             │ 1:N (Salas / Atendimentos Remotos)
             ▼
┌─────────────────────────┐      1:N     ┌─────────────────────────┐
│       video_rooms       ├─────────────►│     video_attendees     │
│ (Sinalização WebRTC ES) │              │  (MOS Score, Telemetria)│
└─────────────────────────┘              └─────────────────────────┘
```

---

## 2. Especificação Detalhada das 12 Migrações PostgreSQL 16

### 2.1 Migração 1: `create_perfis_table`
- **Arquivo:** `database/migrations/2026_08_17_000001_create_perfis_table.php`
- **Tabela:** `perfis`
- **Propósito:** Armazena os perfis de acesso do sistema (RBAC).

| Coluna | Tipo | Modificadores / Restrições | Descrição |
|---|---|---|---|
| `id` | `id()` (BigIncrements) | Primary Key | Identificador numérico do perfil |
| `nome` | `string('nome', 50)` | Not Null, Unique | Nome exibível (ex: "Gestor SEJUS", "Técnico", "Egresso", "Familiar") |
| `slug` | `string('slug', 50)` | Not Null, Unique, Index | Identificador interno (`gestor`, `tecnico`, `egresso`, `familiar`) |
| `descricao` | `string('descricao', 255)` | Nullable | Descrição das atribuições do perfil |
| `permissoes` | `jsonb('permissoes')` | Nullable | Array estruturado de permissões granulares |
| `ativo` | `boolean('ativo')` | Default `true` | Flag indicativa de perfil habilitado |
| `timestamps` | `timestamps()` | Not Null | `created_at` e `updated_at` |

---

### 2.2 Migração 2: `create_municipios_es_table`
- **Arquivo:** `database/migrations/2026_08_17_000002_create_municipios_es_table.php`
- **Tabela:** `municipios_es`
- **Propósito:** Catálogo geoespacial dos 78 municípios capixabas com suporte a PostGIS.

| Coluna | Tipo | Modificadores / Restrições | Descrição |
|---|---|---|---|
| `id` | `id()` (BigIncrements) | Primary Key | Identificador sequencial |
| `codigo_ibge` | `integer('codigo_ibge')` | Not Null, Unique, Index | Código oficial IBGE de 7 dígitos (ex: 3205309 para Vitória) |
| `nome` | `string('nome', 100)` | Not Null, Index | Nome oficial do município |
| `microrregiao` | `string('microrregiao', 100)` | Not Null, Index | Microrregião oficial IJSN (ex: Metropolitana, Rio Doce, Caparaó) |
| `macrorregiao` | `string('macrorregiao', 50)` | Not Null, Index | Macrorregião estadual (Metropolitana, Central, Norte, Sul) |
| `latitude` | `decimal('latitude', 10, 7)` | Not Null | Latitude da sede municipal em graus decimais |
| `longitude` | `decimal('longitude', 10, 7)` | Not Null | Longitude da sede municipal em graus decimais |
| `tem_escritorio_fisico` | `boolean('tem_escritorio_fisico')` | Default `false`, Index | `true` apenas para Vitória, Vila Velha, Serra e Cariacica |
| `populacao_estimada` | `integer('populacao_estimada')` | Nullable | População censitária estimada |
| `total_egressos_atendidos`| `integer('total_egressos_atendidos')` | Default `0` | Contador consolidado para KPIs de gestão |
| `timestamps` | `timestamps()` | Not Null | `created_at` e `updated_at` |

- **Notas de Schema:** Adicionar suporte opcional a `DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');` se disponível no container, ou fallback gracioso para coordenadas numéricas com índices espaciais compostos `(latitude, longitude)`.

---

### 2.3 Migração 3: `create_users_table`
- **Arquivo:** `database/migrations/2026_08_17_000003_create_users_table.php`
- **Tabela:** `users`
- **Propósito:** Autenticação local e federada (Acesso Cidadão PRODEST / Gov.br), blind index de CPF.

| Coluna | Tipo | Modificadores / Restrições | Descrição |
|---|---|---|---|
| `id` | `id()` (BigIncrements) | Primary Key | Identificador do usuário |
| `perfil_id` | `foreignId('perfil_id')` | Not Null, Constrained('perfis')->onDelete('restrict') | Vínculo com a tabela `perfis` |
| `name` | `string('name', 150)` | Not Null | Nome cadastral do usuário |
| `email` | `string('email', 191)` | Not Null, Unique, Index | E-mail corporativo ou pessoal |
| `password` | `string('password', 255)` | Nullable | Hash bcrypt (pode ser nulo se autenticado puramente via OIDC) |
| `govbr_id` | `string('govbr_id', 100)` | Nullable, Unique, Index | Subject identifier do Gov.br / Acesso Cidadão |
| `cpf_encrypted` | `text('cpf_encrypted')` | Nullable | CPF cifrado em AES-256 via `Crypt::encryptString` |
| `hash_cpf` | `string('hash_cpf', 64)` | Nullable, Unique, Index | Blind Index HMAC-SHA256(cpf, PEPPER) para buscas exatas |
| `telefone_encrypted` | `text('telefone_encrypted')` | Nullable | Telefone cifrado |
| `foto_url` | `string('foto_url', 255)` | Nullable | URL da foto do perfil ou snapshot de validação |
| `ativo` | `boolean('ativo')` | Default `true`, Index | Status da conta de usuário |
| `email_verified_at` | `timestamp('email_verified_at')` | Nullable | Data de confirmação do e-mail |
| `remember_token` | `rememberToken()` | Nullable | Token de sessão persistente |
| `timestamps` | `timestamps()` | Not Null | `created_at` e `updated_at` |

---

### 2.4 Migração 4: `create_egressos_table`
- **Arquivo:** `database/migrations/2026_08_17_000004_create_egressos_table.php`
- **Tabela:** `egressos`
- **Propósito:** Dados biográficos, histórico penal e vulnerabilidades socioeconômicas com criptografia LGPD.

| Coluna | Tipo | Modificadores / Restrições | Descrição |
|---|---|---|---|
| `id` | `id()` (BigIncrements) | Primary Key | Identificador do egresso |
| `user_id` | `foreignId('user_id')` | Nullable, Unique, Constrained('users')->nullOnDelete() | Vínculo opcional 1:1 com conta de usuário |
| `nome_completo` | `string('nome_completo', 150)` | Not Null, Index | Nome completo registrado |
| `nome_social` | `string('nome_social', 150)` | Nullable | Nome social adotado |
| `data_nascimento` | `date('data_nascimento')` | Nullable | Data de nascimento |
| `cpf_encrypted` | `text('cpf_encrypted')` | Not Null | CPF cifrado em AES-256 |
| `hash_cpf` | `string('hash_cpf', 64)` | Not Null, Unique, Index | Blind index HMAC-SHA256 para localização unificada |
| `rg_encrypted` | `text('rg_encrypted')` | Nullable | RG / Órgão Emissor cifrado |
| `filiacao_mae_encrypted` | `text('filiacao_mae_encrypted')` | Nullable | Nome da mãe cifrado para conferência civil |
| `municipio_residencia_id`| `foreignId('municipio_residencia_id')`| Not Null, Constrained('municipios_es')->restrictOnDelete() | Município de moradia no ES |
| `endereco_encrypted` | `text('endereco_encrypted')` | Nullable | Endereço residencial cifrado |
| `telefone_encrypted` | `text('telefone_encrypted')` | Nullable | Telefone de contato / WhatsApp cifrado |
| `escolaridade` | `string('escolaridade', 50)` | Nullable | Fundamental, Médio, Superior, etc. |
| `status_penal` | `string('status_penal', 50)` | Not Null, Default `'egresso'` | `egresso`, `livramento_condicional`, `regime_aberto`, `extinta_pena` |
| `unidade_prisional_origem`| `string('unidade_prisional_origem', 150)`| Nullable | Unidade prisional do ES de onde saiu (ex: PSVV, CRL, etc.) |
| `numero_processo_execucao`| `string('numero_processo_execucao', 100)`| Nullable | Número SEEU / Execução Penal |
| `vulnerabilidades` | `jsonb('vulnerabilidades')` | Nullable | Tags de vulnerabilidade (ex: `["sem_documentacao", "dependencia_quimica"]`) |
| `consentimento_geolocalizacao`| `boolean('consentimento_geolocalizacao')`| Default `false` | Aceite explícito do Art. 6º/7º LGPD para geolocalização |
| `consentimento_compartilhamento`| `boolean('consentimento_compartilhamento')`| Default `false` | Aceite para envio de currículo a empresas conveniadas |
| `termo_aceito_em` | `timestamp('termo_aceito_em')` | Nullable | Data/hora exata do aceite do TCLE |
| `timestamps` | `timestamps()` | Not Null | `created_at` e `updated_at` |

---

### 2.5 Migração 5: `create_prontuarios_table`
- **Arquivo:** `database/migrations/2026_08_17_000005_create_prontuarios_table.php`
- **Tabela:** `prontuarios`
- **Propósito:** Registro Único do Egresso (Prontuário Social Eletrônico) na SEJUS.

| Coluna | Tipo | Modificadores / Restrições | Descrição |
|---|---|---|---|
| `id` | `id()` (BigIncrements) | Primary Key | Identificador do prontuário |
| `numero_prontuario` | `string('numero_prontuario', 30)`| Not Null, Unique, Index | Código oficial formatado (ex: `PRT-2026-000001`) |
| `egresso_id` | `foreignId('egresso_id')` | Not Null, Unique, Constrained('egressos')->cascadeOnDelete() | Egresso titular (relação 1:1 estrita) |
| `tecnico_responsavel_id`| `foreignId('tecnico_responsavel_id')`| Nullable, Constrained('users')->nullOnDelete() | Técnico/Assistente Social de referência |
| `situacao` | `string('situacao', 30)` | Not Null, Default `'ativo'`, Index | `ativo`, `em_acompanhamento`, `arquivado`, `desligado` |
| `resumo_diagnostico` | `text('resumo_diagnostico')` | Nullable | Síntese psicossocial de acolhimento |
| `meta_plano_individual` | `text('meta_plano_individual')`| Nullable | Objetivos do Plano Individual de Reintegração (PIR) |
| `data_abertura` | `timestamp('data_abertura')` | Not Null, Default `CURRENT_TIMESTAMP` | Data de inauguração do acompanhamento |
| `timestamps` | `timestamps()` | Not Null | `created_at` e `updated_at` |

---

### 2.6 Migração 6: `create_prontuario_timeline_table`
- **Arquivo:** `database/migrations/2026_08_17_000006_create_prontuario_timeline_table.php`
- **Tabela:** `prontuario_timeline`
- **Propósito:** Feed cronológico dos atendimentos, videochamadas, encaminhamentos e pareceres.

| Coluna | Tipo | Modificadores / Restrições | Descrição |
|---|---|---|---|
| `id` | `id()` (BigIncrements) | Primary Key | Identificador do evento |
| `prontuario_id` | `foreignId('prontuario_id')` | Not Null, Constrained('prontuarios')->cascadeOnDelete(), Index | Vínculo com o prontuário |
| `tipo_evento` | `string('tipo_evento', 50)` | Not Null, Index | `acolhimento_video`, `atendimento_presencial`, `encaminhamento_vaga`, `inscricao_curso`, `emissao_carteira`, `solicitacao_documento`, `parecer_tecnico` |
| `titulo` | `string('titulo', 150)` | Not Null | Título resumido da intervenção |
| `descricao` | `text('descricao')` | Not Null | Descrição do parecer técnico ou intervenção |
| `metadata` | `jsonb('metadata')` | Nullable | Metadados adicionais (duração da chamada, ID da vaga, protocolo de documento) |
| `responsavel_id` | `foreignId('responsavel_id')` | Not Null, Constrained('users')->restrictOnDelete(), Index | Usuário responsável pela inclusão do evento |
| `data_evento` | `timestamp('data_evento')` | Not Null, Default `CURRENT_TIMESTAMP`, Index | Data/hora de ocorrência do fato |
| `timestamps` | `timestamps()` | Not Null | `created_at` e `updated_at` |

---

### 2.7 Migração 7: `create_prontuario_audit_logs_table`
- **Arquivo:** `database/migrations/2026_08_17_000007_create_prontuario_audit_logs_table.php`
- **Tabela:** `prontuario_audit_logs`
- **Propósito:** Trilha de auditoria imutável com encadeamento de hash SHA-256 e proteção por regra de banco.

| Coluna | Tipo | Modificadores / Restrições | Descrição |
|---|---|---|---|
| `id` | `id()` (BigIncrements) | Primary Key | Identificador sequencial estrito |
| `prontuario_id` | `foreignId('prontuario_id')` | Nullable, Constrained('prontuarios')->cascadeOnDelete(), Index | Prontuário afetado |
| `user_id` | `foreignId('user_id')` | Nullable, Constrained('users')->setNullOnDelete(), Index | Usuário executor da ação |
| `acao` | `string('acao', 50)` | Not Null, Index | `VIEW`, `CREATE`, `UPDATE`, `EXPORT_PDF`, `ANONYMIZE`, `UNAUTHORIZED_ACCESS` |
| `ip_address` | `string('ip_address', 45)` | Not Null | Endereço IP IPv4 ou IPv6 do cliente |
| `user_agent` | `text('user_agent')` | Nullable | Navegador e sistema operacional |
| `previous_hash` | `string('previous_hash', 64)` | Not Null | Hash SHA-256 do registro de auditoria anterior |
| `current_hash` | `string('current_hash', 64)` | Not Null, Index | Hash SHA-256 deste registro (elo da corrente imutável) |
| `details` | `jsonb('details')` | Nullable | Snapshot de parâmetros e diff de alterações |
| `timestamp` | `timestamp('timestamp')` | Not Null, Default `CURRENT_TIMESTAMP`, Index | Carimbo de data/hora oficial do servidor |

#### Regras de Banco de Dados para Imutabilidade (PostgreSQL):
```sql
-- Impede qualquer alteração de registros existentes
CREATE RULE prontuario_audit_logs_no_update AS 
    ON UPDATE TO prontuario_audit_logs 
    DO INSTEAD NOTHING;

-- Impede qualquer exclusão de registros existentes
CREATE RULE prontuario_audit_logs_no_delete AS 
    ON DELETE TO prontuario_audit_logs 
    DO INSTEAD NOTHING;
```

---

### 2.8 Migração 8: `create_video_rooms_table`
- **Arquivo:** `database/migrations/2026_08_17_000008_create_video_rooms_table.php`
- **Tabela:** `video_rooms`
- **Propósito:** Gestão de salas de atendimento remoto e sinalização WebRTC.

| Coluna | Tipo | Modificadores / Restrições | Descrição |
|---|---|---|---|
| `id` | `id()` (BigIncrements) | Primary Key | Identificador interno da sala |
| `room_code` | `string('room_code', 64)` | Not Null, Unique, Index | Código único da sala (ex: `ATD-VIT-2026-0012`) |
| `prontuario_id` | `foreignId('prontuario_id')` | Nullable, Constrained('prontuarios')->nullOnDelete() | Prontuário em atendimento |
| `tecnico_id` | `foreignId('tecnico_id')` | Nullable, Constrained('users')->nullOnDelete(), Index | Técnico anfitrião da sala |
| `egresso_id` | `foreignId('egresso_id')` | Nullable, Constrained('egressos')->nullOnDelete(), Index | Egresso participante |
| `municipio_id` | `foreignId('municipio_id')` | Nullable, Constrained('municipios_es')->nullOnDelete() | Município de origem do atendimento |
| `status` | `string('status', 30)` | Not Null, Default `'aguardando'`, Index | `aguardando`, `em_andamento`, `encerrada`, `cancelada` |
| `prioridade` | `string('prioridade', 20)` | Not Null, Default `'normal'` | `normal`, `preferencial`, `urgente` |
| `motivo_atendimento`| `string('motivo_atendimento', 150)`| Nullable | Finalidade da chamada (Acolhimento, Emprego, etc.) |
| `scheduled_at` | `timestamp('scheduled_at')` | Nullable, Index | Horário agendado |
| `started_at` | `timestamp('started_at')` | Nullable | Horário efetivo de início da transmissão |
| `ended_at` | `timestamp('ended_at')` | Nullable | Horário de término da chamada |
| `token_sala` | `text('token_sala')` | Nullable | Token JWT assinado para autenticação WebSockets |
| `timestamps` | `timestamps()` | Not Null | `created_at` e `updated_at` |

---

### 2.9 Migração 9: `create_video_attendees_table`
- **Arquivo:** `database/migrations/2026_08_17_000009_create_video_attendees_table.php`
- **Tabela:** `video_attendees`
- **Propósito:** Registro de participantes e telemetria de qualidade (MOS, perda de pacotes, jitter, latência).

| Coluna | Tipo | Modificadores / Restrições | Descrição |
|---|---|---|---|
| `id` | `id()` (BigIncrements) | Primary Key | Identificador da participação |
| `video_room_id` | `foreignId('video_room_id')` | Not Null, Constrained('video_rooms')->cascadeOnDelete(), Index | Sala vinculada |
| `user_id` | `foreignId('user_id')` | Nullable, Constrained('users')->nullOnDelete(), Index | Usuário participante (se autenticado) |
| `peer_id` | `string('peer_id', 64)` | Nullable | Identificador WebRTC do peer |
| `role` | `string('role', 30)` | Not Null | `tecnico`, `egresso`, `familiar`, `observador` |
| `joined_at` | `timestamp('joined_at')` | Not Null, Default `CURRENT_TIMESTAMP` | Horário de entrada |
| `left_at` | `timestamp('left_at')` | Nullable | Horário de saída |
| `duration_seconds` | `integer('duration_seconds')` | Nullable | Duração da participação em segundos |
| `mos_score` | `decimal('mos_score', 4, 2)` | Nullable | Mean Opinion Score calculado (1.00 a 5.00) |
| `packet_loss` | `decimal('packet_loss', 5, 2)`| Nullable | Percentual de perda de pacotes |
| `jitter` | `decimal('jitter', 6, 2)` | Nullable | Variação de atraso (Jitter) em milissegundos |
| `rtt_ms` | `decimal('rtt_ms', 6, 2)` | Nullable | Round Trip Time em milissegundos |
| `telemetry_data` | `jsonb('telemetry_data')` | Nullable | Payload completo de telemetria RTCP recebido do micro |
| `timestamps` | `timestamps()` | Not Null | `created_at` e `updated_at` |

---

### 2.10 Migração 10: `create_vagas_emprego_table`
- **Arquivo:** `database/migrations/2026_08_17_000010_create_vagas_emprego_table.php`
- **Tabela:** `vagas_emprego`
- **Propósito:** Painel de oportunidades de trabalho inclusivas e vagas afirmativas no ES.

| Coluna | Tipo | Modificadores / Restrições | Descrição |
|---|---|---|---|
| `id` | `id()` (BigIncrements) | Primary Key | Identificador da oportunidade |
| `empresa` | `string('empresa', 150)` | Not Null, Index | Nome da empresa empregadora conveniada |
| `titulo` | `string('titulo', 150)` | Not Null, Index | Cargo ou título da vaga |
| `descricao` | `text('descricao')` | Not Null | Atividades e requisitos do cargo |
| `categoria` | `string('categoria', 50)` | Not Null, Index | `logistica`, `construcao_civil`, `agropecuaria`, `servicos`, `industria`, `comercio` |
| `municipio_id` | `foreignId('municipio_id')` | Not Null, Constrained('municipios_es')->restrictOnDelete(), Index | Município de lotação da vaga |
| `salario` | `decimal('salario', 10, 2)` | Nullable | Remuneração mensal proposta em R$ |
| `regime_contratacao`| `string('regime_contratacao', 30)`| Not Null, Default `'CLT'` | `CLT`, `PJ`, `Temporario`, `Estagio` |
| `afirmativa_egresso`| `boolean('afirmativa_egresso')`| Default `true`, Index | Se a vaga é prioritária para egressos |
| `empresa_amiga_reintegracao`| `boolean('empresa_amiga_reintegracao')`| Default `true` | Selo oficial SEJUS Empresa Amiga |
| `escolaridade_minima`| `string('escolaridade_minima', 50)`| Not Null, Default `'sem_exigencia'` | Nível educacional mínimo exigido |
| `vagas_totais` | `integer('vagas_totais')` | Not Null, Default `1` | Quantidade total de postos disponíveis |
| `vagas_preenchidas`| `integer('vagas_preenchidas')`| Not Null, Default `0` | Quantidade de vagas já preenchidas |
| `status` | `string('status', 30)` | Not Null, Default `'aberta'`, Index | `aberta`, `preenchida`, `pausada`, `cancelada` |
| `beneficios` | `jsonb('beneficios')` | Nullable | Benefícios adicionais (VT, VR, Plano Saúde, etc.) |
| `timestamps` | `timestamps()` | Not Null | `created_at` e `updated_at` |

---

### 2.11 Migração 11: `create_cursos_capacitacao_table`
- **Arquivo:** `database/migrations/2026_08_17_000011_create_cursos_capacitacao_table.php`
- **Tabela:** `cursos_capacitacao`
- **Propósito:** Catálogo de cursos profissionalizantes e programas de qualificação parceiros.

| Coluna | Tipo | Modificadores / Restrições | Descrição |
|---|---|---|---|
| `id` | `id()` (BigIncrements) | Primary Key | Identificador do curso |
| `instituicao` | `string('instituicao', 150)` | Not Null, Index | Instituição parceira (SENAI, IFES, FAETEC, Findes, SEJUS) |
| `titulo` | `string('titulo', 150)` | Not Null, Index | Nome do curso |
| `descricao` | `text('descricao')` | Not Null | Conteúdo programático e competências |
| `categoria` | `string('categoria', 50)` | Not Null, Index | `industrial`, `tecnologia`, `gestao`, `servicos`, `artesanato` |
| `municipio_id` | `foreignId('municipio_id')` | Nullable, Constrained('municipios_es')->nullOnDelete(), Index | Município sede (nulo se 100% EAD) |
| `carga_horaria` | `integer('carga_horaria')` | Not Null | Duração total em horas |
| `modalidade` | `string('modalidade', 30)` | Not Null, Default `'presencial'` | `presencial`, `ead`, `hibrido` |
| `bolsa_auxilio` | `decimal('bolsa_auxilio', 10, 2)`| Nullable | Valor da bolsa auxílio em R$ (se houver) |
| `vagas_disponiveis`| `integer('vagas_disponiveis')`| Not Null, Default `0` | Total de vagas ofertadas |
| `status` | `string('status', 30)` | Not Null, Default `'aberto'`, Index | `aberto`, `em_andamento`, `encerrado`, `cancelado` |
| `link_inscricao` | `string('link_inscricao', 255)`| Nullable | Link externo ou protocolo |
| `timestamps` | `timestamps()` | Not Null | `created_at` e `updated_at` |

---

### 2.12 Migração 12: `create_rede_apoio_table`
- **Arquivo:** `database/migrations/2026_08_17_000012_create_rede_apoio_table.php`
- **Tabela:** `rede_apoio`
- **Propósito:** Equipamentos públicos socioassistenciais e de saúde distribuídos nos 78 municípios.

| Coluna | Tipo | Modificadores / Restrições | Descrição |
|---|---|---|---|
| `id` | `id()` (BigIncrements) | Primary Key | Identificador da unidade |
| `nome` | `string('nome', 150)` | Not Null, Index | Nome da unidade (ex: "CRAS Central Vitória", "SINE Linhares") |
| `tipo` | `string('tipo', 30)` | Not Null, Index | `CRAS`, `CREAS`, `SINE`, `CAPS`, `CASA_CIDADAO`, `DEFENSORIA` |
| `municipio_id` | `foreignId('municipio_id')` | Not Null, Constrained('municipios_es')->cascadeOnDelete(), Index | Município capixaba |
| `endereco` | `string('endereco', 255)` | Not Null | Logradouro, número e bairro |
| `telefone` | `string('telefone', 50)` | Nullable | Telefone de contato institucional |
| `email` | `string('email', 150)` | Nullable | E-mail de atendimento |
| `horario_funcionamento`| `string('horario_funcionamento', 100)`| Nullable | Ex: "Segunda a Sexta, 08h às 17h" |
| `servicos_oferecidos`| `jsonb('servicos_oferecidos')`| Nullable | Array de serviços (Bolsa Capixaba, Emissão RG, etc.) |
| `latitude` | `decimal('latitude', 10, 7)` | Nullable | Latitude para cálculo de rota mais próxima |
| `longitude` | `decimal('longitude', 10, 7)`| Nullable | Longitude para cálculo de rota mais próxima |
| `ativo` | `boolean('ativo')` | Default `true`, Index | Se a unidade está em operação ativa |
| `timestamps` | `timestamps()` | Not Null | `created_at` e `updated_at` |

---

## 3. Especificação dos 12 Modelos Eloquent

### 3.1 Modelo `Perfil` (`app/Models/Perfil.php`)
- **Tabela:** `perfis`
- **Fillable:** `['nome', 'slug', 'descricao', 'permissoes', 'ativo']`
- **Casts:** `['permissoes' => 'array', 'ativo' => 'boolean']`
- **Relacionamentos:**
  - `users(): HasMany<User>` -> `$this->hasMany(User::class, 'perfil_id')`
- **Escopos (Scopes):**
  - `scopeGestores($query)`: `$query->where('slug', 'gestor')`
  - `scopeTecnicos($query)`: `$query->where('slug', 'tecnico')`
  - `scopeEgressos($query)`: `$query->where('slug', 'egresso')`
  - `scopeAtivos($query)`: `$query->where('ativo', true)`

### 3.2 Modelo `MunicipioEs` (`app/Models/MunicipioEs.php`)
- **Tabela:** `municipios_es`
- **Fillable:** `['codigo_ibge', 'nome', 'microrregiao', 'macrorregiao', 'latitude', 'longitude', 'tem_escritorio_fisico', 'populacao_estimada', 'total_egressos_atendidos']`
- **Casts:** `['latitude' => 'float', 'longitude' => 'float', 'tem_escritorio_fisico' => 'boolean', 'populacao_estimada' => 'integer', 'total_egressos_atendidos' => 'integer']`
- **Relacionamentos:**
  - `egressos(): HasMany<Egresso>` -> `$this->hasMany(Egresso::class, 'municipio_residencia_id')`
  - `vagas(): HasMany<VagaEmprego>` -> `$this->hasMany(VagaEmprego::class, 'municipio_id')`
  - `cursos(): HasMany<CursoCapacitacao>` -> `$this->hasMany(CursoCapacitacao::class, 'municipio_id')`
  - `redeApoio(): HasMany<RedeApoio>` -> `$this->hasMany(RedeApoio::class, 'municipio_id')`
  - `videoRooms(): HasMany<VideoRoom>` -> `$this->hasMany(VideoRoom::class, 'municipio_id')`
- **Escopos (Scopes):**
  - `scopeComEscritorioFisico($query)`: `$query->where('tem_escritorio_fisico', true)`
  - `scopeRemotos($query)`: `$query->where('tem_escritorio_fisico', false)`
  - `scopePorMicrorregiao($query, string $microrregiao)`: `$query->where('microrregiao', $microrregiao)`
  - `scopePorMacrorregiao($query, string $macrorregiao)`: `$query->where('macrorregiao', $macrorregiao)`

### 3.3 Modelo `User` (`app/Models/User.php`)
- **Tabela:** `users`
- **Fillable:** `['perfil_id', 'name', 'email', 'password', 'govbr_id', 'cpf_encrypted', 'hash_cpf', 'telefone_encrypted', 'foto_url', 'ativo']`
- **Hidden:** `['password', 'remember_token', 'cpf_encrypted', 'telefone_encrypted']`
- **Casts:** `['email_verified_at' => 'datetime', 'password' => 'hashed', 'ativo' => 'boolean']`
- **Relacionamentos:**
  - `perfil(): BelongsTo<Perfil, User>` -> `$this->belongsTo(Perfil::class, 'perfil_id')`
  - `egresso(): HasOne<Egresso>` -> `$this->hasOne(Egresso::class, 'user_id')`
  - `prontuariosComoTecnico(): HasMany<Prontuario>` -> `$this->hasMany(Prontuario::class, 'tecnico_responsavel_id')`
  - `timelineEventos(): HasMany<ProntuarioTimeline>` -> `$this->hasMany(ProntuarioTimeline::class, 'responsavel_id')`
  - `auditLogs(): HasMany<ProntuarioAuditLog>` -> `$this->hasMany(ProntuarioAuditLog::class, 'user_id')`
  - `videoRoomsComoTecnico(): HasMany<VideoRoom>` -> `$this->hasMany(VideoRoom::class, 'tecnico_id')`
  - `participacoesVideo(): HasMany<VideoAttendee>` -> `$this->hasMany(VideoAttendee::class, 'user_id')`
- **Métodos Auxiliares:**
  - `isGestor(): bool` -> `$this->perfil?->slug === 'gestor'`
  - `isTecnico(): bool` -> `$this->perfil?->slug === 'tecnico'`
  - `isEgresso(): bool` -> `$this->perfil?->slug === 'egresso'`
  - `getCpfAttribute(): ?string` -> descriptografa via `LgpdSecurityService`
  - `setCpfAttribute(?string $value): void` -> calcula `hash_cpf` e criptografa `cpf_encrypted`

### 3.4 Modelo `Egresso` (`app/Models/Egresso.php`)
- **Tabela:** `egressos`
- **Fillable:** `['user_id', 'nome_completo', 'nome_social', 'data_nascimento', 'cpf_encrypted', 'hash_cpf', 'rg_encrypted', 'filiacao_mae_encrypted', 'municipio_residencia_id', 'endereco_encrypted', 'telefone_encrypted', 'escolaridade', 'status_penal', 'unidade_prisional_origem', 'numero_processo_execucao', 'vulnerabilidades', 'consentimento_geolocalizacao', 'consentimento_compartilhamento', 'termo_aceito_em']`
- **Hidden:** `['cpf_encrypted', 'rg_encrypted', 'filiacao_mae_encrypted', 'endereco_encrypted', 'telefone_encrypted']`
- **Casts:** `['data_nascimento' => 'date', 'vulnerabilidades' => 'array', 'consentimento_geolocalizacao' => 'boolean', 'consentimento_compartilhamento' => 'boolean', 'termo_aceito_em' => 'datetime']`
- **Relacionamentos:**
  - `user(): BelongsTo<User, Egresso>` -> `$this->belongsTo(User::class, 'user_id')`
  - `municipio(): BelongsTo<MunicipioEs, Egresso>` -> `$this->belongsTo(MunicipioEs::class, 'municipio_residencia_id')`
  - `prontuario(): HasOne<Prontuario>` -> `$this->hasOne(Prontuario::class, 'egresso_id')`
  - `videoRooms(): HasMany<VideoRoom>` -> `$this->hasMany(VideoRoom::class, 'egresso_id')`
- **Escopos (Scopes):**
  - `scopePorMunicipio($query, int $municipioId)`: `$query->where('municipio_residencia_id', $municipioId)`
  - `scopePorStatusPenal($query, string $status)`: `$query->where('status_penal', $status)`
  - `scopeComConsentimento($query)`: `$query->where('consentimento_compartilhamento', true)`

### 3.5 Modelo `Prontuario` (`app/Models/Prontuario.php`)
- **Tabela:** `prontuarios`
- **Fillable:** `['numero_prontuario', 'egresso_id', 'tecnico_responsavel_id', 'situacao', 'resumo_diagnostico', 'meta_plano_individual', 'data_abertura']`
- **Casts:** `['data_abertura' => 'datetime']`
- **Relacionamentos:**
  - `egresso(): BelongsTo<Egresso, Prontuario>` -> `$this->belongsTo(Egresso::class, 'egresso_id')`
  - `tecnicoResponsavel(): BelongsTo<User, Prontuario>` -> `$this->belongsTo(User::class, 'tecnico_responsavel_id')`
  - `timeline(): HasMany<ProntuarioTimeline>` -> `$this->hasMany(ProntuarioTimeline::class, 'prontuario_id')->orderBy('data_evento', 'desc')`
  - `auditLogs(): HasMany<ProntuarioAuditLog>` -> `$this->hasMany(ProntuarioAuditLog::class, 'prontuario_id')->orderBy('id', 'desc')`
  - `videoRooms(): HasMany<VideoRoom>` -> `$this->hasMany(VideoRoom::class, 'prontuario_id')`
- **Escopos (Scopes):**
  - `scopeAtivos($query)`: `$query->where('situacao', 'ativo')`
  - `scopePorTecnico($query, int $tecnicoId)`: `$query->where('tecnico_responsavel_id', $tecnicoId)`

### 3.6 Modelo `ProntuarioTimeline` (`app/Models/ProntuarioTimeline.php`)
- **Tabela:** `prontuario_timeline`
- **Fillable:** `['prontuario_id', 'tipo_evento', 'titulo', 'descricao', 'metadata', 'responsavel_id', 'data_evento']`
- **Casts:** `['metadata' => 'array', 'data_evento' => 'datetime']`
- **Relacionamentos:**
  - `prontuario(): BelongsTo<Prontuario, ProntuarioTimeline>` -> `$this->belongsTo(Prontuario::class, 'prontuario_id')`
  - `responsavel(): BelongsTo<User, ProntuarioTimeline>` -> `$this->belongsTo(User::class, 'responsavel_id')`
- **Escopos (Scopes):**
  - `scopePorTipo($query, string $tipo)`: `$query->where('tipo_evento', $tipo)`
  - `scopeRecentes($query, int $limit = 10)`: `$query->orderBy('data_evento', 'desc')->limit($limit)`

### 3.7 Modelo `ProntuarioAuditLog` (`app/Models/ProntuarioAuditLog.php`)
- **Tabela:** `prontuario_audit_logs`
- **Fillable:** `['prontuario_id', 'user_id', 'acao', 'ip_address', 'user_agent', 'previous_hash', 'current_hash', 'details', 'timestamp']`
- **Casts:** `['details' => 'array', 'timestamp' => 'datetime']`
- **Timestamps:** `public $timestamps = false;` (utiliza campo `timestamp`)
- **Relacionamentos:**
  - `prontuario(): BelongsTo<Prontuario, ProntuarioAuditLog>` -> `$this->belongsTo(Prontuario::class, 'prontuario_id')`
  - `user(): BelongsTo<User, ProntuarioAuditLog>` -> `$this->belongsTo(User::class, 'user_id')`
- **Escopos (Scopes):**
  - `scopePorAcao($query, string $acao)`: `$query->where('acao', $acao)`
  - `scopePorUsuario($query, int $userId)`: `$query->where('user_id', $userId)`
  - `scopePorProntuario($query, int $prontuarioId)`: `$query->where('prontuario_id', $prontuarioId)`

### 3.8 Modelo `VideoRoom` (`app/Models/VideoRoom.php`)
- **Tabela:** `video_rooms`
- **Fillable:** `['room_code', 'prontuario_id', 'tecnico_id', 'egresso_id', 'municipio_id', 'status', 'prioridade', 'motivo_atendimento', 'scheduled_at', 'started_at', 'ended_at', 'token_sala']`
- **Casts:** `['scheduled_at' => 'datetime', 'started_at' => 'datetime', 'ended_at' => 'datetime']`
- **Relacionamentos:**
  - `prontuario(): BelongsTo<Prontuario, VideoRoom>` -> `$this->belongsTo(Prontuario::class, 'prontuario_id')`
  - `tecnico(): BelongsTo<User, VideoRoom>` -> `$this->belongsTo(User::class, 'tecnico_id')`
  - `egresso(): BelongsTo<Egresso, VideoRoom>` -> `$this->belongsTo(Egresso::class, 'egresso_id')`
  - `municipio(): BelongsTo<MunicipioEs, VideoRoom>` -> `$this->belongsTo(MunicipioEs::class, 'municipio_id')`
  - `attendees(): HasMany<VideoAttendee>` -> `$this->hasMany(VideoAttendee::class, 'video_room_id')`
- **Escopos (Scopes):**
  - `scopeAguardando($query)`: `$query->where('status', 'aguardando')`
  - `scopeEmAndamento($query)`: `$query->where('status', 'em_andamento')`
  - `scopeEncerradas($query)`: `$query->where('status', 'encerrada')`
  - `scopePorPrioridade($query, string $prioridade)`: `$query->where('prioridade', $prioridade)`

### 3.9 Modelo `VideoAttendee` (`app/Models/VideoAttendee.php`)
- **Tabela:** `video_attendees`
- **Fillable:** `['video_room_id', 'user_id', 'peer_id', 'role', 'joined_at', 'left_at', 'duration_seconds', 'mos_score', 'packet_loss', 'jitter', 'rtt_ms', 'telemetry_data']`
- **Casts:** `['joined_at' => 'datetime', 'left_at' => 'datetime', 'duration_seconds' => 'integer', 'mos_score' => 'float', 'packet_loss' => 'float', 'jitter' => 'float', 'rtt_ms' => 'float', 'telemetry_data' => 'array']`
- **Relacionamentos:**
  - `room(): BelongsTo<VideoRoom, VideoAttendee>` -> `$this->belongsTo(VideoRoom::class, 'video_room_id')`
  - `user(): BelongsTo<User, VideoAttendee>` -> `$this->belongsTo(User::class, 'user_id')`
- **Escopos (Scopes):**
  - `scopeTecnicos($query)`: `$query->where('role', 'tecnico')`
  - `scopeEgressos($query)`: `$query->where('role', 'egresso')`
  - `scopeQualidadeAlta($query)`: `$query->where('mos_score', '>=', 4.0)`

### 3.10 Modelo `VagaEmprego` (`app/Models/VagaEmprego.php`)
- **Tabela:** `vagas_emprego`
- **Fillable:** `['empresa', 'titulo', 'descricao', 'categoria', 'municipio_id', 'salario', 'regime_contratacao', 'afirmativa_egresso', 'empresa_amiga_reintegracao', 'escolaridade_minima', 'vagas_totais', 'vagas_preenchidas', 'status', 'beneficios']`
- **Casts:** `['salario' => 'decimal:2', 'afirmativa_egresso' => 'boolean', 'empresa_amiga_reintegracao' => 'boolean', 'vagas_totais' => 'integer', 'vagas_preenchidas' => 'integer', 'beneficios' => 'array']`
- **Relacionamentos:**
  - `municipio(): BelongsTo<MunicipioEs, VagaEmprego>` -> `$this->belongsTo(MunicipioEs::class, 'municipio_id')`
- **Escopos (Scopes):**
  - `scopeAbertas($query)`: `$query->where('status', 'aberta')`
  - `scopeAfirmativas($query)`: `$query->where('afirmativa_egresso', true)`
  - `scopePorMunicipio($query, int $municipioId)`: `$query->where('municipio_id', $municipioId)`
  - `scopePorCategoria($query, string $categoria)`: `$query->where('categoria', $categoria)`

### 3.11 Modelo `CursoCapacitacao` (`app/Models/CursoCapacitacao.php`)
- **Tabela:** `cursos_capacitacao`
- **Fillable:** `['instituicao', 'titulo', 'descricao', 'categoria', 'municipio_id', 'carga_horaria', 'modalidade', 'bolsa_auxilio', 'vagas_disponiveis', 'status', 'link_inscricao']`
- **Casts:** `['carga_horaria' => 'integer', 'bolsa_auxilio' => 'decimal:2', 'vagas_disponiveis' => 'integer']`
- **Relacionamentos:**
  - `municipio(): BelongsTo<MunicipioEs, CursoCapacitacao>` -> `$this->belongsTo(MunicipioEs::class, 'municipio_id')`
- **Escopos (Scopes):**
  - `scopeAbertos($query)`: `$query->where('status', 'aberto')`
  - `scopePorModalidade($query, string $modalidade)`: `$query->where('modalidade', $modalidade)`
  - `scopePorMunicipio($query, int $municipioId)`: `$query->where('municipio_id', $municipioId)`

### 3.12 Modelo `RedeApoio` (`app/Models/RedeApoio.php`)
- **Tabela:** `rede_apoio`
- **Fillable:** `['nome', 'tipo', 'municipio_id', 'endereco', 'telefone', 'email', 'horario_funcionamento', 'servicos_oferecidos', 'latitude', 'longitude', 'ativo']`
- **Casts:** `['latitude' => 'float', 'longitude' => 'float', 'servicos_oferecidos' => 'array', 'ativo' => 'boolean']`
- **Relacionamentos:**
  - `municipio(): BelongsTo<MunicipioEs, RedeApoio>` -> `$this->belongsTo(MunicipioEs::class, 'municipio_id')`
- **Escopos (Scopes):**
  - `scopeAtivos($query)`: `$query->where('ativo', true)`
  - `scopePorTipo($query, string $tipo)`: `$query->where('tipo', $tipo)`
  - `scopePorMunicipio($query, int $municipioId)`: `$query->where('municipio_id', $municipioId)`
  - `scopeCras($query)`: `$query->where('tipo', 'CRAS')`
  - `scopeCreas($query)`: `$query->where('tipo', 'CREAS')`
  - `scopeSine($query)`: `$query->where('tipo', 'SINE')`
  - `scopeCaps($query)`: `$query->where('tipo', 'CAPS')`

---

## 4. Base Completa dos 78 Municípios do Espírito Santo

Tabela oficial com os 78 municípios do Estado do Espírito Santo (Código UF: 32), coordenadas oficiais da sede municipal, microrregião (IJSN) e distinção entre Escritório Social Físico e Atendimento Remoto Conecta Egresso:

| # | Código IBGE | Nome do Município | Microrregião (IJSN) | Macrorregião | Latitude | Longitude | Escritório Físico |
|---|---|---|---|---|---|---|---|
| 1 | 3200102 | Afonso Cláudio | Central Serrana | Central | -20.0778 | -41.1444 | Não (Remoto) |
| 2 | 3200169 | Água Doce do Norte | Noroeste | Norte | -18.5472 | -40.9858 | Não (Remoto) |
| 3 | 3200136 | Águia Branca | Centro-Oeste | Central | -18.9839 | -40.7408 | Não (Remoto) |
| 4 | 3200201 | Alegre | Caparaó | Sul | -20.7631 | -41.5331 | Não (Remoto) |
| 5 | 3200300 | Alfredo Chaves | Sudoeste Serrana | Sul | -20.6358 | -40.7519 | Não (Remoto) |
| 6 | 3200359 | Alto Rio Novo | Noroeste | Central | -19.0583 | -41.0189 | Não (Remoto) |
| 7 | 3200409 | Anchieta | Litoral Sul | Sul | -20.8058 | -40.6450 | Não (Remoto) |
| 8 | 3200508 | Apiacá | Caparaó | Sul | -21.1542 | -41.5678 | Não (Remoto) |
| 9 | 3200607 | Aracruz | Rio Doce | Norte | -19.8203 | -40.2733 | Não (Remoto) |
| 10 | 3200706 | Atílio Vivácqua | Central Sul | Sul | -20.9150 | -41.1983 | Não (Remoto) |
| 11 | 3200805 | Baixo Guandu | Central Oeste | Central | -19.5189 | -41.0147 | Não (Remoto) |
| 12 | 3200904 | Barra de São Francisco | Noroeste | Norte | -18.7547 | -40.8906 | Não (Remoto) |
| 13 | 3201001 | Boa Esperança | Nordeste | Norte | -18.5400 | -40.2947 | Não (Remoto) |
| 14 | 3201100 | Bom Jesus do Norte | Caparaó | Sul | -21.1106 | -41.6706 | Não (Remoto) |
| 15 | 3201159 | Brejetuba | Central Serrana | Central | -20.1447 | -41.2917 | Não (Remoto) |
| 16 | 3201209 | Cachoeiro de Itapemirim | Central Sul | Sul | -20.8489 | -41.1128 | Não (Remoto) |
| 17 | 3201308 | Cariacica | Metropolitana | Metropolitana | -20.2639 | -40.4200 | **Sim (Físico)** |
| 18 | 3201407 | Castelo | Central Sul | Sul | -20.6036 | -41.2033 | Não (Remoto) |
| 19 | 3201506 | Colatina | Central Oeste | Central | -19.5392 | -40.6300 | Não (Remoto) |
| 20 | 3201605 | Conceição da Barra | Nordeste | Norte | -18.5933 | -39.7322 | Não (Remoto) |
| 21 | 3201704 | Conceição do Castelo | Central Serrana | Central | -20.3686 | -41.2439 | Não (Remoto) |
| 22 | 3201803 | Divino de São Lourenço | Caparaó | Sul | -20.6200 | -41.6858 | Não (Remoto) |
| 23 | 3201902 | Domingos Martins | Sudoeste Serrana | Central | -20.3633 | -40.6589 | Não (Remoto) |
| 24 | 3202009 | Dores do Rio Preto | Caparaó | Sul | -20.6897 | -41.8447 | Não (Remoto) |
| 25 | 3202108 | Ecoporanga | Noroeste | Norte | -18.3733 | -40.8306 | Não (Remoto) |
| 26 | 3202207 | Fundão | Metropolitana | Metropolitana | -19.9333 | -40.4058 | Não (Remoto) |
| 27 | 3202256 | Governador Lindenberg | Central Oeste | Central | -19.2558 | -40.4789 | Não (Remoto) |
| 28 | 3202306 | Guaçuí | Caparaó | Sul | -20.7758 | -41.6792 | Não (Remoto) |
| 29 | 3202405 | Guarapari | Metropolitana | Metropolitana | -20.6708 | -40.4981 | Não (Remoto) |
| 30 | 3202454 | Ibatiba | Caparaó | Sul | -20.2336 | -41.5108 | Não (Remoto) |
| 31 | 3202504 | Ibiraçu | Rio Doce | Norte | -19.8319 | -40.3700 | Não (Remoto) |
| 32 | 3202553 | Ibitirama | Caparaó | Sul | -20.5408 | -41.6669 | Não (Remoto) |
| 33 | 3202603 | Iconha | Litoral Sul | Sul | -20.7931 | -40.8106 | Não (Remoto) |
| 34 | 3202652 | Irupi | Caparaó | Sul | -20.3458 | -41.6419 | Não (Remoto) |
| 35 | 3202702 | Itaguaçu | Central Serrana | Central | -19.8028 | -40.8567 | Não (Remoto) |
| 36 | 3202801 | Itapemirim | Litoral Sul | Sul | -20.9997 | -40.8336 | Não (Remoto) |
| 37 | 3202900 | Itarana | Central Serrana | Central | -19.8739 | -40.8756 | Não (Remoto) |
| 38 | 3203007 | Iúna | Caparaó | Sul | -20.3458 | -41.5358 | Não (Remoto) |
| 39 | 3203056 | Jaguaré | Nordeste | Norte | -18.9069 | -40.0761 | Não (Remoto) |
| 40 | 3203106 | Jerônimo Monteiro | Central Sul | Sul | -20.7906 | -41.3961 | Não (Remoto) |
| 41 | 3203130 | João Neiva | Rio Doce | Norte | -19.7547 | -40.3839 | Não (Remoto) |
| 42 | 3203163 | Laranja da Terra | Central Serrana | Central | -19.8986 | -41.0558 | Não (Remoto) |
| 43 | 3203205 | Linhares | Rio Doce | Norte | -19.3964 | -40.0644 | Não (Remoto) |
| 44 | 3203304 | Mantenópolis | Noroeste | Norte | -18.8622 | -41.1228 | Não (Remoto) |
| 45 | 3203320 | Marataízes | Litoral Sul | Sul | -21.0433 | -40.8244 | Não (Remoto) |
| 46 | 3203346 | Marechal Floriano | Sudoeste Serrana | Central | -20.4128 | -40.6831 | Não (Remoto) |
| 47 | 3203353 | Marilândia | Central Oeste | Central | -19.4131 | -40.5414 | Não (Remoto) |
| 48 | 3203403 | Mimoso do Sul | Central Sul | Sul | -21.0644 | -41.3658 | Não (Remoto) |
| 49 | 3203502 | Montanha | Nordeste | Norte | -18.1269 | -40.3633 | Não (Remoto) |
| 50 | 3203601 | Mucurici | Nordeste | Norte | -18.0933 | -40.5158 | Não (Remoto) |
| 51 | 3203700 | Muniz Freire | Caparaó | Sul | -20.4647 | -41.4131 | Não (Remoto) |
| 52 | 3203809 | Muqui | Central Sul | Sul | -20.9525 | -41.3458 | Não (Remoto) |
| 53 | 3203908 | Nova Venécia | Noroeste | Norte | -18.7106 | -40.4006 | Não (Remoto) |
| 54 | 3204005 | Pancas | Central Oeste | Central | -19.2247 | -40.8514 | Não (Remoto) |
| 55 | 3204054 | Pedro Canário | Nordeste | Norte | -18.0286 | -40.1486 | Não (Remoto) |
| 56 | 3204104 | Pinheiros | Nordeste | Norte | -18.4239 | -40.2189 | Não (Remoto) |
| 57 | 3204203 | Piúma | Litoral Sul | Sul | -20.8358 | -40.7289 | Não (Remoto) |
| 58 | 3204252 | Ponto Belo | Nordeste | Norte | -18.1247 | -40.5369 | Não (Remoto) |
| 59 | 3204302 | Presidente Kennedy | Litoral Sul | Sul | -21.0967 | -41.0478 | Não (Remoto) |
| 60 | 3204351 | Rio Bananal | Rio Doce | Norte | -19.2650 | -40.3333 | Não (Remoto) |
| 61 | 3204401 | Rio Novo do Sul | Litoral Sul | Sul | -20.8589 | -40.9367 | Não (Remoto) |
| 62 | 3204500 | Santa Leopoldina | Central Serrana | Central | -20.1006 | -40.5297 | Não (Remoto) |
| 63 | 3204559 | Santa Maria de Jetibá | Central Serrana | Central | -20.0406 | -40.7461 | Não (Remoto) |
| 64 | 3204609 | Santa Teresa | Central Serrana | Central | -19.9367 | -40.6006 | Não (Remoto) |
| 65 | 3204658 | São Domingos do Norte | Central Oeste | Central | -19.1417 | -40.5239 | Não (Remoto) |
| 66 | 3204708 | São Gabriel da Palha | Noroeste | Norte | -19.0169 | -40.5361 | Não (Remoto) |
| 67 | 3204807 | São José do Calçado | Caparaó | Sul | -20.9806 | -41.6547 | Não (Remoto) |
| 68 | 3204906 | São Mateus | Nordeste | Norte | -18.7161 | -39.8589 | Não (Remoto) |
| 69 | 3204955 | São Roque do Canaã | Central Serrana | Central | -19.7389 | -40.6558 | Não (Remoto) |
| 70 | 3205002 | Serra | Metropolitana | Metropolitana | -20.1286 | -40.3078 | **Sim (Físico)** |
| 71 | 3205010 | Sooretama | Rio Doce | Norte | -19.1969 | -40.0906 | Não (Remoto) |
| 72 | 3205036 | Vargem Alta | Central Sul | Sul | -20.6722 | -41.0078 | Não (Remoto) |
| 73 | 3205069 | Venda Nova do Imigrante | Sudoeste Serrana | Central | -20.3267 | -41.1344 | Não (Remoto) |
| 74 | 3205101 | Viana | Metropolitana | Metropolitana | -20.3906 | -40.4958 | Não (Remoto) |
| 75 | 3205150 | Vila Pavão | Noroeste | Norte | -18.6147 | -40.6094 | Não (Remoto) |
| 76 | 3205176 | Vila Valério | Noroeste | Norte | -18.9989 | -40.3889 | Não (Remoto) |
| 77 | 3205200 | Vila Velha | Metropolitana | Metropolitana | -20.3297 | -40.2925 | **Sim (Físico)** |
| 78 | 3205309 | Vitória | Metropolitana | Metropolitana | -20.3155 | -40.3128 | **Sim (Físico)** |

---

## 5. Serviços Criptográficos e de Segurança (Core Services)

### 5.1 `LgpdSecurityService` (`app/Services/LgpdSecurityService.php`)
- **Objetivo:** Garantir a conformidade estrita com o Art. 6º da LGPD (minimização e proteção criptográfica).
- **Funções:**
  1. `calculateBlindIndex(string $cpf): string`: Normaliza o CPF removendo caracteres não numéricos e gera o hash determinístico `hash_hmac('sha256', $cpfClean, config('app.lgpd_pepper'))`.
  2. `encryptField(?string $value): ?string`: Cifra via AES-256 (`Crypt::encryptString($value)`).
  3. `decryptField(?string $cipher): ?string`: Decifra de forma segura (`Crypt::decryptString($cipher)`).
  4. `maskCpf(string $cpf): string`: Retorna no formato `***.XXX.XXX-**`.

### 5.2 `AuditService` (`app/Services/AuditService.php`)
- **Objetivo:** Registro automático e verificação da corrente imutável de auditoria.
- **Funções:**
  1. `log(int|null $prontuarioId, string $acao, array $details = []): ProntuarioAuditLog`:
     - Obtém o último registro da tabela `prontuario_audit_logs`.
     - Se for o primeiro registro (gênese), `previous_hash = str_repeat('0', 64)`. Caso contrário, utiliza o `current_hash` do último registro.
     - Monta o payload canônico: `$previousHash . '|' . $prontuarioId . '|' . $userId . '|' . $acao . '|' . $ip . '|' . $timestamp . '|' . json_encode($details)`.
     - Calcula `current_hash = hash('sha256', $payload)`.
     - Persiste o registro.
  2. `verifyChainIntegrity(int|null $prontuarioId = null): array`:
     - Percorre os registros em ordem sequencial (`id ASC`), recalculando o hash de cada elo.
     - Retorna `['valid' => true, 'checked_count' => N]` ou aponta a quebra na corrente com ID adulterado.

### 5.3 `CarteiraPdfService` (`app/Services/CarteiraPdfService.php`)
- **Objetivo:** Geração do PDF oficial da Carteira Digital do Egresso utilizando `dompdf/dompdf`.
- **Elementos Visuais:**
  - Brasão Oficial do Estado do Espírito Santo (SVG/PNG base64).
  - Título Institucional: *"GOVERNO DO ESTADO DO ESPÍRITO SANTO • SEJUS / ESCRITÓRIO SOCIAL"*.
  - Foto do Egresso (ou placeholder institucional) com selo *"✓ Verificado"*.
  - Dados do Atendido: Nome Completo, CPF Mascarado, Número de Registro SEJUS (`ES-2026-XXXXXX`), Município de Residência, Data de Emissão, Data de Validade (1 ano).
  - QR Code de Validação Integrado no canto inferior direito.
  - Carimbo de Segurança e Amparo Legal: *"Validade em todo o Território Capixaba — Lei Complementar nº 182/2021"*.

### 5.4 `QrCodeSecurityService` (`app/Services/QrCodeSecurityService.php`)
- **Objetivo:** Geração e validação de QR Codes assinados com HMAC-SHA256.
- **Funções:**
  1. `generatePayload(Egresso $egresso, string $docId): array`:
     - Monta o payload canônico com `doc_id`, `registro_sejus`, `cpf_masked`, `nome`, `municipio`, `emitido_em`, `valido_ate`.
  2. `signPayload(array $payload): string`:
     - Gera a assinatura `hash_hmac('sha256', json_encode($payload), config('app.carteira_signing_key'))`.
  3. `generateQrCodeImage(string $validationUrl): string`:
     - Renderiza o QR Code em formato SVG ou PNG Base64 via `simplesoftwareio/simple-qrcode` ou `bacon/bacon-qr-code`.
  4. `verifySignature(array $payload, string $signature): bool`:
     - Verifica se `hash_equals(signPayload($payload), $signature)` é verdadeiro e confere se `valido_ate >= now()`.

---

## 6. Seeders do Banco de Dados

### 6.1 `MunicipiosEsSeeder`
- Insere exaustivamente os 78 municípios da Seção 4 com os códigos IBGE, coordenadas e flags de escritório físico.

### 6.2 `PerfisSeeder`
- Insere os 4 perfis fundamentais:
  1. `Gestor SEJUS` (`slug: gestor`)
  2. `Técnico Escritório Social` (`slug: tecnico`)
  3. `Egresso` (`slug: egresso`)
  4. `Familiar` (`slug: familiar`)

### 6.3 `UsersSeeder`
- Cria os usuários demonstrativos do protótipo com senhas hash bcrypt:
  1. `carlos.silva@sejus.es.gov.br` (Gestor SEJUS)
  2. `marcia.oliveira@social.es.gov.br` (Dra. Márcia Oliveira - Assistente Social / Técnico)
  3. `lucas.santos@cidadao.es.gov.br` (Lucas Santos - Egresso)
  4. `roberto.fonseca@cidadao.es.gov.br` (Roberto Fonseca - Egresso de São Mateus)

### 6.4 `EgressosSeeder` & `ProntuariosSeeder`
- Cria registros de egressos completos com dados cifrados em AES-256 e `hash_cpf`, associando prontuários com numeração `PRT-2026-000001` e `PRT-2026-000002`.
- Insere eventos históricos na `prontuario_timeline` (acolhimentos, emissões de carteira e encaminhamentos).

### 6.5 `OportunidadesSeeder`
- Insere as vagas e cursos mapeados no protótipo validado (`index.html`):
  - *Auxiliar de Logística e Carga* (Porto de Tubarão / Vitória)
  - *Capacitação em Solda Industrial* (SENAI/Findes / Linhares)
  - *Operador de Máquinas Agrícolas* (Cooperativa Agropecuária / Colatina)
  - *Letramento Digital & Informática Básica* (IFES / EAD)
  - *Oficial de Construção Civil* (Construtora Capixaba / Vila Velha)
  - *Empreendedorismo e Microcrédito NossoCrédito* (ADERES / Remoto ES)

### 6.6 `RedeApoioSeeder`
- Insere equipamentos públicos essenciais (CRAS, CREAS, SINE, CAPS, Casa do Cidadão) nos polos regionais (Vitória, Serra, Vila Velha, Cariacica, Linhares, Cachoeiro, Colatina, São Mateus).

### 6.7 `DatabaseSeeder`
- Orquestra a execução ordenada de todos os seeders acima.

---

## 7. Grafo de Dependências e Ordem de Migrações

```
[1. perfis] ─────────────┐
                         ├──────► [3. users] ──────► [4. egressos] ──────► [5. prontuarios] ──┬──► [6. prontuario_timeline]
[2. municipios_es] ──────┴─────────────────────────────────▲                       ▲          ├──► [7. prontuario_audit_logs]
       │                                                   │                       │          └──► [8. video_rooms] ──► [9. video_attendees]
       ├───────────────────────────────────────────────────┴───────────────────────┘
       ├─────────────────────────────────────────► [10. vagas_emprego]
       ├─────────────────────────────────────────► [11. cursos_capacitacao]
       └─────────────────────────────────────────► [12. rede_apoio]
```

---

## 8. Verificação e Critérios de Aceite para Milestone M2

1. **Migrações:** Execução de `php artisan migrate:fresh --seed` sem erros, criando as 12 tabelas com índices, chaves estrangeiras e regras PostgreSQL.
2. **Imutabilidade Audit Log:** Tentativa de `UPDATE` ou `DELETE` em `prontuario_audit_logs` resulta em `INSTEAD NOTHING` (0 linhas afetadas, dados preservados).
3. **Criptografia LGPD:** Campos sensíveis (`cpf_encrypted`, `rg_encrypted`, `filiacao_mae_encrypted`, `endereco_encrypted`, `telefone_encrypted`) nunca são salvos em texto claro. O blind index `hash_cpf` permite consultas exatas `where('hash_cpf', $hash)`.
4. **Validação de Assinatura QR Code:** Documentos gerados passam na verificação criptográfica; qualquer bit alterado no payload resulta em falha de validação.
5. **Cobertura de Municípios:** Consulta `MunicipioEs::count()` retorna exatamente 78 registros, com 4 municípios marcados como escritório físico e 74 como remotos.
