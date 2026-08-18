# Especificação Técnica de Implementação: Milestone M2
## Segurança LGPD, Serviços Core, Seeders do Espírito Santo & Suíte de Testes
### Plataforma CONECTA EGRESSO — SEJUS / Governo do Estado do Espírito Santo

**Documento:** Especificação Técnica Detalhada de Engenharia e Arquitetura de Software  
**Milestone:** M2 — Database Models, Migrations, Seeds & Core Services  
**Autor:** Explorer 3 (`explorer_3`)  
**Data:** 17 de Agosto de 2026  
**Status:** Aprovado para Implementação  

---

## 1. Sumário Executivo & Delimitação do Escopo

O Milestone M2 do projeto **CONECTA EGRESSO** estabelece a fundação criptográfica, as regras de negócio de segurança, os serviços essenciais de emissão e validação documental, a base de dados realista do ecossistema capixaba e a suíte abrangente de testes automatizados (Pest/PHPUnit).

Esta especificação aborda minuciosamente os seguintes pilares:
1. **Serviço de Segurança e Indexação Cega LGPD (`LgpdSecurityService`):**
   - Hashing determinístico por Blind Index (`HMAC-SHA256`) com chave *pepper* dedicada e isolada para consultas de alta performance em PII (CPF).
   - Criptografia simétrica em repouso (`AES-256-CBC` / `AES-256-GCM`) para dados sensíveis biográficos e psicossociais.
   - Algoritmo de validação de CPF e funções de mascaramento institucional (`***.482.910-**`).
2. **Trilha de Auditoria Imutável no PostgreSQL & Encadeamento Criptográfico (`AuditService`):**
   - Regras nativas PostgreSQL (`CREATE RULE ... DO INSTEAD NOTHING`) que tornam a tabela `prontuario_audit_logs` estritamente indelével (bloqueio de `UPDATE` e `DELETE`).
   - Encadeamento sequencial de blocos criptográficos (*Hash Chaining* SHA-256), vinculando `previous_hash`, `user_id`, `prontuario_id`, `acao`, `payload` canônico e `timestamp`.
   - Rotina automatizada de verificação de integridade da cadeia forense (`verifyChainIntegrity()`).
3. **Serviço de Geração de Carteira Digital em PDF (`CarteiraPdfService`):**
   - Compilação de PDF oficial (via Dompdf) com o leiaute institucional da SEJUS/ES, crachá funcional frente/verso, brasão oficial em alta definição, avatar/foto com selo *"✓ Verificado"*, campos normatizados e selo de autenticidade estadual (Lei Complementar nº 182/2021).
4. **Serviço de Assinatura e Validação Criptográfica de QR Code (`QrCodeSecurityService` & `CarteiraValidationController`):**
   - Geração de payload canônico assinado com `HMAC-SHA256`.
   - Conversão em código QR vetorial (SVG / Data-URI) para embutimento direto no PDF e frontend.
   - Endpoint público de validação (`/validar-carteira/{token}`) com checagem de assinatura, validade temporal e registro automático de auditoria.
5. **Seeders Realistas dos 78 Municípios e Ecossistema do Espírito Santo:**
   - Carga oficial de todos os 78 municípios capixabas com códigos IBGE, microrregiões do IJSN, coordenadas geográficas e distinção entre os 4 polos presenciais e os 74 municípios remotos.
   - Perfis de acesso e usuários de demonstração (Gestor SEJUS, Técnico do Escritório Social, Egressos).
   - Prontuários únicos com linhas do tempo realistas.
   - Vagas de emprego com ações afirmativas (*"Empresa Amiga da Reintegração"* / *"Inclusão no Campo"*).
   - Cursos de capacitação profissional (SENAI, IFES, ADERES NossoCrédito).
   - Rede socioassistencial territorial (CRAS, CREAS, SINE, CAPS) distribuída pelo território estadual.
6. **Suíte Completa de Testes Automatizados (Pest / PHPUnit):**
   - Testes Unitários de Criptografia, Hash Chaining, Assinatura de QR Code e Renderização de PDF.
   - Testes de Integração e Funcionalidades (Migrations, Imutabilidade de Regras SQL, Scopes, Rota Pública de Validação e Execução de Seeders).

---

## 2. Especificação Arquitetural dos Componentes

```
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                                   CORE SECURITY & SERVICES                             │
├───────────────────────────────┬───────────────────────────────┬────────────────────────┤
│     LgpdSecurityService       │         AuditService          │  QrCodeSecurityService │
│  - HMAC-SHA256 (Pepper Key)   │  - PostgreSQL Immutability    │  - HMAC-SHA256 Signing │
│  - AES-256 PII Encryption     │  - SHA-256 Hash Chaining      │  - SVG QR Code Render  │
│  - CPF Validate / Mask / Norm │  - Forensic Audit Checker     │  - Public Token Verify │
└───────────────┬───────────────┴───────────────┬───────────────┴────────────┬───────────┘
                │                               │                            │
                ▼                               ▼                            ▼
┌───────────────────────────────┐ ┌───────────────────────────┐ ┌────────────────────────┐
│    Modelos Eloquent & BD      │ │    CarteiraPdfService     │ │   Controller Público   │
│  - Users, Egressos            │ │  - Dompdf Official Layout │ │  - /validar-carteira   │
│  - Prontuarios, AuditLogs     │ │  - Security Seal & QR     │ │  - Instant Integrity  │
└───────────────────────────────┘ └───────────────────────────┘ └────────────────────────┘
```

---

### 2.1 LGPD Blind Index & AES-256 PII Encryption (`LgpdSecurityService`)

#### Motivação e Mecanismo Técnico
O armazenamento de CPF e dados biográficos em texto claro viola as diretrizes de minimização e segurança da LGPD (Art. 6º, VII e X). Por outro lado, a criptografia simétrica não determinística (AES-256-CBC/GCM com IV/nonce aleatório) inviabiliza consultas indexadas diretas no banco de dados (`WHERE cpf = ?`).

A arquitetura resolve este desafio através do padrão **Blind Index (Índice Cego)**:
- **Coluna `cpf_encrypted` (`text`):** Contém o CPF criptografado com chave de aplicação padrão `APP_KEY` via AES-256.
- **Coluna `cpf_hash` (`char(64)`):** Contém o hash determinístico calculado por `HMAC-SHA256(cpf_normalizado, LGPD_PEPPER_KEY)`.
- A chave *pepper* (`LGPD_PEPPER_KEY`) é segregada da chave principal da aplicação, inviabilizando ataques de dicionário (*rainbow tables*) mesmo em caso de vazamento isolado do banco de dados.

#### Estrutura da Classe `App\Services\LgpdSecurityService`

```php
namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;

class LgpdSecurityService
{
    protected string $pepperKey;

    public function __construct(?string $pepperKey = null)
    {
        $this->pepperKey = $pepperKey ?? config('services.lgpd.pepper', env('LGPD_PEPPER_KEY', 'default-sejus-lgpd-pepper-secret-key-2026'));
    }

    /**
     * Normaliza CPF removendo caracteres não numéricos.
     */
    public function normalizeCpf(string $cpf): string
    {
        $digits = preg_replace('/\D/', '', $cpf);
        if (strlen($digits) !== 11) {
            throw new InvalidArgumentException("CPF inválido: deve conter exatamente 11 dígitos numéricos.");
        }
        return $digits;
    }

    /**
     * Validação algorítmica de dígitos verificadores de CPF brasileiro.
     */
    public function validateCpf(string $cpf): bool
    {
        $cpf = preg_replace('/\D/', '', $cpf);
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($c = 0; $c < $t; $c++) {
                $d += (int) $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ((int) $cpf[$c] !== $d) {
                return false;
            }
        }
        return true;
    }

    /**
     * Gera o Blind Index criptográfico para indexação segura no PostgreSQL.
     */
    public function generateBlindIndex(string $rawCpf): string
    {
        $cleanCpf = $this->normalizeCpf($rawCpf);
        return hash_hmac('sha256', $cleanCpf, $this->pepperKey);
    }

    /**
     * Criptografa campo sensível via AES-256.
     */
    public function encryptField(string $plaintext): string
    {
        return Crypt::encryptString($plaintext);
    }

    /**
     * Descriptografa campo sensível via AES-256.
     */
    public function decryptField(string $ciphertext): string
    {
        return Crypt::decryptString($ciphertext);
    }

    /**
     * Formata CPF no padrão institucional com máscara LGPD (ex: ***.482.910-**).
     */
    public function maskCpf(string $cpf): string
    {
        $clean = preg_replace('/\D/', '', $cpf);
        if (strlen($clean) !== 11) {
            return '***.***.***-**';
        }
        return sprintf('***.%s.%s-**', substr($clean, 3, 3), substr($clean, 6, 3));
    }

    /**
     * Mascara nome completo preservando primeiro e último nome.
     */
    public function maskName(string $name): string
    {
        $parts = explode(' ', trim($name));
        if (count($parts) <= 1) {
            return $name;
        }
        $first = array_shift($parts);
        $last = array_pop($parts);
        $middle = array_map(fn($p) => mb_substr($p, 0, 1) . '.', $parts);
        return trim($first . ' ' . implode(' ', $middle) . ' ' . $last);
    }
}
```

---

### 2.2 Trilha de Auditoria Imutável & Encadeamento Criptográfico (`AuditService`)

#### 1. Regras Nativas PostgreSQL na Migration (`prontuario_audit_logs`)
Na migration `2026_08_17_000007_create_prontuario_audit_logs_table.php`, a integridade indelével é garantida no nível do motor relacional através de PostgreSQL Rules:

```sql
-- Bloqueio absoluto de UPDATE
CREATE RULE prontuario_audit_logs_no_update AS 
ON UPDATE TO prontuario_audit_logs 
DO INSTEAD NOTHING;

-- Bloqueio absoluto de DELETE
CREATE RULE prontuario_audit_logs_no_delete AS 
ON DELETE TO prontuario_audit_logs 
DO INSTEAD NOTHING;
```

#### 2. Fórmula do Encadeamento Criptográfico (*Hash Chaining*)
Cada registro possui um elo criptográfico com o registro imediatamente anterior:

$$\text{current\_hash} = \text{SHA256}(\text{previous\_hash} \parallel \text{user\_id} \parallel \text{prontuario\_id} \parallel \text{acao} \parallel \text{canonical\_payload} \parallel \text{timestamp})$$

- Para o primeiro registro do sistema (*Genesis Record*), $\text{previous\_hash} = \text{'0'}^{64}$ (64 zeros).
- O payload é serializado em JSON Canônico (`JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION`).

#### Estrutura da Classe `App\Services\AuditService`

```php
namespace App\Services;

use App\Models\ProntuarioAuditLog;
use Illuminate\Support\Facades\DB;

class AuditService
{
    public const GENESIS_HASH = '0000000000000000000000000000000000000000000000000000000000000000';

    /**
     * Grava um registro de auditoria imutável encadeado criptograficamente.
     */
    public function log(
        string $acao,
        ?string $prontuarioId = null,
        ?string $userId = null,
        array $payload = [],
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): ProntuarioAuditLog {
        return DB::transaction(function () use ($acao, $prontuarioId, $userId, $payload, $ipAddress, $userAgent) {
            // Lock pessimista no último registro para evitar condições de corrida na cadeia
            $lastLog = ProntuarioAuditLog::orderBy('id', 'desc')->lockForUpdate()->first();
            $previousHash = $lastLog ? $lastLog->current_hash : self::GENESIS_HASH;

            $timestamp = now()->toIso8601String();
            $canonicalPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $dataToHash = implode('|', [
                $previousHash,
                $userId ?? 'ANONYMOUS',
                $prontuarioId ?? 'GLOBAL',
                $acao,
                $canonicalPayload,
                $timestamp
            ]);

            $currentHash = hash('sha256', $dataToHash);

            return ProntuarioAuditLog::create([
                'prontuario_id' => $prontuarioId,
                'user_id' => $userId,
                'acao' => $acao,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'previous_hash' => $previousHash,
                'current_hash' => $currentHash,
                'payload' => $payload,
                'created_at' => $timestamp,
            ]);
        });
    }

    /**
     * Percorre sequencialmente toda a tabela de auditoria e valida a integridade da cadeia.
     */
    public function verifyChainIntegrity(): array
    {
        $logs = ProntuarioAuditLog::orderBy('id', 'asc')->get();
        $expectedPreviousHash = self::GENESIS_HASH;
        $verifiedCount = 0;

        foreach ($logs as $log) {
            // 1. Verifica se o previous_hash corresponde ao elo anterior
            if ($log->previous_hash !== $expectedPreviousHash) {
                return [
                    'valid' => false,
                    'broken_record_id' => $log->id,
                    'reason' => "Elo quebrado: previous_hash [{$log->previous_hash}] divergente do hash anterior esperado [{$expectedPreviousHash}].",
                    'verified_count' => $verifiedCount,
                ];
            }

            // 2. Recalcula o hash do registro atual
            $canonicalPayload = json_encode($log->payload ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $timestamp = $log->created_at->toIso8601String();

            $dataToHash = implode('|', [
                $log->previous_hash,
                $log->user_id ?? 'ANONYMOUS',
                $log->prontuario_id ?? 'GLOBAL',
                $log->acao,
                $canonicalPayload,
                $timestamp
            ]);

            $calculatedHash = hash('sha256', $dataToHash);

            if (!hash_equals($log->current_hash, $calculatedHash)) {
                return [
                    'valid' => false,
                    'broken_record_id' => $log->id,
                    'reason' => "Hash adulterado: current_hash gravado [{$log->current_hash}] difere do recalculado [{$calculatedHash}].",
                    'verified_count' => $verifiedCount,
                ];
            }

            $expectedPreviousHash = $log->current_hash;
            $verifiedCount++;
        }

        return [
            'valid' => true,
            'total_verified' => $verifiedCount,
            'latest_hash' => $expectedPreviousHash,
            'message' => 'Trilha de auditoria 100% íntegra e não violada.',
        ];
    }
}
```

---

### 2.3 Carteira Digital Dompdf Service (`CarteiraPdfService`)

#### Requisitos Visuais e Funcionais do PDF Oficial
- **Formato:** Página A4 ou Cartão Funcional CR80 (85.6mm x 53.98mm) de alta resolução.
- **Cabeçalho:**
  - Brasão Oficial do Estado do Espírito Santo (embutido como SVG em Base64 para total independência de rede no contêiner).
  - Título: *"ESTADO DO ESPÍRITO SANTO"* / *"SECRETARIA DE ESTADO DA JUSTIÇA - SEJUS"*.
  - Subtítulo: *"ESCRITÓRIO SOCIAL DIGITAL • CARTEIRA DIGITAL DO EGRESSO"*.
- **Corpo da Carteira:**
  - Foto 3x4 do Egresso ou Box Avatar estilizado com iniciais e tarja *"✓ Verificado"*.
  - Nome Completo (em caixa alta, destaque).
  - CPF Mascarado: `***.482.910-**`.
  - Número de Registro SEJUS: `ES-2026-XXXXXX`.
  - Município de Residência: `São Mateus / ES`.
  - Data de Emissão e Validade (1 ano após emissão).
- **Área de Segurança & QR Code:**
  - QR Code vetorial gerado pelo `QrCodeSecurityService` contendo a URL pública com token criptográfico.
  - Código de Validação: Primeiros 16 caracteres em blocos `XXXX-XXXX-XXXX-XXXX`.
  - Selo Oficial: *"Documento Oficial Digital • Validez em todo o Território Capixaba (Lei 182/2021)"*.

#### Estrutura da Classe `App\Services\CarteiraPdfService`

```php
namespace App\Services;

use App\Models\Egresso;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\View;

class CarteiraPdfService
{
    protected QrCodeSecurityService $qrService;
    protected LgpdSecurityService $lgpdService;

    public function __construct(QrCodeSecurityService $qrService, LgpdSecurityService $lgpdService)
    {
        $this->qrService = $qrService;
        $this->lgpdService = $lgpdService;
    }

    /**
     * Renderiza o HTML completo da Carteira Digital com QR Code vetorial embutido.
     */
    public function renderHtml(Egresso $egresso): string
    {
        $payload = $this->qrService->generatePayload($egresso);
        $token = $this->qrService->generateToken($payload);
        $validationUrl = $this->qrService->getValidationUrl($token);
        $qrCodeSvgDataUri = $this->qrService->generateQrCodeDataUri($validationUrl);

        $authCodeBlock = strtoupper(implode('-', str_split(substr($this->qrService->signPayload($payload), 0, 16), 4)));

        return View::make('pdf.carteira_digital', [
            'egresso' => $egresso,
            'nome' => mb_strtoupper($egresso->nome_completo),
            'cpfMasked' => $this->lgpdService->maskCpf($egresso->cpf ?? '00000000000'),
            'registroSejus' => $egresso->registro_sejus ?? ('ES-2026-' . str_pad($egresso->id, 6, '0', STR_PAD_LEFT)),
            'municipio' => $egresso->municipio?->nome ?? 'Espírito Santo',
            'dataEmissao' => now()->format('d/m/Y'),
            'dataValidade' => now()->addYear()->format('d/m/Y'),
            'qrCodeDataUri' => $qrCodeSvgDataUri,
            'authCode' => $authCodeBlock,
            'validationUrl' => $validationUrl,
        ])->render();
    }

    /**
     * Compila o documento em binário PDF via Dompdf.
     */
    public function generatePdf(Egresso $egresso): string
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false); // Totalmente auto-contido para segurança
        $options->set('defaultFont', 'Helvetica');
        $options->set('dpi', 150);

        $dompdf = new Dompdf($options);
        $html = $this->renderHtml($egresso);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
```

---

### 2.4 Serviço de QR Code Criptográfico & Validação Pública (`QrCodeSecurityService`)

#### Mecanismo de Assinatura e Validação Criptográfica
1. O payload do documento é normalizado em formato estruturado:
   ```json
   {
     "doc_id": "a1b2c3d4-e5f6-7a8b-9c0d-1e2f3a4b5c6d",
     "registro_sejus": "ES-2026-948102",
     "cpf_masked": "***.192.830-**",
     "nome": "LUCAS SANTOS",
     "municipio": "São Mateus / ES",
     "issued_at": "2026-08-17T12:00:00Z",
     "expires_at": "2027-08-17T12:00:00Z",
     "legal_basis": "Lei Complementar Estadual nº 182/2021"
   }
   ```
2. O payload é assinado com a chave `CARTEIRA_SIGNING_KEY` utilizando `HMAC-SHA256`.
3. O token compactado codificado em Base64 URL-Safe é anexado à URL pública de verificação:
   `https://conectaegresso.es.gov.br/validar-carteira/{token}`
4. Na validação pública (`CarteiraValidationController`):
   - Decodifica-se o token;
   - Recalcula-se a assinatura HMAC sobre o payload;
   - Realiza-se a comparação em tempo constante (`hash_equals`);
   - Verifica-se se `now() <= expires_at`;
   - Registra-se a tentativa de validação no `AuditService` (`acao = 'VALIDATE_QR'`).

#### Estrutura da Classe `App\Services\QrCodeSecurityService`

```php
namespace App\Services;

use App\Models\Egresso;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use InvalidArgumentException;

class QrCodeSecurityService
{
    protected string $signingKey;
    protected LgpdSecurityService $lgpdService;

    public function __construct(LgpdSecurityService $lgpdService, ?string $signingKey = null)
    {
        $this->lgpdService = $lgpdService;
        $this->signingKey = $signingKey ?? config('services.carteira.signing_key', env('CARTEIRA_SIGNING_KEY', 'sejus-conecta-egresso-qr-signing-key-2026-sha256'));
    }

    public function generatePayload(Egresso $egresso): array
    {
        return [
            'doc_id' => (string) $egresso->id,
            'registro_sejus' => $egresso->registro_sejus ?? ('ES-2026-' . str_pad($egresso->id, 6, '0', STR_PAD_LEFT)),
            'cpf_masked' => $this->lgpdService->maskCpf($egresso->cpf ?? '00000000000'),
            'nome' => mb_strtoupper($egresso->nome_completo),
            'municipio' => $egresso->municipio?->nome ?? 'Espírito Santo',
            'issued_at' => now()->toIso8601String(),
            'expires_at' => now()->addYear()->toIso8601String(),
            'legal_basis' => 'Lei Complementar Estadual nº 182/2021 - SEJUS/ES',
        ];
    }

    public function signPayload(array $payload): string
    {
        ksort($payload);
        $canonicalJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return hash_hmac('sha256', $canonicalJson, $this->signingKey);
    }

    public function generateToken(array $payload): string
    {
        $signature = $this->signPayload($payload);
        $data = ['p' => $payload, 's' => $signature];
        return rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
    }

    public function verifyToken(string $token): array
    {
        $decodedJson = base64_decode(strtr($token, '-_', '+/'));
        if (!$decodedJson) {
            return ['valid' => false, 'status' => 'MALFORMED_TOKEN', 'message' => 'Token de verificação inválido ou corrompido.'];
        }

        $data = json_decode($decodedJson, true);
        if (!isset($data['p'], $data['s']) || !is_array($data['p'])) {
            return ['valid' => false, 'status' => 'INVALID_STRUCTURE', 'message' => 'Estrutura do documento inválida.'];
        }

        $payload = $data['p'];
        $providedSignature = $data['s'];
        $calculatedSignature = $this->signPayload($payload);

        // Comparação segura contra Timing Attacks
        if (!hash_equals($calculatedSignature, $providedSignature)) {
            return [
                'valid' => false,
                'status' => 'TAMPERED_DOCUMENT',
                'message' => 'DOCUMENTO INVÁLIDO OU ADULTERADO! A assinatura criptográfica não confere.',
            ];
        }

        // Checagem de validade temporal
        if (isset($payload['expires_at']) && now()->isAfter($payload['expires_at'])) {
            return [
                'valid' => false,
                'status' => 'EXPIRED_DOCUMENT',
                'message' => 'DOCUMENTO EXPIRADO. A validade de 1 ano foi ultrapassada.',
                'payload' => $payload,
            ];
        }

        return [
            'valid' => true,
            'status' => 'VALID_DOCUMENT',
            'message' => 'DOCUMENTO OFICIAL AUTÊNTICO E HOMOLOGADO PELA SEJUS/ES.',
            'payload' => $payload,
        ];
    }

    public function generateQrCodeSvg(string $content): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(200, 2),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        return $writer->writeString($content);
    }

    public function generateQrCodeDataUri(string $content): string
    {
        $svg = $this->generateQrCodeSvg($content);
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    public function getValidationUrl(string $token): string
    {
        return url('/validar-carteira/' . $token);
    }
}
```

---

### 2.5 Seeders Realistas do Ecossistema do Espírito Santo

#### 1. Seeder dos 78 Municípios Capixabas (`MunicipiosEsSeeder`)
Distribuição exata dos 78 municípios do Estado do Espírito Santo com códigos IBGE oficiais, coordenadas geográficas e separação entre polos físicos e atendimento remoto:

| # | Município | Código IBGE | Microrregião | Tipo de Atendimento |
|---|---|---|---|---|
| 1 | **Vitória (Sede)** | 3205309 | Metropolitana | **Escritório Social Físico (Sede)** |
| 2 | **Vila Velha** | 3205200 | Metropolitana | **Escritório Social Físico** |
| 3 | **Serra** | 3205002 | Metropolitana | **Escritório Social Físico** |
| 4 | **Cariacica** | 3201308 | Metropolitana | **Escritório Social Físico** |
| 5 | **Linhares** | 3203205 | Rio Doce | Atendimento Remoto Conecta Egresso |
| 6 | **Cachoeiro de Itapemirim** | 3201209 | Central Sul | Atendimento Remoto Conecta Egresso |
| 7 | **Colatina** | 3201506 | Centro-Oeste | Atendimento Remoto Conecta Egresso |
| 8 | **São Mateus** | 3204906 | Nordeste | Atendimento Remoto Conecta Egresso |
| 9 | **Aracruz** | 3200607 | Rio Doce | Atendimento Remoto Conecta Egresso |
| 10 | **Guarapari** | 3202405 | Metropolitana | Atendimento Remoto Conecta Egresso |
| 11 | **Viana** | 3205101 | Metropolitana | Atendimento Remoto Conecta Egresso |
| 12-78 | *Demais 67 Municípios* | *Oficiais IBGE* | *IJSN* | *Atendimento Remoto Conecta Egresso* |

#### 2. Usuários Demonstrativos & Perfis de Acesso (`UsersAndRolesSeeder`)
Configuração de contas prontas para uso e demonstração em conformidade com o protótipo:
1. **Gestor Estadual SEJUS:**
   - Nome: `Carlos Eduardo Silva`
   - E-mail: `gestor@sejus.es.gov.br`
   - Cargo/Setor: Subsecretaria de Reintegração Social
   - Papel: `gestor`
   - CPF: `111.222.333-44` (criptografado + blind index)
2. **Técnico do Escritório Social:**
   - Nome: `Dra. Márcia Oliveira`
   - E-mail: `marcia.oliveira@sejus.es.gov.br`
   - Cargo: Assistente Social (CRESS 4891/ES)
   - Papel: `tecnico`
   - CPF: `555.666.777-88`
3. **Egresso 1 (Interior - Remoto):**
   - Nome: `Lucas Santos`
   - E-mail: `lucas.santos@cidadao.es.gov.br`
   - Município: `São Mateus / ES`
   - Papel: `egresso`
   - CPF: `192.830.456-78` (Registro SEJUS: `ES-2026-948102`)
4. **Egresso 2 (Metropolitano):**
   - Nome: `Roberto Fonseca da Silva`
   - E-mail: `roberto.fonseca@cidadao.es.gov.br`
   - Município: `Vitória / ES`
   - Papel: `egresso`
   - CPF: `482.910.374-92` (Registro SEJUS: `ES-2026-104928`)

#### 3. Vagas de Emprego & Cursos de Capacitação (`OportunidadesSeeder`)
- **Vagas de Emprego Inclusivas:**
  1. *Auxiliar de Logística e Carga* — Porto de Tubarão / Parceiro SEJUS (Vitória/Serra) — R$ 2.100,00 + Benefícios.
  2. *Operador de Máquinas Agrícolas* — Cooperativa Agropecuária do ES (Colatina/São Mateus) — R$ 2.800,00.
  3. *Oficial de Construção Civil* — Construtora Capixaba S.A. (Vila Velha/Cariacica) — R$ 2.450,00.
  4. *Montador Industrial* — Estaleiro Jurong Aracruz (Aracruz) — R$ 3.200,00.
  5. *Auxiliar de Serviços Gerais e Limpeza* — Shopping Vitória / Parceiro SEJUS (Vitória) — R$ 1.680,00.
  6. *Atendente de Padaria e Confeitaria* — Supermercados Capixaba (Cachoeiro de Itapemirim) — R$ 1.750,00.
- **Cursos Profissionalizantes:**
  1. *Capacitação em Solda Industrial* — SENAI / Findes / SEJUS (Linhares/Remoto) — 160h (Bolsa R$ 400,00/mês).
  2. *Letramento Digital & Informática Básica* — IFES EAD (100% Online via celular) — 60h.
  3. *Empreendedorismo e Microcrédito NossoCrédito* — ADERES / Banestes / SEJUS (Remoto ES) — 40h.
  4. *Instalações Elétricas Prediais* — SENAI Vitória (Vitória) — 120h.
  5. *Mecânica Básica de Motocicletas* — SENAI Colatina (Colatina) — 100h.

#### 4. Rede Socioassistencial Territorial (`RedeApoioSeeder`)
Equipamentos públicos cadastrados com latitude, longitude, endereço e serviços:
- **Vitória:** CRAS Central, CREAS Bento Ferreira, SINE Vitória, CAPS III Ilha de Santa Maria, Casa do Cidadão.
- **Vila Velha:** CRAS Centro, SINE Vila Velha, CAPS II Centro.
- **Serra:** CRAS Laranjeiras, SINE Serra, CAPS i.
- **Cariacica:** CRAS Campo Grande, SINE Cariacica, CAPS AD III.
- **Linhares:** CRAS Aviso, SINE Linhares, CAPS II.
- **São Mateus:** CRAS Guriri, SINE São Mateus, CAPS I.
- **Colatina:** CRAS São Silvano, SINE Colatina, CAPS II.
- **Cachoeiro:** CRAS Alto União, SINE Cachoeiro, CAPS II.
- **Aracruz, Guarapari, Viana, Nova Venécia, Barra de São Francisco, Afonso Cláudio, Alegre, etc.**

---

## 3. Especificação da Suíte de Testes (Pest / PHPUnit)

A suíte de testes do Milestone M2 foi desenhada para garantir 100% de cobertura nos componentes críticos de segurança, integridade e modelos.

```
tests/
├── Unit/
│   ├── Services/
│   │   ├── LgpdSecurityServiceTest.php       # Blind Index, AES-256, CPF Mask/Validation
│   │   ├── AuditServiceTest.php             # Hash Chaining, Integrity Verification, Tamper Detection
│   │   ├── QrCodeSecurityServiceTest.php    # Canonical Payload, HMAC Signing, Token Verification
│   │   └── CarteiraPdfServiceTest.php       # Dompdf binary compilation, HTML markup validation
├── Feature/
│   ├── DatabaseMigrationsAndModelsTest.php  # 12 Migrations, Eloquent relations & casts
│   ├── AuditLogImmutabilityTest.php         # PostgreSQL Rules (UPDATE/DELETE DO INSTEAD NOTHING)
│   ├── BlindIndexSearchTest.php             # Exact search on blind index without leaking plaintext
│   ├── CarteiraValidationRouteTest.php      # Public endpoint GET /validar-carteira/{token}
│   └── SeedersExecutionTest.php             # Full seed verification (78 munis, 4 physical, demo users)
```

### Casos de Teste Essenciais Especificados

1. **`LgpdSecurityServiceTest`:**
   - `test_normalizes_cpf_stripping_non_numeric_characters`: assegura que `123.456.789-00` vira `12345678900`.
   - `test_validates_valid_and_invalid_cpfs`: valida CPFs reais de teste e rejeita CPFs com dígitos inválidos ou repetidos.
   - `test_generates_deterministic_blind_index`: assegura que o mesmo CPF produz sempre o mesmo hash SHA256 com a mesma chave pepper.
   - `test_different_pepper_produces_different_hash`: verifica isolamento da chave secreta.
   - `test_aes_256_encryption_and_decryption`: garante que `decrypt(encrypt($data)) === $data` e o texto cifrado não expõe o plaintext.

2. **`AuditServiceTest`:**
   - `test_creates_genesis_audit_log_with_genesis_hash`: primeiro registro contém `previous_hash` com 64 zeros.
   - `test_sequential_audit_logs_form_unbroken_hash_chain`: registro 2 referencia `current_hash` do registro 1.
   - `test_verify_chain_integrity_passes_on_valid_chain`: cadeia não modificada retorna `valid: true`.
   - `test_verify_chain_integrity_detects_tampered_payload`: alteração manual de 1 caractere em `payload` faz `verifyChainIntegrity` falhar apontando o ID exato.
   - `test_verify_chain_integrity_detects_broken_hash_link`: alteração no `previous_hash` é detectada imediatamente.

3. **`QrCodeSecurityServiceTest` & `CarteiraValidationRouteTest`:**
   - `test_generates_signed_qr_payload_with_hmac_sha256`: gera token com assinatura válida.
   - `test_token_verification_succeeds_for_genuine_token`: status `VALID_DOCUMENT`.
   - `test_token_verification_fails_if_payload_tampered`: alteração de campo gera `TAMPERED_DOCUMENT`.
   - `test_token_verification_detects_expired_document`: expirado após 1 ano gera `EXPIRED_DOCUMENT`.
   - `test_public_validation_route_renders_authenticated_badge`: rota pública retorna HTTP 200 com selo verde para credencial válida.
   - `test_public_validation_creates_immutable_audit_log`: validação pública gera log na tabela `prontuario_audit_logs`.

4. **`AuditLogImmutabilityTest`:**
   - `test_postgresql_rule_prevents_update_on_audit_logs`: comando SQL `UPDATE prontuario_audit_logs SET acao = 'TAMPERED'` não altera o banco (preserva o valor original sem erro ou com bloqueio).
   - `test_postgresql_rule_prevents_delete_on_audit_logs`: comando SQL `DELETE FROM prontuario_audit_logs` não exclui linhas.

5. **`SeedersExecutionTest`:**
   - `test_all_seeders_run_successfully`: comando `Artisan::call('db:seed')` completa com código 0.
   - `test_municipios_seeder_populates_exactly_78_municipalities`: contagem exata de 78 registros.
   - `test_municipios_seeder_flags_exactly_4_physical_offices`: Vitória, Vila Velha, Serra e Cariacica com `tem_escritorio_fisico = true` e 74 com `false`.
   - `test_demo_users_created_with_correct_roles`: Gestor, Técnico e Egressos presentes e autenticáveis.

---

## 4. Matriz de Rastreabilidade e Conformidade (Critérios de Aceite M2)

| Componente | Critério de Aceite / Requisito | Arquivo de Destino | Status |
|---|---|---|---|
| Blind Index & AES-256 | R1, TR 3.1 `c` (LGPD Art. 6º) | `app/Services/LgpdSecurityService.php` | **Especificado 100%** |
| PostgreSQL Rule Immutável | R1, Critério 2 (`prontuario_audit_logs`) | `database/migrations/2026_08_17_000007_create_prontuario_audit_logs_table.php` | **Especificado 100%** |
| Hash Chaining SHA-256 | R1, TR 3.1 `d` (Trilha Criptográfica) | `app/Services/AuditService.php` | **Especificado 100%** |
| Dompdf Carteira Digital | R1, Critério 4 (PDF Oficial SEJUS) | `app/Services/CarteiraPdfService.php`, `resources/views/pdf/carteira_digital.blade.php` | **Especificado 100%** |
| QR Code Criptográfico | R1, Critério 4 (Assinatura HMAC) | `app/Services/QrCodeSecurityService.php` | **Especificado 100%** |
| Rota Pública de Validação | R1, Critério 4 (`/validar-carteira/{token}`) | `app/Http/Controllers/CarteiraValidationController.php` | **Especificado 100%** |
| Seeder 78 Municípios ES | R1, Critério 5 (78 Municípios / 4 Físicos / 74 Remotos) | `database/seeders/MunicipiosEsSeeder.php` | **Especificado 100%** |
| Seeders Usuários, Vagas, Cursos, Rede | R1, Critério 1, 5 (Ecossistema ES) | `database/seeders/UsersAndRolesSeeder.php`, `OportunidadesSeeder.php`, `RedeApoioSeeder.php`, `ProntuarioSeeder.php` | **Especificado 100%** |
| Suíte de Testes Pest/PHPUnit | Critérios de Qualidade e Integridade | `tests/Unit/`, `tests/Feature/` | **Especificado 100%** |

---

## 5. Diretrizes para o Implementador (Worker / Builder)

1. **Configuração das Chaves de Ambiente:**
   - Adicionar ao `.env.example` e `.env`:
     ```env
     LGPD_PEPPER_KEY=sejus_lgpd_pepper_secret_key_2026_change_in_production
     CARTEIRA_SIGNING_KEY=sejus_carteira_hmac_signing_key_2026_sha256
     ```
   - Configurar `config/services.php`:
     ```php
     'lgpd' => [
         'pepper' => env('LGPD_PEPPER_KEY', 'default-pepper-key'),
     ],
     'carteira' => [
         'signing_key' => env('CARTEIRA_SIGNING_KEY', 'default-carteira-key'),
     ],
     ```
2. **Dependências Composer Necessárias:**
   - `barryvdh/laravel-dompdf` (ou `dompdf/dompdf`)
   - `bacon/bacon-qr-code` (ou `simplesoftwareio/simple-qrcode`)
3. **Ordem de Execução dos Seeders:**
   1. `MunicipiosEsSeeder`
   2. `PerfisSeeder`
   3. `UsersAndRolesSeeder`
   4. `RedeApoioSeeder`
   5. `OportunidadesSeeder`
   6. `ProntuarioSeeder`
4. **Execução da Suíte de Testes:**
   - Executar `php artisan test` ou `./vendor/bin/pest`.
   - Garantir 100% de aprovação (zero falhas e zero alertas de depreciação).
