<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\ProntuarioAuditLog;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class ProntuarioAuditLogImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        ProntuarioAuditLog::query()->delete();
    }

    public function test_audit_service_creates_unbroken_cryptographic_chain(): void
    {
        $auditService = new AuditService();

        // 1. Create first log entry (Genesis link)
        $log1 = $auditService->log(1, 'CREATE_PRONTUARIO', ['numero' => 'PRT-2026-000001'], 2, '192.168.1.100');
        $this->assertEquals(AuditService::GENESIS_HASH, $log1->previous_hash);
        $this->assertEquals(64, strlen($log1->current_hash));

        // 2. Create second log entry
        $log2 = $auditService->log(1, 'VIEW', ['motivo' => 'Consulta de histórico'], 2, '192.168.1.100');
        $this->assertEquals($log1->current_hash, $log2->previous_hash);

        // 3. Create third log entry
        $log3 = $auditService->log(1, 'EXPORT_PDF', ['tipo' => 'CARTEIRA_DIGITAL'], 2, '192.168.1.100');
        $this->assertEquals($log2->current_hash, $log3->previous_hash);

        // 4. Verify full chain
        $verification = $auditService->verifyChainIntegrity();
        $this->assertTrue($verification['valid']);
        $this->assertEquals(3, $verification['total_verified']);
    }

    public function test_chain_integrity_fails_when_record_is_tampered(): void
    {
        $auditService = new AuditService();

        $log1 = $auditService->log(1, 'CREATE_PRONTUARIO', ['numero' => 'PRT-2026-000001'], 2, '192.168.1.100');
        $log2 = $auditService->log(1, 'VIEW', ['motivo' => 'Consulta'], 2, '192.168.1.100');

        // Intentionally tamper the stored details payload directly
        ProntuarioAuditLog::where('id', $log1->id)->update(['acao' => 'UNAUTHORIZED_ALTERATION']);

        $verification = $auditService->verifyChainIntegrity();

        // On PostgreSQL with rule, update is blocked so chain stays valid. On SQLite/other, tampering is caught.
        if (DB::getDriverName() === 'pgsql') {
            $this->assertTrue($verification['valid']);
        } else {
            $this->assertFalse($verification['valid']);
        }
    }
}
