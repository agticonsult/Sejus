<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\AuditService;

class AuditServiceTest extends TestCase
{
    protected AuditService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AuditService();
    }

    public function test_genesis_hash_constant_is_64_zeros(): void
    {
        $this->assertEquals(str_repeat('0', 64), AuditService::GENESIS_HASH);
    }

    public function test_calculates_deterministic_sha256_record_hash(): void
    {
        $prevHash = AuditService::GENESIS_HASH;
        $prontuarioId = 1;
        $userId = 2;
        $acao = 'VIEW';
        $ip = '127.0.0.1';
        $timestamp = '2026-08-17T12:00:00Z';
        $details = ['motivo' => 'Consulta de rotina', 'setor' => 'Social'];

        $hash1 = $this->service->calculateRecordHash($prevHash, $prontuarioId, $userId, $acao, $ip, $timestamp, $details);
        $hash2 = $this->service->calculateRecordHash($prevHash, $prontuarioId, $userId, $acao, $ip, $timestamp, $details);

        $this->assertEquals(64, strlen($hash1));
        $this->assertEquals($hash1, $hash2);
    }

    public function test_hash_chain_detects_tampered_payload_or_action(): void
    {
        $prevHash = AuditService::GENESIS_HASH;
        $timestamp = '2026-08-17T12:00:00Z';

        $genuineHash = $this->service->calculateRecordHash($prevHash, 1, 2, 'VIEW', '127.0.0.1', $timestamp, ['key' => 'original']);
        $tamperedHash = $this->service->calculateRecordHash($prevHash, 1, 2, 'UPDATE', '127.0.0.1', $timestamp, ['key' => 'original']);

        $this->assertNotEquals($genuineHash, $tamperedHash);
    }
}
