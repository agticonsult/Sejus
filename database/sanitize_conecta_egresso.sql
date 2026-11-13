-- ==============================================================================
-- PROJETO CONECTA EGRESSO (SEJUS/ES) - SCRIPT DE SANITIZAÇÃO PRÉ-DEPLOY
-- COMPATIBILIDADE: PostgreSQL 16+
-- AUTOR: Database Sanitization Specialist
-- DATA: 2026-11-13
-- FINALIDADE: Limpeza integral de dados transacionais, de auditoria e de teste,
--             preservando rigorosamente estruturas, catálogos e administradores.
-- ==============================================================================

\set ON_ERROR_STOP on

BEGIN;

-- ------------------------------------------------------------------------------
-- 0. DESATIVA TEMPORARIAMENTE AS REGRAS DE IMUTABILIDADE DO POSTGRESQL
-- ------------------------------------------------------------------------------
-- A regra 'ON UPDATE TO prontuario_audit_logs DO INSTEAD NOTHING' bloqueia o 
-- 'ON DELETE SET NULL' na foreign key fk_prontuario_aud_user_id quando deletamos
-- usuários de teste. Desativamos temporariamente a regra durante a transação.
-- ------------------------------------------------------------------------------
DROP RULE IF EXISTS prontuario_audit_logs_no_update ON prontuario_audit_logs;
DROP RULE IF EXISTS prontuario_audit_logs_no_delete ON prontuario_audit_logs;

-- ------------------------------------------------------------------------------
-- 1. TRUNCAGEM DAS TABELAS TRANSACIONAIS E DE TESTE
-- ------------------------------------------------------------------------------
TRUNCATE TABLE 
    conference_transcript_entries,
    conference_transcripts,
    conference_recordings,
    conference_participants,
    conference_records,
    meeting_rooms,
    video_attendees,
    video_rooms,
    prontuario_audit_logs,
    prontuario_timeline,
    prontuarios,
    egresso_conhecimentos,
    egressos,
    mobile_access_tokens,
    sessions,
    password_reset_tokens
RESTART IDENTITY CASCADE;

-- ------------------------------------------------------------------------------
-- 2. HIGIENIZAÇÃO CIRÚRGICA DA TABELA 'users'
-- ------------------------------------------------------------------------------
-- Preserva:
--   - Gestores SEJUS (slug 'gestor')
--   - Equipe Técnica dos Escritórios Sociais (slug 'tecnico')
--   - Suporte Técnico e Sysadmins (slug 'suporte')
--   - Empregadores e Parceiros Credenciados (slug 'empregador')
-- Remove:
--   - Egressos semeados para teste (slug 'egresso')
--   - Familiares vinculados a testes (slug 'familiar')
--   - Contas temporárias geradas em esteiras de teste/QA
-- ------------------------------------------------------------------------------
DELETE FROM users 
WHERE perfil_id IN (
    SELECT id FROM perfis WHERE slug IN ('egresso', 'familiar')
)
OR email LIKE '%@cidadao.es.gov.br'
OR email LIKE '%teste%'
OR email LIKE '%example.com%';

-- ------------------------------------------------------------------------------
-- 3. RECALIBRAÇÃO DINÂMICA DA SEQUENCE DE 'users'
-- ------------------------------------------------------------------------------
-- Sincroniza o contador da sequence 'users_id_seq' com o maior ID remanescente,
-- evitando qualquer colisão de chave primária no próximo INSERT de produção.
-- ------------------------------------------------------------------------------
SELECT setval(
    pg_get_serial_sequence('users', 'id'), 
    COALESCE((SELECT MAX(id) FROM users), 1)
);

-- ------------------------------------------------------------------------------
-- 4. RESET DE CONTADORES E MÉTRICAS EM TABELAS DE DOMÍNIO PRESERVADAS
-- ------------------------------------------------------------------------------
-- Zera contadores estatísticos inflados por testes sem afetar os dados mestres.
-- ------------------------------------------------------------------------------
-- A. Redefine o acumulador de egressos atendidos nos 78 municípios capixabas
UPDATE municipios_es 
SET total_egressos_atendidos = 0;

-- B. Redefine status e preenchimento das vagas de emprego conveniadas
UPDATE vagas_emprego 
SET vagas_preenchidas = 0, 
    status = 'aberta';

-- ------------------------------------------------------------------------------
-- 5. VALIDAÇÃO DE INTEGRIDADE PÓS-LIMPEZA (ASSERTIONS)
-- ------------------------------------------------------------------------------
DO $$
DECLARE
    v_egressos_count INT;
    v_prontuarios_count INT;
    v_audit_count INT;
    v_admin_count INT;
    v_municipios_count INT;
    v_perfis_count INT;
BEGIN
    SELECT COUNT(*) INTO v_egressos_count FROM egressos;
    SELECT COUNT(*) INTO v_prontuarios_count FROM prontuarios;
    SELECT COUNT(*) INTO v_audit_count FROM prontuario_audit_logs;
    SELECT COUNT(*) INTO v_admin_count FROM users;
    SELECT COUNT(*) INTO v_municipios_count FROM municipios_es;
    SELECT COUNT(*) INTO v_perfis_count FROM perfis;

    IF v_egressos_count > 0 OR v_prontuarios_count > 0 OR v_audit_count > 0 THEN
        RAISE EXCEPTION 'FALHA DE SANITIZAÇÃO: Tabelas transacionais ainda contêm dados! Abortando.';
    END IF;

    IF v_admin_count = 0 THEN
        RAISE EXCEPTION 'FALHA DE SANITIZAÇÃO: Usuários administrativos foram deletados indevidamente! Abortando.';
    END IF;

    IF v_municipios_count <> 78 THEN
        RAISE EXCEPTION 'FALHA DE SANITIZAÇÃO: Catálogo de municípios do ES foi corrompido! Abortando.';
    END IF;

    IF v_perfis_count = 0 THEN
        RAISE EXCEPTION 'FALHA DE SANITIZAÇÃO: Perfis de acesso foram corrompidos! Abortando.';
    END IF;

    RAISE NOTICE 'VALIDAÇÃO CONCLUÍDA: Banco de dados sanitizado com sucesso e 100%% íntegro para Go-Live.';
END $$;

-- ------------------------------------------------------------------------------
-- 6. REATIVA AS REGRAS DE IMUTABILIDADE DO POSTGRESQL (LGPD)
-- ------------------------------------------------------------------------------
CREATE RULE prontuario_audit_logs_no_update AS ON UPDATE TO prontuario_audit_logs DO INSTEAD NOTHING;
CREATE RULE prontuario_audit_logs_no_delete AS ON DELETE TO prontuario_audit_logs DO INSTEAD NOTHING;

COMMIT;
