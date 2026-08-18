<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\LgpdSecurityService;
use InvalidArgumentException;

class LgpdSecurityServiceTest extends TestCase
{
    protected LgpdSecurityService $service;
    protected string $pepper = 'test_sejus_pepper_2026';

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LgpdSecurityService($this->pepper);
    }

    public function test_normalizes_cpf_stripping_non_numeric_characters(): void
    {
        $raw = '123.456.789-01';
        $normalized = $this->service->normalizeCpf($raw);
        $this->assertEquals('12345678901', $normalized);
    }

    public function test_normalizes_cpf_throws_exception_on_invalid_length(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->normalizeCpf('123.456.78');
    }

    public function test_validates_valid_and_invalid_cpfs(): void
    {
        // Invalid sequence of all identical digits
        $this->assertFalse($this->service->validateCpf('111.111.111-11'));
        $this->assertFalse($this->service->validateCpf('000.000.000-00'));

        // Invalid checksum
        $this->assertFalse($this->service->validateCpf('123.456.789-00'));

        // Valid test CPFs
        $this->assertTrue($this->service->validateCpf('52998224725'));
        $this->assertTrue($this->service->validateCpf('192.830.456-78') || $this->service->validateCpf('529.982.247-25'));
    }

    public function test_generates_deterministic_blind_index(): void
    {
        $cpf = '123.456.789-01';
        $hash1 = $this->service->generateBlindIndex($cpf);
        $hash2 = $this->service->generateBlindIndex('12345678901');

        $this->assertEquals(64, strlen($hash1));
        $this->assertEquals($hash1, $hash2);
        $this->assertEquals(hash_hmac('sha256', '12345678901', $this->pepper), $hash1);
    }

    public function test_different_pepper_produces_different_hash(): void
    {
        $cpf = '123.456.789-01';
        $serviceAlt = new LgpdSecurityService('alternative_pepper_key');

        $hash1 = $this->service->generateBlindIndex($cpf);
        $hash2 = $serviceAlt->generateBlindIndex($cpf);

        $this->assertNotEquals($hash1, $hash2);
    }

    public function test_aes_256_encryption_and_decryption(): void
    {
        $sensitiveText = 'Paciente diagnosticado com CID F10.2 - Acolhimento SEJUS';
        $encrypted = $this->service->encryptField($sensitiveText);

        $this->assertNotNull($encrypted);
        $this->assertNotEquals($sensitiveText, $encrypted);

        $decrypted = $this->service->decryptField($encrypted);
        $this->assertEquals($sensitiveText, $decrypted);
    }

    public function test_masks_cpf_correctly(): void
    {
        $masked = $this->service->maskCpf('192.830.456-78');
        $this->assertEquals('***.830.456-**', $masked);
    }

    public function test_masks_name_correctly(): void
    {
        $masked = $this->service->maskName('Lucas Silva Santos');
        $this->assertEquals('Lucas S. Santos', $masked);
    }
}
