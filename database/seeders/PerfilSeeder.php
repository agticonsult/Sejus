<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Perfil;

class PerfilSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $perfis = [
            [
                'id' => 1,
                'nome' => 'Gestor SEJUS',
                'slug' => 'gestor',
                'descricao' => 'Administrador estadual da SEJUS com acesso total aos relatórios, auditoria e gestão da política pública.',
                'permissoes' => [
                    'prontuario' => ['read', 'write', 'delete', 'export', 'audit'],
                    'relatorios' => ['read', 'export'],
                    'vagas' => ['read', 'write', 'delete'],
                    'cursos' => ['read', 'write', 'delete'],
                    'webrtc' => ['manage', 'observe'],
                    'usuarios' => ['manage'],
                ],
                'ativo' => true,
            ],
            [
                'id' => 2,
                'nome' => 'Técnico Escritório Social',
                'slug' => 'tecnico',
                'descricao' => 'Assistente Social ou Psicólogo responsável pelos atendimentos, elaboração de PIR e encaminhamentos.',
                'permissoes' => [
                    'prontuario' => ['read', 'write'],
                    'relatorios' => ['read'],
                    'vagas' => ['read', 'encaminhar'],
                    'cursos' => ['read', 'inscrever'],
                    'webrtc' => ['host'],
                    'carteira' => ['emit'],
                ],
                'ativo' => true,
            ],
            [
                'id' => 3,
                'nome' => 'Egresso',
                'slug' => 'egresso',
                'descricao' => 'Cidadão egresso do sistema prisional com acesso à carteira digital, vagas, cursos e atendimento remoto.',
                'permissoes' => [
                    'carteira' => ['view', 'download'],
                    'vagas' => ['view', 'apply'],
                    'cursos' => ['view', 'enroll'],
                    'webrtc' => ['join'],
                    'prontuario' => ['view_own_timeline'],
                ],
                'ativo' => true,
            ],
            [
                'id' => 4,
                'nome' => 'Familiar',
                'slug' => 'familiar',
                'descricao' => 'Familiar autorizado para apoio e acompanhamento sociofamiliar.',
                'permissoes' => [
                    'webrtc' => ['join_assisted'],
                    'vagas' => ['view'],
                    'cursos' => ['view'],
                ],
                'ativo' => true,
            ],
        ];

        foreach ($perfis as $p) {
            Perfil::updateOrCreate(['id' => $p['id']], $p);
        }
    }
}
