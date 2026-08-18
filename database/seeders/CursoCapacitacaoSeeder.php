<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CursoCapacitacao;
use App\Models\MunicipioEs;

class CursoCapacitacaoSeeder extends Seeder
{
    /**
     * Run the database seeds for vocational courses.
     */
    public function run(): void
    {
        $linhares = MunicipioEs::where('nome', 'Linhares')->first();
        $vitoria = MunicipioEs::where('nome', 'Vitória')->first();
        $colatina = MunicipioEs::where('nome', 'Colatina')->first();
        $serra = MunicipioEs::where('nome', 'Serra')->first();

        $cursos = [
            [
                'instituicao' => 'SENAI / Findes / SEJUS',
                'titulo' => 'Capacitação em Solda Industrial (Eletrodo e MIG/MAG)',
                'descricao' => 'Formação prática de soldadores industriais para atendimento a indústrias navais e caldeirarias do ES. Oferece bolsa auxílio e material didático gratuito.',
                'categoria' => 'industrial',
                'municipio_id' => $linhares ? $linhares->id : 43,
                'carga_horaria' => 160,
                'modalidade' => 'presencial',
                'bolsa_auxilio' => 400.00,
                'vagas_disponiveis' => 25,
                'status' => 'aberto',
                'link_inscricao' => 'https://conectaegresso.es.gov.br/cursos/solda-linhares',
            ],
            [
                'instituicao' => 'IFES (Instituto Federal do Espírito Santo)',
                'titulo' => 'Letramento Digital, Smartphone & Informática Básica',
                'descricao' => 'Curso de inclusão digital acessível para cidadãos com baixo letramento tecnológico. Ensina uso de aplicativos de serviços públicos, Gov.br, e-mail e carteira de trabalho digital.',
                'categoria' => 'tecnologia',
                'municipio_id' => null, // 100% EAD
                'carga_horaria' => 60,
                'modalidade' => 'ead',
                'bolsa_auxilio' => null,
                'vagas_disponiveis' => 150,
                'status' => 'aberto',
                'link_inscricao' => 'https://conectaegresso.es.gov.br/cursos/letramento-digital-ifes',
            ],
            [
                'instituicao' => 'ADERES / Banestes / SEJUS',
                'titulo' => 'Empreendedorismo Popular e Acesso ao Microcrédito NossoCrédito',
                'descricao' => 'Capacitação para microempreendedores individuais, gestão financeira de pequenos negócios e orientação para obtenção de microcrédito orientado com taxas subsidiadas pelo Estado.',
                'categoria' => 'gestao',
                'municipio_id' => null, // Hibrido / Remoto
                'carga_horaria' => 40,
                'modalidade' => 'hibrido',
                'bolsa_auxilio' => null,
                'vagas_disponiveis' => 80,
                'status' => 'aberto',
                'link_inscricao' => 'https://conectaegresso.es.gov.br/cursos/aderes-nossocredito',
            ],
            [
                'instituicao' => 'SENAI Vitória',
                'titulo' => 'Instalações Elétricas Prediais e NR-10',
                'descricao' => 'Capacitação técnica em montagem de quadros elétricos residenciais, circuitos monofásicos/trifásicos e certificação oficial de segurança NR-10.',
                'categoria' => 'industrial',
                'municipio_id' => $vitoria ? $vitoria->id : 78,
                'carga_horaria' => 120,
                'modalidade' => 'presencial',
                'bolsa_auxilio' => 300.00,
                'vagas_disponiveis' => 20,
                'status' => 'aberto',
                'link_inscricao' => 'https://conectaegresso.es.gov.br/cursos/eletrica-vitoria',
            ],
            [
                'instituicao' => 'SENAI Colatina',
                'titulo' => 'Mecânica Básica e Manutenção de Motocicletas',
                'descricao' => 'Diagnóstico, desmontagem e manutenção preventiva de motores a combustão de duas rodas, sistemas de freio e injeção eletrônica.',
                'categoria' => 'servicos',
                'municipio_id' => $colatina ? $colatina->id : 19,
                'carga_horaria' => 100,
                'modalidade' => 'presencial',
                'bolsa_auxilio' => 250.00,
                'vagas_disponiveis' => 18,
                'status' => 'aberto',
                'link_inscricao' => 'https://conectaegresso.es.gov.br/cursos/mecanica-motos-colatina',
            ],
        ];

        foreach ($cursos as $c) {
            CursoCapacitacao::updateOrCreate(
                ['instituicao' => $c['instituicao'], 'titulo' => $c['titulo']],
                $c
            );
        }
    }
}
