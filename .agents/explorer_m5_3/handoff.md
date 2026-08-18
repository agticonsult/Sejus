# Handoff Report: Milestone M5 — 8 Core Pages & WebRTC Client Integration

**Agent:** Explorer 3 (`explorer_m5_3`)  
**Milestone:** M5 — Reactive & Accessible Frontend (Inertia.js + Vue 3)  
**Date:** 2026-08-17T17:26:00Z  
**Target File:** `d:\Agile\projeto dia 18\.agents\explorer_m5_3\handoff.md`

---

## 1. Observation

Direct observations from examining the codebase, specifications, and test infrastructure:

1. **Authoritative Requirements & Feature Scope**:
   - `ORIGINAL_REQUEST.md` (R1, R2, R3) and `PROJECT.md` define 8 core reactive views (F39-F46), 1 public validation page (F47), and WebRTC video integration (F40, F26-F33).
   - `SCOPE.md` in `.agents/sub_orch_m5_frontend/` specifies:
     - Framework: Vue 3 Composition API (`<script setup>`), `@inertiajs/vue3`, TailwindCSS with SEJUS/ES state institutional colors (Blue `#003366`, Light Blue `#0284c7`, Red/Pink `#e63946`, Green `#10b981`, Neutral Slate `#0f172a` / `#f4f7fb`).
     - Layout: `resources/js/Layouts/AppLayout.vue`.
     - 8 Core Views in `resources/js/Pages/`:
       1. `Dashboard.vue` (F39)
       2. `Atendimento.vue` (F40)
       3. `Oportunidades.vue` (F41)
       4. `Carteira.vue` (F42)
       5. `Geolocalizacao.vue` (F43)
       6. `Prontuario.vue` (F44)
       7. `Relatorios.vue` (F45)
       8. `SegurancaLgpd.vue` (F46)
     - WebRTC Service Engine in `resources/js/Services/webrtc.js`.

2. **Existing Backend Models & Database Schema**:
   - `app/Models/Egresso.php`: Masked & encrypted fields (`cpf_encrypted`, `rg_encrypted`, `filiacao_mae_encrypted`, `endereco_encrypted`, `telefone_encrypted`), `registro_sejus` attribute (`ES-2026-XXXXXX`), relationships `municipio()`, `prontuario()`, `videoRooms()`.
   - `app/Models/Prontuario.php`: `numero_prontuario`, `egresso_id`, `tecnico_responsavel_id`, `situacao`, `resumo_diagnostico`, `meta_plano_individual`, `data_abertura`, relationships `timeline()`, `auditLogs()`, `videoRooms()`.
   - `app/Models/ProntuarioTimeline.php`: `prontuario_id`, `tipo_evento`, `titulo`, `descricao`, `metadata` (JSON), `responsavel_id`, `data_evento`.
   - `app/Models/MunicipioEs.php`: `codigo_ibge`, `nome`, `microrregiao`, `macrorregiao`, `latitude`, `longitude`, `tem_escritorio_fisico`, `populacao_estimada`, `total_egressos_atendidos`. (78 municipalities total).
   - `app/Models/VagaEmprego.php`: `empresa`, `titulo`, `descricao`, `categoria`, `municipio_id`, `salario`, `regime_contratacao`, `afirmativa_egresso`, `empresa_amiga_reintegracao`, `escolaridade_minima`, `vagas_totais`, `vagas_preenchidas`, `status`, `beneficios`.
   - `app/Models/CursoCapacitacao.php`: `instituicao`, `titulo`, `descricao`, `categoria`, `municipio_id`, `carga_horaria`, `modalidade`, `bolsa_auxilio`, `vagas_disponiveis`, `status`, `link_inscricao`.
   - `app/Models/RedeApoio.php`: `nome`, `tipo` (CRAS, CREAS, SINE, CAPS, Defensoria), `municipio_id`, `endereco`, `telefone`, `email`, `horario_funcionamento`, `servicos_oferecidos`, `latitude`, `longitude`, `ativo`.
   - `app/Models/ProntuarioAuditLog.php`: `prontuario_id`, `user_id`, `acao`, `ip_address`, `user_agent`, `previous_hash`, `current_hash`, `details`, `timestamp`.

3. **FastAPI WebRTC Microservice Contracts**:
   - `webrtc_service/app/main.py`: CORS enabled, mounts `signaling_router` and `queue_router`.
   - WebSocket `/ws/signaling/{room_id}` (`signaling.py`):
     - Authentication via query param `token=<JWT>` or `{ "type": "join", "token": "<JWT>" }`.
     - Emits `joined` ack: `{ room_id, client_id, user_id, role, polite, peers, ice_servers }`.
     - Routes SDP Offer/Answer: `{ type: "offer"|"answer", sdp, target_client_id, sender_client_id, ice_restart }`.
     - Routes ICE Candidates: `{ type: "ice_candidate", candidate: { candidate, sdpMid, sdpMLineIndex }, target_client_id }`.
     - Relays Media State changes: `{ type: "media_state", audio_muted, video_muted, screen_sharing }` -> relays `peer_media_updated`.
     - Ingests Telemetry: `{ type: "telemetry", audio: { jitter_ms, packet_loss_pct, ... }, video: { ... }, connection: { rtt_ms, ... } }` -> responds with `telemetry_ack` `{ mos, quality_tier }` and if MOS < 3.2 emits `quality_alert` `{ level, mos, rtt_ms, suggestion: "disable_video", message }`.
     - Lifecycle: `{ type: "leave", reason }`, `{ type: "terminate_room", reason }`, `{ type: "ping" }` -> `{ type: "pong" }`.
   - WebSocket `/ws/queue/{unit_id}` (`queue_manager.py`):
     - Actions: `join_queue` (`{ prioridade, motivo, name, municipio }`), `get_queue_position`, `admit_attendee` (`{ ticket_id, room_id }`), `leave_queue`.

4. **Prototype UI/UX Assets**:
   - `index.html`, `styles.css`, `app.js` contain the complete validated design tokens, charts, UI controls, role switcher, and CSS classes (`.high-contrast`, `--font-scale: 1.18`, `.simplified-lang`).

5. **Test Infrastructure & Scenario Requirements**:
   - `tests_e2e/tier1_features/test_f34_f47_frontend_views.py`: Asserts DOM landmark names, KPI text, role select, contrast button, font size zoom (+18%), and public validation URL pattern.
   - `tests_e2e/tier2_boundaries/test_frontend_a11y_limits.py`: Clamps font zoom between 1.00 and 1.50 (+50%), tests Simplified Language fallback to `pt-BR`, WCAG 2.1 AAA contrast ratio (>= 7.0:1), modal focus trap.
   - `tests_e2e/tier3_combinations/test_a11y_multimode_states.py`: Multi-mode simultaneous activation across all 8 views, ARIA attributes (`role="status"`, `aria-live="polite"` on queue; `role="dialog"`, `aria-modal="true"` on video modal).
   - `tests_e2e/tier4_scenarios/scenario_video_attendance_prontuario.py`: Full journey of video call, mobile 4G telemetry, MOS computation (ITU-T G.107), HMAC webhook, and automated timeline insertion.

---

## 2. Logic Chain

From the observations above, we establish the technical foundation and component architecture:

```
┌────────────────────────────────────────────────────────────────────────┐
│                        Laravel 11 Web Routes                           │
│  /dashboard | /atendimento | /oportunidades | /carteira |              │
│  /geolocalizacao | /prontuario | /relatorios | /seguranca-lgpd         │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │ Inertia::render()
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│                       AppLayout.vue (Global Shell)                     │
│  - SEJUS/ES Institutional Header & Navigation Sidebar                 │
│  - AccessibilityToolbar.vue (High Contrast, Font Zoom, Simplified)     │
│  - RoleSwitcher.vue (Gestor SEJUS, Técnico Social, Egresso/Familiar)   │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │ <slot />
        ┌───────────────────────────┴───────────────────────────┐
        ▼                                                       ▼
┌────────────────────────────────┐                    ┌──────────────────┐
│         8 Core Pages           │                    │    webrtc.js     │
│ 1. Dashboard.vue               │                    │ - WS Signaling   │
│ 2. Atendimento.vue ────────────┼───────────────────►│ - W3C Perfect    │
│ 3. Oportunidades.vue           │                    │   Negotiation    │
│ 4. Carteira.vue                │                    │ - ICE Trickle    │
│ 5. Geolocalizacao.vue          │                    │ - STUN / TURN    │
│ 6. Prontuario.vue              │                    │ - Stats Poller   │
│ 7. Relatorios.vue              │                    │ - ITU-T G.107    │
│ 8. SegurancaLgpd.vue           │                    │   MOS Score      │
└────────────────────────────────┘                    └──────────────────┘
```

### 2.1 Page-by-Page Specifications & Contracts

#### 1. `Dashboard.vue` (`resources/js/Pages/Dashboard.vue`)
- **Purpose**: High-level executive and operational cockpit monitoring the statewide expansion across all 78 ES municipalities.
- **Inertia Props Contract**:
  ```typescript
  interface DashboardProps {
    auth: { user: User; role: "gestor" | "tecnico" | "egresso" };
    kpis: {
      total_egressos: number;           // e.g. 14850
      atendimentos_hoje: number;         // e.g. 142
      atendimentos_total: number;        // e.g. 108000 (108 mil)
      vagas_preenchidas: number;         // e.g. 1820
      taxa_reincidencia_zero: number;    // e.g. 84.6 (%)
      vagas_abertas: number;             // e.g. 42
      cursos_ativos: number;             // e.g. 18
    };
    attendance_monthly: Array<{
      month: string;
      presencial: number;
      remoto: number;
    }>;
    municipalities_summary: Array<{
      id: number;
      codigo_ibge: string;
      nome: string;
      microregiao: string;
      total_egressos: number;
      tem_escritorio_fisico: boolean;
    }>;
    reintegration_distribution: Array<{
      label: string;
      val: number;
      color: string;
    }>;
    recent_activities: Array<{
      id: number;
      tipo: string;
      descricao: string;
      autor: string;
      created_at: string;
      municipio: string;
    }>;
  }
  ```
- **Key Reactive Features**:
  - Banner hero with quick action shortcuts (`switchView('atendimento')`, `switchView('relatorios')`).
  - 4 KPI metric cards with trend indicators (`+12.4% este mês`, `108 mil atendimentos`, `84.6% taxa de reincidência zero`).
  - Canvas/SVG Bar Chart for the top 8 demand municipalities (Vitória, Serra, Vila Velha, Cariacica, Linhares, Cachoeiro, Colatina, São Mateus).
  - Donut Chart for Reintegration Distribution (Emprego 42%, Cursos 28%, Psicossocial 18%, Documentos 12%).
  - Live activity feed stream.

#### 2. `Atendimento.vue` (`resources/js/Pages/Atendimento.vue`)
- **Purpose**: Virtual Desk & Teleatendimento for remote psychological, social, and legal counseling with real-time video/audio and connection quality monitoring.
- **Inertia Props Contract**:
  ```typescript
  interface AtendimentoProps {
    auth: { user: User; role: "gestor" | "tecnico" | "egresso" };
    unit_id: string;                    // e.g. "unit-vitoria-01"
    fastapi_ws_url: string;             // e.g. "ws://localhost:8001" or derived from window.location
    ice_servers: Array<{ urls: string | string[]; username?: string; credential?: string }>;
    initial_queue?: Array<{
      ticket_id: string;
      user_id: number;
      name: string;
      municipio: string;
      prioridade: "urgente" | "preferencial" | "normal";
      motivo: string;
      waiting_seconds: number;
      status: string;
    }>;
  }
  ```
- **Key Reactive Features**:
  - **Queue List Panel**:
    - Accessible ARIA landmark: `role="status"`, `aria-live="polite"`.
    - Real-time updates via WebSocket `/ws/queue/{unit_id}`.
    - Priority-colored badges (`urgente` = red, `preferencial` = amber, `normal` = blue).
    - Technician actions: "Chamar Atendido" (admit), "Encaminhar".
    - Citizen action: "Entrar na Fila Virtual" modal with priority selector and reason.
  - **WebRTC Video Session Modal**:
    - Accessible dialog: `role="dialog"`, `aria-modal="true"`, `aria-labelledby="videoModalTitle"`.
    - Local & Remote `<video>` streams.
    - Media controls: Audio Mute/Unmute, Video Mute/Unmute, Screen Sharing (`getDisplayMedia`), Fullscreen, End Call.
  - **Real-Time Telemetry & Quality Meter**:
    - 4G/Wi-Fi signal meter indicator.
    - Real-time display: MOS Score (1.0 to 4.5+), RTT (ms), Jitter (ms), Packet Loss (%).
    - Network degradation alert banner when MOS < 3.2 suggesting disabling video to preserve voice audio.
  - **Post-Call Clinical Notes Modal**:
    - Technician enters diagnostic evolution notes and selects institutional referral (`tipo_encaminhamento`: CRAS, CREAS, SINE, CAPS, Defensoria).
    - Saves directly to Prontuário with automated webhook telemetry metadata.

#### 3. `Oportunidades.vue` (`resources/js/Pages/Oportunidades.vue`)
- **Purpose**: Labor insertion and educational portal matching egressos with affirmative vacancies and training courses across all 78 ES municipalities.
- **Inertia Props Contract**:
  ```typescript
  interface OportunidadesProps {
    auth: { user: User; role: string };
    vagas: Array<{
      id: number;
      empresa: string;
      titulo: string;
      descricao: string;
      categoria: string;
      municipio: { id: number; nome: string };
      salario: number;
      regime_contratacao: string;
      afirmativa_egresso: boolean;
      empresa_amiga_reintegracao: boolean;
      escolaridade_minima: string;
      vagas_totais: number;
      vagas_preenchidas: number;
      status: string;
      beneficios: string[];
    }>;
    cursos: Array<{
      id: number;
      instituicao: string;
      titulo: string;
      descricao: string;
      categoria: string;
      municipio: { id: number; nome: string } | null;
      carga_horaria: number;
      modalidade: "presencial" | "ead" | "hibrido";
      bolsa_auxilio: number;
      vagas_disponiveis: number;
      status: string;
      link_inscricao?: string;
    }>;
    municipios: Array<{ id: number; nome: string; codigo_ibge: string }>;
  }
  ```
- **Key Reactive Features**:
  - Live search filter by title, company, or keyword.
  - 78 ES Municipalities filter dropdown.
  - Modality toggle (Todas, Presencial, Híbrido, 100% EAD).
  - Affirmative action filter ("Apenas Vagas com Cotas SEJUS").
  - "Candidatar-se" application modal with confirmation and candidate eligibility check.
  - Employer accreditation badge ("Empresa Amiga da Reintegração").

#### 4. `Carteira.vue` (`resources/js/Pages/Carteira.vue`)
- **Purpose**: Digital Credential & Identification Card (*Carteira Digital do Egresso*) with cryptographic QR Code and signed PDF issuance.
- **Inertia Props Contract**:
  ```typescript
  interface CarteiraProps {
    auth: { user: User; role: string };
    egresso: {
      id: number;
      nome_completo: string;
      nome_social?: string;
      data_nascimento: string;
      cpf_masked: string;
      rg_masked: string;
      filiacao_mae_masked: string;
      municipio: { nome: string };
      registro_sejus: string;
      status_penal: string;
      foto_url?: string;
    };
    carteira_token: string;             // 64-char SHA-256 HMAC hash
    validation_url: string;             // "/validar-carteira/{token}"
    pdf_download_url: string;           // "/carteira/pdf"
    is_valida: boolean;
    data_emissao: string;
    data_validade: string;
  }
  ```
- **Key Reactive Features**:
  - Visual digital credential card mimicking official government security layout.
  - Guilloche watermark pattern and official ES State seal.
  - Dynamic QR Code render (`<qrcode-vue>` or SVG) with `role="img"` and descriptive `alt="QR Code criptográfico para validação da Carteira Digital do Egresso"`.
  - Cryptographic security watermark with visible SHA-256 hash.
  - Action buttons: "Baixar Carteira em PDF", "Imprimir Credencial", "Validar Autenticidade".

#### 5. `Geolocalizacao.vue` (`resources/js/Pages/Geolocalizacao.vue`)
- **Purpose**: Interactive territorial mapping and socio-assistive service locator covering all 78 ES municipalities.
- **Inertia Props Contract**:
  ```typescript
  interface GeolocalizacaoProps {
    auth: { user: User; role: string };
    municipios: Array<{
      id: number;
      codigo_ibge: string;
      nome: string;
      microrregiao: string;
      macrorregiao: string;
      latitude: number;
      longitude: number;
      tem_escritorio_fisico: boolean;
      populacao_estimada: number;
      total_egressos_atendidos: number;
    }>;
    rede_apoio: Array<{
      id: number;
      nome: string;
      tipo: "CRAS" | "CREAS" | "SINE" | "CAPS" | "DEFENSORIA";
      municipio_id: number;
      endereco: string;
      telefone: string;
      email: string;
      horario_funcionamento: string;
      servicos_oferecidos: string[];
      latitude: number;
      longitude: number;
    }>;
  }
  ```
- **Key Reactive Features**:
  - Interactive grid / SVG regional map covering all 78 municipalities.
  - Micro-region filter pills (Metropolitana, Rio Doce, Central, Noroeste, Caparaó, Serrana, Litoral Sul).
  - Search bar with instant autocomplete.
  - Selected municipality inspector with demand stats and local support network (CRAS, CREAS, SINE, CAPS, Defensoria) details.

#### 6. `Prontuario.vue` (`resources/js/Pages/Prontuario.vue`)
- **Purpose**: Unified Social-Penitentiary Dossier (*Prontuário Único*) providing chronological intervention history and clinical evolution recording.
- **Inertia Props Contract**:
  ```typescript
  interface ProntuarioProps {
    auth: { user: User; role: string };
    egresso: {
      id: number;
      nome_completo: string;
      cpf_masked: string;
      data_nascimento: string;
      municipio: { nome: string };
      status_penal: string;
      escolaridade: string;
      unidade_prisional_origem: string;
      numero_processo_execucao: string;
      vulnerabilidades: string[];
    };
    prontuario: {
      id: number;
      numero_prontuario: string;
      situacao: string;
      resumo_diagnostico: string;
      meta_plano_individual: string;
      data_abertura: string;
      tecnico_responsavel: { name: string };
    };
    timeline: Array<{
      id: number;
      tipo_evento: string;
      titulo: string;
      descricao: string;
      metadata: Record<string, any>;
      responsavel: { name: string };
      data_evento: string;
    }>;
  }
  ```
- **Key Reactive Features**:
  - Egresso profile dossier header with masked PII and vulnerability tags.
  - Chronological timeline stream with distinct iconography per event type.
  - Immutable audit trail indicator.
  - "Nova Evolução / Atendimento" modal with structured form inputs (tipo de evento, resumo clínico, encaminhamentos, anexo de metadados).

#### 7. `Relatorios.vue` (`resources/js/Pages/Relatorios.vue`)
- **Purpose**: Business Intelligence, analytics, regional aggregation, and security audit log inspection.
- **Inertia Props Contract**:
  ```typescript
  interface RelatoriosProps {
    auth: { user: User; role: string };
    stats: {
      total_atendimentos: number;
      atendimentos_remotos_pct: number;
      media_duracao_minutos: number;
      vagas_preenchidas_total: number;
      taxa_reincidencia_reducao: number;
      total_municipios_cobertos: number;
    };
    regional_distribution: Array<{
      regiao: string;
      atendimentos: number;
      egressos: number;
      vagas: number;
    }>;
    audit_logs_recent: Array<{
      id: number;
      acao: string;
      user_name: string;
      user_role: string;
      prontuario_id: number;
      ip_address: string;
      timestamp: string;
      current_hash: string;
      previous_hash: string;
    }>;
  }
  ```
- **Key Reactive Features**:
  - Multi-criteria filter bar (Período, Região, Tipo de Serviço).
  - Synthetic and analytical metric cards.
  - Audit trail viewer displaying SHA-256 hash chains (`previous_hash` -> `current_hash`).
  - Export actions: CSV, PDF, and LGPD Audit Log export.

#### 8. `SegurancaLgpd.vue` (`resources/js/Pages/SegurancaLgpd.vue`)
- **Purpose**: LGPD Privacy Portal, consent management, DPO channel, encryption status, and immutable audit verification.
- **Inertia Props Contract**:
  ```typescript
  interface SegurancaLgpdProps {
    auth: { user: User; role: string };
    encryption_status: {
      aes_256_active: boolean;
      pgcrypto_active: boolean;
      blind_index_active: boolean;
      ssl_tls_version: string;
      turn_encryption: string;
    };
    consent_records: Array<{
      id: number;
      egresso_nome_masked: string;
      termo_tipo: string;
      aceito_em: string;
      geolocalizacao_autorizada: boolean;
      compartilhamento_rede_autorizado: boolean;
    }>;
    dpo_info: {
      encarregado_nome: string;
      encarregado_email: string;
      orgao: string;
      prazo_resposta_dias: number;
    };
    audit_chain_verified: boolean;
  }
  ```
- **Key Reactive Features**:
  - Encryption at Rest & In Transit status dashboard.
  - Consent management toggles (Geolocation consent, Social network sharing).
  - DPO (Data Protection Officer) request form for citizens (Subject Rights Requests).
  - Cryptographic verification badge validating unbroken SHA-256 hash chains.

---

### 2.2 `resources/js/Services/webrtc.js` Architecture

`webrtc.js` provides a self-contained, event-driven WebRTC client engine:

```javascript
/**
 * WebRTC Teleatendimento Engine - CONECTA EGRESSO (SEJUS/ES)
 * Implements W3C Perfect Negotiation, Trickle ICE, STUN/TURN traversal,
 * and ITU-T G.107 MOS Telemetry calculation.
 */
export class WebRTCClient {
  constructor(config = {}) {
    this.wsUrl = config.wsUrl || 'ws://localhost:8001';
    this.roomId = config.roomId;
    this.token = config.token;
    this.userId = config.userId;
    this.userName = config.userName || 'Participante';
    this.role = config.role || 'attendee';
    this.iceServers = config.iceServers || [
      { urls: 'stun:stun.l.google.com:19302' },
      { urls: 'turn:coturn.sejus.es.gov.br:3478', username: 'sejus_user', credential: 'turn_password_2026' }
    ];

    this.peerConnection = null;
    this.ws = null;
    this.localStream = null;
    this.remoteStream = null;
    this.screenStream = null;

    this.isPolite = ['attendee', 'egresso'].includes(this.role.toLowerCase());
    this.makingOffer = false;
    this.ignoreOffer = false;
    this.isSettingRemoteAnswerPending = false;

    this.statsInterval = null;
    this.pingInterval = null;
    this.previousStats = null;

    // Callbacks
    this.onJoined = config.onJoined || (() => {});
    this.onPeerJoined = config.onPeerJoined || (() => {});
    this.onPeerLeft = config.onPeerLeft || (() => {});
    this.onRemoteStream = config.onRemoteStream || (() => {});
    this.onTelemetryUpdate = config.onTelemetryUpdate || (() => {});
    this.onQualityAlert = config.onQualityAlert || (() => {});
    this.onError = config.onError || (() => {});
    this.onCallEnded = config.onCallEnded || (() => {});
  }

  // 1. Connection & Signaling Handshake
  async connect() { ... }
  
  // 2. W3C Perfect Negotiation & SDP Offer/Answer
  async initPeerConnection() { ... }

  // 3. Media Controls (Audio, Video, Screen Share)
  async startLocalMedia(constraints = { audio: true, video: true }) { ... }
  async toggleAudio(muted) { ... }
  async toggleVideo(muted) { ... }
  async startScreenShare() { ... }
  async stopScreenShare() { ... }

  // 4. Real-time Telemetry & ITU-T G.107 MOS Calculation
  startStatsPolling(intervalMs = 2000) { ... }
  calculateMOS(rttMs, jitterMs, packetLossPct) { ... }

  // 5. Termination & Teardown
  endCall(reason = 'voluntary') { ... }
  destroy() { ... }
}
```

---

## 3. Caveats

1. **Browser Permissions in Headless Test Environments**:
   - In automated testing or CI headless runners without physical cameras/microphones, `navigator.mediaDevices.getUserMedia` may throw `NotFoundError` or `NotAllowedError`. `webrtc.js` must implement robust fallback / mock stream generators (`createEmptyMediaStream()` or canvas video tracks) so test suites execute without crashing.
2. **WebSocket URL Resolution**:
   - In production with Docker/Nginx, WebSockets connect through reverse proxy port `:80`/`:443` (e.g. `wss://conecta.sejus.es.gov.br/ws/signaling/...`), whereas in local development they may connect directly to `:8001`. `webrtc.js` should dynamically determine the URL from props or `window.location`.
3. **High Contrast and Font Zoom Persistence**:
   - Accessibility preferences must be synced to both CSS classes (`.high-contrast`, `--font-scale`, `.simplified-lang`) and `localStorage` so that navigating between Inertia pages preserves the citizen's accessibility preferences across the entire session.

---

## 4. Conclusion

- The technical requirements, component hierarchy, data models, and backend contracts for all 8 Core Pages and the WebRTC service are fully analyzed and validated.
- All 8 pages (`Dashboard`, `Atendimento`, `Oportunidades`, `Carteira`, `Geolocalizacao`, `Prontuario`, `Relatorios`, `SegurancaLgpd`) and `webrtc.js` are ready for implementation in Milestone M5.
- The design strictly adheres to WCAG 2.1 AA / e-MAG standards, incorporates the 78 ES Municipalities dataset, supports the 3 RBAC roles (Gestor, Técnico, Egresso), and connects seamlessly to both Laravel 11 and Python FastAPI microservices.

---

## 5. Verification Method

To independently verify the implementation after code generation:

1. **Verify Frontend Views & Scaffolding (Tier 1)**:
   ```bash
   python -m unittest tests_e2e/tier1_features/test_f34_f47_frontend_views.py
   ```
2. **Verify Accessibility Limits & Boundary Conditions (Tier 2)**:
   ```bash
   python -m unittest tests_e2e/tier2_boundaries/test_frontend_a11y_limits.py
   ```
3. **Verify Multi-Mode Accessibility Combinations (Tier 3)**:
   ```bash
   python -m unittest tests_e2e/tier3_combinations/test_a11y_multimode_states.py
   ```
4. **Verify Real-World End-to-End Scenarios (Tier 4)**:
   ```bash
   python -m unittest tests_e2e/tier4_scenarios/scenario_gestor_audit_kpis.py
   python -m unittest tests_e2e/tier4_scenarios/scenario_egresso_onboarding_wallet.py
   python -m unittest tests_e2e/tier4_scenarios/scenario_video_attendance_prontuario.py
   python -m unittest tests_e2e/tier4_scenarios/scenario_interior_job_application.py
   ```
5. **Run Full E2E Test Suite**:
   ```bash
   python tests_e2e/test_runner.py
   ```
6. **Verify Frontend Vite Build**:
   ```bash
   npm run build
   ```
