<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;
use Throwable;

class LgpdSecurityService
{
    protected string $pepperKey;

    public function __construct(?string $pepperKey = null)
    {
        $this->pepperKey = $pepperKey ?? config('services.lgpd.pepper', env('LGPD_PEPPER_KEY', 'conecta_egresso_lgpd_pepper_2026_sejus_es'));
    }

    /**
     * Normalizes CPF by removing non-numeric characters and ensuring 11 digits.
     */
    public function normalizeCpf(string $cpf): string
    {
        $digits = preg_replace('/\D/', '', $cpf);
        if (strlen($digits) !== 11) {
            throw new InvalidArgumentException("CPF invalido: deve conter exatamente 11 digitos numericos.");
        }
        return $digits;
    }

    /**
     * Algorithmic validation of Brazilian CPF verification digits.
     */
    public function validateCpf(string $cpf): bool
    {
        $digits = preg_replace('/\D/', '', $cpf);

        if (strlen($digits) !== 11) {
            return false;
        }

        // Rejeita sequencias com todos os digitos iguais (ex: 111.111.111-11)
        if (preg_match('/^(\d)\1{10}$/', $digits)) {
            return false;
        }

        // Validacao do primeiro digito verificador
        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($c = 0; $c < $t; $c++) {
                $d += (int) $digits[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ((int) $digits[$c] !== $d) {
                return false;
            }
        }

        return true;
    }

    /**
     * Generates a deterministic Blind Index HMAC-SHA256 hash using the segregated pepper key.
     */
    public function generateBlindIndex(string $rawCpf): string
    {
        $cleanCpf = $this->normalizeCpf($rawCpf);
        return hash_hmac('sha256', $cleanCpf, $this->pepperKey);
    }

    /**
     * Encrypts a sensitive PII field using AES-256.
     */
    public function encryptField(?string $plaintext): ?string
    {
        if ($plaintext === null || $plaintext === '') {
            return null;
        }

        try {
            return Crypt::encryptString($plaintext);
        } catch (Throwable $e) {
            // Fallback AES-256-CBC with raw key if Crypt facade is uninitialized in standalone scripts
            $key = hash('sha256', $this->pepperKey, true);
            $iv = openssl_random_pseudo_bytes(16);
            $ciphertext = openssl_encrypt($plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            return 'raw_aes:' . base64_encode($iv . $ciphertext);
        }
    }

    /**
     * Decrypts a sensitive PII field.
     */
    public function decryptField(?string $ciphertext): ?string
    {
        if ($ciphertext === null || $ciphertext === '') {
            return null;
        }

        if (str_starts_with($ciphertext, 'raw_aes:')) {
            $raw = base64_decode(substr($ciphertext, 8), true);
            if ($raw === false || strlen($raw) < 16) {
                return null;
            }
            $iv = substr($raw, 0, 16);
            $cipher = substr($raw, 16);
            if ($cipher === '' || $cipher === false) {
                return null;
            }
            $key = hash('sha256', $this->pepperKey, true);
            $decrypted = @openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            return $decrypted !== false ? $decrypted : null;
        }

        try {
            return Crypt::decryptString($ciphertext);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Formats CPF with standard LGPD mask: ***.482.910-**
     */
    public function maskCpf(?string $cpf): string
    {
        if (empty($cpf)) {
            return '***.***.***-**';
        }

        $clean = preg_replace('/\D/', '', $cpf);
        if (strlen($clean) !== 11) {
            return '***.***.***-**';
        }

        return sprintf('***.%s.%s-**', substr($clean, 3, 3), substr($clean, 6, 3));
    }

    /**
     * Masks full name preserving first and last name: "LUCAS D. S. SANTOS"
     */
    public function maskName(?string $name): string
    {
        if (empty($name)) {
            return '***';
        }

        $parts = preg_split('/\s+/', trim($name));
        if (count($parts) <= 1) {
            return $name;
        }

        $first = array_shift($parts);
        $last = array_pop($parts);
        $middle = array_map(fn($p) => mb_substr($p, 0, 1) . '.', $parts);

        if (empty($middle)) {
            return $first . ' ' . $last;
        }

        return $first . ' ' . implode(' ', $middle) . ' ' . $last;
    }
}
