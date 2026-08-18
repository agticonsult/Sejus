<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MunicipioEs;

class MunicipioEsSeeder extends Seeder
{
    /**
     * Run the database seeds for all 78 municipalities in Espírito Santo.
     */
    public function run(): void
    {
        $municipios = [
            ['codigo_ibge' => 3200102, 'nome' => 'Afonso Cláudio', 'microrregiao' => 'Central Serrana', 'macrorregiao' => 'Central', 'latitude' => -20.0778, 'longitude' => -41.1444, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 30455],
            ['codigo_ibge' => 3200169, 'nome' => 'Água Doce do Norte', 'microrregiao' => 'Noroeste', 'macrorregiao' => 'Norte', 'latitude' => -18.5472, 'longitude' => -40.9858, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 10910],
            ['codigo_ibge' => 3200136, 'nome' => 'Águia Branca', 'microrregiao' => 'Centro-Oeste', 'macrorregiao' => 'Central', 'latitude' => -18.9839, 'longitude' => -40.7408, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 9631],
            ['codigo_ibge' => 3200201, 'nome' => 'Alegre', 'microrregiao' => 'Caparaó', 'macrorregiao' => 'Sul', 'latitude' => -20.7631, 'longitude' => -41.5331, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 30084],
            ['codigo_ibge' => 3200300, 'nome' => 'Alfredo Chaves', 'microrregiao' => 'Sudoeste Serrana', 'macrorregiao' => 'Sul', 'latitude' => -20.6358, 'longitude' => -40.7519, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 14636],
            ['codigo_ibge' => 3200359, 'nome' => 'Alto Rio Novo', 'microrregiao' => 'Noroeste', 'macrorregiao' => 'Central', 'latitude' => -19.0583, 'longitude' => -41.0189, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 7874],
            ['codigo_ibge' => 3200409, 'nome' => 'Anchieta', 'microrregiao' => 'Litoral Sul', 'macrorregiao' => 'Sul', 'latitude' => -20.8058, 'longitude' => -40.6450, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 29734],
            ['codigo_ibge' => 3200508, 'nome' => 'Apiacá', 'microrregiao' => 'Caparaó', 'macrorregiao' => 'Sul', 'latitude' => -21.1542, 'longitude' => -41.5678, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 7512],
            ['codigo_ibge' => 3200607, 'nome' => 'Aracruz', 'microrregiao' => 'Rio Doce', 'macrorregiao' => 'Norte', 'latitude' => -19.8203, 'longitude' => -40.2733, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 103101],
            ['codigo_ibge' => 3200706, 'nome' => 'Atílio Vivácqua', 'microrregiao' => 'Central Sul', 'macrorregiao' => 'Sul', 'latitude' => -20.9150, 'longitude' => -41.1983, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 12109],
            ['codigo_ibge' => 3200805, 'nome' => 'Baixo Guandu', 'microrregiao' => 'Central Oeste', 'macrorregiao' => 'Central', 'latitude' => -19.5189, 'longitude' => -41.0147, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 31132],
            ['codigo_ibge' => 3200904, 'nome' => 'Barra de São Francisco', 'microrregiao' => 'Noroeste', 'macrorregiao' => 'Norte', 'latitude' => -18.7547, 'longitude' => -40.8906, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 44979],
            ['codigo_ibge' => 3201001, 'nome' => 'Boa Esperança', 'microrregiao' => 'Nordeste', 'macrorregiao' => 'Norte', 'latitude' => -18.5400, 'longitude' => -40.2947, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 15098],
            ['codigo_ibge' => 3201100, 'nome' => 'Bom Jesus do Norte', 'microrregiao' => 'Caparaó', 'macrorregiao' => 'Sul', 'latitude' => -21.1106, 'longitude' => -41.6706, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 10254],
            ['codigo_ibge' => 3201159, 'nome' => 'Brejetuba', 'microrregiao' => 'Central Serrana', 'macrorregiao' => 'Central', 'latitude' => -20.1447, 'longitude' => -41.2917, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 12428],
            ['codigo_ibge' => 3201209, 'nome' => 'Cachoeiro de Itapemirim', 'microrregiao' => 'Central Sul', 'macrorregiao' => 'Sul', 'latitude' => -20.8489, 'longitude' => -41.1128, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 210589],
            ['codigo_ibge' => 3201308, 'nome' => 'Cariacica', 'microrregiao' => 'Metropolitana', 'macrorregiao' => 'Metropolitana', 'latitude' => -20.2639, 'longitude' => -40.4200, 'tem_escritorio_fisico' => true, 'populacao_estimada' => 383917],
            ['codigo_ibge' => 3201407, 'nome' => 'Castelo', 'microrregiao' => 'Central Sul', 'macrorregiao' => 'Sul', 'latitude' => -20.6036, 'longitude' => -41.2033, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 37747],
            ['codigo_ibge' => 3201506, 'nome' => 'Colatina', 'microrregiao' => 'Central Oeste', 'macrorregiao' => 'Central', 'latitude' => -19.5392, 'longitude' => -40.6300, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 123400],
            ['codigo_ibge' => 3201605, 'nome' => 'Conceição da Barra', 'microrregiao' => 'Nordeste', 'macrorregiao' => 'Norte', 'latitude' => -18.5933, 'longitude' => -39.7322, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 31273],
            ['codigo_ibge' => 3201704, 'nome' => 'Conceição do Castelo', 'microrregiao' => 'Central Serrana', 'macrorregiao' => 'Central', 'latitude' => -20.3686, 'longitude' => -41.2439, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 12806],
            ['codigo_ibge' => 3201803, 'nome' => 'Divino de São Lourenço', 'microrregiao' => 'Caparaó', 'macrorregiao' => 'Sul', 'latitude' => -20.6200, 'longitude' => -41.6858, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 4270],
            ['codigo_ibge' => 3201902, 'nome' => 'Domingos Martins', 'microrregiao' => 'Sudoeste Serrana', 'macrorregiao' => 'Central', 'latitude' => -20.3633, 'longitude' => -40.6589, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 33946],
            ['codigo_ibge' => 3202009, 'nome' => 'Dores do Rio Preto', 'microrregiao' => 'Caparaó', 'macrorregiao' => 'Sul', 'latitude' => -20.6897, 'longitude' => -41.8447, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 6749],
            ['codigo_ibge' => 3202108, 'nome' => 'Ecoporanga', 'microrregiao' => 'Noroeste', 'macrorregiao' => 'Norte', 'latitude' => -18.3733, 'longitude' => -40.8306, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 22915],
            ['codigo_ibge' => 3202207, 'nome' => 'Fundão', 'microrregiao' => 'Metropolitana', 'macrorregiao' => 'Metropolitana', 'latitude' => -19.9333, 'longitude' => -40.4058, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 21948],
            ['codigo_ibge' => 3202256, 'nome' => 'Governador Lindenberg', 'microrregiao' => 'Central Oeste', 'macrorregiao' => 'Central', 'latitude' => -19.2558, 'longitude' => -40.4789, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 12879],
            ['codigo_ibge' => 3202306, 'nome' => 'Guaçuí', 'microrregiao' => 'Caparaó', 'macrorregiao' => 'Sul', 'latitude' => -20.7758, 'longitude' => -41.6792, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 31122],
            ['codigo_ibge' => 3202405, 'nome' => 'Guarapari', 'microrregiao' => 'Metropolitana', 'macrorregiao' => 'Metropolitana', 'latitude' => -20.6708, 'longitude' => -40.4981, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 126701],
            ['codigo_ibge' => 3202454, 'nome' => 'Ibatiba', 'microrregiao' => 'Caparaó', 'macrorregiao' => 'Sul', 'latitude' => -20.2336, 'longitude' => -41.5108, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 26426],
            ['codigo_ibge' => 3202504, 'nome' => 'Ibiraçu', 'microrregiao' => 'Rio Doce', 'macrorregiao' => 'Norte', 'latitude' => -19.8319, 'longitude' => -40.3700, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 12591],
            ['codigo_ibge' => 3202553, 'nome' => 'Ibitirama', 'microrregiao' => 'Caparaó', 'macrorregiao' => 'Sul', 'latitude' => -20.5408, 'longitude' => -41.6669, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 8885],
            ['codigo_ibge' => 3202603, 'nome' => 'Iconha', 'microrregiao' => 'Litoral Sul', 'macrorregiao' => 'Sul', 'latitude' => -20.7931, 'longitude' => -40.8106, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 13973],
            ['codigo_ibge' => 3202652, 'nome' => 'Irupi', 'microrregiao' => 'Caparaó', 'macrorregiao' => 'Sul', 'latitude' => -20.3458, 'longitude' => -41.6419, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 13444],
            ['codigo_ibge' => 3202702, 'nome' => 'Itaguaçu', 'microrregiao' => 'Central Serrana', 'macrorregiao' => 'Central', 'latitude' => -19.8028, 'longitude' => -40.8567, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 14134],
            ['codigo_ibge' => 3202801, 'nome' => 'Itapemirim', 'microrregiao' => 'Litoral Sul', 'macrorregiao' => 'Sul', 'latitude' => -20.9997, 'longitude' => -40.8336, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 34656],
            ['codigo_ibge' => 3202900, 'nome' => 'Itarana', 'microrregiao' => 'Central Serrana', 'macrorregiao' => 'Central', 'latitude' => -19.8739, 'longitude' => -40.8756, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 10494],
            ['codigo_ibge' => 3203007, 'nome' => 'Iúna', 'microrregiao' => 'Caparaó', 'macrorregiao' => 'Sul', 'latitude' => -20.3458, 'longitude' => -41.5358, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 29290],
            ['codigo_ibge' => 3203056, 'nome' => 'Jaguaré', 'microrregiao' => 'Nordeste', 'macrorregiao' => 'Norte', 'latitude' => -18.9069, 'longitude' => -40.0761, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 31039],
            ['codigo_ibge' => 3203106, 'nome' => 'Jerônimo Monteiro', 'microrregiao' => 'Central Sul', 'macrorregiao' => 'Sul', 'latitude' => -20.7906, 'longitude' => -41.3961, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 12295],
            ['codigo_ibge' => 3203130, 'nome' => 'João Neiva', 'microrregiao' => 'Rio Doce', 'macrorregiao' => 'Norte', 'latitude' => -19.7547, 'longitude' => -40.3839, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 16722],
            ['codigo_ibge' => 3203163, 'nome' => 'Laranja da Terra', 'microrregiao' => 'Central Serrana', 'macrorregiao' => 'Central', 'latitude' => -19.8986, 'longitude' => -41.0558, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 10935],
            ['codigo_ibge' => 3203205, 'nome' => 'Linhares', 'microrregiao' => 'Rio Doce', 'macrorregiao' => 'Norte', 'latitude' => -19.3964, 'longitude' => -40.0644, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 176688],
            ['codigo_ibge' => 3203304, 'nome' => 'Mantenópolis', 'microrregiao' => 'Noroeste', 'macrorregiao' => 'Norte', 'latitude' => -18.8622, 'longitude' => -41.1228, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 15503],
            ['codigo_ibge' => 3203320, 'nome' => 'Marataízes', 'microrregiao' => 'Litoral Sul', 'macrorregiao' => 'Sul', 'latitude' => -21.0433, 'longitude' => -40.8244, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 38883],
            ['codigo_ibge' => 3203346, 'nome' => 'Marechal Floriano', 'microrregiao' => 'Sudoeste Serrana', 'macrorregiao' => 'Central', 'latitude' => -20.4128, 'longitude' => -40.6831, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 16920],
            ['codigo_ibge' => 3203353, 'nome' => 'Marilândia', 'microrregiao' => 'Central Oeste', 'macrorregiao' => 'Central', 'latitude' => -19.4131, 'longitude' => -40.5414, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 12963],
            ['codigo_ibge' => 3203403, 'nome' => 'Mimoso do Sul', 'microrregiao' => 'Central Sul', 'macrorregiao' => 'Sul', 'latitude' => -21.0644, 'longitude' => -41.3658, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 26115],
            ['codigo_ibge' => 3203502, 'nome' => 'Montanha', 'microrregiao' => 'Nordeste', 'macrorregiao' => 'Norte', 'latitude' => -18.1269, 'longitude' => -40.3633, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 18900],
            ['codigo_ibge' => 3203601, 'nome' => 'Mucurici', 'microrregiao' => 'Nordeste', 'macrorregiao' => 'Norte', 'latitude' => -18.0933, 'longitude' => -40.5158, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 5496],
            ['codigo_ibge' => 3203700, 'nome' => 'Muniz Freire', 'microrregiao' => 'Caparaó', 'macrorregiao' => 'Sul', 'latitude' => -20.4647, 'longitude' => -41.4131, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 17319],
            ['codigo_ibge' => 3203809, 'nome' => 'Muqui', 'microrregiao' => 'Central Sul', 'macrorregiao' => 'Sul', 'latitude' => -20.9525, 'longitude' => -41.3458, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 15526],
            ['codigo_ibge' => 3203908, 'nome' => 'Nova Venécia', 'microrregiao' => 'Noroeste', 'macrorregiao' => 'Norte', 'latitude' => -18.7106, 'longitude' => -40.4006, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 50434],
            ['codigo_ibge' => 3204005, 'nome' => 'Pancas', 'microrregiao' => 'Central Oeste', 'macrorregiao' => 'Central', 'latitude' => -19.2247, 'longitude' => -40.8514, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 23306],
            ['codigo_ibge' => 3204054, 'nome' => 'Pedro Canário', 'microrregiao' => 'Nordeste', 'macrorregiao' => 'Norte', 'latitude' => -18.0286, 'longitude' => -40.1486, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 26381],
            ['codigo_ibge' => 3204104, 'nome' => 'Pinheiros', 'microrregiao' => 'Nordeste', 'macrorregiao' => 'Norte', 'latitude' => -18.4239, 'longitude' => -40.2189, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 27388],
            ['codigo_ibge' => 3204203, 'nome' => 'Piúma', 'microrregiao' => 'Litoral Sul', 'macrorregiao' => 'Sul', 'latitude' => -20.8358, 'longitude' => -40.7289, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 22053],
            ['codigo_ibge' => 3204252, 'nome' => 'Ponto Belo', 'microrregiao' => 'Nordeste', 'macrorregiao' => 'Norte', 'latitude' => -18.1247, 'longitude' => -40.5369, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 7863],
            ['codigo_ibge' => 3204302, 'nome' => 'Presidente Kennedy', 'microrregiao' => 'Litoral Sul', 'macrorregiao' => 'Sul', 'latitude' => -21.0967, 'longitude' => -41.0478, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 11658],
            ['codigo_ibge' => 3204351, 'nome' => 'Rio Bananal', 'microrregiao' => 'Rio Doce', 'macrorregiao' => 'Norte', 'latitude' => -19.2650, 'longitude' => -40.3333, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 19273],
            ['codigo_ibge' => 3204401, 'nome' => 'Rio Novo do Sul', 'microrregiao' => 'Litoral Sul', 'macrorregiao' => 'Sul', 'latitude' => -20.8589, 'longitude' => -40.9367, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 11626],
            ['codigo_ibge' => 3204500, 'nome' => 'Santa Leopoldina', 'microrregiao' => 'Central Serrana', 'macrorregiao' => 'Central', 'latitude' => -20.1006, 'longitude' => -40.5297, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 12240],
            ['codigo_ibge' => 3204559, 'nome' => 'Santa Maria de Jetibá', 'microrregiao' => 'Central Serrana', 'macrorregiao' => 'Central', 'latitude' => -20.0406, 'longitude' => -40.7461, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 41015],
            ['codigo_ibge' => 3204609, 'nome' => 'Santa Teresa', 'microrregiao' => 'Central Serrana', 'macrorregiao' => 'Central', 'latitude' => -19.9367, 'longitude' => -40.6006, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 23724],
            ['codigo_ibge' => 3204658, 'nome' => 'São Domingos do Norte', 'microrregiao' => 'Central Oeste', 'macrorregiao' => 'Central', 'latitude' => -19.1417, 'longitude' => -40.5239, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 8687],
            ['codigo_ibge' => 3204708, 'nome' => 'São Gabriel da Palha', 'microrregiao' => 'Centro-Oeste', 'macrorregiao' => 'Central', 'latitude' => -19.0169, 'longitude' => -40.5361, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 38522],
            ['codigo_ibge' => 3204807, 'nome' => 'São José do Calçado', 'microrregiao' => 'Caparaó', 'macrorregiao' => 'Sul', 'latitude' => -20.9814, 'longitude' => -41.6544, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 10549],
            ['codigo_ibge' => 3204906, 'nome' => 'São Mateus', 'microrregiao' => 'Nordeste', 'macrorregiao' => 'Norte', 'latitude' => -18.7161, 'longitude' => -39.8589, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 132642],
            ['codigo_ibge' => 3204955, 'nome' => 'São Roque do Canaã', 'microrregiao' => 'Central Serrana', 'macrorregiao' => 'Central', 'latitude' => -19.7389, 'longitude' => -40.6558, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 12515],
            ['codigo_ibge' => 3205002, 'nome' => 'Serra', 'microrregiao' => 'Metropolitana', 'macrorregiao' => 'Metropolitana', 'latitude' => -20.1286, 'longitude' => -40.3078, 'tem_escritorio_fisico' => true, 'populacao_estimada' => 527240],
            ['codigo_ibge' => 3205010, 'nome' => 'Sooretama', 'microrregiao' => 'Rio Doce', 'macrorregiao' => 'Norte', 'latitude' => -19.1969, 'longitude' => -40.0906, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 30680],
            ['codigo_ibge' => 3205036, 'nome' => 'Vargem Alta', 'microrregiao' => 'Central Sul', 'macrorregiao' => 'Sul', 'latitude' => -20.6722, 'longitude' => -41.0078, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 21591],
            ['codigo_ibge' => 3205069, 'nome' => 'Venda Nova do Imigrante', 'microrregiao' => 'Sudoeste Serrana', 'macrorregiao' => 'Central', 'latitude' => -20.3267, 'longitude' => -41.1344, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 25745],
            ['codigo_ibge' => 3205101, 'nome' => 'Viana', 'microrregiao' => 'Metropolitana', 'macrorregiao' => 'Metropolitana', 'latitude' => -20.3906, 'longitude' => -40.4958, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 79500],
            ['codigo_ibge' => 3205150, 'nome' => 'Vila Pavão', 'microrregiao' => 'Noroeste', 'macrorregiao' => 'Norte', 'latitude' => -18.6147, 'longitude' => -40.6094, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 9244],
            ['codigo_ibge' => 3205176, 'nome' => 'Vila Valério', 'microrregiao' => 'Noroeste', 'macrorregiao' => 'Norte', 'latitude' => -18.9989, 'longitude' => -40.3889, 'tem_escritorio_fisico' => false, 'populacao_estimada' => 14073],
            ['codigo_ibge' => 3205200, 'nome' => 'Vila Velha', 'microrregiao' => 'Metropolitana', 'macrorregiao' => 'Metropolitana', 'latitude' => -20.3297, 'longitude' => -40.2925, 'tem_escritorio_fisico' => true, 'populacao_estimada' => 501325],
            ['codigo_ibge' => 3205309, 'nome' => 'Vitória', 'microrregiao' => 'Metropolitana', 'macrorregiao' => 'Metropolitana', 'latitude' => -20.3155, 'longitude' => -40.3128, 'tem_escritorio_fisico' => true, 'populacao_estimada' => 365855],
        ];

        foreach ($municipios as $m) {
            MunicipioEs::updateOrCreate(
                ['codigo_ibge' => $m['codigo_ibge']],
                $m
            );
        }
    }
}
