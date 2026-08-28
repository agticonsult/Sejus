# Handoff Report — Explorer 2: PDF Generation Architecture & Document Generator API Survey
**Milestone:** Conecta Egresso Technical Survey (Requirement R2)  
**Agent:** `explorer_survey_2`  
**Recipient:** Orchestrator (`d1fff5db-63e7-45f8-859e-5033cc3b20ad`)  
**Date:** 2026-08-18  
**Handoff Type:** Hard (Task Complete)

---

## 1. Observation

1. **`ORIGINAL_REQUEST.md` (Lines 16-21, 45-48):**
   - R2 explicitly specifies:
     - Integrar a geração da carteira digital em PDF com o microsserviço Document Generator (`http://localhost:8080`, API Key `token-secreto-dev`).
     - No `CarteiraPdfService`, enviar requisição `POST` com o template HTML compilado para a API externa e obter o PDF resultante.
     - Manter o gerador local `Dompdf` como fallback automático caso o microsserviço falhe ou esteja offline.
     - Registrar a rota GET `/carteira/pdf` em `routes/web.php` e acoplar a este serviço. Se deslogado, usar o primeiro egresso como fallback para testes.
   - Acceptance Criteria:
     - `/carteira/pdf` retorna um stream de PDF válido no navegador com cabeçalhos apropriados (`Content-Type: application/pdf`, `Content-Disposition`).
     - A geração consome a API do Document Generator em `localhost:8080`, com fallback local funcional.

2. **`app/Services/CarteiraPdfService.php` (Lines 1-141):**
   - Method `renderHtml(object $egresso)` compiles data (`$nome`, `$nomeSocial`, `$cpfMasked`, `$registroSejus`, `$municipio`, `$dataEmissao`, `$dataValidade`, `$qrCodeDataUri`, `$authCode`, `$validationUrl`, `$token`) and renders Blade template `pdf.carteira_digital` (or inline fallback).
   - Method `generatePdf(object $egresso)` (Lines 63-87) directly invokes `Dompdf` and lacks any HTTP call to the Document Generator microservice.

3. **`resources/views/pdf/carteira_digital.blade.php` (Lines 1-213):**
   - Full Blade template formatted for A4 portrait with CSS `@page { size: A4 portrait; margin: 20mm; }`.
   - Contains institutional header ("GOVERNO DO ESTADO DO ESPÍRITO SANTO / SEJUS / ESCRITÓRIO SOCIAL DIGITAL"), status badge ("✓ CREDENCIAL OFICIAL AUTENTICADA & VERIFICADA"), two-column table (65% data, 35% QR code), and legal notice citing Lei Complementar Estadual nº 182/2021.

4. **`routes/web.php` (Lines 1-54):**
   - Contains `Route::get('/carteira', ...)` returning Inertia view `Carteira`.
   - **Missing:** Route `Route::get('/carteira/pdf', ...)` is not registered in `routes/web.php`.

5. **`resources/js/Pages/Carteira.vue` (Lines 22-28, 237-241):**
   - Button uses `<a :href="pdfDownloadUrl" target="_blank">` with default `pdfDownloadUrl: '/carteira/pdf'`.

6. **`app/Models/Egresso.php` (Lines 1-229):**
   - Accessor `getRegistroSejusAttribute` computes `'ES-2026-' . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT)`.
   - Mutator/Accessor for `cpf` handles LGPD encryption and blind indexing.
   - Relationship `municipio()` links to `MunicipioEs`.

7. **Configuration & Testing Files:**
   - `config/services.php` needs `document_generator` entry: `['url' => env('DOCUMENT_GENERATOR_URL', 'http://localhost:8080'), 'key' => env('DOCUMENT_GENERATOR_KEY', 'token-secreto-dev')]`.
   - `tests/Unit/CarteiraPdfServiceTest.php` tests HTML rendering.
   - `tests_e2e/tier3_combinations/test_pdf_qr_validation_chain.py` tests PDF stream validation (`%PDF-1.4`, MIME `application/pdf`, headers).

---

## 2. Logic Chain

1. **Microservice Invocation Design (From Observation 1 & 2):**
   - When `CarteiraPdfService::generatePdf(object $egresso)` is called, it first compiles the HTML template via `$this->renderHtml($egresso)`.
   - It then dispatches a POST request using Laravel's `Http::timeout(5)->withHeaders([...])->post($url, ['html' => $html, 'api_key' => $apiKey, ...])`.
   - If the external Document Generator returns HTTP 200 and a non-empty binary body starting with `%PDF` (or valid PDF data), it returns the response body directly.

2. **Fault Tolerance & Graceful Fallback (From Observation 1, 2, 7):**
   - If the microservice is offline, times out (after 5s), returns 4xx/5xx, or fails network resolution, `CarteiraPdfService` catches `Throwable` and immediately triggers Tier 2 fallback: local `Dompdf\Dompdf`.
   - If `Dompdf` generates the PDF stream, it is returned without user interruption.
   - If `Dompdf` fails or is not installed, it falls back to Tier 3 standard `%PDF-1.4` text stream.

3. **Route & Controller Architecture (From Observation 1, 4, 5, 6):**
   - Adding `Route::get('/carteira/pdf', [CarteiraPdfController::class, 'download'])->name('carteira.pdf');` in `routes/web.php` solves the 404 gap for `Carteira.vue`.
   - In `CarteiraPdfController::download(Request $request)`:
     - If authenticated as Egresso: `$user->egresso`.
     - If authenticated as Gestor/Técnico/Suporte: supports `?egresso_id=X` query param, falling back to `Egresso::with('municipio')->first()`.
     - If unauthenticated (logged out): safely queries `Egresso::with('municipio')->first()` or mock Egresso object if database is empty.
     - Returns `response($pdfContent, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="carteira_digital_' . $id . '.pdf"'])`.

---

## 3. Caveats

- **Microservice Availability in Test/Sandbox:** In environments where `http://localhost:8080` is not actively running a Document Generator daemon, the fallback to `Dompdf` will execute automatically and seamlessly, satisfying all unit and E2E requirements.
- **Dompdf Remote Images:** Blade template embeds QR Code directly via Base64 Data URI (`data:image/svg+xml;base64,...`), which prevents SSRF and requires no remote network fetching during Dompdf execution.
- No caveats regarding database or model compatibility.

---

## 4. Conclusion

1. **Architecture Is Ready for Implementation:** All required components (Blade template `pdf.carteira_digital`, QR cryptographic signing `QrCodeSecurityService`, LGPD masking `LgpdSecurityService`, and model accessors `Egresso`) are fully defined and tested.
2. **Missing Pieces Identified:**
   - Need to update `CarteiraPdfService::generatePdf()` to query `http://localhost:8080` with API Key `token-secreto-dev` before falling back to `Dompdf`.
   - Need to create `app/Http/Controllers/CarteiraPdfController.php` (or add method to controller).
   - Need to register `Route::get('/carteira/pdf', ...)` in `routes/web.php`.
   - Need to add config entries in `config/services.php` and `.env.example`.
3. **No Breaking Changes:** The existing public QR verification pipeline (`/validar-carteira/{token}`) and cryptographic signatures remain 100% intact and backward compatible.

---

## 5. Verification Method

1. **Controller & Route Verification:**
   - Request `GET /carteira/pdf` (logged out) -> Expect HTTP 200 with `Content-Type: application/pdf` and `%PDF` magic bytes.
   - Request `GET /carteira/pdf` (logged in as Egresso / Gestor / Suporte) -> Expect HTTP 200 with matching Egresso data.
2. **Microservice Integration & Fallback Verification:**
   - Run unit test mocking HTTP 200 from `http://localhost:8080` -> Expect microservice response returned.
   - Run unit test simulating HTTP timeout / Connection Refused on `http://localhost:8080` -> Expect seamless fallback to Dompdf returning valid `%PDF` stream.
3. **Automated Test Suites:**
   - Execute: `pytest tests_e2e/tier1_features/test_f10_f12_carteira_qr.py`
   - Execute: `pytest tests_e2e/tier3_combinations/test_pdf_qr_validation_chain.py`
   - Execute: `php artisan test --filter=Carteira`
