# Conecta Egresso (SEJUS/ES) — Mobile API Specification & Integration Contract

**Specification Version:** 1.0.0  
**Target Release:** 2026 Mobile Client (iOS / Android)  
**Maintained by:** SEJUS/ES Backend Team  
**Security Standard:** LGPD (Lei Geral de Proteção de Dados - Lei 13.709/2018)  
**Authentication Standard:** HTTP Authorization Bearer Token (RFC 6750)  

---

## 1. Overview & Architectural Principles

The **Conecta Egresso** Mobile API is a RESTful backend service maintained by the Secretaria de Estado da Justiça do Espírito Santo (SEJUS/ES) to support digital social reintegration services. The mobile application consumed by egressos, family members, and social office staff interfaces directly with this API.

### Core Design Guarantees
1. **Stateless Bearer Authentication**: Mobile apps authenticate via credentials and exchange them for a persistent, cryptographically secure 64-character SHA-256 hashed Bearer token.
2. **Delta Synchronization (R3)**: Listing endpoints support incremental queries via the `since` (alias `updated_after`) parameter, returning only records updated at or after that point in time.
3. **Authoritative Server Timestamp**: Responses include `meta.server_timestamp` in ISO-8601 UTC format. Mobile clients **must** store this cursor and provide it in subsequent sync calls, avoiding mobile device clock drift.
4. **LGPD Privacy by Design**: Personal identifiers (CPF, phone numbers) are masked in all mobile API responses (e.g. `192.***.***-78`). Raw values are never transmitted over the mobile wire.
5. **Offline Readiness**: Delta responses are designed for direct local database `UPSERT` operations (`INSERT OR REPLACE INTO ... WHERE id = :id`).

---

## 2. Base URLs & Request Headers

### Base Endpoints
- **Production Base URL**: `https://conectaegresso.es.gov.br/api`
- **Staging / QA Base URL**: `https://homolog-conectaegresso.es.gov.br/api`
- **Development / Localhost**: `http://localhost:8000/api`

### Mandatory Request Headers
```http
Accept: application/json
Content-Type: application/json
User-Agent: ConectaEgressoMobile/1.0.0 (Android 15; Pixel 9)
Authorization: Bearer <access_token>
```
*(Note: `Authorization` header is required for all authenticated endpoints, omitted only for `/api/auth/login`, `/api/health`, and `/api/validar-carteira/{token}`)*.

---

## 3. Mobile Authentication & Token Lifecycle (R1)

### 3.1 Mobile Login (`POST /api/auth/login`)
Authenticates an egresso, technician, or manager using either an email address or an 11-digit CPF (formatted or unformatted).

- **URL**: `/api/auth/login`
- **Method**: `POST`
- **Auth Required**: No
- **Request Body**:
```json
{
  "login": "lucas.santos@cidadao.es.gov.br",
  "password": "secret123",
  "device_name": "iPhone 15 Pro (iOS 18.0)"
}
```
*Note: `login` accepts both email (`lucas.santos@cidadao.es.gov.br`) and formatted/unformatted CPF (`192.830.456-78` or `19283045678`).*

- **Success Response (200 OK)**:
```json
{
  "status": "authenticated",
  "token_type": "Bearer",
  "access_token": "ce_mob_7b9d3e8a1f4c2069b2d8e7c105fa932b48ec71df",
  "expires_at": "2026-10-15T15:00:00Z",
  "user": {
    "id": 3,
    "name": "Lucas Santos",
    "email": "lucas.santos@cidadao.es.gov.br",
    "role": "egresso",
    "role_name": "Egresso do Sistema Prisional",
    "cpf_masked": "192.***.***-78",
    "telefone_masked": "(27) 9****-3344",
    "ativo": true,
    "egresso_id": 1
  }
}
```

- **Error Responses**:
  - `401 Unauthorized`:
    ```json
    {
      "error": "Credenciais inválidas. Verifique seu login e senha.",
      "code": "INVALID_CREDENTIALS"
    }
    ```
  - `403 Forbidden`:
    ```json
    {
      "error": "Conta de usuário desativada. Entre em contato com a administração SEJUS.",
      "code": "ACCOUNT_DEACTIVATED"
    }
    ```
  - `422 Unprocessable Entity`:
    ```json
    {
      "message": "Identificador de login (email ou CPF) é obrigatório."
    }
    ```

### 3.2 Authenticated Profile (`GET /api/auth/me`)
Retrieves the profile and permissions of the currently authenticated mobile session.

- **URL**: `/api/auth/me`
- **Method**: `GET`
- **Auth Required**: Yes (`Authorization: Bearer <access_token>`)
- **Success Response (200 OK)**:
```json
{
  "id": 3,
  "name": "Lucas Santos",
  "email": "lucas.santos@cidadao.es.gov.br",
  "role": "egresso",
  "role_name": "Egresso do Sistema Prisional",
  "permissions": [
    "visualizar_vagas",
    "candidatar_vagas",
    "visualizar_cursos",
    "inscrever_cursos",
    "visualizar_carteira",
    "visualizar_prontuario_proprio"
  ],
  "cpf_masked": "192.***.***-78",
  "telefone_masked": "(27) 9****-3344",
  "ativo": true,
  "egresso": {
    "id": 1,
    "nome_completo": "Lucas Santos",
    "nome_social": null,
    "status_penal": "egresso",
    "municipio_id": 68,
    "municipio_nome": "São Mateus",
    "macrorregiao": "Norte"
  }
}
```
- **Error Response (401 Unauthorized)**:
```json
{
  "error": "Não autenticado ou token inválido.",
  "code": "UNAUTHENTICATED"
}
```

### 3.3 Mobile Logout & Revocation (`POST /api/auth/logout`)
Permanently revokes and deletes the mobile access token from the database.

- **URL**: `/api/auth/logout`
- **Method**: `POST`
- **Auth Required**: Yes (`Authorization: Bearer <access_token>`)
- **Success Response (200 OK)**:
```json
{
  "status": "logged_out",
  "message": "Sessão e token revogados com sucesso."
}
```
*Note: Once revoked, any subsequent request with the same token will return HTTP 401.*

---

## 4. Core Mobile Resources Catalogue (R2)

### 4.1 Digital Wallet / Carteira Digital (`GET /api/carteira`)
Fetches the complete digital wallet profile for the authenticated egresso, including the official cryptographic verification token, QR Code Data-URI, and validation URL.

- **URL**: `/api/carteira` (Alias: `/api/carteira/digital`)
- **Method**: `GET`
- **Auth Required**: Yes (`Authorization: Bearer <access_token>`)
- **Success Response (200 OK)**:
```json
{
  "data": {
    "id": 1,
    "registro_sejus": "ES-2026-000001",
    "nome": "LUCAS SANTOS",
    "nome_social": null,
    "cpf_masked": "192.***.***-78",
    "data_nascimento": "1995-04-12",
    "municipio": "São Mateus",
    "status_penal": "egresso",
    "status_documento": "REGULAR",
    "data_emissao": "15/01/2026",
    "data_validade": "15/01/2027",
    "auth_code": "8A2B-F4C1-09E3-7D5A",
    "token": "eyJwIjp7ImRvY19pZCI6IjEiLCJyZWdpc3Ry...",
    "validation_url": "https://conectaegresso.es.gov.br/validar-carteira/eyJwIjp7...",
    "qr_code_data_uri": "data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDov...",
    "legal_basis": "Lei Complementar Estadual nº 182/2021 - SEJUS/ES"
  }
}
```
- **Error Response (404 Not Found)**:
```json
{
  "error": "Perfil de egresso não encontrado para o usuário autenticado.",
  "code": "EGRESSO_NOT_FOUND"
}
```

### 4.2 Public QR Code Document Verification (`GET /api/validar-carteira/{token}`)
Public cryptographic verification endpoint used by employers, police officers, or municipal inspection agents when scanning the QR code on the mobile screen.

- **URL**: `/api/validar-carteira/{token}`
- **Method**: `GET`
- **Auth Required**: No (Public)
- **Success Response (200 OK — Authentic Document)**:
```json
{
  "valid": true,
  "status": "VALID_DOCUMENT",
  "message": "DOCUMENTO OFICIAL AUTENTICO E HOMOLOGADO PELA SEJUS/ES.",
  "payload": {
    "doc_id": "1",
    "registro_sejus": "ES-2026-000001",
    "cpf_masked": "192.***.***-78",
    "nome": "LUCAS SANTOS",
    "municipio": "São Mateus",
    "issued_at": "2026-01-15T10:00:00+00:00",
    "expires_at": "2027-01-15T10:00:00+00:00",
    "legal_basis": "Lei Complementar Estadual no 182/2021 - SEJUS/ES"
  }
}
```
- **Response (200 OK — Tampered Document)**:
```json
{
  "valid": false,
  "status": "TAMPERED_DOCUMENT",
  "message": "DOCUMENTO ADULTERADO OU INVALIDO! Assinatura criptografica incompativel."
}
```

### 4.3 Job Vacancies (`GET /api/vagas`)
Lists employment vacancies with rich filtering, search, pagination, and delta sync.

- **URL**: `/api/vagas`
- **Method**: `GET`
- **Query Parameters**:
  - `since` or `updated_after` *(string)*: ISO-8601 UTC timestamp or Unix epoch for delta sync.
  - `status` *(string)*: `aberta` (default), `preenchida`, `pausada`.
  - `municipio_id` *(integer)*: Filter by ES municipality ID (1-78).
  - `afirmativa_egresso` *(boolean)*: Filter by affirmative action vacancies.
  - `empresa_amiga` *(boolean)*: Filter by Empresa Amiga da Reintegração Social.
  - `categoria` *(string)*: `logistica`, `construcao_civil`, `agropecuaria`, `servicos`, `industria`, `comercio`.
  - `salario_min` *(numeric)*: Minimum monthly salary in BRL.
  - `q` *(string)*: Search term across title, company name, and description.
  - `page` *(integer)*: Page number (default: 1).
  - `per_page` *(integer)*: Items per page (default: 15, max: 100).
- **Success Response (200 OK)**:
```json
{
  "data": [
    {
      "id": 1,
      "empresa": "Porto de Tubarão Logística S.A.",
      "titulo": "Auxiliar de Logística e Carga Portuária",
      "descricao": "Atuação no pátio logístico com movimentação de cargas...",
      "categoria": "logistica",
      "municipio_id": 78,
      "municipio": {
        "id": 78,
        "nome": "Vitória",
        "codigo_ibge": "3205309",
        "macrorregiao": "Metropolitana"
      },
      "salario": "2100.00",
      "regime_contratacao": "CLT",
      "afirmativa_egresso": true,
      "empresa_amiga_reintegracao": true,
      "escolaridade_minima": "Ensino Fundamental Completo",
      "vagas_totais": 6,
      "vagas_preenchidas": 2,
      "status": "aberta",
      "beneficios": ["Vale Transporte", "Vale Refeição (R$ 35/dia)", "Plano de Saúde"],
      "created_at": "2026-01-10T08:00:00Z",
      "updated_at": "2026-02-01T14:30:00Z"
    }
  ],
  "current_page": 1,
  "per_page": 15,
  "total": 1,
  "last_page": 1,
  "meta": {
    "server_timestamp": "2026-09-15T15:15:00.123456Z",
    "sync_count": 1,
    "since": "2026-01-01T00:00:00Z",
    "total": 1,
    "current_page": 1,
    "per_page": 15,
    "last_page": 1
  }
}
```

### 4.4 Vocational Training Courses (`GET /api/cursos`)
Lists vocational training and qualification courses offered by Findes/SENAI/SEJUS.

- **URL**: `/api/cursos`
- **Method**: `GET`
- **Query Parameters**:
  - `since` or `updated_after` *(string)*: Delta sync cursor.
  - `modalidade` *(string)*: `presencial`, `ead`, `hibrido`.
  - `ead_only` *(boolean)*: Return only statewide distance learning courses.
  - `com_bolsa` *(boolean)*: Return courses offering a student stipend.
  - `municipio_id` *(integer)*: Municipality filter.
  - `q` *(string)*: Keyword search.
  - `page`, `per_page`: Pagination controls.
- **Success Response (200 OK)**:
```json
{
  "data": [
    {
      "id": 1,
      "instituicao": "SENAI / Findes / SEJUS",
      "titulo": "Capacitação em Solda Industrial (Eletrodo e MIG/MAG)",
      "descricao": "Formação prática de soldadores industriais...",
      "categoria": "industrial",
      "municipio_id": 43,
      "municipio": {
        "id": 43,
        "nome": "Linhares",
        "codigo_ibge": "3203205",
        "macrorregiao": "Rio Doce"
      },
      "carga_horaria": 160,
      "modalidade": "presencial",
      "bolsa_auxilio": "400.00",
      "vagas_disponiveis": 25,
      "status": "aberto",
      "link_inscricao": "https://conectaegresso.es.gov.br/cursos/solda-linhares",
      "created_at": "2026-01-12T09:00:00Z",
      "updated_at": "2026-02-10T11:20:00Z"
    }
  ],
  "meta": {
    "server_timestamp": "2026-09-15T15:15:00.123456Z",
    "sync_count": 1,
    "since": null,
    "total": 1,
    "current_page": 1,
    "per_page": 15,
    "last_page": 1
  }
}
```

### 4.5 Electronic Medical/Case Records (`GET /api/prontuarios`)
Retrieves case records and timeline events.
- **Role Scoping Policy**:
  - If authenticated as an **Egresso**, the endpoint strictly returns **only their own single prontuário record** along with its complete clinical timeline.
  - If authenticated as **Técnico** or **Gestor**, it provides full paginated listings across all caseloads.
  - If unauthenticated, it rejects with HTTP 401.

- **URL**: `/api/prontuarios`
- **Method**: `GET`
- **Auth Required**: Yes (`Authorization: Bearer <access_token>`)
- **Success Response (200 OK — for Egresso)**:
```json
{
  "data": [
    {
      "id": 1,
      "numero_prontuario": "PRT-2026-000001",
      "situacao": "ativo",
      "resumo_diagnostico": "Egresso acolhido via teleatendimento...",
      "meta_plano_individual": "1. Concluir capacitação SENAI; 2. Encaminhar para processo seletivo...",
      "data_abertura": "2026-01-15T10:00:00Z",
      "created_at": "2026-01-15T10:00:00Z",
      "updated_at": "2026-01-25T09:00:00Z",
      "egresso": {
        "id": 1,
        "nome_completo": "Lucas Santos",
        "nome_social": null,
        "cpf_masked": "192.***.***-78",
        "status_penal": "egresso",
        "municipio_id": 68,
        "municipio_nome": "São Mateus",
        "macrorregiao": "Norte"
      },
      "tecnico_responsavel": {
        "id": 2,
        "name": "Dra. Márcia Oliveira",
        "email": "marcia.oliveira@sejus.es.gov.br"
      },
      "timeline": [
        {
          "id": 3,
          "tipo_evento": "emissao_carteira",
          "titulo": "Emissão da Carteira Digital do Egresso",
          "descricao": "Documento oficial gerado com QR Code criptográfico...",
          "metadata": {
            "registro_sejus": "ES-2026-000001"
          },
          "data_evento": "2026-01-25T09:00:00Z"
        }
      ]
    }
  ],
  "meta": {
    "server_timestamp": "2026-09-15T15:15:00.123456Z",
    "sync_count": 1,
    "since": null,
    "total": 1,
    "current_page": 1,
    "per_page": 15,
    "last_page": 1
  }
}
```

---

## 5. Incremental Delta Synchronization Protocol (R3)

To ensure rapid offline capability and minimize cellular data usage over mobile 3G/4G/5G connections across the 78 municipalities of Espírito Santo, the mobile client must adhere to the following sync algorithm.

```
+--------------------------------------------------------------------+
|                         Mobile Client Sync Loop                    |
|                                                                    |
|  1. Load stored cursor: cursor = KeyValueStore.get("last_sync")   |
|  2. If cursor exists: query GET /api/vagas?since={cursor}          |
|     Else (fresh install): query GET /api/vagas                     |
|  3. Read response:                                                 |
|     - new_cursor = response.meta.server_timestamp                  |
|     - records = response.data                                      |
|  4. Local SQLite / Room / SwiftData transaction:                   |
|     FOR EACH item IN records:                                      |
|         INSERT OR REPLACE INTO local_vagas VALUES (item.*);        |
|  5. KeyValueStore.set("last_sync", new_cursor)                     |
+--------------------------------------------------------------------+
```

### 5.1 Timestamp Formats Accepted
The backend normalization parser supports:
1. `ISO-8601 UTC`: `2026-09-15T12:00:00Z` or `2026-09-15T12:00:00.000Z`
2. `ISO-8601 with Offset`: `2026-09-15T09:00:00-03:00`
3. `SQL Datetime`: `2026-09-15 12:00:00`
4. `Unix Epoch seconds`: `1789473600`

If an unparseable timestamp is sent, the API returns:
```json
{
  "error": "Parâmetro de data inválido para sincronização delta. Formato esperado: ISO-8601 (ex: 2026-09-15T12:00:00Z).",
  "code": "INVALID_SYNC_TIMESTAMP"
}
```

### 5.2 Semantics of `>=` Over `>`
The server queries:
```sql
WHERE updated_at >= :since
```
Because multiple records can be committed within the same second or millisecond, a strictly greater than (`>`) condition risks missing records. The mobile client simply performs an `UPSERT` (`INSERT OR REPLACE INTO ... WHERE id = :id`), ensuring idempotency with zero data loss.

### 5.3 Pagination in Delta Sets
If the delta payload contains more items than `per_page`, `meta.last_page` will be greater than 1. The mobile client must fetch all pages (`page=1`, `page=2`, etc.) using the **exact same `since` cursor** before advancing its stored `last_sync` to `meta.server_timestamp`.

---

## 6. Error Code Dictionary

| HTTP Status | Error Code | Meaning / Resolution |
|-------------|------------|----------------------|
| `401` | `UNAUTHENTICATED` | Token is missing, expired, or tampered. Mobile client must prompt for re-login. |
| `401` | `INVALID_CREDENTIALS` | Login credentials incorrect. |
| `403` | `ACCOUNT_DEACTIVATED` | Account deactivated by SEJUS administration. Client should present support contact. |
| `403` | `FORBIDDEN` | Authenticated role lacks permissions for the action (e.g. egresso attempting staff action). |
| `404` | `EGRESSO_NOT_FOUND` | User is authenticated but has no associated Egresso profile. |
| `404` | `NOT_FOUND` | Requested entity does not exist. |
| `413` | `PAYLOAD_TOO_LARGE` | Request body exceeds 64KB safety limit. |
| `422` | `INVALID_SYNC_TIMESTAMP` | The `since` or `updated_after` timestamp string could not be parsed. |
| `422` | `VACANCY_CLOSED` | Attempted to apply for a vacancy that is not in `aberta` status. |
| `422` | `VACANCY_FULL` | All spots for this vacancy have been filled. |
| `422` | `COURSE_CLOSED` | Course is no longer accepting new enrollments. |

---

## 7. Mobile App Security & LGPD Best Practices

1. **Storage of Bearer Token**:
   - iOS: Store `access_token` in the **iOS Keychain Services** with `kSecAttrAccessibleAfterFirstUnlock`.
   - Android: Store `access_token` in **EncryptedSharedPreferences** backed by **Android Keystore (MasterKey)**.
   - Do **NOT** store the raw Bearer token in plain SQLite, plain SharedPreferences, or unencrypted AsyncStorage.
2. **Network Security & TLS**:
   - All network calls must be executed strictly over HTTPS (TLS 1.3 or TLS 1.2).
   - Cleartext traffic is disabled on Android (`android:usesCleartextTraffic="false"`) and iOS App Transport Security (`NSAllowsArbitraryLoads = NO`).
3. **Biometric Unlock**:
   - When the user opens the mobile application, recommend authenticating with FaceID / TouchID / BiometricPrompt before using the stored Bearer token to fetch `/api/carteira`.
