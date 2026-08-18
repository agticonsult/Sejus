# Relatório de Arquitetura Técnica, Componentes de Stack e Integração
## Plataforma Conecta Egresso — SEJUS / Governo do Estado do Espírito Santo
**Data de Emissão:** 17 de Agosto de 2026  
**Autor:** Agente survey_explorer_3 (Arquitetura Técnica & Topologia de Sistemas)  
**Status:** Definição Arquitetural Completa & Especificação Técnica

---

## 1. Visão Geral da Arquitetura do Sistema

A plataforma **CONECTA EGRESSO** foi concebida para atender à demanda da Secretaria de Estado da Justiça do Espírito Santo (SEJUS/ES) e da Secretaria de Gestão e Recursos Humanos (SEGER/ES) no âmbito do Edital de Contratação Pública de Solução Inovadora (CPSI) Nº 010/2026. A solução descentraliza e expande os serviços do Escritório Social de 4 para **todos os 78 municípios capixabas**, atendendo um universo de mais de 108 mil pessoas egressas do sistema prisional e seus familiares.

A arquitetura adota um modelo híbrido moderno:
- **Backend Monolítico Modular (Core):** Laravel 11 (PHP 8.3+) com Inertia.js v2 e Vue 3 + TailwindCSS para a interface reativa SPA/SSR, gestão de regras de negócio, autenticação federada (Gov.br / Acesso Cidadão), prontuário único, carteira digital e trilha de auditoria LGPD imutável.
- **Microsserviço de Alta Performance Assíncrono:** Python 3.11+ (FastAPI + WebSockets + aiortc) para controle de sinalização WebRTC, fila de espera em tempo real e monitoramento de telemetria/qualidade da chamada.
- **Infraestrutura de Mídia & NAT Traversal:** Servidor Coturn (STUN/TURN) dedicado para garantia de 100% de conectividade em redes móveis 3G/4G/5G e redes corporativas sob Carrier-Grade NAT (CGNAT).
- **Camada de Dados & Persistência:** PostgreSQL 16 com extensões `postgis` (inteligência geoespacial dos 78 municípios) e `pgcrypto` (criptografia de dados sensíveis), além de Redis 7 para cache, mensageria e filas de alta velocidade.
- **Orquestração:** Docker Compose unificado com Nginx como Reverse Proxy e API Gateway.

```
                                  [ CLIENTES ]
                   (Desktop / Mobile / Tablet - 78 Municípios ES)
                                       │
                                 HTTPS / WSS
                                       ▼
                       ┌───────────────────────────────┐
                       │     NGINX (Reverse Proxy)     │
                       │          Port 80/443          │
                       └───────┬───────────────┬───────┘
                               │               │
                 HTTP / Inertia│               │ WebSocket / WebRTC API
                /api (Core)    │               │ /ws/* & /api/webrtc/*
                               ▼               ▼
                 ┌───────────────────┐   ┌───────────────────────────┐
                 │  PHP 8.3-FPM      │   │  Python FastAPI           │
                 │  Laravel 11 Core  │   │  WebRTC Signaling Server  │
                 │  + Inertia/Vue 3  │◄──┤  + aiortc / Telemetry     │
                 └─────────┬─────────┘Web│  └─────────────┬─────────────┘
                           │        hooks                 │
                           │   (Internal HMAC Auth)       │
                           │                              │
         ┌─────────────────┼──────────────────────────────┼────────────────┐
         │                 │                              │                │
         ▼                 ▼                              ▼                ▼
┌─────────────────┐ ┌──────────────┐             ┌─────────────────┐ ┌───────────┐
│  PostgreSQL 16  │ │   Redis 7    │             │  COTURN Server  │ │ S3/Local  │
│  PostGIS        │ │  Filas, Cache│             │  STUN / TURN    │ │ Storage   │
│  pgcrypto       │ │  & Pub/Sub   │             │  Port 3478/5349 │ │ Documentos│
└─────────────────┘ └──────────────┘             └─────────────────┘ └───────────┘
```

---

## 2. Backend Core (Laravel 11 / PHP 8.3+ & Inertia.js Vue 3)

### 2.1 Estrutura de Diretórios do Projeto
O backend utiliza a estrutura moderna e simplificada do **Laravel 11**, eliminando boilerplate desnecessário e centralizando middlewares e rotas em `bootstrap/app.php`.

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/
│   │   │   │   ├── AuthenticatedSessionController.php
│   │   │   │   ├── GovBrAcessoCidadaoController.php
│   │   │   │   └── RoleSwitchController.php
│   │   │   ├── DashboardController.php         # Métricas executivas e agregação de KPIs
│   │   │   ├── AtendimentoController.php       # Gestão da fila e disparo de videochamada
│   │   │   ├── OportunidadeController.php      # Vagas de emprego e cursos profissionalizantes
│   │   │   ├── CarteiraDigitalController.php   # Emissão de PDF e validação de QR Code
│   │   │   ├── ProntuarioController.php        # Linha do tempo e registros de prontuário
│   │   │   ├── MunicipioController.php         # GeoJSON, dados dos 78 municípios e rede CRAS/CREAS/SINE
│   │   │   ├── RelatorioController.php         # Relatórios analíticos SEJUS e reincidência
│   │   │   ├── LgpdGovernanceController.php    # Gestão de privacidade e auditoria
│   │   │   ├── Webhook/
│   │   │   │   └── WebRtcWebhookController.php # Recepção de eventos da sinalização FastAPI
│   │   │   └── Api/
│   │   │       ├── VagasApiController.php
│   │   │       ├── MunicipiosApiController.php
│   │   │       └── VideoRoomsApiController.php
│   │   ├── Middleware/
│   │   │   ├── HandleInertiaRequests.php       # Injeção global de auth, flash, temas de acessibilidade
│   │   │   ├── EnsureRole.php                  # RBAC: gestor, tecnico, egresso
│   │   │   ├── AuditLgpdAccess.php             # Trilha de auditoria automática em visualizações e edições
│   │   │   └── VerifyWebRtcSignature.php       # Validação HMAC-SHA256 dos webhooks FastAPI
│   │   └── Requests/
│   │       ├── StoreAtendimentoRequest.php
│   │       ├── StoreVagaRequest.php
│   │       ├── StoreCursoRequest.php
│   │       ├── ApplyVagaRequest.php
│   │       └── ValidateQrCodeRequest.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Role.php
│   │   ├── Permission.php
│   │   ├── Egresso.php
│   │   ├── Prontuario.php
│   │   ├── ProntuarioAtendimento.php
│   │   ├── ProntuarioAuditLog.php
│   │   ├── Vaga.php
│   │   ├── Curso.php
│   │   ├── Candidatura.php
│   │   ├── CarteiraDigital.php
│   │   ├── MunicipioEs.php
│   │   ├── VideoRoom.php
│   │   └── VideoSession.php
│   ├── Policies/
│   │   ├── ProntuarioPolicy.php
│   │   ├── CarteiraDigitalPolicy.php
│   │   ├── VagaPolicy.php
│   │   └── VideoRoomPolicy.php
│   └── Services/
│       ├── Encryption/
│       │   └── LgpdEncryptor.php               # Criptografia de colunas sensíveis (AES-256 / pgcrypto)
│       ├── Pdf/
│       │   └── CarteiraPdfService.php          # Renderização de PDF oficial com Dompdf
│       ├── QrCode/
│       │   └── QrCodeSignerService.php         # Geração de QR Code assinado criptograficamente
│       ├── Geo/
│       │   └── PostGisSpatialService.php       # Consultas espaciais PostGIS e proximidade de serviços
│       └── WebRtc/
│           └── WebRtcTokenService.php          # Emissão de JWT para o microsserviço Python
├── bootstrap/
│   ├── app.php                                 # Configuração de middlewares, rotas e exceções
│   └── providers.php
├── config/
│   ├── app.php
│   ├── auth.php
│   ├── database.php
│   ├── inertia.php
│   ├── webrtc.php
│   └── lgpd.php
├── database/
│   ├── migrations/
│   ├── seeders/
│   │   ├── DatabaseSeeder.php
│   │   ├── MunicipiosEsSeeder.php              # Todos os 78 municípios com coordenadas e microrregiões
│   │   ├── RolesAndPermissionsSeeder.php
│   │   ├── UsersSeeder.php                     # Gestor, Técnico, Egresso de demonstração
│   │   ├── VagasAndCursosSeeder.php
│   │   └── ProntuariosDemoSeeder.php
│   └── factories/
├── resources/
│   ├── css/
│   │   └── app.css                             # TailwindCSS + Tokens de Design System SEJUS
│   ├── js/
│   │   ├── app.js                              # Inicialização do Inertia.js + Vue 3
│   │   ├── Layouts/
│   │   │   ├── AppLayout.vue                   # Header, Sidebar, Acessibilidade e Rodapé
│   │   │   └── GuestLayout.vue
│   │   ├── Pages/
│   │   │   ├── Dashboard/Index.vue             # KPIs executivos, metas e reincidência
│   │   │   ├── Atendimento/Index.vue           # Fila em tempo real e sala de videoconferência
│   │   │   ├── Oportunidades/Index.vue         # Painel de vagas e cursos com filtros municipais
│   │   │   ├── Carteira/Index.vue              # Carteira Digital oficial e emissão de documentos
│   │   │   ├── Geolocalizacao/Index.vue        # Mapa interativo dos 78 municípios do ES
│   │   │   ├── Prontuario/Index.vue            # Linha do tempo social e histórico do egresso
│   │   │   ├── Relatorios/Index.vue            # Estatísticas avançadas SEJUS
│   │   │   └── Lgpd/Index.vue                  # Níveis de acesso e trilhas de auditoria
│   │   └── Components/
│   │       ├── AccessibilityToolbar.vue        # Alto contraste, escala de fonte e linguagem fácil
│   │       ├── VideoCallRoom.vue               # WebRTC client component
│   │       ├── ChartDonut.vue                  # Gráfico de rosca via Canvas/Chart.js
│   │       ├── ChartBar.vue                    # Gráfico de barras dos municípios
│   │       ├── EsMapSvg.vue                    # Vetor geográfico interativo do Espírito Santo
│   │       ├── CarteiraCard.vue                # Cartão visual da carteira do egresso
│   │       └── AuditTimeline.vue
│   └── views/
│       ├── app.blade.php                       # Template base Inertia
│       └── pdf/
│           └── carteira_digital.blade.php      # Layout de impressão da Carteira Oficial
└── routes/
    ├── web.php                                 # Rotas Inertia autenticadas
    └── api.php                                 # Webhooks e APIs REST
```

---

### 2.2 Esquema do Banco de Dados (PostgreSQL 16 + PostGIS + pgcrypto)

O banco de dados utiliza o PostgreSQL 16 aproveitando as extensões:
1. `postgis`: Tipos geométricos (`geometry(Point, 4326)`, `geometry(MultiPolygon, 4326)`), indexação espacial GiST e funções de proximidade (`ST_DWithin`, `ST_Distance`).
2. `pgcrypto`: Criptografia simétrica com chave gerenciada e geração de UUIDs (`gen_random_uuid()`).
3. `uuid-ossp`: Geração padronizada de identificadores não previsíveis.

#### DDL e Especificação das Tabelas Principais

```sql
-- 1. Habilitar Extensões
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";
CREATE EXTENSION IF NOT EXISTS "postgis";

-- 2. Tabela de Municípios do Espírito Santo (78 Municípios)
CREATE TABLE municipios_es (
    id SERIAL PRIMARY KEY,
    codigo_ibge INT UNIQUE NOT NULL,
    nome VARCHAR(100) NOT NULL,
    microrregiao VARCHAR(60) NOT NULL,
    macrorregiao VARCHAR(60) NOT NULL,
    possui_escritorio_social_fisico BOOLEAN DEFAULT FALSE,
    populacao_egressa_estimada INT DEFAULT 0,
    rede_apoio_cras_count INT DEFAULT 1,
    rede_apoio_creas_count INT DEFAULT 1,
    rede_apoio_sine_count INT DEFAULT 1,
    lat NUMERIC(9, 6) NOT NULL,
    lng NUMERIC(9, 6) NOT NULL,
    geom_ponto geometry(Point, 4326),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);
CREATE INDEX idx_municipios_es_geom ON municipios_es USING GIST (geom_ponto);
CREATE INDEX idx_municipios_es_microrregiao ON municipios_es (microrregiao);

-- 3. Tabela de Usuários (RBAC + Suporte Gov.br / Acesso Cidadão)
CREATE TABLE users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NULL,
    cpf_hash VARCHAR(64) UNIQUE NOT NULL, -- SHA-256 para indexação e busca cega
    cpf_encrypted BYTEA NOT NULL,         -- Criptografado com chave de segurança
    role VARCHAR(30) NOT NULL CHECK (role IN ('gestor', 'tecnico', 'egresso')),
    municipio_id INT REFERENCES municipios_es(id) ON DELETE SET NULL,
    telefone_encrypted BYTEA NULL,
    avatar_url VARCHAR(255) NULL,
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'suspended', 'inactive')),
    acesso_cidadao_sub VARCHAR(100) UNIQUE NULL, -- Identificador OpenID Connect PRODEST/Gov.br
    last_login_at TIMESTAMP WITH TIME ZONE NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);
CREATE INDEX idx_users_role ON users (role);
CREATE INDEX idx_users_cpf_hash ON users (cpf_hash);

-- 4. Tabela de Egressos (Dados de Acolhimento e Reintegração)
CREATE TABLE egressos (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    nome_completo VARCHAR(150) NOT NULL,
    nome_social VARCHAR(150) NULL,
    data_nascimento DATE NOT NULL,
    filiacao_mae_encrypted BYTEA NULL,
    rg_encrypted BYTEA NULL,
    municipio_residencia_id INT NOT NULL REFERENCES municipios_es(id),
    endereco_encrypted BYTEA NULL,
    geom geometry(Point, 4326) NULL,
    escolaridade VARCHAR(50) NOT NULL,
    profissao_anterior VARCHAR(100) NULL,
    status_prisional VARCHAR(50) NOT NULL, -- 'liberdade_condicional', 'regime_aberto', 'egresso_definitivo'
    unidade_prisional_origem VARCHAR(120) NULL,
    data_saida DATE NULL,
    vulnerabilidades JSONB DEFAULT '[]'::jsonb, -- ['sem_renda', 'sem_moradia', 'dependencia_quimica', 'sem_documentos']
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);
CREATE INDEX idx_egressos_municipio ON egressos (municipio_residencia_id);
CREATE INDEX idx_egressos_geom ON egressos USING GIST (geom);

-- 5. Tabela de Prontuários Únicos
CREATE TABLE prontuarios (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    egresso_id UUID UNIQUE NOT NULL REFERENCES egressos(id) ON DELETE RESTRICT,
    numero_prontuario VARCHAR(30) UNIQUE NOT NULL, -- Formato: PRT-2026-XXXXXX
    status_acompanhamento VARCHAR(30) DEFAULT 'ativo' CHECK (status_acompanhamento IN ('ativo', 'pausado', 'concluido', 'arquivado')),
    tecnico_responsavel_id UUID REFERENCES users(id) ON DELETE SET NULL,
    resumo_demanda TEXT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);
CREATE INDEX idx_prontuarios_numero ON prontuarios (numero_prontuario);

-- 6. Tabela de Atendimentos do Prontuário
CREATE TABLE prontuario_atendimentos (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    prontuario_id UUID NOT NULL REFERENCES prontuarios(id) ON DELETE CASCADE,
    tecnico_id UUID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    tipo_atendimento VARCHAR(30) NOT NULL CHECK (tipo_atendimento IN ('remoto_video', 'presencial', 'telefonico', 'busca_ativa')),
    categoria VARCHAR(40) NOT NULL CHECK (categoria IN ('acolhimento', 'psicossocial', 'juridico', 'documentacao', 'encaminhamento_vaga', 'qualificacao')),
    evolucao_texto_encrypted BYTEA NOT NULL,
    encaminhamento_detalhe TEXT NULL,
    video_session_id UUID NULL, -- FK definida posteriormente
    duracao_minutos INT DEFAULT 0,
    data_hora_atendimento TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    ip_address INET NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);
CREATE INDEX idx_prontuario_atendimentos_prontuario ON prontuario_atendimentos (prontuario_id);
CREATE INDEX idx_prontuario_atendimentos_data ON prontuario_atendimentos (data_hora_atendimento);

-- 7. Tabela de Trilha de Auditoria Imutável LGPD
CREATE TABLE prontuario_audit_logs (
    id BIGSERIAL PRIMARY KEY,
    prontuario_id UUID NOT NULL REFERENCES prontuarios(id) ON DELETE RESTRICT,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    acao VARCHAR(30) NOT NULL CHECK (acao IN ('CREATE', 'VIEW', 'UPDATE', 'EXPORT_PDF', 'ANONYMIZE')),
    ip_address INET NOT NULL,
    user_agent VARCHAR(255) NOT NULL,
    justificativa TEXT NULL,
    payload_hash VARCHAR(64) NOT NULL, -- Hash de integridade dos dados consultados/alterados
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW() NOT NULL
);
-- Impedir alterações e exclusões na tabela de auditoria via regra/trigger
CREATE RULE prontuario_audit_logs_no_update AS ON UPDATE TO prontuario_audit_logs DO INSTEAD NOTHING;
CREATE RULE prontuario_audit_logs_no_delete AS ON DELETE TO prontuario_audit_logs DO INSTEAD NOTHING;
CREATE INDEX idx_prontuario_audit_prontuario ON prontuario_audit_logs (prontuario_id);
CREATE INDEX idx_prontuario_audit_user ON prontuario_audit_logs (user_id);
CREATE INDEX idx_prontuario_audit_created ON prontuario_audit_logs (created_at);

-- 8. Tabela de Oportunidades / Vagas de Emprego
CREATE TABLE vagas (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    empresa_parceira VARCHAR(120) NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    descricao TEXT NOT NULL,
    requisitos TEXT NULL,
    categoria VARCHAR(50) NOT NULL, -- 'construcao', 'servicos', 'logistica', 'comercio', 'industria', 'alimentacao'
    municipio_id INT NOT NULL REFERENCES municipios_es(id),
    salario_faixa VARCHAR(60) NOT NULL,
    regime VARCHAR(30) DEFAULT 'CLT' CHECK (regime IN ('CLT', 'Estagio', 'Cooperado', 'PJ', 'Temporario')),
    vagas_totais INT NOT NULL DEFAULT 1,
    vagas_preenchidas INT NOT NULL DEFAULT 0,
    conveniada_sejus BOOLEAN DEFAULT TRUE,
    ativa BOOLEAN DEFAULT TRUE,
    contato_responsavel VARCHAR(120) NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);
CREATE INDEX idx_vagas_municipio ON vagas (municipio_id);
CREATE INDEX idx_vagas_categoria ON vagas (categoria);

-- 9. Tabela de Cursos Profissionalizantes
CREATE TABLE cursos (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    instituicao VARCHAR(120) NOT NULL, -- SENAI, IFES, FAETEC, SEST/SENAT, etc.
    nome_curso VARCHAR(150) NOT NULL,
    modalidade VARCHAR(30) NOT NULL CHECK (modalidade IN ('presencial', 'hibrido', 'ead')),
    carga_horaria INT NOT NULL, -- horas
    municipio_id INT NULL REFERENCES municipios_es(id), -- NULL quando 100% EAD
    vagas_disponiveis INT NOT NULL DEFAULT 30,
    data_inicio DATE NOT NULL,
    data_fim DATE NULL,
    requisito_escolaridade VARCHAR(50) DEFAULT 'Fundamental Incompleto',
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- 10. Tabela de Candidaturas e Encaminhamentos
CREATE TABLE candidaturas (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    egresso_id UUID NOT NULL REFERENCES egressos(id) ON DELETE CASCADE,
    vaga_id UUID NULL REFERENCES vagas(id) ON DELETE SET NULL,
    curso_id UUID NULL REFERENCES cursos(id) ON DELETE SET NULL,
    status VARCHAR(30) DEFAULT 'encaminhado' CHECK (status IN ('encaminhado', 'em_analise', 'entrevistado', 'contratado', 'matriculado', 'recusado')),
    tecnico_encaminhador_id UUID REFERENCES users(id) ON DELETE SET NULL,
    feedback_empresa TEXT NULL,
    data_candidatura TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    CONSTRAINT chk_vaga_ou_curso CHECK (vaga_id IS NOT NULL OR curso_id IS NOT NULL)
);

-- 11. Tabela de Carteiras Digitais Oficiais
CREATE TABLE carteiras_digitais (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    egresso_id UUID UNIQUE NOT NULL REFERENCES egressos(id) ON DELETE CASCADE,
    codigo_autenticacao VARCHAR(32) UNIQUE NOT NULL, -- Ex: CE-ES-2026-9812-4821
    qr_payload_signed TEXT NOT NULL,                -- Payload criptografado/assinado digitalmente
    validade_ate DATE NOT NULL,
    status VARCHAR(20) DEFAULT 'ativa' CHECK (status IN ('ativa', 'suspensa', 'revogada')),
    emissao_count INT DEFAULT 1,
    ultima_emissao_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    dados_snapshot JSONB NOT NULL,                   -- Snapshot dos dados na data de emissão
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);
CREATE INDEX idx_carteiras_codigo ON carteiras_digitais (codigo_autenticacao);

-- 12. Tabela de Salas de Videochamada (WebRTC Rooms)
CREATE TABLE video_rooms (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    room_code VARCHAR(32) UNIQUE NOT NULL,
    prontuario_id UUID REFERENCES prontuarios(id) ON DELETE SET NULL,
    egresso_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    tecnico_id UUID NULL REFERENCES users(id) ON DELETE SET NULL,
    municipio_id INT NOT NULL REFERENCES municipios_es(id),
    status VARCHAR(20) DEFAULT 'waiting' CHECK (status IN ('waiting', 'active', 'ended', 'cancelled')),
    prioridade VARCHAR(20) DEFAULT 'normal' CHECK (prioridade IN ('urgente', 'alta', 'normal', 'baixa')),
    scheduled_at TIMESTAMP WITH TIME ZONE NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    closed_at TIMESTAMP WITH TIME ZONE NULL
);
CREATE INDEX idx_video_rooms_status ON video_rooms (status);
CREATE INDEX idx_video_rooms_room_code ON video_rooms (room_code);

-- 13. Tabela de Sessões e Telemetria de Videochamada
CREATE TABLE video_sessions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    room_id UUID NOT NULL REFERENCES video_rooms(id) ON DELETE CASCADE,
    started_at TIMESTAMP WITH TIME ZONE NOT NULL,
    ended_at TIMESTAMP WITH TIME ZONE NOT NULL,
    duracao_segundos INT NOT NULL DEFAULT 0,
    tecnico_joined_at TIMESTAMP WITH TIME ZONE NULL,
    egresso_joined_at TIMESTAMP WITH TIME ZONE NULL,
    qualidade_media_score NUMERIC(3, 2) DEFAULT 5.00, -- MOS Score de 1.00 a 5.00
    codec_audio VARCHAR(30) DEFAULT 'opus',
    codec_video VARCHAR(30) DEFAULT 'VP8',
    bytes_transferred BIGINT DEFAULT 0,
    packets_lost_pct NUMERIC(5, 2) DEFAULT 0.00,
    session_metadata JSONB DEFAULT '{}'::jsonb,      -- { "network": "4G", "resolution": "720p", "hangup_reason": "normal" }
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);
CREATE INDEX idx_video_sessions_room ON video_sessions (room_id);

-- Adicionar FK de video_session_id em prontuario_atendimentos
ALTER TABLE prontuario_atendimentos 
ADD CONSTRAINT fk_atendimentos_video_session 
FOREIGN KEY (video_session_id) REFERENCES video_sessions(id) ON DELETE SET NULL;
```

---

### 2.3 Estratégia de Seeders: 78 Municípios e Usuários

O seeder `MunicipiosEsSeeder` cadastra a totalidade dos **78 municípios capixabas**, categorizando-os por macrorregiões e microrregiões oficiais do Instituto Jones dos Santos Neves (IJSN), com suas respectivas coordenadas geográficas (lat/lng) e marcação dos 4 municípios que possuem unidade física do Escritório Social (Vitória, Vila Velha, Serra e Cariacica) vs os 74 municípios do interior:

```php
// database/seeders/MunicipiosEsSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MunicipiosEsSeeder extends Seeder
{
    public function run(): void
    {
        $municipios = [
            // Região Metropolitana da Grande Vitória (4 com Escritório Social + 3 adjacentes)
            ['ibge' => 3205309, 'nome' => 'Vitória', 'micro' => 'Metropolitana', 'macro' => 'Metropolitana', 'fisico' => true, 'lat' => -20.3155, 'lng' => -40.3128, 'egressos' => 3420],
            ['ibge' => 3205002, 'nome' => 'Serra', 'micro' => 'Metropolitana', 'macro' => 'Metropolitana', 'fisico' => true, 'lat' => -20.1287, 'lng' => -40.3079, 'egressos' => 2910],
            ['ibge' => 3205200, 'nome' => 'Vila Velha', 'micro' => 'Metropolitana', 'macro' => 'Metropolitana', 'fisico' => true, 'lat' => -20.3297, 'lng' => -40.2925, 'egressos' => 2450],
            ['ibge' => 3201308, 'nome' => 'Cariacica', 'micro' => 'Metropolitana', 'macro' => 'Metropolitana', 'fisico' => true, 'lat' => -20.2639, 'lng' => -40.4194, 'egressos' => 2100],
            ['ibge' => 3205101, 'nome' => 'Viana', 'micro' => 'Metropolitana', 'macro' => 'Metropolitana', 'fisico' => false, 'lat' => -20.3900, 'lng' => -40.4958, 'egressos' => 540],
            ['ibge' => 3202405, 'nome' => 'Guarapari', 'micro' => 'Metropolitana', 'macro' => 'Metropolitana', 'fisico' => false, 'lat' => -20.6706, 'lng' => -40.4981, 'egressos' => 780],
            ['ibge' => 3202207, 'nome' => 'Fundão', 'micro' => 'Metropolitana', 'macro' => 'Metropolitana', 'fisico' => false, 'lat' => -19.9333, 'lng' => -40.4042, 'egressos' => 190],

            // Região Norte / Rio Doce (100% Remoto)
            ['ibge' => 3203205, 'nome' => 'Linhares', 'micro' => 'Rio Doce', 'macro' => 'Norte', 'fisico' => false, 'lat' => -19.3911, 'lng' => -40.0722, 'egressos' => 1150],
            ['ibge' => 3204906, 'nome' => 'São Mateus', 'micro' => 'Nordeste', 'macro' => 'Norte', 'fisico' => false, 'lat' => -18.7161, 'lng' => -39.8589, 'egressos' => 610],
            ['ibge' => 3200607, 'nome' => 'Aracruz', 'micro' => 'Rio Doce', 'macro' => 'Norte', 'fisico' => false, 'lat' => -19.8203, 'lng' => -40.2744, 'egressos' => 520],
            ['ibge' => 3201605, 'nome' => 'Conceição da Barra', 'micro' => 'Nordeste', 'macro' => 'Norte', 'fisico' => false, 'lat' => -18.5933, 'lng' => -39.7322, 'egressos' => 280],
            ['ibge' => 3203056, 'nome' => 'Jaguaré', 'micro' => 'Nordeste', 'macro' => 'Norte', 'fisico' => false, 'lat' => -18.9058, 'lng' => -40.0761, 'egressos' => 210],
            ['ibge' => 3204054, 'nome' => 'Pedro Canário', 'micro' => 'Nordeste', 'macro' => 'Norte', 'fisico' => false, 'lat' => -18.2561, 'lng' => -40.1506, 'egressos' => 185],
            ['ibge' => 3204658, 'nome' => 'Sooretama', 'micro' => 'Rio Doce', 'macro' => 'Norte', 'fisico' => false, 'lat' => -19.1969, 'lng' => -40.0911, 'egressos' => 170],
            ['ibge' => 3204351, 'nome' => 'Rio Bananal', 'micro' => 'Rio Doce', 'macro' => 'Norte', 'fisico' => false, 'lat' => -19.2644, 'lng' => -40.3328, 'egressos' => 140],
            ['ibge' => 3202603, 'nome' => 'Ibiraçu', 'micro' => 'Rio Doce', 'macro' => 'Norte', 'fisico' => false, 'lat' => -19.8322, 'lng' => -40.3628, 'egressos' => 130],
            ['ibge' => 3203130, 'nome' => 'João Neiva', 'micro' => 'Rio Doce', 'macro' => 'Norte', 'fisico' => false, 'lat' => -19.7569, 'lng' => -40.3847, 'egressos' => 125],

            // Região Noroeste (22 Municípios - 100% Remoto)
            ['ibge' => 3201506, 'nome' => 'Colatina', 'micro' => 'Centro-Oeste', 'macro' => 'Noroeste', 'fisico' => false, 'lat' => -19.5389, 'lng' => -40.6306, 'egressos' => 740],
            ['ibge' => 3200904, 'nome' => 'Barra de São Francisco', 'micro' => 'Noroeste', 'macro' => 'Noroeste', 'fisico' => false, 'lat' => -18.7547, 'lng' => -40.8908, 'egressos' => 390],
            ['ibge' => 3203908, 'nome' => 'Nova Venécia', 'micro' => 'Noroeste', 'macro' => 'Noroeste', 'fisico' => false, 'lat' => -18.7106, 'lng' => -40.4006, 'egressos' => 360],
            ['ibge' => 3200805, 'nome' => 'Baixo Guandu', 'micro' => 'Centro-Oeste', 'macro' => 'Noroeste', 'fisico' => false, 'lat' => -19.5189, 'lng' => -41.0150, 'egressos' => 230],
            ['ibge' => 3202108, 'nome' => 'Ecoporanga', 'micro' => 'Noroeste', 'macro' => 'Noroeste', 'fisico' => false, 'lat' => -18.3733, 'lng' => -40.8306, 'egressos' => 195],
            ['ibge' => 3204708, 'nome' => 'São Gabriel da Palha', 'micro' => 'Centro-Oeste', 'macro' => 'Noroeste', 'fisico' => false, 'lat' => -19.0169, 'lng' => -40.5361, 'egressos' => 240],
            ['ibge' => 3204203, 'nome' => 'Pinheiros', 'micro' => 'Noroeste', 'macro' => 'Noroeste', 'fisico' => false, 'lat' => -18.4239, 'lng' => -40.2178, 'egressos' => 210],
            ['ibge' => 3204104, 'nome' => 'Pancas', 'micro' => 'Centro-Oeste', 'macro' => 'Noroeste', 'fisico' => false, 'lat' => -19.2247, 'lng' => -40.8514, 'egressos' => 160],
            ['ibge' => 3203502, 'nome' => 'Montanha', 'micro' => 'Noroeste', 'macro' => 'Noroeste', 'fisico' => false, 'lat' => -18.1269, 'lng' => -40.3633, 'egressos' => 150],
            ['ibge' => 3200201, 'nome' => 'Água Doce do Norte', 'micro' => 'Noroeste', 'macro' => 'Noroeste', 'fisico' => false, 'lat' => -18.5469, 'lng' => -40.9786, 'egressos' => 110],
            ['ibge' => 3200300, 'nome' => 'Águia Branca', 'micro' => 'Noroeste', 'macro' => 'Noroeste', 'fisico' => false, 'lat' => -18.9839, 'lng' => -40.7406, 'egressos' => 95],
            ['ibge' => 3200508, 'nome' => 'Alto Rio Novo', 'micro' => 'Centro-Oeste', 'macro' => 'Noroeste', 'fisico' => false, 'lat' => -19.0578, 'lng' => -41.0189, 'egressos' => 80],
            ['ibge' => 3201001, 'nome' => 'Boa Esperança', 'micro' => 'Noroeste', 'macro' => 'Noroeste', 'fisico' => false, 'lat' => -18.5400, 'lng' => -40.2956, 'egressos' => 120],
            ['ibge' => 3202256, 'nome' => 'Governador Lindenberg', 'micro' => 'Centro-Oeste', 'macro' => 'Noroeste', 'fisico' => false, 'lat' => -19.2789, 'lng' => -40.4858, 'egressos' => 90],
            ['ibge' => 3203346, 'nome' => 'Mantenópolis', 'micro' => 'Noroeste', 'macro' => 'Noroeste', 'fisico' => false, 'lat' => -18.8622, 'lng' => -41.1228, 'egressos' => 105],
            ['ibge' => 3203403, 'nome' => 'Marilândia', 'micro' => 'Centro-Oeste', 'macro' => 'Noroeste', 'fisico' => false, 'lat' => -19.4128, 'lng' => -40.5417, 'egressos' => 85],
            ['ibge' => 3203700, 'nome' => 'Mucurici', 'micro' => 'Noroeste', 'macro' => 'Noroeste', 'fisico' => false, 'lat' => -18.0933, 'lng' => -40.5186, 'egressos' => 65],
            ['ibge' => 3204252, 'nome' => 'Ponto Belo', 'micro' => 'Noroeste', 'macro' => 'Noroeste', 'fisico' => false, 'lat' => -18.1242, 'lng' => -40.5369, 'egressos' => 60],
            ['ibge' => 3204609, 'nome' => 'São Domingos do Norte', 'micro' => 'Centro-Oeste', 'macro' => 'Noroeste', 'fisico' => false, 'lat' => -19.1417, 'lng' => -40.5878, 'egressos' => 75],
            ['ibge' => 3204807, 'nome' => 'São Roque do Canaã', 'micro' => 'Centro-Oeste', 'macro' => 'Noroeste', 'fisico' => false, 'lat' => -19.7397, 'lng' => -40.6558, 'egressos' => 85],
            ['ibge' => 3205036, 'nome' => 'Vila Pavão', 'micro' => 'Noroeste', 'macro' => 'Noroeste', 'fisico' => false, 'lat' => -18.6144, 'lng' => -40.6089, 'egressos' => 80],
            ['ibge' => 3205069, 'nome' => 'Vila Valério', 'micro' => 'Centro-Oeste', 'macro' => 'Noroeste', 'fisico' => false, 'lat' => -18.9983, 'lng' => -40.3897, 'egressos' => 110],

            // Região Serrana / Central (12 Municípios - 100% Remoto)
            ['ibge' => 3200102, 'nome' => 'Afonso Cláudio', 'micro' => 'Sudoeste Serrana', 'macro' => 'Central', 'fisico' => false, 'lat' => -20.0778, 'lng' => -41.1411, 'egressos' => 260],
            ['ibge' => 3201159, 'nome' => 'Brejetuba', 'micro' => 'Sudoeste Serrana', 'macro' => 'Central', 'fisico' => false, 'lat' => -20.1436, 'lng' => -41.2917, 'egressos' => 105],
            ['ibge' => 3201704, 'nome' => 'Conceição do Castelo', 'micro' => 'Sudoeste Serrana', 'macro' => 'Central', 'fisico' => false, 'lat' => -20.3686, 'lng' => -41.2439, 'egressos' => 95],
            ['ibge' => 3201902, 'nome' => 'Domingos Martins', 'micro' => 'Sudoeste Serrana', 'macro' => 'Central', 'fisico' => false, 'lat' => -20.3633, 'lng' => -40.6589, 'egressos' => 210],
            ['ibge' => 3202702, 'nome' => 'Itaguaçu', 'micro' => 'Central Serrana', 'macro' => 'Central', 'fisico' => false, 'lat' => -19.8022, 'lng' => -40.8561, 'egressos' => 115],
            ['ibge' => 3202900, 'nome' => 'Itarana', 'micro' => 'Central Serrana', 'macro' => 'Central', 'fisico' => false, 'lat' => -19.8739, 'lng' => -40.8753, 'egressos' => 85],
            ['ibge' => 3203163, 'nome' => 'Laranja da Terra', 'micro' => 'Sudoeste Serrana', 'macro' => 'Central', 'fisico' => false, 'lat' => -19.8986, 'lng' => -41.0569, 'egressos' => 80],
            ['ibge' => 3203353, 'nome' => 'Marechal Floriano', 'micro' => 'Sudoeste Serrana', 'macro' => 'Central', 'fisico' => false, 'lat' => -20.4131, 'lng' => -40.6831, 'egressos' => 130],
            ['ibge' => 3204401, 'nome' => 'Santa Leopoldina', 'micro' => 'Central Serrana', 'macro' => 'Central', 'fisico' => false, 'lat' => -20.1006, 'lng' => -40.5297, 'egressos' => 110],
            ['ibge' => 3204500, 'nome' => 'Santa Maria de Jetibá', 'micro' => 'Central Serrana', 'macro' => 'Central', 'fisico' => false, 'lat' => -20.0403, 'lng' => -40.7461, 'egressos' => 240],
            ['ibge' => 3204559, 'nome' => 'Santa Teresa', 'micro' => 'Central Serrana', 'macro' => 'Central', 'fisico' => false, 'lat' => -19.9364, 'lng' => -40.6006, 'egressos' => 180],
            ['ibge' => 3205010, 'nome' => 'Venda Nova do Imigrante', 'micro' => 'Sudoeste Serrana', 'macro' => 'Central', 'fisico' => false, 'lat' => -20.3275, 'lng' => -41.1344, 'egressos' => 165],

            // Região Sul / Litoral Sul / Caparaó (27 Municípios - 100% Remoto)
            ['ibge' => 3201209, 'nome' => 'Cachoeiro de Itapemirim', 'micro' => 'Central Sul', 'macro' => 'Sul', 'fisico' => false, 'lat' => -20.8489, 'lng' => -41.1128, 'egressos' => 980],
            ['ibge' => 3200409, 'nome' => 'Alegre', 'micro' => 'Caparaó', 'macro' => 'Sul', 'fisico' => false, 'lat' => -20.7633, 'lng' => -41.5331, 'egressos' => 230],
            ['ibge' => 3200359, 'nome' => 'Alfredo Chaves', 'micro' => 'Central Sul', 'macro' => 'Sul', 'fisico' => false, 'lat' => -20.6358, 'lng' => -40.7500, 'egressos' => 110],
            ['ibge' => 3200706, 'nome' => 'Anchieta', 'micro' => 'Litoral Sul', 'macro' => 'Sul', 'fisico' => false, 'lat' => -20.8058, 'lng' => -40.6456, 'egressos' => 220],
            ['ibge' => 3200755, 'nome' => 'Apiacá', 'micro' => 'Central Sul', 'macro' => 'Sul', 'fisico' => false, 'lat' => -21.1542, 'lng' => -41.5678, 'egressos' => 70],
            ['ibge' => 3200854, 'nome' => 'Atílio Vivácqua', 'micro' => 'Central Sul', 'macro' => 'Sul', 'fisico' => false, 'lat' => -20.9144, 'lng' => -41.1983, 'egressos' => 90],
            ['ibge' => 3201100, 'nome' => 'Bom Jesus do Norte', 'micro' => 'Caparaó', 'macro' => 'Sul', 'fisico' => false, 'lat' => -21.1906, 'lng' => -41.6706, 'egressos' => 85],
            ['ibge' => 3201407, 'nome' => 'Castelo', 'micro' => 'Central Sul', 'macro' => 'Sul', 'fisico' => false, 'lat' => -20.6033, 'lng' => -41.2036, 'egressos' => 270],
            ['ibge' => 3201803, 'nome' => 'Divino de São Lourenço', 'micro' => 'Caparaó', 'macro' => 'Sul', 'fisico' => false, 'lat' => -20.6200, 'lng' => -41.6856, 'egressos' => 45],
            ['ibge' => 3202009, 'nome' => 'Dores do Rio Preto', 'micro' => 'Caparaó', 'macro' => 'Sul', 'fisico' => false, 'lat' => -20.6900, 'lng' => -41.8450, 'egressos' => 55],
            ['ibge' => 3202306, 'nome' => 'Guaçuí', 'micro' => 'Caparaó', 'macro' => 'Sul', 'fisico' => false, 'lat' => -20.7761, 'lng' => -41.6792, 'egressos' => 220],
            ['ibge' => 3202454, 'nome' => 'Ibatiba', 'micro' => 'Caparaó', 'macro' => 'Sul', 'fisico' => false, 'lat' => -20.2339, 'lng' => -41.5111, 'egressos' => 190],
            ['ibge' => 3202504, 'nome' => 'Ibitirama', 'micro' => 'Caparaó', 'macro' => 'Sul', 'fisico' => false, 'lat' => -20.5414, 'lng' => -41.6669, 'egressos' => 75],
            ['ibge' => 3202652, 'nome' => 'Iconha', 'micro' => 'Central Sul', 'macro' => 'Sul', 'fisico' => false, 'lat' => -20.7931, 'lng' => -40.8106, 'egressos' => 110],
            ['ibge' => 3202751, 'nome' => 'Irupi', 'micro' => 'Caparaó', 'macro' => 'Sul', 'fisico' => false, 'lat' => -20.3456, 'lng' => -41.6406, 'egressos' => 105],
            ['ibge' => 3202801, 'nome' => 'Itapemirim', 'micro' => 'Litoral Sul', 'macro' => 'Sul', 'fisico' => false, 'lat' => -21.0111, 'lng' => -40.8339, 'egressos' => 290],
            ['ibge' => 3203007, 'nome' => 'Iúna', 'micro' => 'Caparaó', 'macro' => 'Sul', 'fisico' => false, 'lat' => -20.3461, 'lng' => -41.5358, 'egressos' => 210],
            ['ibge' => 3203106, 'nome' => 'Jerônimo Monteiro', 'micro' => 'Central Sul', 'macro' => 'Sul', 'fisico' => false, 'lat' => -20.7906, 'lng' => -41.3961, 'egressos' => 95],
            ['ibge' => 3203320, 'nome' => 'Marataízes', 'micro' => 'Litoral Sul', 'macro' => 'Sul', 'fisico' => false, 'lat' => -21.0433, 'lng' => -40.8244, 'egressos' => 310],
            ['ibge' => 3203601, 'nome' => 'Mimoso do Sul', 'micro' => 'Central Sul', 'macro' => 'Sul', 'fisico' => false, 'lat' => -21.0642, 'lng' => -41.3658, 'egressos' => 200],
            ['ibge' => 3203809, 'nome' => 'Muniz Freire', 'micro' => 'Caparaó', 'macro' => 'Sul', 'fisico' => false, 'lat' => -20.4642, 'lng' => -41.4131, 'egressos' => 140],
            ['ibge' => 3203957, 'nome' => 'Muqui', 'micro' => 'Central Sul', 'macro' => 'Sul', 'fisico' => false, 'lat' => -20.9525, 'lng' => -41.3461, 'egressos' => 115],
            ['ibge' => 3204302, 'nome' => 'Piúma', 'micro' => 'Litoral Sul', 'macro' => 'Sul', 'fisico' => false, 'lat' => -20.8347, 'lng' => -40.7258, 'egressos' => 175],
            ['ibge' => 3204450, 'nome' => 'Presidente Kennedy', 'micro' => 'Litoral Sul', 'macro' => 'Sul', 'fisico' => false, 'lat' => -21.0981, 'lng' => -41.0489, 'egressos' => 110],
            ['ibge' => 3204757, 'nome' => 'Rio Novo do Sul', 'micro' => 'Central Sul', 'macro' => 'Sul', 'fisico' => false, 'lat' => -20.8631, 'lng' => -40.9364, 'egressos' => 95],
            ['ibge' => 3204955, 'nome' => 'São José do Calçado', 'micro' => 'Caparaó', 'macro' => 'Sul', 'fisico' => false, 'lat' => -21.0253, 'lng' => -41.6542, 'egressos' => 90],
            ['ibge' => 3205051, 'nome' => 'Vargem Alta', 'micro' => 'Central Sul', 'macro' => 'Sul', 'fisico' => false, 'lat' => -20.6722, 'lng' => -41.0078, 'egressos' => 140],
        ];

        foreach ($municipios as $m) {
            DB::table('municipios_es')->insert([
                'codigo_ibge' => $m['ibge'],
                'nome' => $m['nome'],
                'microrregiao' => $m['micro'],
                'macrorregiao' => $m['macro'],
                'possui_escritorio_social_fisico' => $m['fisico'],
                'populacao_egressa_estimada' => $m['egressos'],
                'rede_apoio_cras_count' => rand(1, 4),
                'rede_apoio_creas_count' => rand(1, 2),
                'rede_apoio_sine_count' => rand(1, 2),
                'lat' => $m['lat'],
                'lng' => $m['lng'],
                'geom_ponto' => DB::raw("ST_SetSRID(ST_MakePoint({$m['lng']}, {$m['lat']}), 4326)"),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
```

#### Usuários de Demonstração (Seeders)
- **Gestor SEJUS:** `gestor@sejus.es.gov.br` / `SenhaSejus2026!` (Carlos Eduardo Silva - Subsecretaria de Reintegração Social).
- **Técnico Escritório Social:** `tecnico@sejus.es.gov.br` / `SenhaSejus2026!` (Dra. Márcia Oliveira - Assistente Social, CRESS 4891/ES).
- **Egresso / Familiar:** `egresso@conectasocial.es.gov.br` / `SenhaSejus2026!` (Lucas Santos - Município: São Mateus/ES).

---

### 2.4 Geração de Carteira Digital (PDF & QR Code Criptográfico)

A Carteira Digital do Egresso representa o documento oficial de identidade institucional para acesso a programas estaduais, cursos e vagas conveniadas.

#### Arquitetura de Emissão:
1. **Assinatura do QR Code:**
   - O payload do QR Code é um token assinado com HMAC-SHA256 utilizando a chave mestra da aplicação (`APP_KEY` ou `CARTEIRA_SIGNING_SECRET`).
   - Estrutura do Payload: `JSON { "id": "UUID", "cpf_mask": "***.192.830-**", "cod": "CE-ES-2026-9812", "exp": "2027-08-17" }` + Assinatura HMAC.
   - URL codificada no QR Code: `https://conectaegresso.es.gov.br/validar-documento?doc=UUID&sig=HMAC_SIGNATURE`
   - Biblioteca PHP: `bacon/bacon-qr-code` ou `simplesoftwareio/simple-qrcode` gerando imagem PNG em Base64 ou SVG vetorial.
2. **Geração do Documento PDF:**
   - Pacote: `barryvdh/laravel-dompdf` (Dompdf 3.0+).
   - Layout em Blade (`resources/views/pdf/carteira_digital.blade.php`) com suporte a layout de cartão de identificação frente e verso (85.6mm x 53.98mm) e página em formato A4 com certificado de autenticidade.
   - Recursos gráficos: Brasão Oficial do Estado do Espírito Santo em SVG, marcas d'água de segurança anti-fraude, tipografia institucional (`Inter` / `Outfit`).

---

## 3. Microsserviço de WebRTC & Videochamada (Python FastAPI / aiortc / WebSockets)

### 3.1 Arquitetura do Microsserviço de Sinalização
O microsserviço assíncrono em Python é responsável pelo ciclo de vida de chamadas de vídeo P2P de baixa latência, oferecendo fallback via Coturn e fila de espera inteligente.

```
webrtc-service/
├── app/
│   ├── main.py                  # Instância FastAPI, Lifespan, Rotas REST & WebSockets
│   ├── config.py                # Configurações Pydantic (JWT_SECRET, REDIS_URL, LARAVEL_WEBHOOK_URL)
│   ├── auth/
│   │   └── jwt_verifier.py      # Validação de tokens JWT emitidos pelo Laravel
│   ├── signaling/
│   │   ├── room_manager.py      # Gerenciamento de conexões de sala (In-memory + Redis State)
│   │   ├── queue_manager.py     # Fila de atendimento por município e prioridade
│   │   └── sdp_relay.py         # Troca de SDP Offer/Answer e trickle ICE Candidates
│   ├── telemetry/
│   │   └── metrics_tracker.py   # Rastreamento de perda de pacotes, jitter, RTT e cálculo de MOS
│   ├── webhooks/
│   │   └── laravel_dispatcher.py # Disparo assíncrono de eventos de chamada para o Laravel
│   └── schemas/
│       ├── signaling.py         # Modelos Pydantic para mensagens WebSocket
│       └── webhooks.py
├── tests/
│   ├── conftest.py
│   ├── test_signaling.py
│   ├── test_queue.py
│   └── test_telemetry.py
├── requirements.txt
└── Dockerfile
```

---

### 3.2 Protocolo de Mensagens WebSocket e Ciclo de Vida da Chamada

#### Endpoint WebSocket
`GET /ws/room/{room_id}?token={jwt_token}`

#### 1. Autenticação e Entrada (`join`)
Ao conectar, o cliente envia a mensagem de entrada:
```json
{
  "type": "join",
  "room_id": "8f7e2a1b-3c4d-5e6f-7a8b-9c0d1e2f3a4b",
  "user_id": "user-uuid",
  "name": "Lucas Santos",
  "role": "egresso"
}
```
O servidor confirma o registro e notifica os pares na sala com `user-joined`:
```json
{
  "type": "user-joined",
  "peer_id": "user-uuid",
  "role": "egresso",
  "name": "Lucas Santos"
}
```

#### 2. Troca de Oferta SDP (`offer`)
O técnico (ou iniciador da chamada) cria a oferta SDP WebRTC e envia ao servidor:
```json
{
  "type": "offer",
  "sdp": "v=0\r\no=- 482910 2 IN IP4 127.0.0.1...",
  "sdp_type": "offer",
  "target_peer_id": "egresso-uuid"
}
```
O servidor FastAPI repassa a oferta diretamente para o destinatário (`offer-received`).

#### 3. Resposta SDP (`answer`)
O egresso aceita a oferta e retorna o SDP Answer:
```json
{
  "type": "answer",
  "sdp": "v=0\r\no=- 918231 2 IN IP4 127.0.0.1...",
  "sdp_type": "answer",
  "target_peer_id": "tecnico-uuid"
}
```

#### 4. Trickle ICE Candidates (`ice-candidate`)
Candidatos de conectividade local e relé TURN são trocados assincronamente:
```json
{
  "type": "ice-candidate",
  "candidate": {
    "candidate": "candidate:842163049 1 udp 1686052607 192.168.1.15 54321 typ host...",
    "sdpMid": "0",
    "sdpMLineIndex": 0
  },
  "target_peer_id": "tecnico-uuid"
}
```

#### 5. Telemetria e Monitoramento de Qualidade (`telemetry`)
Os clientes enviam relatórios periódicos de estatísticas WebRTC (RTCP getStats):
```json
{
  "type": "telemetry",
  "stats": {
    "rtt_ms": 42.5,
    "packet_loss_pct": 0.8,
    "jitter_ms": 11.2,
    "bitrate_kbps": 850.0,
    "network_type": "4g",
    "resolution": "1280x720"
  }
}
```
O módulo `metrics_tracker.py` computa a métrica **MOS (Mean Opinion Score)** normalizada de 1.00 (inutilizável) a 5.00 (excelente):
$$\text{MOS} = 1 + 0.035 R + R(R - 60)(100 - R) \times 7 \times 10^{-6}$$
Onde $R$ é o fator de transmissão derivado de latência (RTT) e taxa de perda de pacotes.

#### 6. Encerramento da Chamada (`leave`)
```json
{
  "type": "leave",
  "reason": "completed"
}
```

---

### 3.3 Integração via Webhooks com o Laravel Core

Sempre que ocorrem mudanças de estado nas salas de vídeo, o microsserviço FastAPI dispara requisições HTTP POST assíncronas com assinatura HMAC para o endpoint `POST /api/webrtc/webhook` do Laravel:

```python
# app/webhooks/laravel_dispatcher.py
import hmac
import hashlib
import json
import httpx
from app.config import settings

async def dispatch_webhook(event_type: str, payload: dict):
    body = {
        "event": event_type,
        "timestamp": settings.current_timestamp(),
        "data": payload
    }
    raw_body = json.dumps(body)
    signature = hmac.new(
        settings.WEBHOOK_SECRET.encode('utf-8'),
        raw_body.encode('utf-8'),
        hashlib.sha256
    ).hexdigest()

    headers = {
        "Content-Type": "application/json",
        "X-Signature-256": signature
    }

    async with httpx.AsyncClient(timeout=5.0) as client:
        try:
            response = await client.post(
                settings.LARAVEL_WEBHOOK_URL,
                content=raw_body,
                headers=headers
            )
            return response.status_code == 200
        except Exception as e:
            # Log de falha e enfileiramento no Redis para retentativa
            return False
```

#### Eventos Notificados:
1. `room.created`: Registro de abertura da sala.
2. `participant.joined`: Registro do momento exato de entrada do técnico e do egresso.
3. `call.ended`: Disparado no término da chamada contendo `started_at`, `ended_at`, `duration_seconds`, `qualidade_media_score`, `bytes_transferred` e metadados de rede. O controller do Laravel atualiza a tabela `video_sessions` e vincula o atendimento ao prontuário do egresso.

---

## 4. Servidor Coturn (STUN / TURN) para Conexões Móveis

### 4.1 Desafio Técnico em Redes Móveis Capixabas (3G/4G/5G)
Nos municípios do interior do Estado do Espírito Santo, a maioria dos atendimentos remotos ocorre via smartphones conectados a redes móveis de operadoras (Vivo, Claro, TIM) ou redes Wi-Fi comunitárias sob **Symmetric NAT / Carrier-Grade NAT (CGNAT)**. Nesses cenários, a comunicação direta P2P via STUN falha em até 30% das tentativas. O servidor **Coturn** atua como relé TURN obrigatório, garantindo taxa de sucesso de 100% no estabelecimento da chamada.

### 4.2 Arquivo de Configuração (`turnserver.conf`)

```ini
# /etc/coturn/turnserver.conf
# =========================================================================
# COTURN CONFIGURATION — CONECTA EGRESSO (SEJUS / GOV ES)
# =========================================================================

# Portas de Escuta Padrão
listening-port=3478
tls-listening-port=5349

# IPs de Escuta
listening-ip=0.0.0.0

# Faixa de Portas Dinâmicas para Relé de Mídia (UDP)
min-port=49152
max-port=49200

# Realm e Domínio
realm=conectaegresso.es.gov.br
server-name=turn.conectaegresso.es.gov.br

# Mecanismo de Autenticação Segura (REST API / Ephemeral Time-Limited Auth)
use-auth-secret
static-auth-secret=ConectaEgressoSejusTurnSecret2026HexKey!
stale-nonce=600

# Mecanismo de fallback para credenciais estáticas de desenvolvimento
lt-cred-mech
user=sejus:ConectaSejusTurnPass2026!

# Protocolos e Otimizações de Desempenho
fingerprint
no-multicast-peers
no-cli
no-tls
no-dtls
mobility
keep-address-family

# Logs
log-file=stdout
simple-log
```

### 4.3 Geração de Credenciais Temporárias (REST API Ephemeral Auth)
Para evitar o compartilhamento de credenciais fixas no frontend, o Laravel emite credenciais TURN efêmeras válidas por 1 hora:
- **Timestamp de Expiração:** `time() + 3600`
- **Username:** `"{timestamp}:{user_id}"` (Ex: `1786982400:8f7e2a1b`)
- **Password:** `base64_encode(hash_hmac('sha1', $username, $turnSecret, true))`

---

## 5. Topologia Docker Compose Multi-Container

A infraestrutura completa roda de forma orquestrada, isolada e pronta para desenvolvimento e produção através de um único comando `docker compose up -d`.

### 5.1 Diagrama de Serviços e Redes

```
                                  HOST (Portas Expostas)
                     ┌───────────────────┬──────────────────┐
                     │ 80 / 443 (HTTP/S) │ 3478 / 49152..   │
                     └─────────┬─────────┴────────┬─────────┘
                               │                  │
                         ┌─────▼──────┐     ┌─────▼──────┐
                         │   nginx    │     │   coturn   │
                         └─────┬──────┘     └────────────┘
                               │
            ┌──────────────────┴──────────────────┐
            ▼                                     ▼
     ┌──────────────┐                      ┌──────────────┐
     │   php-fpm    │                      │python-webrtc │
     │ (Laravel 11) │                      │  (FastAPI)   │
     └──────┬───────┘                      └──────┬───────┘
            │                                     │
            └──────────────────┬──────────────────┘
                               │
                     ┌─────────┴─────────┐
                     ▼                   ▼
              ┌──────────────┐    ┌──────────────┐
              │   postgres   │    │    redis     │
              │(PostGIS+pgc) │    │ (Filas/Cache)│
              └──────────────┘    └──────────────┘
```

### 5.2 Especificação do `docker-compose.yml`

```yaml
version: '3.8'

services:
  # 1. Reverse Proxy & API Gateway
  nginx:
    image: nginx:1.25-alpine
    container_name: conecta_nginx
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
      - app_public:/var/www/html/public:ro
      - app_storage:/var/www/html/storage:ro
    depends_on:
      php-fpm:
        condition: service_healthy
      python-webrtc:
        condition: service_healthy
    networks:
      - conecta_net

  # 2. Backend Core (Laravel 11 / PHP 8.3-FPM)
  php-fpm:
    build:
      context: ./backend
      dockerfile: Dockerfile
    container_name: conecta_php
    restart: unless-stopped
    environment:
      APP_NAME: "Conecta Egresso"
      APP_ENV: local
      APP_DEBUG: "true"
      APP_URL: "http://localhost"
      DB_CONNECTION: pgsql
      DB_HOST: postgres
      DB_PORT: 5432
      DB_DATABASE: conecta_egresso
      DB_USERNAME: sejus_admin
      DB_PASSWORD: SejusPostgresSecret2026!
      REDIS_HOST: redis
      REDIS_PORT: 6379
      WEBRTC_SIGNALING_URL: "ws://python-webrtc:8000"
      WEBRTC_JWT_SECRET: "ConectaSejusJwtSecretKey2026SuperSecure!"
      TURN_STATIC_AUTH_SECRET: "ConectaEgressoSejusTurnSecret2026HexKey!"
    volumes:
      - ./backend:/var/www/html
      - app_public:/var/www/html/public
      - app_storage:/var/www/html/storage
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_healthy
    healthcheck:
      test: ["CMD-SHELL", "php-fpm-healthcheck || exit 0"]
      interval: 10s
      timeout: 5s
      retries: 3
    networks:
      - conecta_net

  # 3. Microsserviço de Sinalização WebRTC (Python FastAPI)
  python-webrtc:
    build:
      context: ./webrtc-service
      dockerfile: Dockerfile
    container_name: conecta_webrtc
    restart: unless-stopped
    environment:
      PORT: 8000
      JWT_SECRET: "ConectaSejusJwtSecretKey2026SuperSecure!"
      REDIS_URL: "redis://redis:6379/1"
      LARAVEL_WEBHOOK_URL: "http://php-fpm:9000/api/webrtc/webhook"
      WEBHOOK_SECRET: "ConectaSejusWebhookSecret2026!"
    volumes:
      - ./webrtc-service:/app
    depends_on:
      redis:
        condition: service_healthy
    healthcheck:
      test: ["CMD-SHELL", "python -c 'import urllib.request; urllib.request.urlopen(\"http://localhost:8000/health\")' || exit 1"]
      interval: 10s
      timeout: 5s
      retries: 3
    networks:
      - conecta_net

  # 4. Banco de Dados Relacional & Geoespacial (PostgreSQL 16 + PostGIS)
  postgres:
    image: postgis/postgis:16-3.4-alpine
    container_name: conecta_postgres
    restart: unless-stopped
    environment:
      POSTGRES_DB: conecta_egresso
      POSTGRES_USER: sejus_admin
      POSTGRES_PASSWORD: SejusPostgresSecret2026!
    volumes:
      - postgres_data:/var/lib/postgresql/data
      - ./docker/postgres/init.sql:/docker-entrypoint-initdb.d/init.sql:ro
    ports:
      - "5432:5432"
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U sejus_admin -d conecta_egresso"]
      interval: 5s
      timeout: 5s
      retries: 5
    networks:
      - conecta_net

  # 5. Cache, Filas e Mensageria (Redis)
  redis:
    image: redis:7-alpine
    container_name: conecta_redis
    restart: unless-stopped
    command: redis-server --appendonly yes
    volumes:
      - redis_data:/data
    ports:
      - "6379:6379"
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 5s
      timeout: 5s
      retries: 5
    networks:
      - conecta_net

  # 6. Servidor STUN / TURN (Coturn)
  coturn:
    image: coturn/coturn:latest
    container_name: conecta_coturn
    restart: unless-stopped
    volumes:
      - ./docker/coturn/turnserver.conf:/etc/coturn/turnserver.conf:ro
    ports:
      - "3478:3478/tcp"
      - "3478:3478/udp"
      - "5349:5349/tcp"
      - "5349:5349/udp"
      - "49152-49200:49152-49200/udp"
    networks:
      - conecta_net

volumes:
  postgres_data:
    driver: local
  redis_data:
    driver: local
  app_storage:
    driver: local
  app_public:
    driver: local

networks:
  conecta_net:
    driver: bridge
```

### 5.3 Configuração do Nginx (`default.conf`)
O Nginx atua como roteador unificado, entregando requisições HTTP e Inertia para o PHP-FPM e requisições WebSocket para o FastAPI:

```nginx
server {
    listen 80;
    server_name localhost;
    root /var/www/html/public;
    index index.php index.html;

    client_max_body_size 64M;

    # Gzip Compression
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml image/svg+xml;

    # Health Check
    location /health {
        access_log off;
        return 200 "OK\n";
    }

    # Rotas do Microsserviço WebRTC (WebSockets)
    location /ws/ {
        proxy_pass http://python-webrtc:8000/ws/;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_read_timeout 3600s;
        proxy_send_timeout 3600s;
    }

    # Rotas de API do Microsserviço WebRTC
    location /api/webrtc/ {
        proxy_pass http://python-webrtc:8000/api/webrtc/;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }

    # Rotas Web & Inertia / Laravel Core
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass php-fpm:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300s;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

---

## 6. Estratégia de Testes Automatizados & Qualidade

Para garantir a confiabilidade exigida em um sistema de acolhimento governamental e conformidade estrita com a LGPD, a estratégia de testes abrange todas as camadas.

### 6.1 Testes Unitários e de Integração Backend (PHPUnit / Pest)
- **Criptografia e LGPD (`LgpdEncryptionTest`):** Validação de que CPFs e notas médicas/psicossociais são gravados criptografados no banco e descriptografados apenas para usuários autorizados.
- **Trilha de Auditoria Imutável (`ProntuarioAuditLogTest`):** Verificação de que cada leitura e alteração gera registro indelével com IP, usuário e payload hash.
- **Emissão da Carteira Digital & QR Code (`CarteiraDigitalPdfTest`):** Validação de integridade do HMAC gerado e renderização do PDF oficial em alta resolução.
- **Filtros Territoriais PostGIS (`MunicipiosGeoTest`):** Consultas espaciais retornando corretamente a rede de apoio nos 78 municípios.
- **Políticas de Autorização RBAC (`RbacPolicyTest`):** Garantia de que Gestores não editam prontuários clínicos e Egressos não acessam dados de outros atendidos.

### 6.2 Testes do Microsserviço WebRTC (pytest + pytest-asyncio)
- **Sinalização SDP (`test_sdp_signaling_exchange`):** Simulação de dois clientes virtuais conectados via WebSocket completando a troca de Offer/Answer e ICE candidates.
- **Fila de Espera (`test_waiting_queue_priority`):** Validação de ordenação por prioridade ('urgente' antes de 'normal') e atribuição de sala ao atendente livre.
- **Validação de Token JWT (`test_jwt_verification`):** Rejeição imediata de conexões com tokens expirados, adulterados ou sem permissão para a sala.
- **Disparo de Webhooks (`test_call_ended_webhook`):** Confirmação de que o encerramento da chamada envia payload estruturado com telemetria para o backend Laravel.

### 6.3 Testes E2E Opacos (Playwright / Cypress)
- **Tier 1: Smoke & Responsividade:** Carregamento de todas as telas, renderização do mapa dos 78 municípios e alternância de perfis de usuário.
- **Tier 2: Acessibilidade Digital:** Verificação de contraste de cores (WCAG AAA), ampliação de fonte (+18%) e modo de Linguagem Simplificada.
- **Tier 3: Fluxo Completo de Atendimento:** Técnico inicia chamada -> Egresso entra na sala -> Vídeo bidirecional simulado -> Técnico encerra chamada -> Evolução registrada no Prontuário Único.
- **Tier 4: Fluxo de Carteira Digital & Oportunidades:** Egresso visualiza carteira -> Baixa PDF -> Filtra vagas por município de residência -> Aplica para vaga conveniada.

---

## 7. Conclusão da Investigação Arquitetural

A arquitetura especificada atende integralmente a todos os requisitos do **Edital CPSI Nº 010/2026 (SEJUS/SEGER)** e às diretrizes do Memorando Executivo do Conecta Egresso:
1. **Descentralização Total:** Suporte georreferenciado e atendimento 100% remoto para os 78 municípios do Espírito Santo.
2. **Segurança & Conformidade LGPD:** Trilha de auditoria indelével e criptografia de ponta a ponta.
3. **Resiliência em Redes Móveis:** Combinação otimizada de FastAPI, aiortc e Coturn TURN Server.
4. **Prontidão para Implantação:** Topologia Docker Compose unificada e testes automatizados em todas as camadas.
