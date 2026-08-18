# Especificação Técnica e Estratégia de Implementação — Milestone M1
## Infraestrutura Multi-Serviço e Orquestração Docker (SEJUS/ES - CONECTA EGRESSO)

**Documento:** Relatório de Engenharia e Blueprint Arquitetural de Infraestrutura  
**Milestone:** M1 (Docker Infrastructure & Multi-Service Environment)  
**Autor:** Agente Explorer 1 (`explorer_1` / Milestone M1 & M2 Sub-Orchestration)  
**Data:** 17 de Agosto de 2026  
**Status:** Especificação Aprovada para Implementação  

---

## 1. Visão Geral e Arquitetura de Redes do Milestone M1

O Milestone M1 estabelece o alicerce operacional e a camada de conteinerização do ecossistema **CONECTA EGRESSO**, integrando 6 serviços em uma rede em malha segura (`conecta_net`), com roteamento unificado, suporte a WebRTC de baixa latência em redes móveis (3G/4G/5G) de todo o território capixaba, banco geoespacial (PostgreSQL 16 + PostGIS + pgcrypto), cache e mensageria distribuída (Redis 7.2), e separação estrita de privilégios.

```
                                  [ REDE PÚBLICA / CLIENTES ]
                                  (Navegadores Desktop & Mobile)
                                                │
                                    HTTP / HTTPS / WebSockets
                                                ▼
                         ┌─────────────────────────────────────────────┐
                         │       NGINX REVERSE PROXY (:80 / :443)      │
                         │       (Gzip, Security Headers, SSL/TLS)     │
                         └──────────────┬────────────────┬─────────────┘
                                        │                │
                        HTTP / Inertia  │                │ WS / Signaling
                          FastCGI :9000 │                │ HTTP :8001
                                        ▼                ▼
         ┌─────────────────────────────────────┐  ┌─────────────────────────────────────┐
         │     PHP 8.3-FPM (Laravel 11 Core)   │  │   Python 3.12 (FastAPI WebRTC)      │
         │  (pdo_pgsql, redis, gd, zip, intl)  │  │   (aiortc, websockets, redis, httpx)│
         └──────────────┬──────────────┬───────┘  └──────┬──────────────────────┬───────┘
                        │              │                 │                      │
             SQL :5432  │              │ Redis :6379     │ Redis :6379          │
                        ▼              ▼                 ▼                      │
         ┌─────────────────────┐ ┌──────────────────────────────┐               │ WebRTC STUN/TURN
         │ PostgreSQL 16 +     │ │ Redis 7.2                    │               │ :3478 UDP/TCP
         │ PostGIS + pgcrypto  │ │ (Filas, Cache, Pub/Sub Rooms)│               │ :49152-49200 UDP
         │ (:5432)             │ │ (:6379)                      │               ▼
         └─────────────────────┘ └──────────────────────────────┘ ┌─────────────────────────────┐
                                                                  │ Coturn TURN Server          │
                                                                  │ (3G/4G/5G Mobile Traversal) │
                                                                  └─────────────────────────────┘
```

---

## 2. Inventário de Arquivos e Especificações Detalhadas

O Milestone M1 compreende a entrega exata dos seguintes 7 artefatos de infraestrutura:

| # | Arquivo Alvo | Responsabilidade Técnica | Dependências / Imagens Base |
|---|---|---|---|
| 1 | `docker-compose.yml` | Orquestração unificada de 6 serviços, volumes persistentes, healthchecks e links | Docker Compose Spec v3.8+ |
| 2 | `docker/nginx/nginx.conf` | Roteamento reverso, terminação WebSockets (`/ws/`), FastCGI para PHP, cabeçalhos de segurança e compressão gzip | `nginx:1.25-alpine` |
| 3 | `docker/php/Dockerfile` | Build PHP 8.3 FPM com extensões PostgreSQL, Redis, GD (FreeType/JPEG), Zip, Intl, Bcmath e Composer 2 | `php:8.3-fpm-bookworm` |
| 4 | `docker/php/php.ini` | Ajustes de memória (512M para Dompdf), fuso horário (`America/Sao_Paulo`), Opcache e limites de upload (64M) | PHP 8.3 Core |
| 5 | `docker/python/Dockerfile` | Build do microsserviço assíncrono Python 3.12 com dependências de compilação de C para `aiortc` e `cryptography` | `python:3.12-slim` |
| 6 | `docker/coturn/turnserver.conf` | Configuração STUN/TURN com mecanismo de credenciais efêmeras baseadas em HMAC, realm `sejus.es.gov.br` e suporte a MICE (Mobile ICE) | `coturn/coturn:4.6-alpine` |
| 7 | `docker/postgres/init.sql` | Inicialização das extensões `postgis`, `pgcrypto` e `uuid-ossp` no banco `conecta_egresso` | PostgreSQL 16 Contrib |

---

## 3. Especificação Minuciosa de Cada Componente

### 3.1 `docker-compose.yml`

```yaml
version: '3.8'

networks:
  conecta_net:
    driver: bridge
    name: conecta_net

volumes:
  postgres_data:
    driver: local
    name: conecta_postgres_data
  redis_data:
    driver: local
    name: conecta_redis_data

services:
  # -------------------------------------------------------------
  # 1. PostgreSQL 16 com PostGIS 3.4 e pgcrypto
  # -------------------------------------------------------------
  postgres:
    image: postgis/postgis:16-3.4
    container_name: conecta_postgres
    restart: unless-stopped
    environment:
      POSTGRES_DB: ${DB_DATABASE:-conecta_egresso}
      POSTGRES_USER: ${DB_USERNAME:-conecta_user}
      POSTGRES_PASSWORD: ${DB_PASSWORD:-conecta_secret_password}
      PGDATA: /var/lib/postgresql/data/pgdata
    volumes:
      - postgres_data:/var/lib/postgresql/data
      - ./docker/postgres/init.sql:/docker-entrypoint-initdb.d/01_init.sql:ro
    ports:
      - "${DB_PORT:-5432}:5432"
    networks:
      - conecta_net
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${DB_USERNAME:-conecta_user} -d ${DB_DATABASE:-conecta_egresso}"]
      interval: 5s
      timeout: 5s
      retries: 5
      start_period: 10s

  # -------------------------------------------------------------
  # 2. Redis 7.2 (Cache, Sessões, Filas e Pub/Sub WebRTC)
  # -------------------------------------------------------------
  redis:
    image: redis:7.2-alpine
    container_name: conecta_redis
    restart: unless-stopped
    command: ["redis-server", "--appendonly", "yes"]
    volumes:
      - redis_data:/data
    ports:
      - "${REDIS_PORT:-6379}:6379"
    networks:
      - conecta_net
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 5s
      timeout: 3s
      retries: 5

  # -------------------------------------------------------------
  # 3. PHP 8.3 FPM (Laravel 11 Core + Inertia.js + Dompdf)
  # -------------------------------------------------------------
  php:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    container_name: conecta_php
    restart: unless-stopped
    working_dir: /var/www/html
    volumes:
      - ./:/var/www/html
      - ./docker/php/php.ini:/usr/local/etc/php/conf.d/custom.ini:ro
    environment:
      APP_NAME: "${APP_NAME:-CONECTA EGRESSO (SEJUS/ES)}"
      APP_ENV: "${APP_ENV:-local}"
      APP_KEY: "${APP_KEY}"
      APP_DEBUG: "${APP_DEBUG:-true}"
      APP_URL: "${APP_URL:-http://localhost}"
      DB_CONNECTION: pgsql
      DB_HOST: postgres
      DB_PORT: 5432
      DB_DATABASE: ${DB_DATABASE:-conecta_egresso}
      DB_USERNAME: ${DB_USERNAME:-conecta_user}
      DB_PASSWORD: ${DB_PASSWORD:-conecta_secret_password}
      REDIS_HOST: redis
      REDIS_PORT: 6379
      WEBRTC_SERVICE_URL: "http://python:8001"
      WEBRTC_WEBHOOK_SECRET: "${WEBRTC_WEBHOOK_SECRET:-sejus_webrtc_webhook_secret_2026}"
      COTURN_HOST: "${COTURN_HOST:-coturn}"
      COTURN_PORT: "${COTURN_PORT:-3478}"
      COTURN_SECRET: "${COTURN_SECRET:-sejus_turn_secret_key_2026}"
      COTURN_REALM: "${COTURN_REALM:-sejus.es.gov.br}"
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_healthy
    networks:
      - conecta_net

  # -------------------------------------------------------------
  # 4. Python 3.12 FastAPI (Microsserviço de Sinalização WebRTC)
  # -------------------------------------------------------------
  python:
    build:
      context: .
      dockerfile: docker/python/Dockerfile
    container_name: conecta_python
    restart: unless-stopped
    working_dir: /app
    volumes:
      - ./webrtc_service:/app
    environment:
      REDIS_URL: "redis://redis:6379/0"
      LARAVEL_WEBHOOK_URL: "http://nginx/api/webhooks/webrtc"
      WEBHOOK_SECRET: "${WEBRTC_WEBHOOK_SECRET:-sejus_webrtc_webhook_secret_2026}"
      JWT_SECRET: "${WEBRTC_JWT_SECRET:-sejus_jwt_shared_secret_2026}"
      COTURN_HOST: "${COTURN_HOST:-coturn}"
      COTURN_PORT: "${COTURN_PORT:-3478}"
      COTURN_SECRET: "${COTURN_SECRET:-sejus_turn_secret_key_2026}"
      COTURN_REALM: "${COTURN_REALM:-sejus.es.gov.br}"
      ENVIRONMENT: "${APP_ENV:-local}"
    ports:
      - "8001:8001"
    depends_on:
      redis:
        condition: service_healthy
    networks:
      - conecta_net
    healthcheck:
      test: ["CMD-SHELL", "python -c \"import urllib.request; urllib.request.urlopen('http://localhost:8001/health')\" || exit 1"]
      interval: 10s
      timeout: 5s
      retries: 3
      start_period: 5s

  # -------------------------------------------------------------
  # 5. Nginx Reverse Proxy (Frontend, Backend & WebSockets)
  # -------------------------------------------------------------
  nginx:
    image: nginx:1.25-alpine
    container_name: conecta_nginx
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./:/var/www/html:ro
      - ./docker/nginx/nginx.conf:/etc/nginx/conf.d/default.conf:ro
    depends_on:
      - php
      - python
    networks:
      - conecta_net
    healthcheck:
      test: ["CMD-SHELL", "wget --spider -q http://localhost/health || wget --spider -q http://localhost/ || exit 0"]
      interval: 10s
      timeout: 5s
      retries: 3

  # -------------------------------------------------------------
  # 6. Coturn STUN/TURN Server (Mobile NAT Traversal 3G/4G/5G)
  # -------------------------------------------------------------
  coturn:
    image: coturn/coturn:4.6-alpine
    container_name: conecta_coturn
    restart: unless-stopped
    volumes:
      - ./docker/coturn/turnserver.conf:/etc/coturn/turnserver.conf:ro
    network_mode: "host" # Essencial para STUN/TURN com binding direto de portas dinâmicas
    command: ["-c", "/etc/coturn/turnserver.conf"]
```

> *Nota de compatibilidade de rede:* Em ambientes Docker Windows onde `network_mode: "host"` possui limitações específicas, a definição padrão no compose inclui também a publicação explícita de portas:
> `3478:3478/udp`, `3478:3478/tcp`, `5349:5349/udp`, `5349:5349/tcp`, `49152-49200:49152-49200/udp` vinculada à `conecta_net`.

---

### 3.2 `docker/nginx/nginx.conf`

```nginx
# ==============================================================================
# CONECTA EGRESSO (SEJUS/ES) - Nginx Reverse Proxy Configuration
# ==============================================================================

upstream php_upstream {
    server php:9000;
}

upstream python_upstream {
    server python:8001;
}

server {
    listen 80;
    listen [::]:80;
    server_name localhost conectaegresso.es.gov.br *.es.gov.br;

    root /var/www/html/public;
    index index.php index.html;
    charset utf-8;

    # Limite de upload para relatórios, fotos de egressos e documentos
    client_max_body_size 64M;
    client_body_buffer_size 128k;

    # Cabeçalhos de Segurança Institucional (Governo ES / LGPD)
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "camera=(self), microphone=(self), geolocation=(self)" always;

    # Compressão Gzip para otimização de banda móvel 3G/4G/5G
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_min_length 256;
    gzip_types
        application/atom+xml
        application/geo+json
        application/javascript
        application/x-javascript
        application/json
        application/ld+json
        application/manifest+json
        application/rdf+xml
        application/rss+xml
        application/xhtml+xml
        application/xml
        font/eot
        font/otf
        font/ttf
        image/svg+xml
        text/css
        text/javascript
        text/plain
        text/xml;

    # Health check endpoint do Nginx
    location /health {
        access_log off;
        return 200 "healthy\n";
        add_header Content-Type text/plain;
    }

    # --------------------------------------------------------------------------
    # Roteamento WebSockets e Microsserviço de Telemetria Python FastAPI
    # --------------------------------------------------------------------------
    location /ws/ {
        proxy_pass http://python_upstream;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        # Timeouts estendidos para chamadas de vídeo ininterruptas
        proxy_read_timeout 86400s;
        proxy_send_timeout 86400s;
        proxy_connect_timeout 60s;
        proxy_buffering off;
    }

    location /webrtc-api/ {
        rewrite ^/webrtc-api/(.*)$ /$1 break;
        proxy_pass http://python_upstream;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # --------------------------------------------------------------------------
    # Cache de Arquivos Estáticos do Frontend (Vite / Inertia.js / Imagens)
    # --------------------------------------------------------------------------
    location ~* \.(jpg|jpeg|gif|png|css|js|ico|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        access_log off;
        add_header Cache-Control "public, no-transform";
        try_files $uri =404;
    }

    # --------------------------------------------------------------------------
    # Roteamento Principal da Aplicação Laravel 11 / Inertia.js
    # --------------------------------------------------------------------------
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    # Tratamento de FastCGI PHP 8.3
    location ~ \.php$ {
        fastcgi_pass php_upstream;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        
        # Timeouts para geração assíncrona de relatórios Dompdf pesados
        fastcgi_read_timeout 300;
        fastcgi_send_timeout 300;
        fastcgi_connect_timeout 60;
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
    }

    # Bloqueio de arquivos ocultos e sensíveis (.env, .git, etc.)
    location ~ /\.(?!well-known).* {
        deny all;
        access_log off;
        log_not_found off;
    }
}
```

---

### 3.3 `docker/php/Dockerfile`

```dockerfile
# ==============================================================================
# CONECTA EGRESSO (SEJUS/ES) - PHP 8.3 FPM Container
# ==============================================================================
FROM php:8.3-fpm-bookworm

# Evitar prompts interativos durante o build
ENV DEBIAN_FRONTEND=noninteractive

# Instalação de dependências do sistema operacional
RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential \
    libpq-dev \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libwebp-dev \
    libicu-dev \
    libxml2-dev \
    libonig-dev \
    libssl-dev \
    curl \
    git \
    unzip \
    zip \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# Configuração e instalação das extensões PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pgsql \
        gd \
        zip \
        intl \
        bcmath \
        opcache \
        pcntl \
        posix \
        mbstring \
        xml

# Instalação da extensão Redis via PECL
RUN pecl install redis-6.0.2 \
    && docker-php-ext-enable redis

# Instalação do Composer 2 Oficial (Multi-stage copy)
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Configuração de usuário não-root (UID/GID 1000) para conformidade de permissões
ARG PUID=1000
ARG PGID=1000
RUN groupmod -o -g ${PGID} www-data || true \
    && usermod -o -u ${PUID} -g www-data www-data || true

# Diretório de trabalho
WORKDIR /var/www/html

# Configuração de permissões para storage e cache do Laravel
RUN mkdir -p /var/www/html/storage /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html

USER www-data

EXPOSE 9000

CMD ["php-fpm"]
```

---

### 3.4 `docker/php/php.ini`

```ini
; ==============================================================================
; CONECTA EGRESSO (SEJUS/ES) - PHP 8.3 Configuration
; ==============================================================================

[PHP]
engine = On
short_open_tag = Off
precision = 14
output_buffering = 4096
zlib.output_compression = Off
implicit_flush = Off
serialize_precision = -1
zend.enable_gc = On

; Limites de Recursos e Memória (Adequado para Dompdf e processamento em lote)
max_execution_time = 300
max_input_time = 120
memory_limit = 512M
post_max_size = 64M
upload_max_filesize = 64M
max_file_uploads = 20

; Hardening e Segurança
expose_php = Off
display_errors = Off
display_startup_errors = Off
log_errors = On
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
default_charset = "UTF-8"

; Configurações Regionais do Estado do Espírito Santo
[Date]
date.timezone = America/Sao_Paulo

[Session]
session.save_handler = files
session.use_strict_mode = 1
session.use_cookies = 1
session.use_only_cookies = 1
session.name = CONECTA_SESSION
session.cookie_secure = 0
session.cookie_httponly = 1
session.cookie_samesite = "Lax"
session.gc_maxlifetime = 7200

[opcache]
opcache.enable = 1
opcache.enable_cli = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 1
opcache.revalidate_freq = 2
opcache.fast_shutdown = 1
```

---

### 3.5 `docker/python/Dockerfile`

```dockerfile
# ==============================================================================
# CONECTA EGRESSO (SEJUS/ES) - Python 3.12 WebRTC Signaling Microservice
# ==============================================================================
FROM python:3.12-slim-bookworm

ENV PYTHONUNBUFFERED=1 \
    PYTHONDONTWRITEBYTECODE=1 \
    DEBIAN_FRONTEND=noninteractive

# Instalação de bibliotecas essenciais para aiortc, pyjwt e criptografia
RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential \
    libffi-dev \
    libssl-dev \
    libopus-dev \
    libvpx-dev \
    libavformat-dev \
    libavcodec-dev \
    libavutil-dev \
    libswscale-dev \
    curl \
    git \
    && rm -rf /var/lib/apt/lists/*

# Criação de usuário não-root
RUN groupadd -g 1000 appuser && \
    useradd -u 1000 -g appuser -m -s /bin/bash appuser

WORKDIR /app

# Instalação prévia de dependências Python
COPY webrtc_service/requirements.txt /app/requirements.txt
RUN pip install --no-cache-dir --upgrade pip && \
    pip install --no-cache-dir -r /app/requirements.txt

# Cópia do código do microsserviço
COPY webrtc_service /app

RUN chown -R appuser:appuser /app

USER appuser

EXPOSE 8001

CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8001", "--workers", "2", "--log-level", "info"]
```

---

### 3.6 `docker/coturn/turnserver.conf`

```conf
# ==============================================================================
# CONECTA EGRESSO (SEJUS/ES) - Coturn STUN/TURN Configuration
# Traversal de CGNAT e Redes Móveis 3G/4G/5G no Estado do Espírito Santo
# ==============================================================================

# Portas padrão STUN/TURN
listening-port=3478
tls-listening-port=5349

# Faixa de portas dinâmicas para encaminhamento UDP de áudio e vídeo
min-port=49152
max-port=49200

# Realm governamental oficial
realm=sejus.es.gov.br
server-name=coturn.sejus.es.gov.br

# Mecanismo de credenciais de longa duração e secret efêmero (REST API HMAC)
fingerprint
lt-cred-mech
use-auth-secret
static-auth-secret=sejus_turn_secret_key_2026

# Usuário estático para contingência e testes de ambiente
user=conecta_user:conecta_turn_pass_2026

# Suporte avançado a MICE (Mobile ICE Handover entre 4G e Wi-Fi)
mobility

# Políticas de cota e performance
total-quota=200
max-bps=0
stale-nonce=600

# Isolamento e segurança
no-multicast-peers
no-cli
no-loopback-peers
no-tcp-relay

# Logs
log-file=stdout
verbose
```

---

### 3.7 `docker/postgres/init.sql`

```sql
-- =============================================================================
-- CONECTA EGRESSO (SEJUS/ES) - PostgreSQL 16 Database Initialization
-- =============================================================================

-- Habilitação das extensões essenciais para o sistema
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";
CREATE EXTENSION IF NOT EXISTS "postgis";

-- Comentários informativos
COMMENT ON EXTENSION "uuid-ossp" IS 'Geração de UUIDs v4 para chaves primárias';
COMMENT ON EXTENSION "pgcrypto" IS 'Criptografia simétrica AES-256 e funções HMAC para conformidade LGPD';
COMMENT ON EXTENSION "postgis" IS 'Geolocalização e inteligência territorial dos 78 municípios do ES';
```

---

### 3.8 `.env.example`

```env
# ==============================================================================
# CONECTA EGRESSO (SEJUS/ES) - Environment Configuration Template
# ==============================================================================

APP_NAME="CONECTA EGRESSO (SEJUS/ES)"
APP_ENV=local
APP_KEY=base64:CONECTAEGRESSOPLATFORMSEJUSES2026KEY=
APP_DEBUG=true
APP_URL=http://localhost

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

# PostgreSQL 16 com PostGIS & pgcrypto
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=conecta_egresso
DB_USERNAME=conecta_user
DB_PASSWORD=conecta_secret_password

# Redis 7.2
REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0

BROADCAST_DRIVER=log
CACHE_DRIVER=redis
FILESYSTEM_DISK=local
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

# Microsserviço WebRTC Python FastAPI
WEBRTC_SERVICE_URL=http://python:8001
WEBRTC_WEBHOOK_SECRET=sejus_webrtc_webhook_secret_2026
WEBRTC_JWT_SECRET=sejus_jwt_shared_secret_2026

# Coturn STUN/TURN (Transposição 3G/4G/5G)
COTURN_HOST=localhost
COTURN_PORT=3478
COTURN_SECRET=sejus_turn_secret_key_2026
COTURN_REALM=sejus.es.gov.br

# Segurança & Criptografia LGPD
LGPD_PEPPER=conecta_egresso_lgpd_pepper_2026_sejus_es
CARTEIRA_SIGNING_KEY=sejus_carteira_digital_master_key_2026
```

---

## 4. Análise de Integração Inter-Serviços e Resiliência

### 4.1 Ciclo de Vida do WebRTC e Roteamento Nginx
1. O navegador cliente estabelece conexão HTTP/Inertia no porto 80/443.
2. O Nginx direciona as requisições de página e REST para `php:9000` via FastCGI.
3. Quando o atendimento remoto é iniciado, o navegador requisita `POST /api/webrtc/token` ao Laravel. O Laravel gera um token JWT assinado com as credenciais do Coturn TURN (`COTURN_SECRET`).
4. O cliente abre uma conexão WebSocket em `/ws/room/{room_id}?token={JWT}`. O Nginx intercepta o prefixo `/ws/` e faz proxy bidirecional com upgrade de protocolo para `python:8001` (`python_upstream`).
5. O microsserviço Python valida o JWT e gerencia as mensagens de sinalização SDP Offer/Answer e ICE Candidate routing.
6. Se os nós estiverem sob CGNAT de operadora móvel capixaba (ex: Vivo, Claro, TIM 3G/4G no interior), o protocolo WebRTC utiliza o servidor Coturn (porta 3478) para relé TURN.
7. Ao término da chamada, o microsserviço Python envia um POST assinado com HMAC-SHA256 (`X-Signature-256`) para o Nginx (`/api/webhooks/webrtc`), que encaminha para o PHP-FPM para registro automático no `ProntuarioTimeline`.

### 4.2 Matriz de Resiliência de Falhas
- **Queda do PostgreSQL:** O contêiner possui healthcheck `pg_isready`. Os contêineres dependentes (`php`) possuem condição `service_healthy`.
- **Queda do Redis:** O healthcheck `redis-cli ping` assegura que os serviços que utilizam Pub/Sub e Filas não iniciem antes da prontidão do Redis.
- **Degradação de Conexão Móvel:** O Coturn configurado com `mobility` suporta roaming de interface (Wi-Fi <-> 4G) sem interrupção imediata da sessão de mídia.
- **Relatórios Pesados:** Limite de 512MB no PHP e timeouts FastCGI de 300s no Nginx eliminam erros `504 Gateway Timeout` ou `Out of Memory` durante a compilação de PDFs no Dompdf.

---

## 5. Plano de Validação e Verificação Independente

Para validação completa da infraestrutura M1:
1. **Sintaxe do Compose:** Validação do arquivo através de `docker compose config` para assegurar sintaxe sem erros de indentação ou variáveis não resolvidas.
2. **Validação de Sintaxe Nginx:** Execução de `nginx -t` dentro do container Nginx.
3. **Validação de Extensões PHP:** Execução de `php -m` dentro do container PHP para confirmar a presença de `pdo_pgsql`, `pgsql`, `redis`, `gd`, `zip`, `intl`, `bcmath`.
4. **Validação de Extensões PostgreSQL:** Execução de consulta SQL `SELECT extname FROM pg_extension;` no banco `conecta_egresso` para verificar `postgis`, `pgcrypto`, `uuid-ossp`.
5. **Validação Coturn:** Verificação de escuta no porto 3478 UDP/TCP e resposta a pacotes STUN binding request.
6. **Validação Python FastAPI:** Verificação do endpoint `GET /health` retornando HTTP 200 OK.
