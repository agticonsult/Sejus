<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\MunicipioEs;
use App\Models\VagaEmprego;
use App\Models\CursoCapacitacao;
use App\Models\RedeApoio;
use App\Models\Prontuario;
use App\Models\ProntuarioTimeline;
use App\Services\AuditService;
use App\Services\CepMunicipioService;
use Illuminate\Support\Facades\Auth;

class TerritorioController extends Controller
{
    /**
     * List all 78 ES municipalities with aggregated statistics and coverage info.
     */
    public function index(Request $request): JsonResponse
    {
        $query = MunicipioEs::withCount([
            'vagas as total_vagas_abertas' => fn($q) => $q->where('status', 'aberta'),
            'redeApoio as total_unidades_apoio' => fn($q) => $q->where('ativo', true),
        ]);

        // Accent-insensitive search on name or IBGE code
        if ($request->filled('q')) {
            $q = trim($request->input('q'));
            $qLower = '%' . mb_strtolower($q, 'UTF-8') . '%';
            $cleanIbge = preg_replace('/\D/', '', $q);

            $query->where(function ($sub) use ($qLower, $cleanIbge) {
                if (strlen($cleanIbge) >= 2) {
                    $sub->where('codigo_ibge', 'like', "%{$cleanIbge}%");
                }
                $sub->orWhereRaw('LOWER(nome) LIKE ?', [$qLower])
                    ->orWhereRaw('LOWER(microrregiao) LIKE ?', [$qLower])
                    ->orWhereRaw('LOWER(macrorregiao) LIKE ?', [$qLower]);
            });
        }

        // Macrorregiao filter
        if ($request->filled('macrorregiao')) {
            $query->where('macrorregiao', $request->input('macrorregiao'));
        }

        // Microrregiao filter
        if ($request->filled('microrregiao')) {
            $query->where('microrregiao', $request->input('microrregiao'));
        }

        // Physical office indicator filter
        if ($request->has('tem_escritorio_fisico')) {
            $temFisico = filter_var($request->input('tem_escritorio_fisico'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($temFisico !== null) {
                $query->where('tem_escritorio_fisico', $temFisico);
            }
        }

        $municipios = $query->orderBy('nome', 'asc')->get();

        $data = $municipios->map(function ($m) {
            return [
                'id' => $m->id,
                'codigo_ibge' => (int) $m->codigo_ibge,
                'nome' => $m->nome,
                'microrregiao' => $m->microrregiao,
                'macrorregiao' => $m->macrorregiao,
                'latitude' => (float) $m->latitude,
                'longitude' => (float) $m->longitude,
                'tem_escritorio_fisico' => (bool) $m->tem_escritorio_fisico,
                'atendimento_remoto_disponivel' => true,
                'populacao_estimada' => (int) $m->populacao_estimada,
                'total_egressos_atendidos' => (int) $m->total_egressos_atendidos,
                'total_vagas_abertas' => (int) $m->total_vagas_abertas,
                'total_unidades_apoio' => (int) $m->total_unidades_apoio,
            ];
        });

        return response()->json([
            'total' => $data->count(),
            'total_municipios_es' => 78,
            'com_escritorio_fisico' => 4,
            'cobertura_remota_conecta_egresso' => 74,
            'data' => $data,
        ]);
    }

    /**
     * Show detailed municipality profile, including CRAS/SINE network, open jobs, and courses.
     */
    public function show(string $codigoIbgeOrId): JsonResponse
    {
        $cleanParam = trim($codigoIbgeOrId);

        // Validation for IBGE code if 7 digits
        if (is_numeric($cleanParam) && strlen($cleanParam) === 7) {
            if (!str_starts_with($cleanParam, '32')) {
                return response()->json([
                    'error' => 'Código IBGE inválido ou fora do Estado do Espírito Santo (UF 32).',
                    'code' => 'INVALID_ES_IBGE_CODE',
                    'provided_ibge' => $cleanParam,
                ], 422);
            }
        }

        $municipio = MunicipioEs::where('codigo_ibge', $cleanParam)
            ->orWhere('id', is_numeric($cleanParam) ? (int)$cleanParam : 0)
            ->first();

        if (!$municipio) {
            return response()->json([
                'error' => 'Município não encontrado.',
                'code' => 'MUNICIPIO_NOT_FOUND',
            ], 404);
        }

        // Load support network
        $redeApoio = RedeApoio::where('municipio_id', $municipio->id)
            ->where('ativo', true)
            ->get()
            ->map(function ($r) use ($municipio) {
                $hasExactGps = $r->latitude !== null && $r->longitude !== null;
                return [
                    'id' => $r->id,
                    'nome' => $r->nome,
                    'tipo' => $r->tipo,
                    'endereco' => $r->endereco,
                    'telefone' => $r->telefone,
                    'email' => $r->email,
                    'horario_funcionamento' => $r->horario_funcionamento,
                    'servicos_oferecidos' => $r->servicos_oferecidos ?? [],
                    'latitude' => (float) ($hasExactGps ? $r->latitude : $municipio->latitude),
                    'longitude' => (float) ($hasExactGps ? $r->longitude : $municipio->longitude),
                    'origem_coordenada' => $hasExactGps ? 'exact_gps' : 'municipality_centroid_fallback',
                ];
            });

        // Load open jobs
        $vagas = VagaEmprego::where('municipio_id', $municipio->id)
            ->where('status', 'aberta')
            ->get();

        // Load courses (local + statewide EAD)
        $cursos = CursoCapacitacao::where(function ($q) use ($municipio) {
            $q->where('municipio_id', $municipio->id)
              ->orWhereNull('municipio_id')
              ->orWhere('modalidade', 'ead');
        })->where('status', 'aberto')->get();

        return response()->json([
            'data' => [
                'id' => $municipio->id,
                'codigo_ibge' => (int) $municipio->codigo_ibge,
                'nome' => $municipio->nome,
                'microrregiao' => $municipio->microrregiao,
                'macrorregiao' => $municipio->macrorregiao,
                'latitude' => (float) $municipio->latitude,
                'longitude' => (float) $municipio->longitude,
                'tem_escritorio_fisico' => (bool) $municipio->tem_escritorio_fisico,
                'atendimento_remoto_disponivel' => true,
                'populacao_estimada' => (int) $municipio->populacao_estimada,
                'total_egressos_atendidos' => (int) $municipio->total_egressos_atendidos,
                'unidades_apoio' => $redeApoio,
                'vagas_abertas' => $vagas,
                'cursos_disponiveis' => $cursos,
            ],
        ]);
    }

    /**
     * Regional distribution summary across Macrorregiões and Microrregiões.
     */
    public function regioes(): JsonResponse
    {
        $macroStats = MunicipioEs::selectRaw('macrorregiao, COUNT(*) as total_municipios, SUM(populacao_estimada) as populacao_total, SUM(total_egressos_atendidos) as egressos_atendidos, SUM(CASE WHEN tem_escritorio_fisico THEN 1 ELSE 0 END) as escritorios_fisicos')
            ->groupBy('macrorregiao')
            ->get();

        $microStats = MunicipioEs::selectRaw('macrorregiao, microrregiao, COUNT(*) as total_municipios, SUM(total_egressos_atendidos) as egressos_atendidos')
            ->groupBy('macrorregiao', 'microrregiao')
            ->orderBy('macrorregiao')
            ->get();

        return response()->json([
            'macrorregioes' => $macroStats,
            'microrregioes' => $microStats,
        ]);
    }

    /**
     * Save explicit LGPD geolocation consent, find closest municipality & support units via Haversine,
     * and record location check-in in AuditService & ProntuarioTimeline.
     */
    public function checkinGps(Request $request, CepMunicipioService $geoService, AuditService $audit): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Não autenticado.'], 401);
        }

        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'consentimento_lgpd' => 'required|accepted',
        ]);

        $lat = (float) $validated['latitude'];
        $lon = (float) $validated['longitude'];

        $municipios = MunicipioEs::all();
        $nearest = null;
        $minDistKm = PHP_FLOAT_MAX;

        foreach ($municipios as $m) {
            if ($m->latitude === null || $m->longitude === null) {
                continue;
            }
            $dist = $geoService->calculateDistanceKm($lat, $lon, (float) $m->latitude, (float) $m->longitude);
            if ($dist < $minDistKm) {
                $minDistKm = $dist;
                $nearest = $m;
            }
        }

        if (!$nearest) {
            return response()->json(['error' => 'Não há municípios com coordenadas cadastradas.'], 422);
        }

        $prontuarioId = null;
        if ($user->egresso) {
            $user->egresso->update([
                'consentimento_geolocalizacao' => true,
                'termo_aceito_em' => $user->egresso->termo_aceito_em ?? now(),
                'municipio_residencia_id' => $nearest->id,
            ]);
            $prontuario = Prontuario::firstOrCreate(
                ['egresso_id' => $user->egresso->id],
                [
                    'numero_prontuario' => sprintf('PRT-%s-%06d', now()->format('Y'), (Prontuario::max('id') ?? 0) + 1),
                    'situacao' => 'ativo',
                    'resumo_diagnostico' => 'Prontuário inicializado após check-in territorial.',
                    'data_abertura' => now(),
                ]
            );
            $prontuarioId = $prontuario->id;

            ProntuarioTimeline::create([
                'prontuario_id' => $prontuarioId,
                'tipo_evento' => 'parecer_tecnico',
                'titulo' => 'Check-in Territorial GPS (Consentimento LGPD)',
                'descricao' => "Localização territorial registrada via GPS em {$nearest->nome}/ES (~" . round($minDistKm, 1) . ' km do centro do polo).',
                'metadata' => [
                    'municipio' => $nearest->nome,
                    'codigo_ibge' => $nearest->codigo_ibge,
                    'latitude' => round($lat, 5),
                    'longitude' => round($lon, 5),
                    'distancia_km' => round($minDistKm, 2),
                ],
                'responsavel_id' => $user->id,
                'data_evento' => now(),
            ]);
        }

        $audit->log($prontuarioId, 'TERRITORIO_GPS_CHECKIN', [
            'municipio_id' => $nearest->id,
            'municipio_nome' => $nearest->nome,
            'distancia_km' => round($minDistKm, 2),
            'consentimento_lgpd' => true,
        ], $user->id, $request->ip(), $request->userAgent());

        $unidades = RedeApoio::where('municipio_id', $nearest->id)
            ->where('ativo', true)
            ->get()
            ->map(function ($u) use ($lat, $lon, $nearest, $geoService) {
                $uLat = (float) ($u->latitude ?? $nearest->latitude);
                $uLon = (float) ($u->longitude ?? $nearest->longitude);
                return [
                    'id' => $u->id,
                    'nome' => $u->nome,
                    'tipo' => $u->tipo,
                    'endereco' => $u->endereco,
                    'telefone' => $u->telefone,
                    'horario' => $u->horario_funcionamento,
                    'distancia_km' => round($geoService->calculateDistanceKm($lat, $lon, $uLat, $uLon), 1),
                ];
            })
            ->sortBy('distancia_km')
            ->values();

        return response()->json([
            'status' => 'success',
            'municipio' => [
                'id' => $nearest->id,
                'codigo_ibge' => (string) $nearest->codigo_ibge,
                'nome' => $nearest->nome,
                'distancia_km' => round($minDistKm, 1),
            ],
            'unidades_proximas' => $unidades,
            'message' => "Localização confirmada em {$nearest->nome}/ES ({$unidades->count()} unidades de apoio próximas).",
        ]);
    }
}
