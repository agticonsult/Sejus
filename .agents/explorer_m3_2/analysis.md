# Technical Analysis: M3 Backend Business APIs, RBAC & Analytics

**Explorer**: explorer_m3_2  
**Milestone**: M3 (Backend Business APIs, RBAC & Webhooks)  
**Date**: 2026-08-17  
**Scope**: Prontuário Único CRUD & Timeline, Vagas & Cursos, Territorial Mapping & Rede de Apoio (78 ES Municipalities), Management KPIs & Analytics.

---

## 1. Executive Summary

Milestone M2 established a robust database layer (12 PostgreSQL migrations, Eloquent models, 78 ES municipalities seeder, LGPD blind index hashing via HMAC-SHA256, AES-256 field encryption, cryptographic audit chain with PostgreSQL `RULE DO INSTEAD NOTHING`, and Dompdf Digital Wallet generation).

Milestone M3 requires implementing the RESTful business APIs that power the core frontend views and integrate with the Python WebRTC microservice. This technical analysis provides the complete architectural specifications, model interactions, endpoint contracts, input validation boundaries, RBAC matrix, and automated test strategies for:
1. **Prontuário Único & Timeline API** (`/api/prontuarios`, `/api/prontuarios/{id}/timeline`, `/api/prontuarios/{id}/evolucao`)
2. **Vagas de Emprego & Cursos de Capacitação APIs** (`/api/vagas`, `/api/cursos`, `/api/candidaturas`)
3. **Territorial Mapping & Rede de Apoio APIs** (`/api/territorios`, `/api/rede-apoio`)
4. **Management KPIs & Analytics APIs** (`/api/kpis/dashboard`, `/api/kpis/regional`, `/api/kpis/timeline`, `/api/kpis/telemetria`)

---

## 2. Existing Database & Model Inventory

| Model | Table | Key Fields & Casts | Relationships | Existing State |
|---|---|---|---|---|
| `Prontuario` | `prontuarios` | `id`, `numero_prontuario` (unique, e.g. `PRT-2026-000001`), `egresso_id` (foreign, unique), `tecnico_responsavel_id` (foreign), `situacao` (`ativo`, `em_acompanhamento`, `arquivado`, `desligado`), `resumo_diagnostico`, `meta_plano_individual`, `data_abertura` | `egresso()` (1:1), `tecnicoResponsavel()` (N:1), `timeline()` (1:N), `auditLogs()` (1:N), `videoRooms()` (1:N) | Fully defined in `app/Models/Prontuario.php` |
| `ProntuarioTimeline` | `prontuario_timeline` | `id`, `prontuario_id`, `tipo_evento` (string), `titulo`, `descricao`, `metadata` (JSON array), `responsavel_id`, `data_evento` (datetime) | `prontuario()` (N:1), `responsavel()` (N:1) | Fully defined in `app/Models/ProntuarioTimeline.php` |
| `ProntuarioAuditLog` | `prontuario_audit_logs` | `id`, `prontuario_id`, `user_id`, `acao`, `ip_address`, `user_agent`, `previous_hash`, `current_hash`, `details` (JSON array), `timestamp` | `prontuario()` (N:1), `user()` (N:1) | Fully defined in `app/Models/ProntuarioAuditLog.php`, backed by `AuditService` |
| `Egresso` | `egressos` | `id`, `user_id`, `nome_completo`, `nome_social`, `data_nascimento`, `cpf_encrypted`, `hash_cpf`, `rg_encrypted`, `filiacao_mae_encrypted`, `municipio_residencia_id`, `endereco_encrypted`, `telefone_encrypted`, `escolaridade`, `status_penal`, `unidade_prisional_origem`, `numero_processo_execucao`, `vulnerabilidades` (JSON), `consentimento_geolocalizacao`, `consentimento_compartilhamento`, `termo_aceito_em` | `user()` (1:1), `municipio()` (N:1), `prontuario()` (1:1), `videoRooms()` (1:N) | Fully defined in `app/Models/Egresso.php` with automatic LGPD accessors/mutators |
| `VagaEmprego` | `vagas_emprego` | `id`, `empresa`, `titulo`, `descricao`, `categoria`, `municipio_id`, `salario` (decimal), `regime_contratacao`, `afirmativa_egresso` (bool), `empresa_amiga_reintegracao` (bool), `escolaridade_minima`, `vagas_totais` (int), `vagas_preenchidas` (int), `status` (`aberta`, `preenchida`, `pausada`, `cancelada`), `beneficios` (JSON) | `municipio()` (N:1) | Fully defined in `app/Models/VagaEmprego.php` |
| `CursoCapacitacao` | `cursos_capacitacao` | `id`, `instituicao`, `titulo`, `descricao`, `categoria`, `municipio_id` (nullable for EAD), `carga_horaria` (int), `modalidade` (`presencial`, `ead`, `hibrido`), `bolsa_auxilio` (decimal), `vagas_disponiveis` (int), `status` (`aberto`, `em_andamento`, `encerrado`, `cancelado`), `link_inscricao` | `municipio()` (N:1) | Fully defined in `app/Models/CursoCapacitacao.php` |
| `MunicipioEs` | `municipios_es` | `id`, `codigo_ibge` (unique 7 digits), `nome` (78 municipalities), `microrregiao`, `macrorregiao`, `latitude` (float), `longitude` (float), `tem_escritorio_fisico` (bool), `populacao_estimada`, `total_egressos_atendidos` | `egressos()`, `vagas()`, `cursos()`, `redeApoio()`, `videoRooms()` | Fully seeded with all 78 ES municipalities |
| `RedeApoio` | `rede_apoio` | `id`, `nome`, `tipo` (`CRAS`, `CREAS`, `SINE`, `CAPS`, `CASA_CIDADAO`, `DEFENSORIA`, `ESCRITORIO_SOCIAL`), `municipio_id`, `endereco`, `telefone`, `email`, `horario_funcionamento`, `servicos_oferecidos` (JSON), `latitude`, `longitude`, `ativo` (bool) | `municipio()` (N:1) | Fully defined in `app/Models/RedeApoio.php` |

---

## 3. Focus Area 1: Prontuário Único CRUD API & Timeline

### 3.1 Business Requirements & Security
- **LGPD Trilha de Auditoria Obrigatória**: Every read (`GET /api/prontuarios`, `GET /api/prontuarios/{id}`) and write (`POST`, `PUT`, `DELETE`, `POST timeline`) MUST invoke `AuditService::log()` to record the authenticated user ID, IP address, user-agent, action type (`VIEW`, `CREATE`, `UPDATE`, `DELETE`, `ADD_TIMELINE_EVENT`), and cryptographic SHA-256 hash chaining.
- **RBAC Policy**:
  - `gestor`: Full access (Read all, write, update, delete, view all audit logs).
  - `tecnico`: Full access to create/update prontuários and append evoluções/timeline entries.
  - `egresso`: Read-only access to their OWN prontuário (`/api/prontuarios/me` or `/api/prontuarios/{id}` matching their `egresso_id`). Forbidden (403) from writing evolution notes or accessing other egressos' records.

### 3.2 Endpoint Specifications

#### 1. `GET /api/prontuarios`
- **Query Parameters**:
  - `q`: Search string (searches `numero_prontuario`, egresso name, or CPF blind index hash `hash_cpf`).
  - `situacao`: Filter by status (`ativo`, `em_acompanhamento`, `arquivado`, `desligado`).
  - `tecnico_id`: Filter by responsible technician.
  - `municipio_id`: Filter by egresso's municipality.
  - `page`: Integer >= 1 (default 1).
  - `per_page`: Integer clamped between 1 and 100 (default 15).
- **Behavior**:
  - Eager loads `egresso:id,nome_completo,status_penal,municipio_residencia_id`, `tecnicoResponsavel:id,name,email`.
  - Automatically records audit log `AuditService::log(null, 'VIEW', ['query' => 'list_prontuarios', 'filters' => $filters])`.
- **Response**: Paginated JSON object with `data` and `meta` (current_page, per_page, total, last_page).

#### 2. `POST /api/prontuarios`
- **Payload**:
  ```json
  {
    "egresso_id": 8412,
    "tecnico_responsavel_id": 2,
    "situacao": "ativo",
    "resumo_diagnostico": "Acolhimento socioassistencial realizado...",
    "meta_plano_individual": "1. Qualificação profissional; 2. Emissão de carteira digital."
  }
  ```
- **Validation**:
  - `egresso_id`: required, integer, exists in `egressos,id`, unique in `prontuarios,egresso_id`.
  - `tecnico_responsavel_id`: nullable, integer, exists in `users,id` (defaults to `Auth::id()` if caller is técnico).
  - `situacao`: required, in `ativo,em_acompanhamento,arquivado,desligado`.
  - `resumo_diagnostico`: nullable, string, max 65536 bytes (64KB).
  - `meta_plano_individual`: nullable, string, max 65536 bytes (64KB).
- **Behavior**:
  - Generates unique `numero_prontuario` format: `PRT-2026-` + zero-padded 6 digits (`PRT-2026-000101`).
  - Sets `data_abertura = now()`.
  - Records audit log `AuditService::log($prontuario->id, 'CREATE', ['numero_prontuario' => $prontuario->numero_prontuario])`.
- **Response**: HTTP 201 Created with created resource.

#### 3. `GET /api/prontuarios/{id}`
- **Behavior**:
  - Finds by ID or `numero_prontuario`. Returns 404 if non-existent.
  - Eager loads `egresso.municipio`, `tecnicoResponsavel`, `timeline.responsavel`, `videoRooms`.
  - Checks authorization policy (Gestor / Técnico / Egresso owner).
  - Records audit log `AuditService::log($prontuario->id, 'VIEW', ['action' => 'single_prontuario_read'])`.
- **Response**: HTTP 200 OK with detailed record.

#### 4. `PUT /api/prontuarios/{id}`
- **Payload**: Editable fields (`situacao`, `resumo_diagnostico`, `meta_plano_individual`, `tecnico_responsavel_id`).
- **Validation**: Strict boundary validation (size <= 64KB, valid status values).
- **Behavior**:
  - Updates record.
  - Records audit log `AuditService::log($prontuario->id, 'UPDATE', ['updated_fields' => array_keys($validated)])`.
- **Response**: HTTP 200 OK with updated record.

#### 5. `DELETE /api/prontuarios/{id}`
- **Behavior**:
  - Restricted to Gestor role.
  - Updates `situacao = 'arquivado'` or performs soft deletion.
  - Records audit log `AuditService::log($prontuario->id, 'DELETE', ['status' => 'arquivado'])`.
- **Response**: HTTP 200 OK / 204 No Content.

#### 6. `GET /api/prontuarios/{id}/timeline`
- **Query Parameters**:
  - `tipo_evento`: Filter by event taxonomy.
  - `limit` / `per_page`: Pagination boundary.
- **Behavior**: Returns chronological list of events ordered by `data_evento desc`.

#### 7. `POST /api/prontuarios/{id}/timeline` and `POST /api/prontuarios/{id}/evolucao`
- **Payload**:
  ```json
  {
    "tipo_evento": "atendimento_remoto",
    "titulo": "Acolhimento Telepresencial",
    "descricao": "Atendimento psicossocial realizado com sucesso via WebRTC...",
    "metadata": {
      "duracao_segundos": 920,
      "qualidade_mos": 4.3,
      "sala_id": "sala-vitoria-101"
    },
    "data_evento": "2026-08-17T14:30:00Z"
  }
  ```
- **Validation & Boundaries**:
  - **Role Check**: Only `tecnico` and `gestor` roles allowed. Egressos receive HTTP 403 Forbidden.
  - **Prontuario Check**: Prontuario must exist, else HTTP 404.
  - **Description Check**: Non-empty, non-whitespace string required; else HTTP 422. Max size 64KB (65,536 bytes); else HTTP 413.
  - **Timestamp Check**: Must be valid ISO 8601 format; else HTTP 422. Defaults to `now()` if omitted.
  - **Taxonomy Validation**: `tipo_evento` must be one of:
    `acolhimento_video`, `atendimento_remoto`, `atendimento_presencial`, `encaminhamento_vaga`, `inscricao_curso`, `matricula_curso`, `emissao_carteira`, `emissao_documento`, `solicitacao_documento`, `parecer_tecnico`, `apoio_psicossocial`.
  - **Author Binding**: `responsavel_id` is strictly bound to `Auth::id()`. Any forged ID in payload is overwritten.
  - **XSS Sanitization**: HTML entity escaping (`htmlspecialchars` / `strip_tags`) before persistence.
- **Behavior**:
  - Inserts event into `prontuario_timeline`.
  - Records audit log `AuditService::log($prontuario->id, 'ADD_TIMELINE_EVENT', ['tipo_evento' => $tipo, 'event_id' => $event->id])`.
- **Response**: HTTP 201 Created.

---

## 4. Focus Area 2: Vagas de Emprego & Cursos de Capacitação APIs

### 4.1 Vagas de Emprego (`/api/vagas`)

#### Endpoints:
1. `GET /api/vagas`:
   - **Filters**:
     - `municipio` (name or IBGE code, accent-insensitive e.g. "Vitória", "vitoria", "Linhares").
     - `municipio_id` (integer).
     - `afirmativa_egresso` (boolean `true`/`1` or `false`/`0`).
     - `empresa_amiga` (boolean).
     - `categoria` (`logistica`, `construcao_civil`, `agropecuaria`, `servicos`, `industria`, `comercio`).
     - `salario_min` (numeric >= 0; negative values clamped to 0.0).
     - `status` (`aberta`, `preenchida`, `pausada`, `cancelada`; defaults to `aberta`).
     - `regime_contratacao` (`CLT`, `PJ`, `Temporario`, `Estagio`).
     - `escolaridade_minima` (`sem_exigencia`, `fundamental_incompleto`, `fundamental_completo`, `medio_completo`, `superior`).
     - `page`, `per_page` (clamped strictly 1..100 for DoS protection).
   - **Response**: Paginated list with eager-loaded `municipio:id,nome,codigo_ibge,macrorregiao`.

2. `GET /api/vagas/{id}`: Single vacancy details.

3. `POST /api/vagas`: Create vacancy (Gestor / Técnico).
   - Validation: `empresa` (req, max 150), `titulo` (req, max 150), `descricao` (req), `categoria` (req), `municipio_id` (req, exists `municipios_es,id`), `salario` (nullable, numeric >= 0), `vagas_totais` (int >= 1), `afirmativa_egresso` (bool), `beneficios` (array).

4. `PUT /api/vagas/{id}`: Update vacancy.

5. `DELETE /api/vagas/{id}`: Delete/close vacancy.

6. `POST /api/vagas/{id}/candidatar` & `POST /api/candidaturas`:
   - **Payload**: `{"egresso_id": 1, "vaga_id": 3, "observacoes": "Disponibilidade imediata"}`.
   - **Behavior**:
     - Verifies vacancy is `aberta` and has available spots (`vagas_preenchidas < vagas_totais`).
     - Finds egresso's `Prontuario`.
     - Automatically creates a timeline event on the Prontuário:
       - `tipo_evento = 'encaminhamento_vaga'`
       - `titulo = 'Candidatura / Encaminhamento para Vaga: ' . $vaga->titulo`
       - `descricao = 'Egresso encaminhado para a vaga de ' . $vaga->titulo . ' na empresa ' . $vaga->empresa . ' em ' . $vaga->municipio->nome`
       - `metadata = ['vaga_id' => $vaga->id, 'empresa' => $vaga->empresa, 'salario' => $vaga->salario]`
     - Records audit log.
   - **Response**: HTTP 201 Created.

### 4.2 Cursos de Capacitação (`/api/cursos`)

#### Endpoints:
1. `GET /api/cursos`:
   - **Filters**:
     - `municipio_id` / `municipio` (or `ead_only=true` for 100% remote courses where `municipio_id is null`).
     - `modalidade` (`presencial`, `ead`, `hibrido`).
     - `categoria` (`industrial`, `tecnologia`, `gestao`, `servicos`, `artesanato`).
     - `com_bolsa` (boolean: `bolsa_auxilio > 0`).
     - `status` (`aberto`, `em_andamento`, `encerrado`; defaults to `aberto`).
     - `page`, `per_page` (clamped 1..100).
   - **Response**: Paginated list of courses with partner institution info (SENAI, IFES, ADERES, etc.).

2. `GET /api/cursos/{id}`: Single course details.

3. `POST /api/cursos`: Create course (Gestor / Técnico).

4. `PUT /api/cursos/{id}`: Update course.

5. `DELETE /api/cursos/{id}`: Delete/close course.

6. `POST /api/cursos/{id}/inscrever`:
   - **Behavior**:
     - Enrolls egresso into course.
     - Automatically registers timeline event on Prontuário:
       - `tipo_evento = 'inscricao_curso'` (or `'matricula_curso'`)
       - `titulo = 'Inscrição no Curso: ' . $curso->titulo`
       - `descricao = 'Matrícula efetuada na instituição ' . $curso->instituicao . ' (' . $curso->modalidade . ', ' . $curso->carga_horaria . 'h)'`
       - `metadata = ['curso_id' => $curso->id, 'instituicao' => $curso->instituicao, 'bolsa' => $curso->bolsa_auxilio]`
   - **Response**: HTTP 201 Created.

---

## 5. Focus Area 3: Territorial Mapping & Rede de Apoio APIs

### 5.1 Territorial Mapping (`/api/territorios` or `/api/municipios`)

#### Geographic Rules:
- **Espírito Santo Bounding Box (WGS84)**:
  - Latitude: `-21.31` to `-17.88`
  - Longitude: `-41.88` to `-39.66`
- **IBGE Code Format**: Exactly 7 digits starting with `32` (e.g. `3205309` Vitória, `3203205` Linhares). Non-ES codes (e.g., RJ 33XXXXX, SP 35XXXXX, MG 31XXXXX) must be rejected with validation errors.
- **Physical vs Remote Coverage**:
  - 4 Municipalities with physical Escritório Social: Vitória, Vila Velha, Serra, Cariacica (`tem_escritorio_fisico = true`).
  - 74 Municipalities covered 100% via remote teleassistance Conecta Egresso (`tem_escritorio_fisico = false`, `atendimento_remoto_disponivel = true`).

#### Endpoints:
1. `GET /api/territorios`:
   - Lists all 78 municipalities with aggregate counts:
     - `codigo_ibge`, `nome`, `microrregiao`, `macrorregiao`, `latitude`, `longitude`, `tem_escritorio_fisico`, `populacao_estimada`, `total_egressos_atendidos`.
     - Computed stats: `total_vagas_abertas`, `total_unidades_apoio` (CRAS/CREAS/SINE).
   - Supports search filter `q` with accent-insensitivity (e.g., `São Mateus` == `Sao Mateus` == `sao mateus`).
   - Supports filter by `macrorregiao` (`Metropolitana`, `Norte`, `Sul`, `Central`) and `tem_escritorio_fisico`.

2. `GET /api/territorios/{codigo_ibge_or_id}`:
   - Detailed municipality view with:
     - Basic demographic and geographic info.
     - Array of `unidades_apoio` (CRAS, CREAS, SINE, CAPS, CASA_CIDADAO, DEFENSORIA).
     - Array of open `vagas` in the municipality.
     - Array of vocational `cursos` available in the municipality or state-wide EAD.
     - Remote care indicator: `atendimento_remoto_disponivel: true`.

3. `GET /api/territorios/regioes`:
   - Summary breakdown by 4 Macrorregiões and 10 Microrregiões (Central Serrana, Noroeste, Centro-Oeste, Caparaó, Sudoeste Serrana, Litoral Sul, Rio Doce, Central Sul, Nordeste, Metropolitana).

### 5.2 Rede de Apoio Socioassistencial (`/api/rede-apoio`)

#### Fallback Coordinate Policy:
- If a CRAS/CREAS/SINE facility has explicit `latitude` and `longitude`, return exact GPS (`origem_coordenada: "exact_gps"`).
- If coordinates are null in database, dynamically fallback to the host municipality's centroid coordinates (`origem_coordenada: "municipality_centroid_fallback"`).

#### Endpoints:
1. `GET /api/rede-apoio`:
   - **Filters**:
     - `municipio_id` or `codigo_ibge`.
     - `tipo` (`CRAS`, `CREAS`, `SINE`, `CAPS`, `CASA_CIDADAO`, `DEFENSORIA`, `ESCRITORIO_SOCIAL`).
     - `ativo` (boolean, default `true`).
     - `q` (search name, address, services).

2. `GET /api/rede-apoio/{id}`: Single facility details.

3. `POST /api/rede-apoio`: Create facility (Gestor).
   - Validates coordinates within ES bounding box.

4. `PUT /api/rede-apoio/{id}`: Update facility.

5. `DELETE /api/rede-apoio/{id}`: Deactivate/delete facility.

---

## 6. Focus Area 4: Management KPIs & Analytics APIs

### 6.1 KPI Computation Formulas

| Metric Key | Metric Name | Computation Method / Source Table | Expected Benchmark |
|---|---|---|---|
| `meta_populacional_egressos_es` | Meta Populacional de Egressos | Constant target pool for ES | `108000` |
| `total_egressos_cadastrados` | Total de Egressos Cadastrados | `Egresso::count()` | Demonstration / Real count |
| `total_prontuarios_ativos` | Prontuários Ativos | `Prontuario::where('situacao', 'ativo')->count()` | Active records |
| `total_atendimentos` | Total de Atendimentos Realizados | Count of `prontuario_timeline` events where `tipo_evento in ('acolhimento_video', 'atendimento_remoto', 'atendimento_presencial')` + `video_rooms` | Combined count |
| `atendimentos_remotos` | Atendimentos Remotos (Conecta Egresso) | Timeline events where `tipo_evento in ('acolhimento_video', 'atendimento_remoto')` | Remote count |
| `atendimentos_presenciais` | Atendimentos Presenciais (Escritório Social) | Timeline events where `tipo_evento = 'atendimento_presencial'` | In-person count |
| `taxa_remoto_pct` | Taxa de Teleatendimento Remoto | `round((atendimentos_remotos / total_atendimentos) * 100, 1)` | e.g. `60.0%` |
| `taxa_empregabilidade_pct` | Taxa de Encaminhamento / Empregabilidade | `round((vagas_preenchidas / vagas_totais) * 100, 1)` or `round((total_encaminhamentos / total_egressos) * 100, 1)` | e.g. `58.4%` |
| `taxa_sucesso_nao_reincidencia_pct` | Taxa de Não Reincidência Criminal | Percentage of attended egressos without new penal execution processes | e.g. `82.5%` |
| `vagas_totais` | Total de Vagas Ofertadas | `VagaEmprego::sum('vagas_totais')` | e.g. `142` |
| `vagas_preenchidas` | Total de Vagas Preenchidas | `VagaEmprego::sum('vagas_preenchidas')` | e.g. `86` |
| `cursos_ativos` | Cursos com Inscrições Abertas | `CursoCapacitacao::where('status', 'aberto')->count()` | e.g. `18` |
| `qualidade_media_video_mos` | Média de Qualidade de Chamada (MOS) | `VideoAttendee::avg('mos_score')` | `4.35` / `5.00` |

### 6.2 Endpoints Specification

#### 1. `GET /api/kpis/dashboard`
- Executive summary metrics returning the JSON structure:
  ```json
  {
    "meta_populacional_egressos_es": 108000,
    "total_egressos_cadastrados": 8412,
    "total_prontuarios_ativos": 3420,
    "total_atendimentos": 5230,
    "atendimentos_remotos": 3140,
    "atendimentos_presenciais": 2090,
    "taxa_remoto_pct": 60.0,
    "taxa_empregabilidade_pct": 58.4,
    "taxa_sucesso_nao_reincidencia_pct": 82.5,
    "vagas_totais": 142,
    "vagas_preenchidas": 86,
    "cursos_ativos": 18,
    "qualidade_media_video_mos": 4.35
  }
  ```

#### 2. `GET /api/kpis/regional`
- Regional breakdown grouped by 4 Macrorregiões and 78 Municipalities:
  ```json
  {
    "macrorregioes": {
      "Metropolitana": { "total_atendimentos": 3420, "egressos": 5120, "percentual": 65.4 },
      "Norte": { "total_atendimentos": 890, "egressos": 1450, "percentual": 17.0 },
      "Sul": { "total_atendimentos": 620, "egressos": 1100, "percentual": 11.9 },
      "Central": { "total_atendimentos": 300, "egressos": 742, "percentual": 5.7 }
    },
    "municipios": [
      { "codigo_ibge": 3205309, "nome": "Vitória", "atendimentos": 1240, "egressos": 1850 },
      { "codigo_ibge": 3205002, "nome": "Serra", "atendimentos": 1100, "egressos": 1620 },
      { "codigo_ibge": 3203205, "nome": "Linhares", "atendimentos": 420, "egressos": 610 }
    ]
  }
  ```

#### 3. `GET /api/kpis/timeline`
- Monthly timeline aggregation showing growth of attendances, job referrals, and course enrollments over the past 12 months.

#### 4. `GET /api/kpis/telemetria`
- Aggregated WebRTC audio/video telemetry metrics:
  - MOS Score Distribution (Excellent 4.5-5.0, Good 4.0-4.4, Fair 3.5-3.9, Poor < 3.5).
  - Average Call Duration (seconds/minutes).
  - Packet Loss % across mobile 3G/4G/5G vs broadband connections.
  - Total video sessions completed and average latency (RTT ms).

---

## 7. Recommended Implementation Structure for Workers

### 7.1 Controller Layout
```
app/Http/Controllers/
├── CarteiraValidationController.php       # (Existing - Public QR code & wallet verification)
├── Controller.php                         # (Existing - Base Controller)
├── ProntuarioController.php               # (New - CRUD Prontuários with LGPD audit logs)
├── ProntuarioTimelineController.php       # (New - Timeline events & evoluções)
├── VagaEmpregoController.php              # (New - Jobs CRUD, filtering by 78 municipalities, candidatura)
├── CursoCapacitacaoController.php         # (New - Courses CRUD, filtering, enrollments)
├── CandidaturaController.php              # (New - Candidaturas / Encaminhamentos)
├── TerritorioController.php               # (New - 78 ES municipalities, regions, physical vs remote)
├── RedeApoioController.php                # (New - CRAS, CREAS, SINE, CAPS support facilities)
└── KpiDashboardController.php             # (New - Management KPIs, regional distribution, analytics)
```

### 7.2 Routes Definition (`routes/api.php`)
```php
// Prontuário Único & Timeline
Route::apiResource('prontuarios', ProntuarioController::class);
Route::get('prontuarios/{prontuario}/timeline', [ProntuarioTimelineController::class, 'index']);
Route::post('prontuarios/{prontuario}/timeline', [ProntuarioTimelineController::class, 'store']);
Route::post('prontuarios/{prontuario}/evolucao', [ProntuarioTimelineController::class, 'storeEvolucao']);

// Vagas de Emprego & Candidaturas
Route::apiResource('vagas', VagaEmpregoController::class);
Route::post('vagas/{vaga}/candidatar', [VagaEmpregoController::class, 'candidatar']);
Route::apiResource('candidaturas', CandidaturaController::class)->only(['index', 'store', 'show']);

// Cursos de Capacitação
Route::apiResource('cursos', CursoCapacitacaoController::class);
Route::post('cursos/{curso}/inscrever', [CursoCapacitacaoController::class, 'inscrever']);

// Territorial Mapping & Rede de Apoio
Route::get('territorios', [TerritorioController::class, 'index']);
Route::get('territorios/regioes', [TerritorioController::class, 'regioes']);
Route::get('territorios/{codigo_ibge_or_id}', [TerritorioController::class, 'show']);
Route::apiResource('rede-apoio', RedeApoioController::class);

// Management KPIs & Analytics
Route::prefix('kpis')->group(function () {
    Route::get('dashboard', [KpiDashboardController::class, 'dashboard']);
    Route::get('regional', [KpiDashboardController::class, 'regional']);
    Route::get('timeline', [KpiDashboardController::class, 'timeline']);
    Route::get('telemetria', [KpiDashboardController::class, 'telemetria']);
});
```

### 7.3 Boundary & Negative Handling Rules
1. **Empty Note / Evolution**: Return HTTP 422 Unprocessable Entity (`validation_error_empty_description`).
2. **Payload Size Limit**: Reject notes > 64KB (65,536 bytes) with HTTP 413 Payload Too Large.
3. **Invalid Non-Existent Egresso/Prontuario ID**: Return HTTP 404 Not Found.
4. **Invalid Non-Integer ID format**: Return HTTP 400 Bad Request.
5. **Non-ES IBGE Code (prefix != 32)**: Return HTTP 422 Unprocessable Entity.
6. **Negative Salary Filter**: Clamp to 0.0 without throwing errors.
7. **Pagination Limits**: Clamp `per_page` strictly between 1 and 100.
8. **Accent-Insensitive Search**: Normalize search strings using `unaccent` or regex mapping (e.g. `Vitória` matches `vitoria`).
9. **Author Binding**: Author `responsavel_id` is automatically set to authenticated user ID, overriding any forged payload ID.
10. **Role Permission**: Egressos attempting to write evolution notes receive HTTP 403 Forbidden.

---

## 8. Test Suite Specifications for Verification

To ensure full coverage across all tiers, the worker should create comprehensive feature tests:
1. `tests/Feature/ProntuarioApiTest.php` (CRUD, audit logging on read/write, timeline insertion, 64KB limit, empty description rejection, XSS escaping, author binding).
2. `tests/Feature/VagasCursosApiTest.php` (Jobs filter by 78 municipalities, affirmative action tag, candidacy auto-logging to prontuário timeline, courses list, enrollments).
3. `tests/Feature/TerritorioRedeApoioApiTest.php` (78 municipalities listing, IBGE code validation, CRAS/SINE coordinates fallback, regional stats).
4. `tests/Feature/KpiAnalyticsApiTest.php` (Dashboard metrics, regional distribution, recidivism reduction %, employment placement %, WebRTC telemetry aggregation).

All tests must pass 100% via:
- `php artisan test`
- `python tests_e2e/test_runner.py`
