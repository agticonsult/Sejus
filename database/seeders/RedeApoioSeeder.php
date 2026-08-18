<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RedeApoio;
use App\Models\MunicipioEs;

class RedeApoioSeeder extends Seeder
{
    /**
     * Run the database seeds for Social Support Facilities across Espírito Santo.
     */
    public function run(): void
    {
        $vitoria = MunicipioEs::where('nome', 'Vitória')->first();
        $vilaVelha = MunicipioEs::where('nome', 'Vila Velha')->first();
        $serra = MunicipioEs::where('nome', 'Serra')->first();
        $cariacica = MunicipioEs::where('nome', 'Cariacica')->first();
        $linhares = MunicipioEs::where('nome', 'Linhares')->first();
        $saoMateus = MunicipioEs::where('nome', 'São Mateus')->first();
        $colatina = MunicipioEs::where('nome', 'Colatina')->first();
        $cachoeiro = MunicipioEs::where('nome', 'Cachoeiro de Itapemirim')->first();
        $aracruz = MunicipioEs::where('nome', 'Aracruz')->first();
        $guarapari = MunicipioEs::where('nome', 'Guarapari')->first();

        $unidades = [
            // Vitória
            [
                'nome' => 'CRAS Centro / Parque Moscoso',
                'tipo' => 'CRAS',
                'municipio_id' => $vitoria ? $vitoria->id : 78,
                'endereco' => 'Rua Thiers Velloso, 256, Centro, Vitória - ES',
                'telefone' => '(27) 3132-8071',
                'email' => 'cras.centro@vitoria.es.gov.br',
                'horario_funcionamento' => 'Segunda a Sexta, 08h às 17h',
                'servicos_oferecidos' => ['CadÚnico', 'PAIF', 'Encaminhamento Social', 'Bolsa Capixaba'],
                'latitude' => -20.3180,
                'longitude' => -40.3390,
                'ativo' => true,
            ],
            [
                'nome' => 'Agência SINE Estadual - Vitória',
                'tipo' => 'SINE',
                'municipio_id' => $vitoria ? $vitoria->id : 78,
                'endereco' => 'Av. Princesa Isabel, 599, Ed. Março, Centro, Vitória - ES',
                'telefone' => '(27) 3636-8800',
                'email' => 'sine.vitoria@setades.es.gov.br',
                'horario_funcionamento' => 'Segunda a Sexta, 08h às 17h',
                'servicos_oferecidos' => ['Intermediação de Mão de Obra', 'Seguro Desemprego', 'Carteira de Trabalho Digital'],
                'latitude' => -20.3195,
                'longitude' => -40.3365,
                'ativo' => true,
            ],
            [
                'nome' => 'CAPS AD III Estadual - Álcool e Drogas Vitória',
                'tipo' => 'CAPS',
                'municipio_id' => $vitoria ? $vitoria->id : 78,
                'endereco' => 'Rua São Sebastião, s/n, Ilha de Santa Maria, Vitória - ES',
                'telefone' => '(27) 3132-5100',
                'email' => 'capsad@vitoria.es.gov.br',
                'horario_funcionamento' => '24 horas / 7 dias por semana',
                'servicos_oferecidos' => ['Acolhimento Psiquiátrico', 'Tratamento de Dependência', 'Oficinas Terapêuticas'],
                'latitude' => -20.3140,
                'longitude' => -40.3200,
                'ativo' => true,
            ],
            // Vila Velha
            [
                'nome' => 'CRAS Centro de Vila Velha',
                'tipo' => 'CRAS',
                'municipio_id' => $vilaVelha ? $vilaVelha->id : 77,
                'endereco' => 'Rua Luciano das Neves, 120, Centro, Vila Velha - ES',
                'telefone' => '(27) 3388-4100',
                'email' => 'cras.centro@vilavelha.es.gov.br',
                'horario_funcionamento' => 'Segunda a Sexta, 08h às 17h',
                'servicos_oferecidos' => ['CadÚnico', 'Apoio à Família', 'Benefícios Eventuais'],
                'latitude' => -20.3320,
                'longitude' => -40.2910,
                'ativo' => true,
            ],
            [
                'nome' => 'SINE Vila Velha',
                'tipo' => 'SINE',
                'municipio_id' => $vilaVelha ? $vilaVelha->id : 77,
                'endereco' => 'Rua 7 de Setembro, 95, Centro, Vila Velha - ES',
                'telefone' => '(27) 3139-9904',
                'email' => 'sine@vilavelha.es.gov.br',
                'horario_funcionamento' => 'Segunda a Sexta, 08h às 17h',
                'servicos_oferecidos' => ['Vagas Afirmativas', 'Cadastro de Currículo'],
                'latitude' => -20.3315,
                'longitude' => -40.2930,
                'ativo' => true,
            ],
            // Serra
            [
                'nome' => 'CRAS Laranjeiras',
                'tipo' => 'CRAS',
                'municipio_id' => $serra ? $serra->id : 70,
                'endereco' => 'Rua 1A, s/n, Parque Residencial Laranjeiras, Serra - ES',
                'telefone' => '(27) 3281-4270',
                'email' => 'cras.laranjeiras@serra.es.gov.br',
                'horario_funcionamento' => 'Segunda a Sexta, 08h às 17h',
                'servicos_oferecidos' => ['CadÚnico', 'PAIF', 'Encaminhamento Social'],
                'latitude' => -20.1980,
                'longitude' => -40.2580,
                'ativo' => true,
            ],
            [
                'nome' => 'SINE Serra / Portal Jacaraípe',
                'tipo' => 'SINE',
                'municipio_id' => $serra ? $serra->id : 70,
                'endereco' => 'Pró-Cidadão, Av. Talma Rodrigues Ribeiro, 5416, Portal de Jacaraípe, Serra - ES',
                'telefone' => '(27) 3252-7404',
                'email' => 'sine@serra.es.gov.br',
                'horario_funcionamento' => 'Segunda a Sexta, 08h às 17h',
                'servicos_oferecidos' => ['Vagas Industriais', 'Intermediação de Trabalho'],
                'latitude' => -20.1340,
                'longitude' => -40.2010,
                'ativo' => true,
            ],
            // Cariacica
            [
                'nome' => 'CRAS Campo Grande',
                'tipo' => 'CRAS',
                'municipio_id' => $cariacica ? $cariacica->id : 17,
                'endereco' => 'Rua Dom Pedro II, 15, Campo Grande, Cariacica - ES',
                'telefone' => '(27) 3346-6300',
                'email' => 'cras.cg@cariacica.es.gov.br',
                'horario_funcionamento' => 'Segunda a Sexta, 08h às 17h',
                'servicos_oferecidos' => ['CadÚnico', 'Apoio Psicossocial'],
                'latitude' => -20.3540,
                'longitude' => -40.3890,
                'ativo' => true,
            ],
            // Linhares
            [
                'nome' => 'CRAS Aviso / Linhares',
                'tipo' => 'CRAS',
                'municipio_id' => $linhares ? $linhares->id : 43,
                'endereco' => 'Av. Filogônio Peixoto, 450, Aviso, Linhares - ES',
                'telefone' => '(27) 3372-6800',
                'email' => 'cras.aviso@linhares.es.gov.br',
                'horario_funcionamento' => 'Segunda a Sexta, 08h às 17h',
                'servicos_oferecidos' => ['Atendimento Social', 'CadÚnico', 'Orientação a Cursos'],
                'latitude' => -19.3920,
                'longitude' => -40.0610,
                'ativo' => true,
            ],
            [
                'nome' => 'SINE Linhares',
                'tipo' => 'SINE',
                'municipio_id' => $linhares ? $linhares->id : 43,
                'endereco' => 'Av. Governador Lindenberg, 660, Centro, Linhares - ES',
                'telefone' => '(27) 3371-3476',
                'email' => 'sine.linhares@setades.es.gov.br',
                'horario_funcionamento' => 'Segunda a Sexta, 08h às 17h',
                'servicos_oferecidos' => ['Intermediação de Vagas Rurais e Industriais'],
                'latitude' => -19.3950,
                'longitude' => -40.0650,
                'ativo' => true,
            ],
            // São Mateus
            [
                'nome' => 'CRAS Guriri / São Mateus',
                'tipo' => 'CRAS',
                'municipio_id' => $saoMateus ? $saoMateus->id : 68,
                'endereco' => 'Rua Horácio Barbosa Ribeiro, s/n, Guriri, São Mateus - ES',
                'telefone' => '(27) 3761-4500',
                'email' => 'cras.guriri@saomateus.es.gov.br',
                'horario_funcionamento' => 'Segunda a Sexta, 08h às 17h',
                'servicos_oferecidos' => ['Acolhimento Comunitário', 'CadÚnico', 'Teleatendimento Conecta Egresso'],
                'latitude' => -18.7210,
                'longitude' => -39.7540,
                'ativo' => true,
            ],
            [
                'nome' => 'SINE São Mateus',
                'tipo' => 'SINE',
                'municipio_id' => $saoMateus ? $saoMateus->id : 68,
                'endereco' => 'Av. Jones dos Santos Neves, 324, Sernamby, São Mateus - ES',
                'telefone' => '(27) 3763-2706',
                'email' => 'sine.saomateus@setades.es.gov.br',
                'horario_funcionamento' => 'Segunda a Sexta, 08h às 17h',
                'servicos_oferecidos' => ['Vagas Agropecuárias e Comerciais'],
                'latitude' => -18.7180,
                'longitude' => -39.8550,
                'ativo' => true,
            ],
            // Colatina
            [
                'nome' => 'CRAS São Silvano / Colatina',
                'tipo' => 'CRAS',
                'municipio_id' => $colatina ? $colatina->id : 19,
                'endereco' => 'Rua Jacinto Bassetti, 110, São Silvano, Colatina - ES',
                'telefone' => '(27) 3721-5080',
                'email' => 'cras.saosilvano@colatina.es.gov.br',
                'horario_funcionamento' => 'Segunda a Sexta, 08h às 17h',
                'servicos_oferecidos' => ['CadÚnico', 'Apoio Psicossocial'],
                'latitude' => -19.5310,
                'longitude' => -40.6380,
                'ativo' => true,
            ],
            // Cachoeiro de Itapemirim
            [
                'nome' => 'CRAS Alto União / Cachoeiro',
                'tipo' => 'CRAS',
                'municipio_id' => $cachoeiro ? $cachoeiro->id : 16,
                'endereco' => 'Rua José Félix, 22, Alto União, Cachoeiro de Itapemirim - ES',
                'telefone' => '(27) 3522-8000',
                'email' => 'cras.altouniao@cachoeiro.es.gov.br',
                'horario_funcionamento' => 'Segunda a Sexta, 08h às 17h',
                'servicos_oferecidos' => ['CadÚnico', 'Inclusão Produtiva'],
                'latitude' => -20.8520,
                'longitude' => -41.1180,
                'ativo' => true,
            ],
        ];

        foreach ($unidades as $u) {
            RedeApoio::updateOrCreate(
                ['nome' => $u['nome'], 'municipio_id' => $u['municipio_id']],
                $u
            );
        }
    }
}
