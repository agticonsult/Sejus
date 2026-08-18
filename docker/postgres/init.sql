-- =============================================================================
-- CONECTA EGRESSO (SEJUS/ES) - PostgreSQL 16 Database Initialization
-- =============================================================================

-- Habilitacao das extensoes essenciais para o sistema
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";
CREATE EXTENSION IF NOT EXISTS "postgis";

-- Comentarios informativos
COMMENT ON EXTENSION "uuid-ossp" IS 'Geracao de UUIDs v4 para chaves primarias';
COMMENT ON EXTENSION "pgcrypto" IS 'Criptografia simetrica AES-256 e funcoes HMAC para conformidade LGPD';
COMMENT ON EXTENSION "postgis" IS 'Geolocalizacao e inteligencia territorial dos 78 municipios do ES';
