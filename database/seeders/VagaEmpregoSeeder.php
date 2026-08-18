<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\VagaEmprego;
use App\Models\MunicipioEs;

class VagaEmpregoSeeder extends Seeder
{
    /**
     * Run the database seeds for Job Openings across Espírito Santo.
     */
    public function run(): void
    {
        $vitoria = MunicipioEs::where('nome', 'Vitória')->first();
        $vilaVelha = MunicipioEs::where('nome', 'Vila Velha')->first();
        $serra = MunicipioEs::where('nome', 'Serra')->first();
        $cariacica = MunicipioEs::where('nome', 'Cariacica')->first();
        $linhares = MunicipioEs::where('nome', 'Linhares')->first();
        $colatina = MunicipioEs::where('nome', 'Colatina')->first();
        $cachoeiro = MunicipioEs::where('nome', 'Cachoeiro de Itapemirim')->first();
        $saoMateus = MunicipioEs::where('nome', 'São Mateus')->first();
        $aracruz = MunicipioEs::where('nome', 'Aracruz')->first();

        $vagas = [
            [
                'empresa' => 'Porto de Tubarão Logística S.A.',
                'titulo' => 'Auxiliar de Logística e Carga Portuária',
                'descricao' => 'Atuação no pátio logístico com movimentação de cargas, paletização e suporte a conferência de contêineres. Empresa parceira com plano de carreira acelerado.',
                'categoria' => 'logistica',
                'municipio_id' => $vitoria ? $vitoria->id : 78,
                'salario' => 2100.00,
                'regime_contratacao' => 'CLT',
                'afirmativa_egresso' => true,
                'empresa_amiga_reintegracao' => true,
                'escolaridade_minima' => 'Ensino Fundamental Completo',
                'vagas_totais' => 6,
                'vagas_preenchidas' => 2,
                'status' => 'aberta',
                'beneficios' => ['Vale Transporte', 'Vale Refeição (R$ 35/dia)', 'Plano de Saúde', 'Seguro de Vida'],
            ],
            [
                'empresa' => 'Cooperativa Agropecuária do Espírito Santo (COOPEG)',
                'titulo' => 'Operador de Máquinas Agrícolas e Tratorista',
                'descricao' => 'Operação de tratores agrícolas, colheitadeiras de café e implementos de irrigação em fazendas parceiras na região norte/central.',
                'categoria' => 'agropecuaria',
                'municipio_id' => $colatina ? $colatina->id : 19,
                'salario' => 2800.00,
                'regime_contratacao' => 'CLT',
                'afirmativa_egresso' => true,
                'empresa_amiga_reintegracao' => true,
                'escolaridade_minima' => 'sem_exigencia',
                'vagas_totais' => 4,
                'vagas_preenchidas' => 1,
                'status' => 'aberta',
                'beneficios' => ['Alojamento Rural', 'Cesta Básica Familiar', 'Plano Odontológico'],
            ],
            [
                'empresa' => 'Construtora Capixaba S.A.',
                'titulo' => 'Oficial de Construção Civil e Armação',
                'descricao' => 'Execução de alvenaria estrutural, armação de ferragens, formas de concreto e acabamentos prediais em canteiros de obras residenciais.',
                'categoria' => 'construcao_civil',
                'municipio_id' => $vilaVelha ? $vilaVelha->id : 77,
                'salario' => 2450.00,
                'regime_contratacao' => 'CLT',
                'afirmativa_egresso' => true,
                'empresa_amiga_reintegracao' => true,
                'escolaridade_minima' => 'sem_exigencia',
                'vagas_totais' => 8,
                'vagas_preenchidas' => 3,
                'status' => 'aberta',
                'beneficios' => ['Vale Transporte', 'Café da Manhã e Almoço no Canteiro', 'EPI Completo', 'Bônus por Assiduidade'],
            ],
            [
                'empresa' => 'Estaleiro Naval Aracruz / Jurong',
                'titulo' => 'Montador Industrial e Caldeiraria Leve',
                'descricao' => 'Montagem de estruturas metálicas e tubulações industriais sob supervisão técnica. Preferência para formados em cursos SENAI.',
                'categoria' => 'industria',
                'municipio_id' => $aracruz ? $aracruz->id : 9,
                'salario' => 3200.00,
                'regime_contratacao' => 'CLT',
                'afirmativa_egresso' => true,
                'empresa_amiga_reintegracao' => true,
                'escolaridade_minima' => 'Ensino Médio Completo',
                'vagas_totais' => 5,
                'vagas_preenchidas' => 0,
                'status' => 'aberta',
                'beneficios' => ['Transporte Fretado', 'Refeitório no Local', 'Plano de Saúde Unimed', 'PLR'],
            ],
            [
                'empresa' => 'Rede de Supermercados Sul Capixaba',
                'titulo' => 'Atendente de Padaria e Confeitaria',
                'descricao' => 'Atendimento ao público, manipulação e fatiamento de alimentos e organização de gôndolas.',
                'categoria' => 'comercio',
                'municipio_id' => $cachoeiro ? $cachoeiro->id : 16,
                'salario' => 1750.00,
                'regime_contratacao' => 'CLT',
                'afirmativa_egresso' => true,
                'empresa_amiga_reintegracao' => true,
                'escolaridade_minima' => 'Ensino Fundamental Completo',
                'vagas_totais' => 3,
                'vagas_preenchidas' => 1,
                'status' => 'aberta',
                'beneficios' => ['Vale Transporte', 'Desconto em Compras (15%)', 'Cesta Básica'],
            ],
            [
                'empresa' => 'Capixaba Limpeza e Serviços Urbanos',
                'titulo' => 'Auxiliar de Serviços Gerais e Conservação',
                'descricao' => 'Higienização e conservação de edifícios comerciais e órgãos públicos parceiros.',
                'categoria' => 'servicos',
                'municipio_id' => $serra ? $serra->id : 70,
                'salario' => 1680.00,
                'regime_contratacao' => 'CLT',
                'afirmativa_egresso' => true,
                'empresa_amiga_reintegracao' => true,
                'escolaridade_minima' => 'sem_exigencia',
                'vagas_totais' => 10,
                'vagas_preenchidas' => 4,
                'status' => 'aberta',
                'beneficios' => ['Vale Transporte', 'Vale Alimentação (R$ 550/mês)', 'Adicional de Insalubridade'],
            ],
        ];

        foreach ($vagas as $v) {
            VagaEmprego::updateOrCreate(
                ['empresa' => $v['empresa'], 'titulo' => $v['titulo']],
                $v
            );
        }
    }
}
