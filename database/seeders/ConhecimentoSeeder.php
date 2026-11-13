<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Conhecimento;
use App\Models\Egresso;

class ConhecimentoSeeder extends Seeder
{
    /**
     * Run the database seeds for vocational knowledge catalog and sample egresso bindings.
     */
    public function run(): void
    {
        $conhecimentos = [
            // Indústria & Metalmecânica
            [
                'nome' => 'Soldagem Industrial (Eletrodo e MIG/MAG)',
                'descricao' => 'Processos de solda em estruturas metálicas, tubulações e manutenção industrial.',
                'categoria' => 'industrial',
                'ativo' => true,
            ],
            [
                'nome' => 'Instalações Elétricas Prediais (NR-10)',
                'descricao' => 'Montagem de quadros elétricos, cabeamento, circuitos e normas de segurança em baixa tensão.',
                'categoria' => 'industrial',
                'ativo' => true,
            ],
            [
                'nome' => 'Caldeiraria e Tubulação Industrial',
                'descricao' => 'Traçagem, corte, dobra e montagem de tubulações e estruturas para indústria e naval.',
                'categoria' => 'industrial',
                'ativo' => true,
            ],

            // Construção Civil
            [
                'nome' => 'Alvenaria Estrutural e Acabamento',
                'descricao' => 'Construção de paredes, assentamento de tijolos, reboco, contrapiso e acabamentos.',
                'categoria' => 'construcao_civil',
                'ativo' => true,
            ],
            [
                'nome' => 'Pintura Predial e Industrial',
                'descricao' => 'Preparação de superfícies, emassamento, aplicação de látex, esmalte e tintas epóxi.',
                'categoria' => 'construcao_civil',
                'ativo' => true,
            ],
            [
                'nome' => 'Armação de Ferragens e Concretagem',
                'descricao' => 'Corte, dobra e amarração de aço para vigas, pilares e sapatas, além de lançamento de concreto.',
                'categoria' => 'construcao_civil',
                'ativo' => true,
            ],
            [
                'nome' => 'Gesseiro e Drywall',
                'descricao' => 'Instalação de placas de gesso acartonado, forros estruturados e sancas.',
                'categoria' => 'construcao_civil',
                'ativo' => true,
            ],

            // Logística & Operação Portuária
            [
                'nome' => 'Operação de Empilhadeira e Paleteira',
                'descricao' => 'Movimentação, armazenagem de paletes e conferência em centros de distribuição e galpões.',
                'categoria' => 'logistica',
                'ativo' => true,
            ],
            [
                'nome' => 'Carga e Descarga Portuária',
                'descricao' => 'Estiva, amarração de contêineres e suporte a pátios logísticos e retroportuários.',
                'categoria' => 'logistica',
                'ativo' => true,
            ],
            [
                'nome' => 'Conferência de Cargas e Estoque',
                'descricao' => 'Controle de entrada e saída, bipagem de notas fiscais e inventário físico.',
                'categoria' => 'logistica',
                'ativo' => true,
            ],

            // Agropecuária
            [
                'nome' => 'Operação de Tratores e Máquinas Agrícolas',
                'descricao' => 'Manuseio de tratores de esteira/pneus, implementos agrícolas e roçadeiras motorizadas.',
                'categoria' => 'agropecuaria',
                'ativo' => true,
            ],
            [
                'nome' => 'Manejo e Colheita de Café e Pimenta',
                'descricao' => 'Técnicas de poda, adubação, colheita seletiva e secagem de grãos.',
                'categoria' => 'agropecuaria',
                'ativo' => true,
            ],

            // Comércio & Serviços
            [
                'nome' => 'Atendimento ao Cliente e Balcão',
                'descricao' => 'Comunicação interpessoal, atendimento presencial/telefônico e resolução de dúvidas.',
                'categoria' => 'comercio',
                'ativo' => true,
            ],
            [
                'nome' => 'Panificação e Manipulação de Alimentos',
                'descricao' => 'Produção de pães, confeitaria básica e cumprimento de boas práticas sanitárias (ANVISA).',
                'categoria' => 'comercio',
                'ativo' => true,
            ],
            [
                'nome' => 'Mecânica de Motocicletas e Automotiva',
                'descricao' => 'Manutenção preventiva, troca de óleo, freios, carburação e injeção eletrônica básica.',
                'categoria' => 'servicos',
                'ativo' => true,
            ],
            [
                'nome' => 'Serviços Gerais e Limpeza Conservacional',
                'descricao' => 'Higienização de instalações comerciais, destinação de resíduos e uso de maquinário de limpeza.',
                'categoria' => 'servicos',
                'ativo' => true,
            ],
            [
                'nome' => 'Jardinagem e Paisagismo',
                'descricao' => 'Poda de árvores, manutenção de gramados, plantio de mudas e sistemas de rega.',
                'categoria' => 'servicos',
                'ativo' => true,
            ],

            // Tecnologia & Administrativo
            [
                'nome' => 'Informática Básica e Pacote Office',
                'descricao' => 'Uso do Windows, digitação, elaboração de documentos Word e planilhas básicas Excel.',
                'categoria' => 'tecnologia',
                'ativo' => true,
            ],
            [
                'nome' => 'Operação de Caixa e Meios de Pagamento',
                'descricao' => 'Abertura/fechamento de caixa, recebimento via PIX/cartão e emissão de cupom fiscal.',
                'categoria' => 'comercio',
                'ativo' => true,
            ],
            [
                'nome' => 'Empreendedorismo e Gestão de Microcrédito',
                'descricao' => 'Noções de formação de preço, fluxo de caixa simples e microcrédito orientado (NossoCrédito).',
                'categoria' => 'gestao',
                'ativo' => true,
            ],
        ];

        // 1. Cadastra/Atualiza o catálogo de conhecimentos
        foreach ($conhecimentos as $item) {
            Conhecimento::updateOrCreate(
                ['nome' => $item['nome']],
                $item
            );
        }

        // 2. Cria vínculos de amostra para os egressos semeados (Lucas Santos e Roberto Fonseca)
        $solda = Conhecimento::where('nome', 'Soldagem Industrial (Eletrodo e MIG/MAG)')->first();
        $eletrica = Conhecimento::where('nome', 'Instalações Elétricas Prediais (NR-10)')->first();
        $empilhadeira = Conhecimento::where('nome', 'Operação de Empilhadeira e Paleteira')->first();
        $alvenaria = Conhecimento::where('nome', 'Alvenaria Estrutural e Acabamento')->first();
        $pintura = Conhecimento::where('nome', 'Pintura Predial e Industrial')->first();
        $informatica = Conhecimento::where('nome', 'Informática Básica e Pacote Office')->first();

        // Egresso 1: Lucas Santos (São Mateus - Perfil Industrial / Logística)
        $lucas = Egresso::find(1);
        if ($lucas && $solda && $eletrica && $empilhadeira) {
            $lucas->conhecimentos()->syncWithoutDetaching([
                $solda->id => [
                    'nivel' => 'intermediario',
                    'necessita_treinamento' => false,
                    'observacoes' => 'Experiência prévia em oficina metalúrgica e curso básico SENAI',
                ],
                $eletrica->id => [
                    'nivel' => 'basico',
                    'necessita_treinamento' => true,
                    'observacoes' => 'Interesse em reciclagem e certificação na norma NR-10',
                ],
                $empilhadeira->id => [
                    'nivel' => 'intermediario',
                    'necessita_treinamento' => false,
                    'observacoes' => 'Possui CNH B e prática de manobras de pátio',
                ],
            ]);
        }

        // Egresso 2: Roberto Fonseca da Silva (Vitória - Perfil Construção Civil)
        $roberto = Egresso::find(2);
        if ($roberto && $alvenaria && $pintura && $informatica) {
            $roberto->conhecimentos()->syncWithoutDetaching([
                $alvenaria->id => [
                    'nivel' => 'avancado',
                    'necessita_treinamento' => false,
                    'observacoes' => 'Mais de 6 anos de atuação comprovada como pedreiro',
                ],
                $pintura->id => [
                    'nivel' => 'intermediario',
                    'necessita_treinamento' => false,
                    'observacoes' => 'Pintura imobiliária e aplicação de texturas',
                ],
                $informatica->id => [
                    'nivel' => 'basico',
                    'necessita_treinamento' => true,
                    'observacoes' => 'Interesse em letramento digital para busca ativa de vagas online',
                ],
            ]);
        }
    }
}
