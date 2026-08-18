<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Prontuario;
use App\Models\ProntuarioTimeline;
use App\Models\Egresso;
use App\Models\User;
use App\Services\AuditService;

class ProntuarioSeeder extends Seeder
{
    /**
     * Run the database seeds for Prontuários and Timeline Events.
     */
    public function run(): void
    {
        $tecnico = User::where('email', 'marcia.oliveira@sejus.es.gov.br')->first();
        $egresso1 = Egresso::find(1);
        $egresso2 = Egresso::find(2);

        $tecnicoId = $tecnico ? $tecnico->id : 2;

        if ($egresso1) {
            $prontuario1 = Prontuario::updateOrCreate(
                ['numero_prontuario' => 'PRT-2026-000001'],
                [
                    'egresso_id' => $egresso1->id,
                    'tecnico_responsavel_id' => $tecnicoId,
                    'situacao' => 'ativo',
                    'resumo_diagnostico' => 'Egresso acolhido via teleatendimento Conecta Egresso em São Mateus. Demonstra alta motivação para qualificação técnica em solda e reinserção formal no mercado de trabalho.',
                    'meta_plano_individual' => '1. Concluir capacitação SENAI; 2. Encaminhar para processo seletivo em indústria regional; 3. Manter acompanhamento socioassistencial quinzenal.',
                    'data_abertura' => '2026-01-15 10:00:00',
                ]
            );

            // Timeline events
            ProntuarioTimeline::updateOrCreate(
                ['prontuario_id' => $prontuario1->id, 'tipo_evento' => 'acolhimento_video'],
                [
                    'titulo' => 'Acolhimento Inicial Remoto (São Mateus)',
                    'descricao' => 'Primeiro atendimento psicossocial realizado por videoconferência com suporte de conexão 4G estável. Identificada demanda de formação profissional e apoio documental.',
                    'metadata' => ['duracao_minutos' => 38, 'qualidade_mos' => 4.4, 'tipo_atendimento' => 'remoto_webrtc'],
                    'responsavel_id' => $tecnicoId,
                    'data_evento' => '2026-01-15 10:30:00',
                ]
            );

            ProntuarioTimeline::updateOrCreate(
                ['prontuario_id' => $prontuario1->id, 'tipo_evento' => 'inscricao_curso'],
                [
                    'titulo' => 'Inscrição no Curso de Solda Industrial (SENAI Linhares)',
                    'descricao' => 'Encaminhado e matriculado no programa de capacitação profissional SENAI/Findes com bolsa auxílio SEJUS.',
                    'metadata' => ['curso_id' => 1, 'instituicao' => 'SENAI Linhares', 'carga_horaria' => 160],
                    'responsavel_id' => $tecnicoId,
                    'data_evento' => '2026-01-22 14:00:00',
                ]
            );

            ProntuarioTimeline::updateOrCreate(
                ['prontuario_id' => $prontuario1->id, 'tipo_evento' => 'emissao_carteira'],
                [
                    'titulo' => 'Emissão da Carteira Digital do Egresso',
                    'descricao' => 'Documento oficial gerado com QR Code criptográfico para identificação e fruição de benefícios sociais.',
                    'metadata' => ['registro_sejus' => 'ES-2026-000001', 'formato' => 'PDF_DOMPDF'],
                    'responsavel_id' => $tecnicoId,
                    'data_evento' => '2026-01-25 09:00:00',
                ]
            );
        }

        if ($egresso2) {
            $prontuario2 = Prontuario::updateOrCreate(
                ['numero_prontuario' => 'PRT-2026-000002'],
                [
                    'egresso_id' => $egresso2->id,
                    'tecnico_responsavel_id' => $tecnicoId,
                    'situacao' => 'ativo',
                    'resumo_diagnostico' => 'Egresso sob regime de livramento condicional em Vitória. Experiência prévia em armação e concreto civil. Encaminhado para vaga na Grande Vitória.',
                    'meta_plano_individual' => '1. Encaminhamento para construtora conveniada; 2. Acompanhamento psicossocial mensal presencial no Escritório Social Sede Vitória.',
                    'data_abertura' => '2026-02-05 11:00:00',
                ]
            );

            ProntuarioTimeline::updateOrCreate(
                ['prontuario_id' => $prontuario2->id, 'tipo_evento' => 'atendimento_presencial'],
                [
                    'titulo' => 'Atendimento Presencial no Escritório Social Vitória',
                    'descricao' => 'Entrevista de acolhimento presencial na sede da SEJUS. Atualização cadastral e análise de currículo.',
                    'metadata' => ['polo' => 'Sede Vitória', 'setor' => 'Escritório Social'],
                    'responsavel_id' => $tecnicoId,
                    'data_evento' => '2026-02-05 11:30:00',
                ]
            );

            ProntuarioTimeline::updateOrCreate(
                ['prontuario_id' => $prontuario2->id, 'tipo_evento' => 'encaminhamento_vaga'],
                [
                    'titulo' => 'Encaminhamento para Oficial de Construção Civil',
                    'descricao' => 'Encaminhamento formal emitido para Construtora Capixaba S.A. com carta de recomendação da SEJUS.',
                    'metadata' => ['vaga_id' => 3, 'empresa' => 'Construtora Capixaba S.A.'],
                    'responsavel_id' => $tecnicoId,
                    'data_evento' => '2026-02-12 15:45:00',
                ]
            );
        }

        // Generate initial Genesis audit chain
        try {
            $auditService = app(AuditService::class);
            $auditService->log(1, 'CREATE_PRONTUARIO', ['numero' => 'PRT-2026-000001', 'egresso_id' => 1], $tecnicoId);
            $auditService->log(1, 'ADD_TIMELINE_EVENT', ['evento' => 'acolhimento_video'], $tecnicoId);
            $auditService->log(2, 'CREATE_PRONTUARIO', ['numero' => 'PRT-2026-000002', 'egresso_id' => 2], $tecnicoId);
        } catch (\Throwable $e) {
            // Standalone safe
        }
    }
}
