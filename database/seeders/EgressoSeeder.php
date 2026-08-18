<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Egresso;
use App\Models\MunicipioEs;
use App\Models\User;

class EgressoSeeder extends Seeder
{
    /**
     * Run the database seeds for realistic Egressos.
     */
    public function run(): void
    {
        $saoMateus = MunicipioEs::where('nome', 'São Mateus')->first();
        $vitoria = MunicipioEs::where('nome', 'Vitória')->first();

        $userLucas = User::where('email', 'lucas.santos@cidadao.es.gov.br')->first();
        $userRoberto = User::where('email', 'roberto.fonseca@cidadao.es.gov.br')->first();

        $egressos = [
            [
                'id' => 1,
                'user_id' => $userLucas ? $userLucas->id : null,
                'nome_completo' => 'Lucas Santos',
                'nome_social' => null,
                'data_nascimento' => '1995-04-12',
                'cpf' => '19283045678',
                'rg' => '3.892.104 SPTC/ES',
                'filiacao_mae' => 'Maria das Graças Santos',
                'municipio_residencia_id' => $saoMateus ? $saoMateus->id : 68,
                'endereco' => 'Rua das Palmeiras, 142, Guriri',
                'telefone' => '(27) 99777-3344',
                'escolaridade' => 'Ensino Médio Completo',
                'status_penal' => 'egresso',
                'unidade_prisional_origem' => 'Centro de Detenção Provisória de São Mateus (CDPSM)',
                'numero_processo_execucao' => '0014820-44.2023.8.08.0047',
                'vulnerabilidades' => ['busca_emprego', 'capacitacao_profissional', 'apoio_familiar'],
                'consentimento_geolocalizacao' => true,
                'consentimento_compartilhamento' => true,
                'termo_aceito_em' => '2026-01-10 14:30:00',
            ],
            [
                'id' => 2,
                'user_id' => $userRoberto ? $userRoberto->id : null,
                'nome_completo' => 'Roberto Fonseca da Silva',
                'nome_social' => null,
                'data_nascimento' => '1989-11-23',
                'cpf' => '48291037492',
                'rg' => '2.190.485 SPTC/ES',
                'filiacao_mae' => 'Ana Lúcia Fonseca da Silva',
                'municipio_residencia_id' => $vitoria ? $vitoria->id : 78,
                'endereco' => 'Avenida Vitória, 890, Bento Ferreira',
                'telefone' => '(27) 99666-5566',
                'escolaridade' => 'Ensino Fundamental Completo',
                'status_penal' => 'livramento_condicional',
                'unidade_prisional_origem' => 'Penitenciária de Segurança Média de Viana (PSMEV)',
                'numero_processo_execucao' => '0009823-12.2022.8.08.0024',
                'vulnerabilidades' => ['busca_emprego', 'reintegracao_social'],
                'consentimento_geolocalizacao' => true,
                'consentimento_compartilhamento' => true,
                'termo_aceito_em' => '2026-02-01 09:15:00',
            ],
        ];

        foreach ($egressos as $e) {
            $egresso = Egresso::firstOrNew(['id' => $e['id']]);
            $egresso->id = $e['id'];
            $egresso->user_id = $e['user_id'];
            $egresso->nome_completo = $e['nome_completo'];
            $egresso->nome_social = $e['nome_social'];
            $egresso->data_nascimento = $e['data_nascimento'];
            $egresso->cpf = $e['cpf']; // mutator sets cpf_encrypted & hash_cpf
            $egresso->rg = $e['rg'];
            $egresso->filiacao_mae = $e['filiacao_mae'];
            $egresso->municipio_residencia_id = $e['municipio_residencia_id'];
            $egresso->endereco = $e['endereco'];
            $egresso->telefone = $e['telefone'];
            $egresso->escolaridade = $e['escolaridade'];
            $egresso->status_penal = $e['status_penal'];
            $egresso->unidade_prisional_origem = $e['unidade_prisional_origem'];
            $egresso->numero_processo_execucao = $e['numero_processo_execucao'];
            $egresso->vulnerabilidades = $e['vulnerabilidades'];
            $egresso->consentimento_geolocalizacao = $e['consentimento_geolocalizacao'];
            $egresso->consentimento_compartilhamento = $e['consentimento_compartilhamento'];
            $egresso->termo_aceito_em = $e['termo_aceito_em'];
            $egresso->save();
        }
    }
}
