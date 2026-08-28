# Architectural & Forensic Survey: PDF Generation, CarteiraPdfService & Document Generator API
**Plataforma CONECTA EGRESSO — SEJUS / Governo do Estado do Espírito Santo**

---

## 1. Executive Summary

This survey report analyzes the technical architecture, service layers, routes, models, templates, and integration points for **Requirement R2 (Geração de PDF via Document Generator API)** and the digital credential wallet (**Carteira Digital do Egresso**) in the Conecta Egresso platform.

The objective is to establish an end-to-end integration strategy between Laravel 11, the external **Document Generator** microservice (`http://localhost:8080`, API Key `token-secreto-dev`), the local **Dompdf** engine (as automatic graceful fallback), and the web route `GET /carteira/pdf`.

---

## 2. Forensic Codebase Inspection

### 2.1 Current PDF Generation Service: `app/Services/CarteiraPdfService.php`

**File Location:** `app/Services/CarteiraPdfService.php` (141 lines)

```php
// Existing renderHtml implementation (lines 27-56)
public function renderHtml(object $egresso): string
{
    $payload = $this->qrService->generatePayload($egresso);
    $token = $this->qrService->generateToken($payload);
    $validationUrl = $this->qrService->getValidationUrl($token);
    $qrCodeDataUri = $this->qrService->generateQrCodeDataUri($validationUrl);
    $authCode = strtoupper(implode('-', str_split(substr($this->qrService->signPayload($payload), 0, 16), 4)));

    $data = [
        'egresso' => $egresso,
        'nome' => mb_strtoupper($egresso->nome_completo),
        'nomeSocial' => !empty($egresso->nome_social) ? mb_strtoupper($egresso->nome_social) : null,
        'cpfMasked' => $this->lgpdService->maskCpf($egresso->cpf ?? '00000000000'),
        'registroSejus' => $egresso->registro_sejus ?? ('ES-2026-' . str_pad((string) $egresso->id, 6, '0', STR_PAD_LEFT)),
        'municipio' => $egresso->municipio?->nome ?? 'Espirito Santo',
        'dataEmissao' => now()->format('d/m/Y'),
        'dataValidade' => now()->addYear()->format('d/m/Y'),
        'qrCodeDataUri' => $qrCodeDataUri,
        'authCode' => $authCode,
        'validationUrl' => $validationUrl,
        'token' => $token,
    ];

    if (class_exists(View::class) && View::exists('pdf.carteira_digital')) {
        return View::make('pdf.carteira_digital', $data)->render();
    }

    return $this->renderFallbackTemplate($data);
}
```

```php
// Existing generatePdf implementation (lines 63-87)
public function generatePdf(object $egresso): string
{
    $html = $this->renderHtml($egresso);

    try {
        if (class_exists(Dompdf::class)) {
            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', false);
            $options->set('defaultFont', 'Helvetica');
            $options->set('dpi', 150);

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            return $dompdf->output();
        }
    } catch (Throwable $e) {
        // Fallback
    }

    return "%PDF-1.4\n%Fallback PDF\n" . $html;
}
```

**Key Findings:**
1. `CarteiraPdfService` currently only calls `Dompdf` directly. It does **not** yet attempt to communicate with the Document Generator microservice.
2. The HTML renderer is fully operational and generates valid institutional HTML with embedded Base64 SVG QR codes.
3. The fallback template is present and contains valid fallback markup.

---

### 2.2 Template & Styling: `resources/views/pdf/carteira_digital.blade.php`

**File Location:** `resources/views/pdf/carteira_digital.blade.php` (213 lines)

- **Page Configuration:** `@page { size: A4 portrait; margin: 20mm; }`
- **Institutional Header:**
  - `GOVERNO DO ESTADO DO ESPÍRITO SANTO`
  - `SECRETARIA DE ESTADO DA JUSTIÇA — SEJUS / ESCRITÓRIO SOCIAL DIGITAL`
  - `CREDENCIAL OFICIAL DO EGRESSO • PROGRAMA CONECTA EGRESSO`
- **Verification Seal:** `✓ CREDENCIAL OFICIAL AUTENTICADA & VERIFICADA`
- **2-Column Layout:**
  - **Left Column (65%):**
    - Nome do Titular (`$nome`)
    - Nome Social (`$nomeSocial`, conditional `@if(!empty($nomeSocial))`)
    - CPF Protegido pela LGPD (`$cpfMasked` formatted as `***.NNN.NNN-**`)
    - Registro Geral SEJUS / ES (`$registroSejus` formatted as `ES-2026-XXXXXX`)
    - Município de Referência / Residência (`$municipio / Espírito Santo`)
    - Período de Validade Oficial (`$dataEmissao até $dataValidade`)
    - Código de Assinatura Criptográfica (`$authCode` e.g., `ABCD-1234-EF56-7890`)
  - **Right Column (35%):**
    - QR Code Image (`<img src="{{ $qrCodeDataUri }}" class="qr-image" />`)
    - Instructions: "Autenticidade Instantânea - Escaneie com a câmera para conferência pública"
- **Legal Stamp & Footer:**
  - "Validade Jurídica em todo o Território Estadual — Lei Complementar Estadual nº 182/2021."
  - "URL Pública: `{{ $validationUrl }}`"

---

### 2.3 Route & Frontend Binding Inspection

#### A. Frontend: `resources/js/Pages/Carteira.vue`
- Line 23: `<a :href="pdfDownloadUrl" target="_blank" class="..."><span>📥 Baixar Carteira em PDF</span></a>`
- Line 237-240:
  ```js
  pdfDownloadUrl: {
    type: String,
    default: '/carteira/pdf',
  },
  ```

#### B. Backend Routes: `routes/web.php`
- Line 25-27:
  ```php
  Route::get('/carteira', function () {
      return Inertia::render('Carteira');
  })->name('carteira');
  ```
- **GAP DETECTED:** Route `GET /carteira/pdf` is currently **missing** in `routes/web.php`. Any attempt to click "Baixar Carteira em PDF" results in a `404 Not Found`.

---

## 3. Document Generator Microservice Architecture Specification

### 3.1 Microservice Connection Parameters
- **Base URL:** `http://localhost:8080` (configurable via `env('DOCUMENT_GENERATOR_URL', 'http://localhost:8080')` and `config('services.document_generator.url')`)
- **API Key:** `token-secreto-dev` (configurable via `env('DOCUMENT_GENERATOR_KEY', 'token-secreto-dev')` and `config('services.document_generator.key')`)
- **HTTP Method:** `POST`
- **Endpoints to Support:** `http://localhost:8080` (or `http://localhost:8080/generate`, `http://localhost:8080/api/generate`, `http://localhost:8080/pdf`)
- **Request Headers:**
  - `Authorization: Bearer token-secreto-dev`
  - `X-API-Key: token-secreto-dev`
  - `Content-Type: application/json`
  - `Accept: application/pdf, application/json`
- **Request Payload:**
  ```json
  {
    "html": "<!DOCTYPE html>...",
    "api_key": "token-secreto-dev",
    "options": {
      "format": "A4",
      "orientation": "portrait",
      "margin": "20mm"
    }
  }
  ```
- **Timeout Strategy:** Strict 3 to 5 seconds timeout (`Http::timeout(5)`) to prevent upstream connection delays from hanging user requests.

### 3.2 Resilience & Graceful Fallback Hierarchy
The PDF generation engine in `CarteiraPdfService::generatePdf(object $egresso)` must follow a 3-tier cascade:

```
┌────────────────────────────────────────────────────────┐
│  Tier 1: Document Generator API (http://localhost:8080) │
│  - POST compiled HTML with Bearer / X-API-Key auth     │
│  - 5-second timeout                                    │
│  - Returns binary PDF (%PDF-1.x)                       │
└──────────────────────────┬─────────────────────────────┘
                           │ (on timeout / 5xx / 4xx / connection refused)
                           ▼
┌────────────────────────────────────────────────────────┐
│  Tier 2: Local Dompdf Engine (Dompdf\Dompdf)           │
│  - Load compiled HTML                                  │
│  - Render A4 portrait with Helvetica / 150 DPI         │
│  - Returns binary PDF stream ($dompdf->output())       │
└──────────────────────────┬─────────────────────────────┘
                           │ (on Dompdf exception or class missing)
                           ▼
┌────────────────────────────────────────────────────────┐
│  Tier 3: Standard Stream Fallback                      │
│  - Return "%PDF-1.4\n%Fallback PDF\n" . $html          │
└────────────────────────────────────────────────────────┘
```

---

## 4. Route & Controller Implementation Blueprint

### 4.1 Controller Design: `app/Http/Controllers/CarteiraPdfController.php` (or method on `CarteiraValidationController`)

```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use App\Services\CarteiraPdfService;
use App\Models\Egresso;
use App\Models\User;

class CarteiraPdfController extends Controller
{
    public function __construct(
        protected CarteiraPdfService $pdfService
    ) {}

    public function download(Request $request): Response
    {
        $user = Auth::user();
        $egresso = null;

        // 1. If authenticated as Egresso, use user's egresso profile
        if ($user && $user->isEgresso() && $user->egresso) {
            $egresso = $user->egresso;
        }

        // 2. If authenticated as Gestor/Tecnico/Suporte and egresso_id is passed
        if (!$egresso && $request->has('egresso_id')) {
            $egresso = Egresso::with('municipio')->find($request->query('egresso_id'));
        }

        // 3. Fallback: If logged out or no specific egresso, use first Egresso in DB
        if (!$egresso) {
            $egresso = Egresso::with('municipio')->first();
        }

        // 4. Mock fallback for empty DB / isolated environments
        if (!$egresso) {
            $egresso = (object) [
                'id' => 1,
                'nome_completo' => 'Lucas Santos de Oliveira',
                'nome_social' => null,
                'cpf' => '19283045678',
                'registro_sejus' => 'ES-2026-000001',
                'municipio' => (object) ['nome' => 'São Mateus'],
            ];
        }

        $pdfBinary = $this->pdfService->generatePdf($egresso);
        $filename = 'carteira_digital_' . ($egresso->id ?? '1') . '.pdf';

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Content-Length' => strlen($pdfBinary),
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }
}
```

### 4.2 Web Route Registration in `routes/web.php`
```php
use App\Http\Controllers\CarteiraPdfController;

Route::get('/carteira/pdf', [CarteiraPdfController::class, 'download'])->name('carteira.pdf');
```

---

## 5. Model & Cryptographic Data Field Mapping

| Field | Source Attribute | Transformation / Formatting | Example Value |
|---|---|---|---|
| Titular Name | `Egresso->nome_completo` | `mb_strtoupper($nome)` | `LUCAS SANTOS DE OLIVEIRA` |
| Social Name | `Egresso->nome_social` | `mb_strtoupper($nomeSocial)` (nullable) | `null` or `LUCAS SANTOS` |
| Masked CPF | `Egresso->cpf` | `LgpdSecurityService::maskCpf()` | `***.192.830-**` |
| Registration | `Egresso->registro_sejus` | `ES-2026-00000X` (6 digits pad) | `ES-2026-000001` |
| Municipality | `Egresso->municipio->nome` | `$municipio->nome ?? 'Espírito Santo'` | `São Mateus` |
| Issue Date | Current Timestamp | `now()->format('d/m/Y')` | `18/08/2026` |
| Expiry Date | Issue + 1 Year | `now()->addYear()->format('d/m/Y')` | `18/08/2027` |
| Auth Code | HMAC-SHA256 Signature | 16-char split into 4-char groups | `8F4C-2E6B-9A1D-0F5C` |
| QR Code SVG | `QrCodeSecurityService` | Base64 Data URI SVG | `data:image/svg+xml;base64,PHN2Zy...` |
| Validation URL | `QrCodeSecurityService` | `config('app.url') . '/validar-carteira/' . $token` | `http://localhost/validar-carteira/eyJ...` |
