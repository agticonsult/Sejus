<?php

namespace App\Services;

use App\Models\MunicipioEs;

class CepMunicipioService
{
    /**
     * Known CEP prefixes / ranges for Espírito Santo municipalities (29000-000 to 29999-999).
     */
    protected static array $cepPrefixMap = [
        // Região Metropolitana
        '290' => 'Vitória',
        '2910' => 'Vila Velha',
        '2911' => 'Vila Velha',
        '2912' => 'Vila Velha',
        '2913' => 'Viana',
        '2914' => 'Cariacica',
        '2915' => 'Cariacica',
        '2916' => 'Serra',
        '2917' => 'Serra',
        '2918' => 'Serra',
        '2919' => 'Aracruz',
        '2920' => 'Guarapari',
        '2921' => 'Guarapari',
        '2922' => 'Guarapari',
        '2923' => 'Anchieta',
        '2924' => 'Piúma',
        '2925' => 'Íconeha',
        '2926' => 'Domingos Martins',
        '2927' => 'Marechal Floriano',
        '2928' => 'Alfredo Chaves',
        '2929' => 'Venda Nova do Imigrante',
        
        // Sul do Estado
        '2930' => 'Cachoeiro de Itapemirim',
        '2931' => 'Cachoeiro de Itapemirim',
        '2932' => 'Castelo',
        '2933' => 'Muqui',
        '2934' => 'Mimoso do Sul',
        '2935' => 'Atílio Vivácqua',
        '2936' => 'Presidente Kennedy',
        '2937' => 'Itapemirim',
        '2938' => 'Marataízes',
        '2939' => 'Alegre',
        '2940' => 'Guaçuí',
        '2941' => 'São José do Calçado',
        '2942' => 'Bom Jesus do Norte',
        '2943' => 'Apiacá',
        '2944' => 'Jerônimo Monteiro',
        '2945' => 'Ibitirama',
        '2946' => 'Muniz Freire',
        '2947' => 'Dores do Rio Preto',
        '2948' => 'Divino de São Lourenço',
        '2949' => 'Irupi',
        '2950' => 'Iúna',

        // Região Central & Serrana
        '2955' => 'Afonso Cláudio',
        '2956' => 'Brejetuba',
        '2960' => 'Santa Maria de Jetibá',
        '2964' => 'Santa Leopoldina',
        '2965' => 'Santa Teresa',
        '2966' => 'Itarana',
        '2967' => 'Itaguaçu',
        '2968' => 'São Roque do Canaã',
        '2969' => 'Laranja da Terra',

        // Noroeste & Norte (Colatina, Linhares, São Mateus)
        '2970' => 'Colatina',
        '2971' => 'Colatina',
        '2972' => 'Baixo Guandu',
        '2973' => 'Marilândia',
        '2974' => 'Pancas',
        '2975' => 'Alto Rio Novo',
        '2976' => 'Governador Lindenberg',
        '2977' => 'São Domingos do Norte',
        '2978' => 'São Gabriel da Palha',
        '2979' => 'Vila Valério',
        '2980' => 'Barra de São Francisco',
        '2981' => 'Água Doce do Norte',
        '2982' => 'Ecoporanga',
        '2983' => 'Mantenópolis',
        '2984' => 'Águia Branca',
        '2985' => 'Nova Venécia',
        '2986' => 'Boa Esperança',
        '2987' => 'Vila Pavão',
        '2988' => 'Pinheiros',
        '2989' => 'Montanha',
        '2990' => 'Linhares',
        '2991' => 'Linhares',
        '2992' => 'Sooretama',
        '2993' => 'São Mateus',
        '2994' => 'São Mateus',
        '2995' => 'Jaguaré',
        '2996' => 'Conceição da Barra',
        '2997' => 'Pedro Canário',
        '2998' => 'Mucurici',
        '2999' => 'Ponto Belo',
    ];

    /**
     * Resolves an ES Municipio model by CEP string (e.g. "29050-000", "29900000").
     */
    public function resolveByCep(string $cep): ?MunicipioEs
    {
        $cleanCep = preg_replace('/\D/', '', $cep);
        if (strlen($cleanCep) !== 8 || !str_starts_with($cleanCep, '29')) {
            return null;
        }

        // 1. Try 4-digit prefix
        $p4 = substr($cleanCep, 0, 4);
        if (isset(self::$cepPrefixMap[$p4])) {
            $m = MunicipioEs::where('nome', self::$cepPrefixMap[$p4])->first();
            if ($m) return $m;
        }

        // 2. Try 3-digit prefix
        $p3 = substr($cleanCep, 0, 3);
        if (isset(self::$cepPrefixMap[$p3])) {
            $m = MunicipioEs::where('nome', self::$cepPrefixMap[$p3])->first();
            if ($m) return $m;
        }

        // Default to Vitória if in ES range
        return MunicipioEs::where('codigo_ibge', 3205309)->first();
    }

    /**
     * Calculates distance in kilometers between two GPS coordinates using Haversine formula.
     */
    public function calculateDistanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadiusKm * $c, 2);
    }
}
