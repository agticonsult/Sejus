## 2026-08-18T13:10:10Z
You are Worker M2 for the Conecta Egresso project.
Your Working Directory: d:\Agile\projeto dia 18\.agents\worker_m2
Original Request File: d:\Agile\projeto dia 18\.agents\ORIGINAL_REQUEST.md
Project Document: d:\Agile\projeto dia 18\PROJECT.md
Survey Report: d:\Agile\projeto dia 18\.agents\explorer_survey_2\handoff.md

MANDATORY INTEGRITY WARNING:
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

Mission: Implement Milestone 2 - PDF Generation with Document Generator API & Graceful Dompdf Fallback.
1. Read ORIGINAL_REQUEST.md, PROJECT.md, and explorer_survey_2/handoff.md.
2. Update `config/services.php` to configure `document_generator`:
   - `'url' => env('DOCUMENT_GENERATOR_URL', 'http://localhost:8080')`
   - `'key' => env('DOCUMENT_GENERATOR_KEY', 'token-secreto-dev')`
3. Update `app/Services/CarteiraPdfService.php`:
   - In `generatePdf(object $egresso)`:
     - Render HTML using `$this->renderHtml($egresso)`.
     - Attempt to send POST request via `Illuminate\Support\Facades\Http` to Document Generator API:
       - URL: `config('services.document_generator.url') . '/generate'` (or `http://localhost:8080/generate` / root endpoint)
       - Header: `X-API-Key: config('services.document_generator.key')`
       - Timeout: 5 seconds
       - Body: `['html' => $html, 'format' => 'A4', 'orientation' => 'portrait']`
     - If response is 200 and binary content starts with `%PDF` (or contains valid PDF stream), return the body.
     - Catch any `\Throwable` (ConnectionException, timeout, 500 status): log warning and gracefully fall back to local `Dompdf\Dompdf`.
     - If Dompdf also throws or is unavailable, fallback to Tier 3 standard text PDF.
4. Create `app/Http/Controllers/CarteiraPdfController.php`:
   - `download(Request $request, CarteiraPdfService $pdfService)`:
     - Check if user is authenticated and has an associated Egresso model, or if `egresso_id` query param is provided and user is staff (gestor/tecnico/suporte).
     - If unauthenticated (logged out / guest test), gracefully fetch the first Egresso with `municipio` relation (`Egresso::with('municipio')->first()`), or build a realistic mock Egresso if table is empty.
     - Call `$pdf = $pdfService->generatePdf($egresso);`
     - Return `response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="carteira-digital-sejus.pdf"', 'Cache-Control' => 'no-cache, private']);`
5. Register route in `routes/web.php`:
   - `Route::get('/carteira/pdf', [CarteiraPdfController::class, 'download'])->name('carteira.pdf');`
6. Run tests to verify:
   - Run PHP unit/verification tests (e.g. `php tests/run_verification.php` or `php tests/run_m3_verification.php` or `php artisan test`).
   - Test PDF generation both with mocked HTTP and with Dompdf fallback.
7. Write comprehensive handoff to `d:\Agile\projeto dia 18\.agents\worker_m2\handoff.md` and notify parent orchestrator via send_message.
