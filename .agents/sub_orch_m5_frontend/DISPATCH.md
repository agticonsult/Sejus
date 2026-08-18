## 2026-08-17T17:19:40Z

You are the Sub-orchestrator for Milestone M5: Reactive & Accessible Frontend (Inertia.js + Vue 3).

Your working directory is: d:\Agile\projeto dia 18\.agents\sub_orch_m5_frontend
Project root: d:\Agile\projeto dia 18
Your Parent Conversation ID: 9285f12b-64c2-4188-ba61-bc8ba009b89b

Mandatory Reading:
- d:\Agile\projeto dia 18\ORIGINAL_REQUEST.md
- d:\Agile\projeto dia 18\PROJECT.md
- d:\Agile\projeto dia 18\TEST_INFRA.md

Scope of Milestone M5:
1. Inertia.js + Vue 3 application scaffolding with TailwindCSS styling (in `resources/js/`, `resources/css/`, `vite.config.js`, `package.json`, `resources/views/app.blade.php`).
2. Global Layout (`resources/js/Layouts/AppLayout.vue`): SEJUS/ES header, sidebar navigation, user profile info, quick role switcher (Gestor, Técnico, Egresso).
3. Accessibility Toolbar (`resources/js/Components/AccessibilityToolbar.vue`):
   - High Contrast mode toggle (`.high-contrast`)
   - Font size scaling (+18% zoom toggle)
   - Simplified Language mode toggle (*Linguagem Fácil*) for low digital literacy
4. 8 Functional Core Views (in `resources/js/Pages/`):
   - `Dashboard.vue`: KPI summary cards, attendance chart, regional distribution, activity feed.
   - `Atendimento.vue`: Queue list, call initiation, WebRTC video/audio grid, chat, call controls, signal meter.
   - `Oportunidades.vue`: Job and course list, filters by 78 ES municipalities, modality, application modal.
   - `Carteira.vue`: Visual credential card, QR Code display, PDF download button.
   - `Geolocalizacao.vue`: Interactive map / grid of 78 municipalities, search, statistics, CRAS/SINE details.
   - `Prontuario.vue`: Egresso profile, timeline of past interventions, notes editor, new entry modal.
   - `Relatorios.vue`: Detailed analytics, filters by date/region, export tools, audit log viewer.
   - `SegurancaLgpd.vue`: Privacy policy, consent records, encryption status, tamper-proof log inspection.
5. Public Document Validation View: `resources/js/Pages/ValidarCarteira.vue` (`/validar-carteira/{token}`).
6. WebRTC Client Integration (`resources/js/Services/webrtc.js`): Connects to FastAPI WebSocket signaling (`/ws/signaling/{room_id}`), negotiates peer connection, streams telemetry (MOS, jitter, packet loss), handles reconnects.
7. Automated verification: Frontend test suite / build verification (`npm run build` / lint / mock tests).
