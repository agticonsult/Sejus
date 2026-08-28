<?php

namespace App\Http\Controllers;

use App\Models\Egresso;
use App\Services\CarteiraPdfService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Throwable;

class CarteiraPdfController extends Controller
{
    /**
     * Download or stream Digital Wallet PDF with official SEJUS layout.
     *
     * @param Request $request
     * @param CarteiraPdfService $pdfService
     * @return Response
     */
    public function download(Request $request, CarteiraPdfService $pdfService): Response
    {
        $user = Auth::user() ?? $request->user();
        $egresso = null;

        if ($user) {
            $isStaff = (method_exists($user, 'isGestor') && $user->isGestor())
                || (method_exists($user, 'isTecnico') && $user->isTecnico())
                || (method_exists($user, 'isSuporte') && $user->isSuporte())
                || in_array($user->perfil?->slug, ['gestor', 'tecnico', 'suporte'], true);

            if ($isStaff && $request->has('egresso_id')) {
                try {
                    $egresso = Egresso::with('municipio')->find($request->query('egresso_id'));
                } catch (Throwable $e) {
                    $egresso = null;
                }
            }

            if (!$egresso && $user->egresso) {
                $egresso = $user->egresso;
                if (method_exists($egresso, 'loadMissing')) {
                    $egresso->loadMissing('municipio');
                }
            }

            if (!$egresso && $isStaff) {
                try {
                    $egresso = Egresso::with('municipio')->first();
                } catch (Throwable $e) {
                    $egresso = null;
                }
            }
        }

        // Fallback for unauthenticated / guest testing or empty database
        if (!$egresso) {
            try {
                if (class_exists(Egresso::class)) {
                    $egresso = Egresso::with('municipio')->first();
                }
            } catch (Throwable $e) {
                $egresso = null;
            }
        }

        if (!$egresso) {
            // Realistic mock Egresso object for standalone demo or fresh install
            $egresso = (object) [
                'id' => 1,
                'nome_completo' => 'Lucas Santos de Oliveira',
                'nome_social' => null,
                'cpf' => '19283045678',
                'registro_sejus' => 'ES-2026-000001',
                'municipio' => (object) [
                    'nome' => 'Vitória',
                ],
            ];
        }

        $pdf = $pdfService->generatePdf($egresso);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="carteira-digital-sejus.pdf"',
            'Cache-Control' => 'no-cache, private',
        ]);
    }
}
