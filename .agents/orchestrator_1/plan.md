# Plan: CONECTA EGRESSO (SEJUS/ES)

## Phase 0: Survey & Requirement Mining
- [ ] 0.1 Dispatch 3 parallel Explorers / Spec Miners to map existing project structure, analyze tools and dependencies, and detail requirements from ORIGINAL_REQUEST.md.
- [ ] 0.2 Synthesize findings and create `PROJECT.md` and `TEST_INFRA.md`.

## Phase 1: Dual Track Decomposition
- [ ] 1.1 Implementation Track:
  - Milestone 1: Docker Infrastructure & Environment (Nginx, PHP 8.3 FPM, Python FastAPI WebRTC, PostgreSQL 16 PostGIS/pgcrypto, Redis, Coturn).
  - Milestone 2: Backend Core (Laravel 11, Auth OAuth2/OIDC/RBAC, Migrations & LGPD Audit Logging, Prontuário Único, Carteira Digital PDF/QR, Mapeamento 78 municípios, Vagas/Cursos).
  - Milestone 3: WebRTC Microservice (Python FastAPI / aiortc / WebSockets / Webhook JWT telemetry & logging).
  - Milestone 4: Frontend Reativo & Acessível (Inertia.js + Vue 3, TailwindCSS, Accessibility/High Contrast/Simple Language, Dashboard KPIs, Timeline, Map, Video room).
  - Milestone 5: Integration & Full Wiring.
- [ ] 1.2 E2E Testing Track:
  - E2E Test infrastructure & runners.
  - Tiers 1-4 Test Suite (Feature, Boundary, Combinatorial, Real-World application scenarios).
  - Publish `TEST_READY.md`.

## Phase 2: Final Acceptance & Hardening
- [ ] 2.1 Pass 100% E2E test suite (Tiers 1-4).
- [ ] 2.2 Adversarial coverage hardening (Tier 5 - Challenger loop).
- [ ] 2.3 Forensic Audit check.
- [ ] 2.4 Final Completion Report to Sentinel.
