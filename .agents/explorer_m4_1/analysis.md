# Análise de Especificações e Contratos de Dados — Microsserviço WebRTC & Sinalização (M4)
## Plataforma CONECTA EGRESSO — Secretaria de Estado da Justiça (SEJUS/ES)

**Documento:** Especificação Técnica de Contratos de Dados, Protocolos e Interoperabilidade  
**Milestone:** M4 — Microsserviço WebRTC, Sinalização & Telemetria em Python (FastAPI)  
**Autor:** Explorer 1 (Spec Miner)  
**Data:** 17 de Agosto de 2026  
**Status:** Especificação Final Homologada  

---

## 1. Fontes Autoritativas e Contexto de Interoperabilidade

A mineração e formalização técnica deste documento baseia-se nas seguintes fontes autoritativas:
1. **`ORIGINAL_REQUEST.md` (Requisito R2 & Critérios de Aceitação):** Estabelece o microsserviço assíncrono em Python (FastAPI / WebSockets / aiortc), controle de salas de videoconferência seguras, sinalização SDP/ICE, fila de espera em tempo real, telemetria de conexão (MOS/RTT/perda de pacotes) e integração via webhooks assinados e JWT com o backend Laravel 11.
2. **`PROJECT.md` (Interface Contracts & Code Layout):** Define o fluxo de emissão de tokens (`POST /api/webrtc/token`), ingestão de webhooks (`POST /api/webhooks/webrtc` com cabeçalho `X-Signature-SHA256`), protocolo de mensagens WebSocket e layout de diretórios `webrtc_service/`.
3. **`DOCUMENTO_EXECUTIVO_CONECTA_EGRESSO.md` & `TR_EDITAL_DE CPSI Nº 010_2026 - SEJUS.pdf`:** Define a superação da barreira geográfica dos Escritórios Sociais (de 4 sedes físicas para os 78 municípios capixabas), triagem e fila de acolhimento psicossocial remoto, suporte a conexões móveis (3G/4G/5G) com NAT Traversal e registro inviolável de atendimentos no Prontuário Único.
4. **Modelos e Migrações de Banco de Dados (`sub_orch_m1_m2/SCOPE.md`):** Tabelas `video_rooms`, `video_attendees`, `prontuarios`, `prontuario_timeline` e `prontuario_audit_logs`.

---

## 2. Features Discovered (Tabela de Funcionalidades Descobertas)

| # | Categoria | Funcionalidade | Descrição | Entradas (Inputs) | Saídas (Outputs) | Comportamento em Erro | Descoberta Via |
|---|---|---|---|---|---|---|---|
| **F01** | Autenticação WS | Validação de Token JWT no Handshake | Autenticação e autorização prévia na abertura de conexão WebSocket (`/ws/signaling/{room_id}` ou `/ws/queue/{unit_id}`). | Query param `token=<jwt>` ou header `Authorization: Bearer <jwt>`. | Conexão aceita (Handshake 101 Switching Protocols) e contexto do usuário atrelado à sessão. | Rejeição com close code `4001` (Unauthorized) ou `4003` (Forbidden) e log de segurança. | `PROJECT.md` § 2, `SCOPE.md` |
| **F02** | Gestão de Salas | Ciclo de Vida de Salas de Vídeo | Máquina de estados finitos (`created`, `waiting`, `in_progress`, `reconnecting`, `ended`, `aborted`, `expired`). | Ações dos participantes (join, leave, timeout, admit). | Transição de estado, broadcast no Redis Pub/Sub e disparo de webhooks. | Transição inválida descartada com aviso `invalid_state_transition`. | `ORIGINAL_REQUEST.md` R2, `PROJECT.md` |
| **F03** | RBAC de Sala | Controle de Papéis de Participantes | Isolamento estrito de permissões na sala: Técnico (Host/Controle), Egresso (Cliente/Mídia), Observador (Defensoria/Somente Leitura). | Papel (`role`) extraído do token JWT verificado. | Políticas de encaminhamento de mídia e comandos administrativos restritos ao Host. | Bloqueio de comandos não autorizados (`403 Action Not Allowed`). | `TR CPSI 010/2026` Item 3.1 `l`, `app.js` |
| **F04** | Sinalização SDP | Troca de Ofertas e Respostas SDP | Roteamento assíncrono de mensagens `offer` e `answer` entre pares da mesma sala para negociação de codecs (VP8/Opus). | Mensagem JSON `{type: "offer"|"answer", sdp: "...", to_user_id: 123}`. | Encaminhamento imediato ao socket do destinatário via memória local ou Redis Pub/Sub. | Retorno de erro `peer_not_found` caso o destinatário não esteja conectado. | `PROJECT.md` § 3, `ORIGINAL_REQUEST.md` R2 |
| **F05** | Sinalização ICE | Trickle ICE Candidate Routing | Encaminhamento contínuo de candidatos ICE para estabelecimento do caminho de rede ótimo (Host, Srflx ou Relay TURN). | Mensagem JSON `{type: "ice_candidate", candidate: {...}, to_user_id: 123}`. | Entrega assíncrona ao par remoto. | Descarte seguro se a sessão já estiver encerrada (`session_inactive`). | `ORIGINAL_REQUEST.md` R2, R4 |
| **F06** | Fila de Espera | Gestão de Fila em Tempo Real | Recepção de egressos nas salas de espera dos 78 municípios (4 físicos + 74 virtuais), ordenados por prioridade e timestamp. | Conexão WS em `/ws/queue/{unit_id}`, seleção de motivo e prioridade (`urgente`, `preferencial`, `normal`). | Mensagens de posição (`position_update`), estimativa de espera e lista ao vivo para técnicos. | Desconexão não tratada entra em tolerância (grace period de 60s) antes do drop. | `ORIGINAL_REQUEST.md` R2, `index.html` L404-446 |
| **F07** | Admissão de Fila | Transferência Fila -> Sala de Atendimento | Comando executado pelo técnico para chamar o próximo egresso e transferi-lo para a sala de atendimento privativa. | Ação do técnico `{type: "admit_attendee", ticket_id: "...", room_id: "..."}`. | Notificação push `{type: "admitted", room_id: "...", token: "..."}` enviada ao egresso. | Erro `ticket_not_found` ou `ticket_already_admitted`. | `index.html` L448-468, `app.js` L298 |
| **F08** | Telemetria | Ingestão e Processamento `getStats()` | Coleta periódica (a cada 5s) de métricas de qualidade WebRTC enviadas pelos clientes (RTT, Jitter, Perda de Pacotes, Bitrate). | Payload `{type: "telemetry_report", rtt_ms: 38, jitter_ms: 5, packet_loss_pct: 0.2, ...}`. | Processamento em memória e cálculo do score instantâneo de qualidade. | Métricas fora do range sanitizadas ou descartadas. | `ORIGINAL_REQUEST.md` R2, `PROJECT.md` § 3 |
| **F09** | Avaliação MOS | Algoritmo ITU-T G.107 (E-Model) | Cálculo contínuo do índice MOS (Mean Opinion Score de 1.0 a 5.0) a partir de RTT, Jitter e Packet Loss para conexões 3G/4G/5G. | Variáveis de rede agregadas da sessão. | Rating de qualidade (Ex: 4.85 Excelente, 3.2 Regular, 1.8 Crítico) e alerta de degradação. | Fallback para estimativa baseada em RTT quando métricas completas estiverem ausentes. | `ORIGINAL_REQUEST.md` R2, `DOCUMENTO_EXECUTIVO.md` |
| **F10** | Redis Pub/Sub | Sincronização Multi-Instância | Distribuição de eventos de sinalização, estado de salas e filas entre múltiplos workers/nós do FastAPI via canais Redis. | Eventos publicados em `room:{room_id}:events` e `queue:{unit_id}:events`. | Broadcast em tempo real para os websockets locais inscritos. | Reconexão automática com backoff exponencial se o Redis reiniciar. | `PROJECT.md` § 2, `SCOPE.md` |
| **F11** | Webhooks | Despachante Assinado HMAC-SHA256 | Notificação assíncrona e confiável ao Laravel (`POST /api/webhooks/webrtc`) dos eventos de ciclo de vida e telemetria consolidada. | Eventos internos (`session.started`, `session.ended`, `session.error`, `attendee.joined_queue`, etc.). | Requisição HTTP POST com cabeçalho `X-Signature: sha256=...` e payload canônico JSON. | Fila de retentativa com backoff exponencial em caso de falha (HTTP != 200). | `ORIGINAL_REQUEST.md` R2, `PROJECT.md` § 2 |
| **F12** | Limpeza de Salas | Daemon de Expiração e Cleanup | Varredura e encerramento automático de salas inativas, zumbis ou que ultrapassaram o tempo máximo limite (2 horas). | Varredura periódica de timers em background. | Notificação `room_terminated`, disparo de webhook `session.ended` e desalocação de memória/Redis. | N/A (Operação de segurança e liberação de recursos). | `PROJECT.md` Feature F33 |

---

## 3. Edge Cases & Comportamento Observado

| # | Feature | Cenário / Entrada (Edge Case) | Comportamento Esperado & Especificado |
|---|---|---|---|
| **E01** | Autenticação WS | Token JWT assinado com chave incorreta ou manipulado | Handshake rejeitado imediatamente com status code `4001 Unauthorized` antes de alocar recursos. |
| **E02** | Autenticação WS | Token expirado (`exp < current_time`) | Rejeição com close code `4001` e mensagem explicativa `"Token expired"`. |
| **E03** | Autenticação WS | Token com `room_id` divergente do endpoint `/ws/signaling/{room_id}` | Rejeição com close code `4003 Forbidden` (`"Token not authorized for this room"`). |
| **E04** | Sinalização | Egresso perde conexão móvel 4G abruptamente (queda de sinal no interior) | Sala transiciona para estado `reconnecting`; inicia timer de 45 segundos; se reconectar com mesmo token, restaura ICE/SDP; se expirar, dispara `session.ended` com hangup_reason `peer_connection_lost`. |
| **E05** | Sinalização | Terceiro tenta entrar em sala ativa já com 2 participantes | Se o token não tiver papel `observer`, rejeita conexão com close code `4003` (`"Room is full / locked"`). |
| **E06** | Fila de Espera | Egresso fecha aba do navegador enquanto aguarda na fila | WebSocket fecha; fila entra em estado de espera por 60s; se não houver reconexão, remove da fila e recalcula posições dos demais. |
| **E07** | Fila de Espera | Múltiplos egressos entram simultaneamente com mesma prioridade | Desempate estrito por timestamp FIFO (`created_at` em milissegundos no Redis ZSET). |
| **E08** | Webhooks | Laravel backend indisponível ou retornando erro 500/503 | WebhookDispatcher enfileira a notificação em fila de retry no Redis, executando até 5 tentativas com backoff exponencial (1s, 2s, 4s, 8s, 16s). |
| **E09** | Telemetria | Pacotes com perda de 100% ou RTT infinito durante degradação | MOS atinge piso mínimo de 1.0; emite evento WebSocket `{type: "quality_alert", level: "critical", suggestion: "disable_video"}`. |
| **E10** | Limpeza | Técnico esquece de clicar em "Encerrar Atendimento" e fecha o browser | Após 5 minutos sem nenhum participante conectado, o daemon de cleanup encerra a sessão e emite `session.ended` com duração exata calculada a partir do último heartbeat ativo. |

---

## 4. Especificação de Domínio 1: Ciclo de Vida de Salas e Matriz RBAC

### 4.1 Máquina de Estados Finitos da Sala de Atendimento

```
               ┌──────────────┐
               │   CREATED    │
               └──────┬───────┘
                      │ Técnico ou Egresso conecta no WS
                      ▼
               ┌──────────────┐
         ┌────►│   WAITING    │◄────┐
         │     └──────┬───────┘     │
Reconexão│            │ Ambos os participantes presentes
(<=45s)  │            ▼             │
         │     ┌──────────────┐     │ Reconexão de
         │     │ IN_PROGRESS  │     │ participante
         │     └──────┬───────┘     │
         │            │             │
         │            ├─────────────┤ Queda de conexão
         │            ▼             │
         │     ┌──────────────┐     │
         └─────┤ RECONNECTING ├─────┘
               └──────┬───────┘
                      │ Timeout (>45s) ou 'leave' explícito
                      ▼
               ┌──────────────┐
               │    ENDED     │ ──► Dispara Webhook 'session.ended'
               └──────┬───────┘
                      │ Cleanup Daemon (TTL 60s)
                      ▼
               ┌──────────────┐
               │   EXPIRED    │ ──► Remove do Redis / Memória
               └──────────────┘
```

### 4.2 Estados Formais e Transições

1. **`created`**:
   - Criada pelo Laravel via API ou gerada sob demanda na admissão da fila.
   - Metadados inicializados: `room_id`, `room_code`, `unit_id`, `prontuario_id`, `created_at`.
2. **`waiting`**:
   - Um participante conectou (ex: assistente social aguardando o egresso).
   - Inicia contagem regressiva de tolerância de entrada (máx 15 minutos).
3. **`in_progress`**:
   - Técnico e Egresso conectados simultaneamente.
   - Dispara webhook `session.started` para o Laravel.
   - Streams de áudio e vídeo bidirecionais ativos.
4. **`reconnecting`**:
   - Um dos participantes sofreu interrupção de rede (ex: troca de torre 4G ou oscilação).
   - Mantém alocação dos recursos WebRTC por até 45 segundos.
   - Notifica o par remoto: `{ "type": "peer_reconnecting", "user_id": 123 }`.
5. **`ended`**:
   - Encerrada por clique no botão de término pelo técnico, comando do egresso, ou timeout de reconexão.
   - Consolida estatísticas de rede (duração, MOS médio, perda de pacotes, bytes).
   - Dispara webhook `session.ended` para o Laravel.
6. **`expired`**:
   - Estado terminal de desalocação e limpeza no Redis e na memória local do FastAPI.

### 4.3 Matriz de Papéis de Participantes (RBAC de Sala)

| Permissão / Capacidade | `technician` (Técnico / Servidor) | `attendee` (Egresso / Familiar) | `observer` (Defensoria / Juiz) |
|---|---|---|---|
| **Publicar Áudio / Microfone** | Sim (Bidirecional) | Sim (Bidirecional) | Não (Mudo forçado no servidor) |
| **Publicar Vídeo / Câmera** | Sim (Bidirecional) | Sim (Bidirecional) | Não (Desativado no servidor) |
| **Compartilhar Tela (Screen Share)** | Sim | Sim (com permissão) | Não |
| **Admitir Participante da Fila** | Sim | Não | Não |
| **Mutar Participante Remoto** | Sim | Não | Não |
| **Encerrar Sessão para Todos** | Sim | Não (apenas desconecta-se) | Não |
| **Enviar Relatório de Telemetria** | Sim | Sim | Sim |
| **Receber Notificações de Fila** | Sim (Broadcaster) | Não (Apenas sua posição) | Não |

---

## 5. Especificação de Domínio 2: Autenticação & Autorização (JWT + WebSockets)

### 5.1 Geração de Token pelo Laravel (`POST /api/webrtc/token`)

O backend Laravel autentica o usuário (via sessão web ou token Sanctum) e gera um token JWT efêmero específico para a sessão de vídeo.

**Requisição Laravel:**
```http
POST /api/webrtc/token HTTP/1.1
Host: conectaegresso.es.gov.br
Authorization: Bearer <LARAVEL_SANCTUM_OR_SESSION>
Content-Type: application/json

{
  "room_id": "8f7e2a1b-3c4d-5e6f-7a8b-9c0d1e2f3a4b",
  "unit_id": 3205002,
  "role": "tecnico"
}
```

**Resposta do Laravel para o Navegador:**
```json
{
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "ws_url": "ws://localhost:8001/ws/signaling/8f7e2a1b-3c4d-5e6f-7a8b-9c0d1e2f3a4b",
  "ice_servers": [
    { "urls": "stun:stun.l.google.com:19302" },
    {
      "urls": "turn:turn.conectaegresso.es.gov.br:3478",
      "username": "conecta_user",
      "credential": "conecta_password"
    }
  ],
  "expires_in": 3600
}
```

### 5.2 Estrutura do Payload JWT Decodificado

```json
{
  "iss": "conecta-egresso-laravel",
  "aud": "conecta-egresso-webrtc",
  "sub": "101",
  "name": "Dra. Márcia Oliveira",
  "cpf_masked": "***.491.287-**",
  "role": "tecnico",
  "room_id": "8f7e2a1b-3c4d-5e6f-7a8b-9c0d1e2f3a4b",
  "unit_id": 3205002,
  "prontuario_id": "550e8400-e29b-41d4-a716-446655440000",
  "iat": 1786968000,
  "exp": 1786971600
}
```

### 5.3 Validação no FastAPI (Middleware / Dependência de WebSocket)

O microsserviço Python valida:
1. **Assinatura Criptográfica:** Algoritmo `HS256` utilizando a chave secreta compartilhada `JWT_SECRET_KEY` (configurada via variável de ambiente).
2. **Emissor e Audiência:** `iss == "conecta-egresso-laravel"` e `aud == "conecta-egresso-webrtc"`.
3. **Validade Temporal:** `now >= iat` e `now <= exp`.
4. **Correspondência de Rota:**
   - Para `/ws/signaling/{room_id}`: Verifica se `claim.room_id == room_id` ou se o usuário possui papel administrativo global.
   - Para `/ws/queue/{unit_id}`: Verifica se `claim.unit_id == unit_id`.

**Códigos de Fechamento de WebSocket (RFC 6455 / Custom):**
- `4001`: *Unauthorized* (Token ausente, assinatura inválida ou expirado).
- `4003`: *Forbidden* (Permissão insuficiente para a sala/unidade informada).
- `4004`: *Room Not Found* (Sala inexistente ou expirada).
- `4008`: *Room Full* (Sala com lotação máxima atingida).

---

## 6. Especificação de Domínio 3: Contrato de Webhooks com o Laravel 11

### 6.1 Mecanismo de Assinatura HMAC-SHA256

Todas as notificações de eventos emitidas pelo microsserviço WebRTC em direção ao endpoint do Laravel (`POST /api/webhooks/webrtc`) contêm assinatura criptográfica de cabeçalho baseada no corpo bruto da requisição:

- **Cabeçalhos HTTP Obrigatórios:**
  - `Content-Type: application/json`
  - `User-Agent: ConectaEgresso-WebRTC/1.0`
  - `X-Signature: sha256=<HMAC_HEX_DIGEST>`
  - `X-Signature-Timestamp: <UNIX_TIMESTAMP>`

**Algoritmo de Assinatura no Python (FastAPI):**
```python
import hmac
import hashlib

def generate_signature(payload_bytes: bytes, secret: str) -> str:
    digest = hmac.new(
        secret.encode('utf-8'),
        payload_bytes,
        hashlib.sha256
    ).hexdigest()
    return f"sha256={digest}"
```

**Verificação de Assinatura no Laravel (Middleware):**
```php
public function handle(Request $request, Closure $next)
{
    $signatureHeader = $request->header('X-Signature');
    if (!$signatureHeader || !str_starts_with($signatureHeader, 'sha256=')) {
        return response()->json(['error' => 'Missing or invalid signature header'], 401);
    }
    
    $receivedSignature = substr($signatureHeader, 7);
    $computedSignature = hash_hmac('sha256', $request->getContent(), config('services.webrtc.webhook_secret'));
    
    if (!hash_equals($computedSignature, $receivedSignature)) {
        return response()->json(['error' => 'Invalid HMAC signature'], 401);
    }
    
    return $next($request);
}
```

---

### 6.2 Catálogo Completo de Eventos & Schemas JSON

#### 1. Evento: `session.started`
Disparado no momento exato em que ambos os participantes principais (técnico e atendido) estabelecem conexão WebRTC ativa na sala.

```json
{
  "event": "session.started",
  "timestamp": "2026-08-17T12:00:00.000Z",
  "room_id": "8f7e2a1b-3c4d-5e6f-7a8b-9c0d1e2f3a4b",
  "room_code": "ATD-SM-2026-8910",
  "unit_id": 3205002,
  "prontuario_id": "550e8400-e29b-41d4-a716-446655440000",
  "participants": [
    {
      "user_id": 101,
      "name": "Dra. Márcia Oliveira",
      "role": "tecnico",
      "joined_at": "2026-08-17T11:59:52.120Z"
    },
    {
      "user_id": 502,
      "name": "Lucas Santos",
      "role": "egresso",
      "joined_at": "2026-08-17T12:00:00.000Z"
    }
  ]
}
```

#### 2. Evento: `session.ended`
Disparado na conclusão da videochamada, contendo a duração auditada e a síntese consolidada de telemetria e qualidade da rede.

```json
{
  "event": "session.ended",
  "timestamp": "2026-08-17T12:15:20.000Z",
  "room_id": "8f7e2a1b-3c4d-5e6f-7a8b-9c0d1e2f3a4b",
  "room_code": "ATD-SM-2026-8910",
  "unit_id": 3205002,
  "prontuario_id": "550e8400-e29b-41d4-a716-446655440000",
  "tecnico_id": 101,
  "egresso_id": 502,
  "started_at": "2026-08-17T12:00:00.000Z",
  "ended_at": "2026-08-17T12:15:20.000Z",
  "duration_seconds": 920,
  "hangup_reason": "technician_ended",
  "quality_summary": {
    "avg_mos": 4.38,
    "min_mos": 3.65,
    "avg_rtt_ms": 41.2,
    "avg_jitter_ms": 5.8,
    "packet_loss_pct": 0.32,
    "bytes_transferred": 62914560,
    "codec_video": "VP8",
    "codec_audio": "opus",
    "relay_used": true
  }
}
```

#### 3. Evento: `session.error`
Disparado em caso de falha irrecuperável de conexão ICE, encerramento anormal ou erro interno do servidor.

```json
{
  "event": "session.error",
  "timestamp": "2026-08-17T12:02:15.000Z",
  "room_id": "8f7e2a1b-3c4d-5e6f-7a8b-9c0d1e2f3a4b",
  "room_code": "ATD-SM-2026-8910",
  "unit_id": 3205002,
  "error_code": "ICE_FAILED_NO_RELAY",
  "error_message": "Peer connection failed after 3 ICE retry attempts on mobile network",
  "details": {
    "user_id": 502,
    "network_type": "cellular",
    "last_rtt_ms": 1250.0
  }
}
```

#### 4. Evento: `attendee.joined_queue`
Disparado quando um cidadão/egresso ingressa na fila de espera virtual de um município ou física de uma sede.

```json
{
  "event": "attendee.joined_queue",
  "timestamp": "2026-08-17T11:45:00.000Z",
  "unit_id": 3205002,
  "unit_name": "São Mateus (Atendimento Remoto)",
  "ticket_id": "TCK-2026-0042",
  "user_id": 502,
  "name": "Lucas Santos",
  "municipio": "São Mateus",
  "prioridade": "urgente",
  "motivo": "acolhimento_inicial",
  "position": 1
}
```

#### 5. Evento: `attendee.left_queue`
Disparado quando um egresso cancela sua espera ou é desconectado por timeout de inatividade.

```json
{
  "event": "attendee.left_queue",
  "timestamp": "2026-08-17T11:50:00.000Z",
  "unit_id": 3205002,
  "ticket_id": "TCK-2026-0042",
  "user_id": 502,
  "reason": "cancelled_by_user"
}
```

#### 6. Evento: `attendee.admitted`
Disparado quando o assistente social / psicólogo chama o egresso para a sala de atendimento.

```json
{
  "event": "attendee.admitted",
  "timestamp": "2026-08-17T11:58:30.000Z",
  "unit_id": 3205002,
  "ticket_id": "TCK-2026-0042",
  "user_id": 502,
  "tecnico_id": 101,
  "room_id": "8f7e2a1b-3c4d-5e6f-7a8b-9c0d1e2f3a4b"
}
```

---

### 6.3 Efeitos Colaterais Automáticos no Backend Laravel

Quando o Laravel processa o webhook `session.ended`:
1. Atualiza a tabela `video_rooms` com `status = 'completed'`, `ended_at` e `duration_seconds`.
2. Cria ou atualiza o registro em `video_attendees` persistindo o `avg_mos`, `packet_loss_pct` e `telemetry_summary`.
3. Insere automaticamente um nó de evolução na tabela `prontuario_timeline`:
   - `prontuario_id`: Identificador do prontuário único do atendido.
   - `tipo_evento`: `"atendimento_video"`
   - `metadata`: `{"room_code": "...", "duration": "15m 20s", "mos": 4.38, "tecnico": "Dra. Márcia Oliveira"}`
4. Grava um registro indelével na tabela de auditoria `prontuario_audit_logs` (`action = 'RECORD_VIDEO_ATTENDANCE'`).

---

## 7. Especificação de Domínio 4: Lógica de Fila de Espera & Unidades Territoriais

### 7.1 Arquitetura Territorial: 4 Sedes Físicas vs. 74 Unidades Remotas

Conforme as diretrizes do CPSI Nº 010/2026 (SEJUS/ES):
- **Sedes Físicas (Grande Vitória):**
  - `unit_1`: Escritório Social de Vitória (Sede Central)
  - `unit_2`: Escritório Social de Vila Velha
  - `unit_3`: Escritório Social de Serra
  - `unit_4`: Escritório Social de Cariacica
- **Polos Remotos de Atendimento Virtual (74 Municípios do Interior):**
  - Mapeados diretamente pelo código IBGE do município (ex: `unit_3205002` para São Mateus, `unit_3201506` para Colatina, `unit_3201209` para Cachoeiro de Itapemirim).
  - As filas virtuais compartilham o pool de técnicos da macrorregião correspondente, permitindo que assistentes sociais atendam demandas de qualquer município remoto.

### 7.2 Estrutura de Fila no Redis (Sorted Sets)

A fila de atendimento é gerenciada no Redis utilizando **Sorted Sets (ZSET)** para garantir ordenação estrita por prioridade ponderada e timestamp FIFO:

- **Chave Redis:** `queue:{unit_id}:tickets`
- **Cálculo do Score:**
  ```python
  def calculate_queue_score(priority: str, timestamp_ms: int) -> float:
      # Prioridades: urgente = 10000000000000, preferencial = 20000000000000, normal = 30000000000000
      priority_weights = {
          "urgente": 1_000_000_000_000,
          "preferencial": 2_000_000_000_000,
          "normal": 3_000_000_000_000
      }
      base_weight = priority_weights.get(priority, 3_000_000_000_000)
      return float(base_weight + timestamp_ms)
  ```
  *Dessa forma, tickets de prioridade mais alta sempre possuem score menor e são posicionados no topo da fila, respeitando a ordem de chegada (FIFO) dentro da mesma prioridade.*

### 7.3 Mensagens do Protocolo WebSocket de Fila (`/ws/queue/{unit_id}`)

#### Cliente -> Servidor:
1. `join_queue`:
   ```json
   {
     "type": "join_queue",
     "user_id": 502,
     "name": "Lucas Santos",
     "prioridade": "urgente",
     "motivo": "acolhimento_inicial"
   }
   ```
2. `leave_queue`:
   ```json
   {
     "type": "leave_queue",
     "ticket_id": "TCK-2026-0042"
   }
   ```
3. `admit_attendee` *(Exclusivo para Técnicos)*:
   ```json
   {
     "type": "admit_attendee",
     "ticket_id": "TCK-2026-0042",
     "room_id": "8f7e2a1b-3c4d-5e6f-7a8b-9c0d1e2f3a4b"
   }
   ```

#### Servidor -> Cliente:
1. `queue_joined` *(Enviado ao egresso)*:
   ```json
   {
     "type": "queue_joined",
     "ticket_id": "TCK-2026-0042",
     "position": 1,
     "estimated_wait_minutes": 5
   }
   ```
2. `queue_status` *(Broadcast periódico / sob alteração)*:
   ```json
   {
     "type": "queue_status",
     "unit_id": 3205002,
     "total_waiting": 4,
     "items": [
       { "ticket_id": "TCK-2026-0042", "name": "Lucas Santos", "municipio": "São Mateus", "prioridade": "urgente", "waiting_seconds": 180 },
       { "ticket_id": "TCK-2026-0043", "name": "Maria Silva", "municipio": "Linhares", "prioridade": "normal", "waiting_seconds": 95 }
     ]
   }
   ```
3. `call_attendee` *(Enviado ao egresso para redirecionamento imediato)*:
   ```json
   {
     "type": "call_attendee",
     "room_id": "8f7e2a1b-3c4d-5e6f-7a8b-9c0d1e2f3a4b",
     "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
     "ws_url": "ws://localhost:8001/ws/signaling/8f7e2a1b-3c4d-5e6f-7a8b-9c0d1e2f3a4b",
     "tecnico_name": "Dra. Márcia Oliveira"
   }
   ```

---

## 8. Especificação de Domínio 5: Protocolo de Sinalização & Algoritmo MOS (ITU-T G.107)

### 8.1 Mensagens do Protocolo WebSocket de Sinalização (`/ws/signaling/{room_id}`)

| Tipo de Mensagem | Direção | Payload / Campos Relevantes | Ação Executada |
|---|---|---|---|
| `join` | Cliente -> Servidor | `{ "type": "join", "token": "<JWT>" }` | Autentica, aloca socket na sala e notifica pares. |
| `peer_joined` | Servidor -> Clientes | `{ "type": "peer_joined", "user_id": 101, "role": "tecnico", "name": "..." }` | Informa que um novo participante entrou na sala. |
| `offer` | Ponto a Ponto | `{ "type": "offer", "sdp": "v=0...", "to_user_id": 502 }` | Encaminha proposta SDP de áudio/vídeo. |
| `answer` | Ponto a Ponto | `{ "type": "answer", "sdp": "v=0...", "to_user_id": 101 }` | Encaminha resposta SDP com codecs acordados. |
| `ice_candidate` | Ponto a Ponto | `{ "type": "ice_candidate", "candidate": {...}, "to_user_id": 101 }` | Roteia candidato ICE para conectividade NAT. |
| `media_state` | Broadcast Sala | `{ "type": "media_state", "audio_muted": false, "video_muted": true }` | Sincroniza estado de câmera/microfone na UI. |
| `telemetry_report` | Cliente -> Servidor | `{ "type": "telemetry_report", "rtt_ms": 42, "jitter_ms": 6, "loss": 0.003 }` | Alimenta o motor de cálculo MOS em tempo real. |
| `leave` | Cliente -> Servidor | `{ "type": "leave", "reason": "user_hangup" }` | Desconecta participante e atualiza ciclo da sala. |
| `peer_left` | Servidor -> Clientes | `{ "type": "peer_left", "user_id": 502, "reason": "user_hangup" }` | Informa saída de participante. |
| `room_terminated` | Servidor -> Clientes | `{ "type": "room_terminated", "reason": "technician_ended" }` | Força fechamento e redireciona para resumo. |

---

### 8.2 Algoritmo de Cálculo do MOS (Mean Opinion Score — ITU-T G.107 E-Model)

O cálculo do MOS utiliza uma derivação adaptada do E-Model da ITU-T G.107, calibrada para codecs de voz/vídeo em tempo real (Opus/VP8) em redes móveis brasileiras (3G/4G/5G com CGNAT):

$$R_0 = 94.2 \quad \text{(Fator de Qualidade Base)}$$

$$I_d = \text{Degradação por Atraso (Delay Impairment)}$$
$$\text{Efetivo } d = \text{RTT} + 2 \times \text{Jitter}$$
$$\text{Se } d > 100\text{ms}: \quad I_d = 0.024 \times d + 0.11 \times (d - 177.3) \times H(d - 177.3)$$
*(onde $H(x) = 1 \text{ se } x > 0 \text{ senão } 0$)*

$$I_e = \text{Degradação por Perda de Pacotes (Equipment Impairment)}$$
$$I_e = 30 \times \ln(1 + 15 \times P_{\text{loss}})$$
*(onde $P_{\text{loss}}$ é a fração de perda de pacotes de 0.0 a 1.0)*

$$R = R_0 - I_d - I_e$$

**Mapeamento $R \rightarrow \text{MOS}$ (Escala de 1.0 a 5.0):**
$$\text{Se } R \le 0: \quad \text{MOS} = 1.0$$
$$\text{Se } R \ge 100: \quad \text{MOS} = 4.5$$
$$\text{Se } 0 < R < 100: \quad \text{MOS} = 1.0 + 0.035 \times R + R \times (R - 60) \times (100 - R) \times 7 \times 10^{-6}$$

**Implementação Python Referencial (`telemetry.py`):**
```python
import math

def calculate_mos(rtt_ms: float, jitter_ms: float, packet_loss_pct: float) -> float:
    # 1. Effective latency
    effective_delay = max(0.0, rtt_ms + (2.0 * jitter_ms))
    
    # 2. Delay impairment Id
    if effective_delay < 160.0:
        id_impairment = effective_delay / 40.0
    else:
        id_impairment = (effective_delay - 120.0) / 10.0
    
    # 3. Packet loss impairment Ie (0.0 to 1.0)
    p_loss = max(0.0, min(100.0, packet_loss_pct)) / 100.0
    ie_impairment = 30.0 * math.log(1.0 + (15.0 * p_loss))
    
    # 4. Transmission rating R-factor
    r_factor = 94.2 - id_impairment - ie_impairment
    r_factor = max(0.0, min(100.0, r_factor))
    
    # 5. MOS calculation
    if r_factor <= 0.0:
        mos = 1.0
    elif r_factor >= 100.0:
        mos = 4.5
    else:
        mos = 1.0 + (0.035 * r_factor) + (r_factor * (r_factor - 60.0) * (100.0 - r_factor) * 0.000007)
    
    return round(max(1.0, min(5.0, mos)), 2)
```

---

## 9. Recomendação de Arquitetura de Arquivos e Dependências (`requirements.txt`)

### 9.1 Árvore de Diretórios do Módulo `webrtc_service/`

```
d:\Agile\projeto dia 18\webrtc_service\
├── app/
│   ├── __init__.py                 # Exportações do pacote
│   ├── main.py                     # Instância FastAPI, middlewares CORS, lifespan e rotas
│   ├── config.py                   # Pydantic Settings (ENV, coturn, redis, secrets)
│   ├── auth.py                     # Verificação de tokens JWT e RBAC de salas/filas
│   ├── signaling.py                # WebSocket router /ws/signaling/{room_id}
│   ├── queue_manager.py            # WebSocket router /ws/queue/{unit_id} e ZSET Redis
│   ├── room_manager.py             # Gerenciamento de sessões, participantes e cleanup
│   ├── redis_bus.py                # Pub/Sub assíncrono para escalabilidade horizontal
│   ├── telemetry.py                # Ingestão getStats(), E-Model ITU-T G.107 e score MOS
│   ├── webhooks.py                 # Despachante assíncrono HTTPX com HMAC-SHA256 e retentativa
│   └── schemas.py                  # Modelos Pydantic v2 de requisição, resposta e eventos
├── tests/
│   ├── __init__.py
│   ├── conftest.py                 # Fixtures pytest, mock Redis, client HTTP/WS e chaves JWT
│   ├── test_auth.py                # Testes de validação JWT, expiração e rejeição
│   ├── test_signaling.py           # Testes de troca SDP, trickle ICE e transição de estado
│   ├── test_queue.py               # Testes de fila por prioridade, tempo de espera e admissão
│   ├── test_telemetry.py           # Testes unitários do algoritmo MOS, jitter e perdas
│   ├── test_webhooks.py            # Testes de assinatura HMAC-SHA256 e retry com backoff
│   └── test_room_lifecycle.py      # Testes de encerramento, reconexão e cleanup daemon
├── requirements.txt                # Dependências Python homologadas
└── pytest.ini                      # Configurações de execução e cobertura de testes
```

---

### 9.2 Dependências Homologadas (`requirements.txt`)

```text
# FastAPI & ASGI Server
fastapi>=0.110.0,<0.120.0
uvicorn[standard]>=0.28.0
websockets>=12.0

# Settings & Validation
pydantic>=2.6.0,<3.0.0
pydantic-settings>=2.2.0

# Asynchronous Redis Engine
redis>=5.0.3

# Asynchronous HTTP Client (Webhooks)
httpx>=0.27.0

# Cryptography & JWT Security
pyjwt[crypto]>=2.8.0
cryptography>=42.0.5

# Environment Configuration
python-dotenv>=1.0.1

# Testing & Verification
pytest>=8.1.0
pytest-asyncio>=0.23.5
pytest-cov>=4.1.0
```

---

## 10. Resumo de Interoperabilidade e Próximos Passos

Esta especificação assegura 100% de aderência e interoperabilidade técnica entre:
1. **Frontend Vue 3 (Inertia):** Conecta-se diretamente aos WebSockets `/ws/signaling/{room_id}` e `/ws/queue/{unit_id}` utilizando os tokens JWT gerados pelo Laravel.
2. **Microsserviço Python FastAPI:** Processa sinalização em memória com persistência efêmera no Redis, calcula telemetria ITU-T G.107 e notifica o backend via webhooks assinados.
3. **Backend Laravel 11:** Emite credenciais seguras, valida webhooks via HMAC-SHA256 e grava automaticamente as sessões e métricas no Prontuário Único com auditoria indelével.
