<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Models\RedeApoio;
use App\Models\MunicipioEs;

class RedeApoioController extends Controller
{
    /**
     * List social support network units (CRAS, CREAS, SINE, CAPS) with coordinate fallback policy.
     */
    public function index(Request $request): JsonResponse
    {
        $query = RedeApoio::with('municipio:id,nome,codigo_ibge,latitude,longitude');

        // Status filter (defaults to active)
        if ($request->has('ativo')) {
            $ativo = filter_var($request->input('ativo'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($ativo !== null) {
                $query->where('ativo', $ativo);
            }
        } else {
            $query->where('ativo', true);
        }

        // Tipo filter
        if ($request->filled('tipo')) {
            $query->where('tipo', strtoupper($request->input('tipo')));
        }

        // Municipio filter by ID or IBGE code
        if ($request->filled('municipio_id')) {
            $query->where('municipio_id', (int) $request->input('municipio_id'));
        } elseif ($request->filled('codigo_ibge')) {
            $ibge = preg_replace('/\D/', '', $request->input('codigo_ibge'));
            $query->whereHas('municipio', fn($mq) => $mq->where('codigo_ibge', $ibge));
        }

        // Search q
        if ($request->filled('q')) {
            $q = trim($request->input('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('nome', 'ILIKE', "%{$q}%")
                    ->orWhere('endereco', 'ILIKE', "%{$q}%")
                    ->orWhere('tipo', 'ILIKE', "%{$q}%");
            });
        }

        $units = $query->orderBy('tipo')->orderBy('nome')->get();

        $formatted = $units->map(function ($unit) {
            $mun = $unit->municipio;
            $hasExactGps = $unit->latitude !== null && $unit->longitude !== null;

            return [
                'id' => $unit->id,
                'nome' => $unit->nome,
                'tipo' => $unit->tipo,
                'municipio_id' => $unit->municipio_id,
                'municipio_nome' => $mun?->nome,
                'codigo_ibge' => (int) ($mun?->codigo_ibge ?? 0),
                'endereco' => $unit->endereco,
                'telefone' => $unit->telefone,
                'email' => $unit->email,
                'horario_funcionamento' => $unit->horario_funcionamento,
                'servicos_oferecidos' => $unit->servicos_oferecidos ?? [],
                'latitude' => (float) ($hasExactGps ? $unit->latitude : ($mun?->latitude ?? -20.3155)),
                'longitude' => (float) ($hasExactGps ? $unit->longitude : ($mun?->longitude ?? -40.3128)),
                'origem_coordenada' => $hasExactGps ? 'exact_gps' : 'municipality_centroid_fallback',
                'ativo' => (bool) $unit->ativo,
            ];
        });

        return response()->json([
            'total' => $formatted->count(),
            'data' => $formatted,
        ]);
    }

    /**
     * Show single support unit.
     */
    public function show(string $id): JsonResponse
    {
        $unit = RedeApoio::with('municipio')->find($id);

        if (!$unit) {
            return response()->json(['error' => 'Unidade socioassistencial não encontrada.'], 404);
        }

        $mun = $unit->municipio;
        $hasExactGps = $unit->latitude !== null && $unit->longitude !== null;

        return response()->json([
            'data' => [
                'id' => $unit->id,
                'nome' => $unit->nome,
                'tipo' => $unit->tipo,
                'municipio_id' => $unit->municipio_id,
                'municipio_nome' => $mun?->nome,
                'codigo_ibge' => (int) ($mun?->codigo_ibge ?? 0),
                'endereco' => $unit->endereco,
                'telefone' => $unit->telefone,
                'email' => $unit->email,
                'horario_funcionamento' => $unit->horario_funcionamento,
                'servicos_oferecidos' => $unit->servicos_oferecidos ?? [],
                'latitude' => (float) ($hasExactGps ? $unit->latitude : ($mun?->latitude ?? -20.3155)),
                'longitude' => (float) ($hasExactGps ? $unit->longitude : ($mun?->longitude ?? -40.3128)),
                'origem_coordenada' => $hasExactGps ? 'exact_gps' : 'municipality_centroid_fallback',
                'ativo' => (bool) $unit->ativo,
            ],
        ]);
    }

    /**
     * Create new support unit (Gestor).
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user && $user->isEgresso()) {
            return response()->json(['error' => 'Acesso não autorizado.'], 403);
        }

        $validated = $request->validate([
            'nome' => 'required|string|max:150',
            'tipo' => 'required|string|in:CRAS,CREAS,SINE,CAPS,CASA_CIDADAO,DEFENSORIA,ESCRITORIO_SOCIAL',
            'municipio_id' => 'required|integer|exists:municipios_es,id',
            'endereco' => 'nullable|string|max:255',
            'telefone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:100',
            'horario_funcionamento' => 'nullable|string|max:100',
            'servicos_oferecidos' => 'nullable|array',
            'latitude' => 'nullable|numeric|between:-21.31,-17.88',
            'longitude' => 'nullable|numeric|between:-41.88,-39.66',
            'ativo' => 'nullable|boolean',
        ]);

        $unit = RedeApoio::create(array_merge($validated, [
            'ativo' => $validated['ativo'] ?? true,
        ]));

        return response()->json([
            'status' => 'created',
            'message' => 'Unidade socioassistencial cadastrada com sucesso.',
            'data' => $unit->load('municipio'),
        ], 201);
    }

    /**
     * Update support unit.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();

        if ($user && $user->isEgresso()) {
            return response()->json(['error' => 'Acesso não autorizado.'], 403);
        }

        $unit = RedeApoio::find($id);
        if (!$unit) {
            return response()->json(['error' => 'Unidade não encontrada.'], 404);
        }

        $validated = $request->validate([
            'nome' => 'nullable|string|max:150',
            'tipo' => 'nullable|string|in:CRAS,CREAS,SINE,CAPS,CASA_CIDADAO,DEFENSORIA,ESCRITORIO_SOCIAL',
            'municipio_id' => 'nullable|integer|exists:municipios_es,id',
            'endereco' => 'nullable|string|max:255',
            'telefone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:100',
            'horario_funcionamento' => 'nullable|string|max:100',
            'servicos_oferecidos' => 'nullable|array',
            'latitude' => 'nullable|numeric|between:-21.31,-17.88',
            'longitude' => 'nullable|numeric|between:-41.88,-39.66',
            'ativo' => 'nullable|boolean',
        ]);

        $unit->update($validated);

        return response()->json([
            'status' => 'updated',
            'data' => $unit->load('municipio'),
        ]);
    }

    /**
     * Deactivate / delete unit.
     */
    public function destroy(string $id): JsonResponse
    {
        $user = Auth::user();

        if ($user && $user->isEgresso()) {
            return response()->json(['error' => 'Acesso não autorizado.'], 403);
        }

        $unit = RedeApoio::find($id);
        if (!$unit) {
            return response()->json(['error' => 'Unidade não encontrada.'], 404);
        }

        $unit->update(['ativo' => false]);

        return response()->json(['status' => 'deactivated', 'message' => 'Unidade desativada com sucesso.']);
    }
}
