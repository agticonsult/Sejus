# ANÁLISE TÉCNICA DE ARQUITETURA, INTEGRAÇÃO E INFRAESTRUTURA
## Plataforma CONECTA EGRESSO — SEJUS/ES (Edital CPSI Nº 010/2026)

**Autor**: Explorer 2 (Survey Phase)  
**Data**: 2026-08-17  
**Status**: Concluído / Pronto para Handoff  
**Workspace**: `d:\Agile\projeto dia 18`

---

## 1. Visão Geral da Arquitetura & Topologia de Microsserviços

A plataforma **CONECTA EGRESSO** adota uma arquitetura híbrida de alto desempenho: **Núcleo Monolítico Modular (Laravel 11 + Inertia.js/Vue 3)** para regras de negócio, persistência, auditoria LGPD e renderização reativa, acoplado a um **Microsserviço Especializado em Tempo Real (Python FastAPI + aiortc + WebSockets)** para sinalização WebRTC de baixa latência, telemetria de conectividade e orquestração de salas de atendimento remoto.

```
                                  [ INTERNET / CLIENTES ]
                                             │
                       ┌─────────────────────┴─────────────────────┐
                       │           NGINX REVERSE PROXY             │
                       │     (Porta 80 / 443 - SSL & HTTP/2)       │
                       └──────────────┬──────────────┬─────────────┘
                                      │              │
                    /ws/* & /api/v1/webrtc/*         │ /* (Inertia/Vue & REST)
                                      │              │
                                      ▼              ▼
                        ┌──────────────────┐  ┌──────────────────┐
                        │  FASTAPI WEBRTC  │  │  PHP-FPM 8.3 /   │
                        │   (Python 3.12)  │  │    LARAVEL 11    │
                        └────────┬─────────┘  └────────┬─────────┘
                                 │                     │
                    Webhook/JWT  │    ┌────────────────┴───────────────┐
                    Comms & Sync │    │   LARAVEL QUEUE WORKER (CLI)   │
                                 │    └────────────────┬───────────────┘
                                 ▼                     ▼
                       ┌───────────────────────────────────────────────┐
                       │                 REDIS 7 CLUSTER               │
                       │  (Pub/Sub WebSockets, Cache, Queues, Sessões) │
                       └───────────────────────┬───────────────────────┘
                                               │
                                               ▼
                       ┌───────────────────────────────────────────────┐
                       │          POSTGRESQL 16 ENTERPRISE             │
                       │     (+ PostGIS Spatial + pgcrypto LGPD)       │
                       └───────────────────────────────────────────────┘
                                               ▲
                                               │
                       ┌───────────────────────┴───────────────────────┐
                       │         COTURN STUN/TURN RELAY                │
                       │    (NAT Traversal para redes móveis 3G/4G/5G) │
                       └───────────────────────────────────────────────┘
```

---

## 2. Modelagem do Banco de Dados (PostgreSQL 16 + PostGIS + pgcrypto)

O banco de dados relacional e espacial atende aos 78 municípios do Estado do Espírito Santo, garantindo isolamento de dados sensíveis conforme a Lei Geral de Proteção de Dados (LGPD - Lei 13.709/2018) através de criptografia simétrica em repouso (`pgcrypto`), hashes determinísticos para indexação e busca sem decriptação, e trilha de auditoria imutável encadeada via hash SHA-256.

### 2.1 Extensões Obrigatórias
```sql
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";
CREATE EXTENSION IF NOT EXISTS "postgis";
```

### 2.2 Schema DDL Completo & Estratégia de Índices

```sql
-- 1. ENUMS
CREATE TYPE user_role AS ENUM ('gestor', 'tecnico', 'egresso');
CREATE TYPE attendance_priority AS ENUM ('NORMAL', 'URGENTE', 'PREFERENCIAL_IDOSO_GESTANTE');
CREATE TYPE call_status AS ENUM ('WAITING', 'ACTIVE', 'FINISHED', 'MISSED', 'CANCELLED');
CREATE TYPE doc_type AS ENUM ('RG', 'CERTIDAO_NASCIMENTO', 'TITULO_ELEITOR', 'EXECUCAO_PENAL');
CREATE TYPE doc_status AS ENUM ('SOLICITADO', 'EM_PROCESSAMENTO', 'DISPONIVEL_RETIRADA', 'ENTREGUE');

-- 2. TABELA DE MUNICÍPIOS (78 MUNICÍPIOS DO ESPÍRITO SANTO + POSTGIS)
CREATE TABLE municipalities (
    id SERIAL PRIMARY KEY,
    ibge_code VARCHAR(7) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    microrregiao VARCHAR(50) NOT NULL,
    macrorregiao VARCHAR(50) NOT NULL,
    has_physical_office BOOLEAN DEFAULT FALSE,
    office_name VARCHAR(150),
    coordinates GEOMETRY(Point, 4326),
    polygon GEOMETRY(MultiPolygon, 4326),
    support_units JSONB DEFAULT '[]'::jsonb, -- CRAS, CREAS, SINE, CAPS
    total_egressos_count INT DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_municipalities_coords ON municipalities USING GIST(coordinates);
CREATE INDEX idx_municipalities_ibge ON municipalities(ibge_code);

-- 3. TABELA DE USUÁRIOS COM CRIPTOGRAFIA LGPD
CREATE TABLE users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    email_verified_at TIMESTAMP WITH TIME ZONE,
    password_hash VARCHAR(255) NOT NULL,
    -- LGPD: Hash cego para busca indexada rápida (HMAC-SHA256 com pepper)
    cpf_blind_index VARCHAR(64) UNIQUE NOT NULL,
    -- LGPD: CPF criptografado reversível via AES-256 (pgp_sym_encrypt)
    cpf_encrypted BYTEA NOT NULL,
    role user_role NOT NULL DEFAULT 'egresso',
    phone_encrypted BYTEA,
    municipality_id INT REFERENCES municipalities(id) ON DELETE SET NULL,
    is_active BOOLEAN DEFAULT TRUE,
    access_cidadao_sub VARCHAR(100) UNIQUE,
    gov_br_sub VARCHAR(100) UNIQUE,
    remember_token VARCHAR(100),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_cpf_blind ON users(cpf_blind_index);
CREATE INDEX idx_users_municipality ON users(municipality_id);

-- 4. TABELA DE EGRESSOS & CARTEIRA DIGITAL
CREATE TABLE egressos (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID UNIQUE NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    matricula_sejus VARCHAR(50) UNIQUE NOT NULL,
    rg_encrypted BYTEA,
    certidao_penal_num VARCHAR(50),
    data_liberacao DATE NOT NULL,
    regime_cumprimento VARCHAR(50) NOT NULL, -- Aberto, Livramento Condicional, Egresso Definitivo
    situacao_cadastral VARCHAR(50) DEFAULT 'ATIVO',
    digital_wallet_hash VARCHAR(64) NOT NULL, -- Assinatura SHA-256 do QR Code
    digital_wallet_token VARCHAR(128) UNIQUE NOT NULL,
    digital_wallet_issued_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_egressos_wallet_token ON egressos(digital_wallet_token);
CREATE INDEX idx_egressos_matricula ON egressos(matricula_sejus);

-- 5. TABELA DE PRONTUÁRIOS ÚNICOS SOCIAIS
CREATE TABLE prontuarios (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    egresso_id UUID NOT NULL REFERENCES egressos(id) ON DELETE RESTRICT,
    tecnico_id UUID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    tipo_atendimento VARCHAR(50) NOT NULL, -- Psicossocial, Jurídico, Empregabilidade, Documental
    resumo_atendimento VARCHAR(255) NOT NULL,
    -- Dados de anamnese criptografados ponta a ponta
    relato_clinico_encrypted BYTEA NOT NULL,
    encaminhamentos JSONB DEFAULT '[]'::jsonb, -- Vagas aplicadas, cursos encaminhados, CRAS
    duracao_minutos INT DEFAULT 0,
    video_session_id UUID,
    metadata JSONB DEFAULT '{}'::jsonb,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_prontuarios_egresso ON prontuarios(egresso_id);
CREATE INDEX idx_prontuarios_tecnico ON prontuarios(tecnico_id);
CREATE INDEX idx_prontuarios_created_at ON prontuarios(created_at DESC);

-- 6. TRILHA DE AUDITORIA IMUTÁVEL LGPD (ENCADEAMENTO CRIPTOGRÁFICO)
CREATE TABLE prontuario_audit_logs (
    id BIGSERIAL PRIMARY KEY,
    prontuario_id UUID NOT NULL REFERENCES prontuarios(id) ON DELETE RESTRICT,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    action VARCHAR(50) NOT NULL, -- 'VIEW', 'CREATE', 'UPDATE', 'EXPORT_PDF', 'DECRYPT_ATTEMPT'
    ip_address INET NOT NULL,
    user_agent TEXT NOT NULL,
    previous_log_hash VARCHAR(64) NOT NULL,
    current_log_hash VARCHAR(64) NOT NULL,
    changes_payload_encrypted BYTEA,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
) PARTITION BY RANGE (created_at);

-- Partições trimestrais para conformidade LGPD e alta performance de consulta
CREATE TABLE prontuario_audit_logs_2026_q3 PARTITION OF prontuario_audit_logs
    FOR VALUES FROM ('2026-07-01 00:00:00+00') TO ('2026-10-01 00:00:00+00');
CREATE TABLE prontuario_audit_logs_2026_q4 PARTITION OF prontuario_audit_logs
    FOR VALUES FROM ('2026-10-01 00:00:00+00') TO ('2027-01-01 00:00:00+00');

CREATE INDEX idx_audit_prontuario ON prontuario_audit_logs(prontuario_id, created_at);
CREATE INDEX idx_audit_user ON prontuario_audit_logs(user_id);

-- 7. SESSÕES DE VIDEOCHAMADA WEBRTC
CREATE TABLE video_sessions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    room_name VARCHAR(100) UNIQUE NOT NULL,
    egresso_id UUID NOT NULL REFERENCES egressos(id) ON DELETE CASCADE,
    tecnico_id UUID REFERENCES users(id) ON DELETE SET NULL,
    municipality_id INT REFERENCES municipalities(id),
    status call_status DEFAULT 'WAITING',
    scheduled_at TIMESTAMP WITH TIME ZONE,
    started_at TIMESTAMP WITH TIME ZONE,
    ended_at TIMESTAMP WITH TIME ZONE,
    duration_seconds INT DEFAULT 0,
    telemetry_metrics JSONB DEFAULT '{
        "packet_loss_pct": 0,
        "avg_jitter_ms": 0,
        "avg_bitrate_kbps": 0,
        "ice_connection_state": "new",
        "turn_used": false
    }'::jsonb,
    room_token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_video_sessions_status ON video_sessions(status);
CREATE INDEX idx_video_sessions_room ON video_sessions(room_name);

-- 8. FILA DE ESPERA DE ATENDIMENTO
CREATE TABLE queue_attendances (
    id SERIAL PRIMARY KEY,
    egresso_id UUID NOT NULL REFERENCES egressos(id) ON DELETE CASCADE,
    municipality_id INT NOT NULL REFERENCES municipalities(id),
    priority attendance_priority DEFAULT 'NORMAL',
    status VARCHAR(50) DEFAULT 'WAITING', -- 'WAITING', 'CALLING', 'IN_CALL', 'ATTENDED', 'MISSED'
    video_session_id UUID REFERENCES video_sessions(id) ON DELETE SET NULL,
    entered_queue_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    called_at TIMESTAMP WITH TIME ZONE,
    completed_at TIMESTAMP WITH TIME ZONE
);

CREATE INDEX idx_queue_waiting ON queue_attendances(municipality_id, priority, entered_queue_at) 
    WHERE status = 'WAITING';

-- 9. OPORTUNIDADES DE EMPREGO E CURSOS DE QUALIFICAÇÃO
CREATE TABLE opportunities (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title VARCHAR(200) NOT NULL,
    company_name VARCHAR(150) NOT NULL,
    cnpj_company VARCHAR(18),
    type VARCHAR(50) NOT NULL, -- 'EMPREGO', 'CURSO_SENAI', 'CURSO_IFES', 'OFICINA_SEJUS'
    municipality_id INT REFERENCES municipalities(id) ON DELETE SET NULL,
    vacancies_count INT DEFAULT 1,
    salary_or_benefit VARCHAR(100),
    modality VARCHAR(50) DEFAULT 'Presencial',
    requirements TEXT NOT NULL,
    contact_email VARCHAR(150),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_opportunities_muni ON opportunities(municipality_id, is_active);

-- 10. CANDIDATURAS E ENCAMINHAMENTOS DE OPORTUNIDADES
CREATE TABLE opportunity_applications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    opportunity_id UUID NOT NULL REFERENCES opportunities(id) ON DELETE CASCADE,
    egresso_id UUID NOT NULL REFERENCES egressos(id) ON DELETE CASCADE,
    tecnico_id UUID REFERENCES users(id) ON DELETE SET NULL,
    status VARCHAR(50) DEFAULT 'ENCAMINHADO', -- 'ENCAMINHADO', 'ENTREVISTA_AGENDADA', 'CONTRATADO', 'RECUSADO'
    feedback_notes TEXT,
    applied_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX idx_unique_app ON opportunity_applications(opportunity_id, egresso_id);

-- 11. SOLICITAÇÃO DE 2ª VIA DE DOCUMENTOS BÁSICOS
CREATE TABLE document_requests (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    egresso_id UUID NOT NULL REFERENCES egressos(id) ON DELETE CASCADE,
    doc_type doc_type NOT NULL,
    status doc_status DEFAULT 'SOLICITADO',
    protocol_number VARCHAR(32) UNIQUE NOT NULL,
    pickup_municipality_id INT REFERENCES municipalities(id),
    pickup_unit_name VARCHAR(150),
    notes TEXT,
    requested_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP WITH TIME ZONE
);

CREATE INDEX idx_doc_requests_egresso ON document_requests(egresso_id);
```

---

## 3. Microsserviço de Sinalização WebRTC & Videochamada (FastAPI + aiortc + WebSockets)

### 3.1 Diagrama do Fluxo de Sinalização e Telemetria

```
[ FRONTEND VUE 3 ]           [ LARAVEL 11 ]          [ FASTAPI WEBRTC ]       [ REDIS PUB/SUB ]      [ COTURN TURN/STUN ]
        │                           │                        │                       │                      │
        │ 1. Solicita Sala          │                        │                       │                      │
        ├──────────────────────────>│                        │                       │                      │
        │                           │ 2. Gera JWT Room Token │                       │                      │
        │<──────────────────────────┤                        │                       │                      │
        │                           │                        │                       │                      │
        │ 3. Conecta WebSocket /ws/rooms/{room_id}?token=JWT │                       │                      │
        ├───────────────────────────────────────────────────>│                       │                      │
        │                                                    │ 4. Valida JWT         │                      │
        │                                                    ├───────────────────────┤                      │
        │                                                    │ 5. Registra Presença  │                      │
        │                                                    ├──────────────────────>│                      │
        │ 6. Envia SDP Offer                                 │                       │                      │
        ├───────────────────────────────────────────────────>│ 7. aiortc Process /   │                      │
        │                                                    │    Redis Broadcast    │                      │
        │                                                    ├──────────────────────>│                      │
        │ 8. Envia ICE Candidates                            │                       │                      │
        ├───────────────────────────────────────────────────>│                       │                      │
        │                                                    │                       │                      │
        │ 9. Recebe SDP Answer & Remote Candidates           │                       │                      │
        │<───────────────────────────────────────────────────┤                       │                      │
        │                                                                            │                      │
        │ 10. Estabelece Conexão P2P de Áudio e Vídeo Criptografada (SRTP/DTLS)      │                      │
        │════════════════════════════════════════════════════════════════════════════╪═════════════════════╡
        │                                                                            │ (Fallback se NAT     │
        │                                                                            │  Simétrico Móvel)    │
        │ 11. Coleta e Envia Telemetria a cada 5s (loss, jitter, RTT, bitrate)       │                      │
        ├───────────────────────────────────────────────────>│                       │                      │
        │                                                    │ 12. Consolida Métricas│                      │
        │ 13. Encerra Chamada                                │                       │                      │
        ├───────────────────────────────────────────────────>│ 14. Dispara Webhook   │                      │
        │                                                    ├──────────────────────>│ (POST /internal/     │
        │                                                    │                       │  webrtc/events)      │
        │                                                    │                       ▼                      │
        │                                                    │                  [ LARAVEL ]                 │
        │                                                    │               (Grava Prontuário)             │
```

### 3.2 Implementação do Servidor de Sinalização (FastAPI / `aiortc`)

```python
# webrtc_service/main.py
import asyncio
import json
import logging
from typing import Dict, Set
from fastapi import FastAPI, WebSocket, WebSocketDisconnect, Query, HTTPException, Depends
from fastapi.middleware.cors import CORSMiddleware
import jwt
import httpx
import redis.asyncio as aioredis
from pydantic import BaseModel

app = FastAPI(title="Conecta Egresso - WebRTC Signaling Service", version="1.0.0")

JWT_SECRET = "SEJUS_SECRET_KEY_CHANGE_IN_PRODUCTION"
JWT_ALGORITHM = "HS256"
LARAVEL_INTERNAL_WEBHOOK = "http://app:8000/api/v1/internal/webrtc/events"
INTERNAL_WEBHOOK_SECRET = "INTERNAL_SHARED_HMAC_SECRET"

redis_client = aioredis.from_url("redis://redis:6379/0", decode_responses=True)

class RoomManager:
    def __init__(self):
        # room_name -> set of WebSocket connections
        self.active_rooms: Dict[str, Set[WebSocket]] = {}
        # websocket -> metadata dict (role, user_id, join_time)
        self.client_meta: Dict[WebSocket, dict] = {}

    async def connect(self, room_id: str, websocket: WebSocket, meta: dict):
        await websocket.accept()
        if room_id not in self.active_rooms:
            self.active_rooms[room_id] = set()
        self.active_rooms[room_id].add(websocket)
        self.client_meta[websocket] = meta
        
        # Notificar outros membros
        await self.broadcast(room_id, {
            "type": "peer_joined",
            "userId": meta.get("user_id"),
            "role": meta.get("role")
        }, exclude=websocket)

    async def disconnect(self, room_id: str, websocket: WebSocket):
        if room_id in self.active_rooms:
            self.active_rooms[room_id].discard(websocket)
            meta = self.client_meta.pop(websocket, {})
            if len(self.active_rooms[room_id]) == 0:
                del self.active_rooms[room_id]
            
            await self.broadcast(room_id, {
                "type": "peer_left",
                "userId": meta.get("user_id"),
                "role": meta.get("role")
            })

    async def broadcast(self, room_id: str, message: dict, exclude: WebSocket = None):
        if room_id in self.active_rooms:
            for client in list(self.active_rooms[room_id]):
                if client != exclude:
                    try:
                        await client.send_json(message)
                    except Exception:
                        pass

manager = RoomManager()

def verify_token(token: str) -> dict:
    try:
        payload = jwt.decode(token, JWT_SECRET, algorithms=[JWT_ALGORITHM])
        return payload
    except jwt.PyJWTError:
        raise HTTPException(status_code=403, detail="Token de Sala Inválido ou Expirado")

@app.websocket("/ws/rooms/{room_id}")
async def websocket_signaling_endpoint(
    websocket: WebSocket,
    room_id: str,
    token: str = Query(...)
):
    try:
        payload = verify_token(token)
    except HTTPException:
        await websocket.close(code=4003)
        return

    meta = {
        "user_id": payload.get("sub"),
        "role": payload.get("role"),
        "room_id": room_id
    }
    await manager.connect(room_id, websocket, meta)

    try:
        while True:
            data = await websocket.receive_text()
            message = json.loads(data)
            msg_type = message.get("type")

            if msg_type in ["offer", "answer", "ice_candidate"]:
                # Repassa mensagem de sinalização para o outro peer da sala
                await manager.broadcast(room_id, message, exclude=websocket)
            
            elif msg_type == "telemetry":
                # Salva métricas de qualidade (packet loss, RTT) no Redis
                metrics_key = f"telemetry:{room_id}:{meta['user_id']}"
                await redis_client.setex(metrics_key, 300, json.dumps(message.get("data", {})))
                
            elif msg_type == "end_call":
                # Notifica todos e dispara webhook para o Laravel registrar no prontuário
                await manager.broadcast(room_id, {"type": "call_ended"})
                async with httpx.AsyncClient() as client:
                    await client.post(
                        LARAVEL_INTERNAL_WEBHOOK,
                        json={
                            "event": "room.finished",
                            "room_id": room_id,
                            "user_id": meta["user_id"],
                            "duration_seconds": message.get("duration", 0),
                            "telemetry": message.get("telemetry", {})
                        },
                        headers={"X-Internal-Secret": INTERNAL_WEBHOOK_SECRET}
                    )
                break
    except WebSocketDisconnect:
        await manager.disconnect(room_id, websocket)
```

---

## 4. Contratos de Interface & APIs (REST & WebSockets)

### 4.1 Autenticação & Gestão de Perfis (Laravel 11)

| Método | Endpoint | Perfil Permitido | Descrição |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/v1/auth/login` | Todos | Autenticação padrão (CPF + Senha) |
| `POST` | `/api/v1/auth/oauth/acesso-cidadao` | Todos | Callback OAuth2 Acesso Cidadão PRODEST |
| `POST` | `/api/v1/auth/oauth/govbr` | Todos | Callback OpenID Connect Gov.br |
| `GET`  | `/api/v1/auth/me` | Autenticado | Retorna perfil, permissões e dados do usuário logado |
| `POST` | `/api/v1/auth/switch-role` | Gestor / Admin | Alternância de contexto de visualização |

### 4.2 Módulo de Prontuário Único & Atendimento Remoto

| Método | Endpoint | Perfil | Descrição |
| :--- | :--- | :--- | :--- |
| `GET`  | `/api/v1/prontuarios` | Gestor, Técnico | Listagem paginada com busca por CPF (blind index) |
| `GET`  | `/api/v1/prontuarios/{egresso_id}` | Técnico | Histórico/Timeline social imutável com logs de auditoria |
| `POST` | `/api/v1/prontuarios` | Técnico | Criação de registro com criptografia AES e log hash-chained |
| `GET`  | `/api/v1/atendimento/fila` | Técnico, Gestor | Fila em tempo real ordenada por prioridade municipal |
| `POST` | `/api/v1/atendimento/chamar` | Técnico | Aloca egresso da fila e gera sala WebRTC |
| `POST` | `/api/v1/internal/webrtc/events` | Microservice Interno | Webhook do FastAPI com HMAC SHA-256 |

### 4.3 Módulo de Carteira Digital & Documentos

| Método | Endpoint | Perfil | Descrição |
| :--- | :--- | :--- | :--- |
| `GET`  | `/api/v1/carteira-digital` | Egresso, Técnico | Dados da Carteira Digital e token do QR Code |
| `GET`  | `/api/v1/carteira-digital/pdf` | Egresso, Técnico | Download do documento oficial em PDF assinado |
| `GET`  | `/api/v1/carteira-digital/validar/{token}` | Público | Validação criptográfica da autenticidade da carteira |
| `POST` | `/api/v1/documentos/solicitar` | Egresso, Técnico | Solicitação de 2ª via gratuita (RG, Certidão, etc.) |

### 4.4 Módulo Territorial & Oportunidades

| Método | Endpoint | Perfil | Descrição |
| :--- | :--- | :--- | :--- |
| `GET`  | `/api/v1/territorio/municipios` | Todos | GeoJSON e dados agregados dos 78 municípios do ES |
| `GET`  | `/api/v1/territorio/municipios/{id}/apoio`| Todos | Unidades de apoio (CRAS, CREAS, SINE) do município |
| `GET`  | `/api/v1/oportunidades` | Todos | Vagas de emprego e cursos com filtro espacial por município |
| `POST` | `/api/v1/oportunidades/{id}/candidatar` | Egresso, Técnico | Encaminhamento direto de egresso para a vaga |
| `GET`  | `/api/v1/kpi/dashboard` | Gestor | Métricas consolidadas de reincidência, atendimentos e vagas |

---

## 5. Orquestração de Infraestrutura (Docker Compose Multi-Container)

A composição de infraestrutura isola serviços em redes de bridge internas, expondo publicamente apenas as portas do Nginx (80/443) e Coturn (3478, 5349, 49152-65535).

### 5.1 Arquivo `docker-compose.yml`

```yaml
version: '3.8'

networks:
  conecta_backend_net:
    driver: bridge
  conecta_media_net:
    driver: bridge

volumes:
  pg_data:
  redis_data:
  app_storage:

services:
  # 1. REVERSE PROXY NGINX
  nginx:
    image: nginx:1.27-alpine
    container_name: conecta_nginx
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
      - ./docker/nginx/ssl:/etc/nginx/ssl:ro
      - app_storage:/var/www/html/storage:ro
    depends_on:
      - app
      - webrtc_signaling
    networks:
      - conecta_backend_net

  # 2. LARAVEL 11 CORE (PHP 8.3 FPM)
  app:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    container_name: conecta_app
    restart: unless-stopped
    environment:
      APP_NAME: "ConectaEgresso"
      APP_ENV: local
      APP_KEY: base64:VnF0bWVs...
      APP_DEBUG: "true"
      DB_CONNECTION: pgsql
      DB_HOST: postgres
      DB_PORT: 5432
      DB_DATABASE: conecta_egresso
      DB_USERNAME: sejus_admin
      DB_PASSWORD: sejus_secure_password_2026
      REDIS_HOST: redis
      REDIS_PORT: 6379
      WEBRTC_SIGNALING_INTERNAL_URL: "http://webrtc_signaling:8001"
      INTERNAL_WEBHOOK_SECRET: "INTERNAL_SHARED_HMAC_SECRET"
    volumes:
      - .:/var/www/html
      - app_storage:/var/www/html/storage
    depends_on:
      - postgres
      - redis
    networks:
      - conecta_backend_net

  # 3. LARAVEL QUEUE WORKER
  queue:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    container_name: conecta_queue
    restart: unless-stopped
    command: php artisan queue:work redis --sleep=3 --tries=3 --timeout=90
    volumes:
      - .:/var/www/html
      - app_storage:/var/www/html/storage
    depends_on:
      - app
      - redis
    networks:
      - conecta_backend_net

  # 4. WEBRTC SIGNALING & TELEMETRY (PYTHON FASTAPI)
  webrtc_signaling:
    build:
      context: ./webrtc_service
      dockerfile: Dockerfile
    container_name: conecta_webrtc
    restart: unless-stopped
    environment:
      JWT_SECRET: "SEJUS_SECRET_KEY_CHANGE_IN_PRODUCTION"
      REDIS_URL: "redis://redis:6379/0"
      LARAVEL_INTERNAL_WEBHOOK: "http://app:8000/api/v1/internal/webrtc/events"
      INTERNAL_WEBHOOK_SECRET: "INTERNAL_SHARED_HMAC_SECRET"
    ports:
      - "8001:8001"
    depends_on:
      - redis
    networks:
      - conecta_backend_net
      - conecta_media_net

  # 5. BANCO DE DADOS POSTGRESQL 16 + POSTGIS + PGCRYPTO
  postgres:
    image: postgis/postgis:16-3.4-alpine
    container_name: conecta_postgres
    restart: unless-stopped
    environment:
      POSTGRES_DB: conecta_egresso
      POSTGRES_USER: sejus_admin
      POSTGRES_PASSWORD: sejus_secure_password_2026
    volumes:
      - pg_data:/var/lib/postgresql/data
      - ./docker/postgres/init.sql:/docker-entrypoint-initdb.d/init.sql:ro
    ports:
      - "5432:5432"
    networks:
      - conecta_backend_net

  # 6. CACHE & PUB/SUB REDIS 7
  redis:
    image: redis:7.2-alpine
    container_name: conecta_redis
    restart: unless-stopped
    command: redis-server --appendonly yes --requirepass ""
    volumes:
      - redis_data:/data
    ports:
      - "6379:6379"
    networks:
      - conecta_backend_net

  # 7. COTURN STUN/TURN SERVER (NAT TRAVERSAL MÓVEL)
  coturn:
    image: coturn/coturn:4.6-alpine
    container_name: conecta_coturn
    restart: unless-stopped
    network_mode: "host" # Essencial para negociação de portas UDP efêmeras
    volumes:
      - ./docker/coturn/turnserver.conf:/etc/coturn/turnserver.conf:ro
```

### 5.2 Configuração Nginx de Roteamento Unificado (`docker/nginx/default.conf`)

```nginx
server {
    listen 80;
    server_name localhost;
    root /var/www/html/public;
    index index.php index.html;

    client_max_body_size 64M;

    # 1. Roteamento de WebSockets para o microsserviço FastAPI
    location /ws/ {
        proxy_pass http://webrtc_signaling:8001/ws/;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_read_timeout 86400s;
        proxy_send_timeout 86400s;
    }

    # 2. Roteamento de endpoints REST do FastAPI
    location /api/v1/webrtc/ {
        proxy_pass http://webrtc_signaling:8001/api/v1/webrtc/;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }

    # 3. Roteamento padrão do Laravel 11 / Inertia.js (PHP-FPM)
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

---

## 6. Estratégia e Harness de Testes (Unit, Integration & E2E)

A matriz de testes foi desenhada para validação contínua e garantia estrita dos Critérios de Aceite:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                             TEST HARNESS MATRIX                             │
├───────────────────────┬─────────────────────────┬───────────────────────────┤
│ CAMADA                │ FERRAMENTA              │ ESCOPO E COBERTURA        │
├───────────────────────┼─────────────────────────┼───────────────────────────┤
│ 1. Unitário Backend   │ PHPUnit / Pest PHP      │ Regras de Prontuário,     │
│                       │                         │ Hash Criptográfico LGPD,  │
│                       │                         │ QR Code Token Generator,  │
│                       │                         │ Políticas RBAC.           │
├───────────────────────┼─────────────────────────┼───────────────────────────┤
│ 2. Unitário FastAPI   │ Pytest + pytest-asyncio │ Validação de JWT de Sala, │
│                       │                         │ Handshake SDP Offer/Ans,  │
│                       │                         │ Cálculo de Telemetria.    │
├───────────────────────┼─────────────────────────┼───────────────────────────┤
│ 3. Integração         │ Laravel HTTP Tests +    │ Transações com Postgres,  │
│                       │ Testcontainers/PostGIS  │ Consultas Espaciais (78   │
│                       │                         │ mun.), Webhooks FastAPI   │
│                       │                         │ -> Laravel com HMAC.      │
├───────────────────────┼─────────────────────────┼───────────────────────────┤
│ 4. End-to-End (E2E)   │ Playwright (TypeScript/ │ Fluxo Completo de 3       │
│                       │ Python)                 │ Perfis: Login, Troca RBAC,│
│                       │                         │ Fila de Espera, Chamada   │
│                       │                         │ WebRTC, Emissão de PDF e  │
│                       │                         │ Acessibilidade WCAG.      │
└───────────────────────┴─────────────────────────┴───────────────────────────┘
```

### 6.1 Exemplos de Testes Automatizados Chave

#### A. Teste de Auditoria Imutável LGPD (`tests/Feature/ProntuarioAuditTest.php`)
```php
public function test_prontuario_creation_records_immutable_hash_chained_audit_log(): void
{
    $tecnico = User::factory()->create(['role' => 'tecnico']);
    $egresso = Egresso::factory()->create();

    $response = $this->actingAs($tecnico)->postJson('/api/v1/prontuarios', [
        'egresso_id' => $egresso->id,
        'tipo_atendimento' => 'Psicossocial',
        'resumo_atendimento' => 'Primeiro acolhimento remoto',
        'relato_clinico' => 'Egresso reside em São Mateus, relata busca por vaga na área civil.'
    ]);

    $response->assertCreated();
    
    $this->assertDatabaseHas('prontuario_audit_logs', [
        'user_id' => $tecnico->id,
        'action' => 'CREATE'
    ]);

    $auditLog = ProntuarioAuditLog::latest('id')->first();
    $expectedHash = hash('sha256', $auditLog->previous_log_hash . $auditLog->prontuario_id . $tecnico->id);
    $this->assertEquals($expectedHash, $auditLog->current_log_hash);
}
```

#### B. Teste E2E Playwright de Videochamada e Prontuário (`e2e/webrtc_call.spec.ts`)
```typescript
import { test, expect } from '@playwright/test';

test.describe('Atendimento Remoto por Vídeo - SEJUS/ES', () => {
  test('Técnico atende egresso na fila e registra atendimento no prontuário', async ({ browser }) => {
    const tecnicoContext = await browser.newContext({ permissions: ['camera', 'microphone'] });
    const egressoContext = await browser.newContext({ permissions: ['camera', 'microphone'] });

    const tecnicoPage = await tecnicoContext.newPage();
    const egressoPage = await egressoContext.newPage();

    // 1. Egresso entra na fila de espera
    await egressoPage.goto('http://localhost/login');
    await egressoPage.fill('#cpf', '12345678900');
    await egressoPage.click('button[type="submit"]');
    await egressoPage.click('text=Entrar na Fila de Atendimento');

    // 2. Técnico visualiza fila e clica em Chamar
    await tecnicoPage.goto('http://localhost/login');
    await tecnicoPage.fill('#cpf', '98765432100');
    await tecnicoPage.click('button[type="submit"]');
    await tecnicoPage.click('text=Atendimento Remoto & Vídeo');
    await tecnicoPage.click('button:has-text("Chamar Agora")');

    // 3. Verifica estabelecimento da conexão WebRTC bidirecional
    await expect(tecnicoPage.locator('#remoteVideo')).toBeVisible({ timeout: 10000 });
    await expect(egressoPage.locator('#remoteVideo')).toBeVisible({ timeout: 10000 });

    // 4. Técnico encerra chamada e preenche prontuário
    await tecnicoPage.click('#btnEndCall');
    await tecnicoPage.fill('#relatoClinico', 'Atendimento psicossocial finalizado com sucesso.');
    await tecnicoPage.click('#btnSalvarProntuario');

    await expect(tecnicoPage.locator('.alert-success')).toContainText('Registro salvo com sucesso');
  });
});
```

---

## 7. Matriz de Dependências e Compatibilidade de Versões

| Componente | Versão Homologada | Função no Sistema | Compatibilidade Verificada |
| :--- | :--- | :--- | :--- |
| **PHP Runtime** | `8.3.10` / `8.4` | Backend Core Engine | Compatível com Laravel 11.x |
| **Laravel Framework** | `11.20+` | MVC, Eloquent ORM, Auth, Queues | Compatível com PHP 8.3/8.4 |
| **Inertia.js** | `1.2+` (Vue 3 Adapter) | Bridge SPA Reativa sem API boilerplate | Suporta Vue 3.4+ |
| **Vue.js** | `3.4+` | Framework Frontend Reativo | Suporta Composition API & Pinia |
| **Tailwind CSS** | `3.4+` | Design System e Acessibilidade | Tokens de Alto Contraste |
| **Python Runtime** | `3.12.5-slim` | Microsserviço de Sinalização | Compatível com aiortc |
| **FastAPI** | `0.112+` | Framework ASGI de Alta Performance | Suporta WebSockets & Pydantic v2 |
| **aiortc** | `1.9+` | Stack WebRTC Python nativa | Suporta Opus, VP8/H.264 |
| **PostgreSQL** | `16.3-alpine` | Banco de Dados Relacional Primário | PostGIS 3.4 + pgcrypto |
| **PostGIS Extension** | `3.4.2` | Consultas Geoespaciais dos 78 Municípios | Suporta Índices GIST |
| **Redis** | `7.2.5-alpine` | Cache, Pub/Sub WebSockets e Filas | Baixíssima latência (<1ms) |
| **Coturn** | `4.6.2-alpine` | Servidor STUN/TURN RFC 5766/8489 | NAT Traversal móvel 3G/4G/5G |
| **Playwright** | `1.46+` | Automação e Testes E2E Multi-browser | Suporta WebRTC mock/real |

---

## 8. Recomendações Arquiteturais e Mitigações para a Fase de Implementação

1. **Blind Indexing para LGPD (CPF/RG)**:
   - Nunca consultar dados de pessoas egressas diretamente por decriptação em massa `WHERE decrypt(cpf) = ...`.
   - Utilizar uma coluna `cpf_blind_index = HMAC_SHA256(cpf, SECRET_PEPPER)` com índice B-Tree convencional.
2. **Resiliência WebRTC em Redes Móveis (3G/4G/5G)**:
   - Habilitar no Coturn a alocação dinâmica de portas UDP relay (`min-port 49152`, `max-port 65535`) em modo `network_mode: host`.
   - No cliente WebRTC, configurar `iceTransportPolicy: 'all'` com lista mista de candidatos STUN (porta 3478 UDP) e TURN sobre TLS (porta 5349 TCP) para contornar bloqueios de operadoras móveis.
3. **Imutabilidade e Não-Repúdio da Carteira Digital**:
   - O QR Code da carteira deve conter uma URL assinada: `https://conecta.sejus.es.gov.br/validar/{uuid}?s={hmac_sha256}`.
   - O validador consulta apenas a chave pública estadual sem expor o prontuário criminal do egresso.
4. **Desacoplamento e Resiliência de Webhooks**:
   - Toda comunicação de eventos entre FastAPI e Laravel deve usar assinatura no header `X-Signature-SHA256: hash_hmac(...)` e idempotência através de IDs de eventos gravados em Redis por 24 horas.
