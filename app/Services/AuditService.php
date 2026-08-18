<?php

namespace App\Services;

use App\Models\ProntuarioAuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    public const GENESIS_HASH = '0000000000000000000000000000000000000000000000000000000000000000';

    /**
     * Compute canonical SHA-256 hash for a block in the audit chain.
     */
    public function calculateRecordHash(
        string $previousHash,
        ?int $prontuarioId,
        ?int $userId,
        string $acao,
        ?string $ipAddress,
        string $timestamp,
        array $details
    ): string {
        ksort($details);
        $canonicalDetails = json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $payload = implode('|', [
            $previousHash,
            $prontuarioId !== null ? (string) $prontuarioId : 'GLOBAL',
            $userId !== null ? (string) $userId : 'ANONYMOUS',
            $acao,
            $ipAddress ?? '127.0.0.1',
            $timestamp,
            $canonicalDetails,
        ]);

        return hash('sha256', $payload);
    }

    /**
     * Append an immutable, cryptographically chained audit log entry.
     */
    public function log(
        ?int $prontuarioId,
        string $acao,
        array $details = [],
        ?int $userId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): ProntuarioAuditLog {
        $resolvedUserId = $userId ?? Auth::id();
        $resolvedIp = $ipAddress ?? Request::ip() ?? '127.0.0.1';
        $resolvedUserAgent = $userAgent ?? Request::userAgent() ?? 'System/CLI';
        $timestamp = now()->toIso8601String();

        // Obtem o ultimo registro para encadeamento de hash
        $lastLog = ProntuarioAuditLog::orderBy('id', 'desc')->first();
        $previousHash = $lastLog ? $lastLog->current_hash : self::GENESIS_HASH;

        $currentHash = $this->calculateRecordHash(
            $previousHash,
            $prontuarioId,
            $resolvedUserId,
            $acao,
            $resolvedIp,
            $timestamp,
            $details
        );

        return ProntuarioAuditLog::create([
            'prontuario_id' => $prontuarioId,
            'user_id' => $resolvedUserId,
            'acao' => $acao,
            'ip_address' => $resolvedIp,
            'user_agent' => $resolvedUserAgent,
            'previous_hash' => $previousHash,
            'current_hash' => $currentHash,
            'details' => $details,
            'timestamp' => $timestamp,
        ]);
    }

    /**
     * Forensic verification of the entire hash chain integrity.
     */
    public function verifyChainIntegrity(?int $prontuarioId = null): array
    {
        $query = ProntuarioAuditLog::orderBy('id', 'asc');
        if ($prontuarioId !== null) {
            $query->where('prontuario_id', $prontuarioId);
        }

        $logs = $query->get();

        if ($logs->isEmpty()) {
            return [
                'valid' => true,
                'total_verified' => 0,
                'latest_hash' => self::GENESIS_HASH,
                'message' => 'Nenhum registro de auditoria encontrado.',
            ];
        }

        $expectedPreviousHash = self::GENESIS_HASH;
        $verifiedCount = 0;

        foreach ($logs as $index => $log) {
            // Em caso de filtro por prontuario individual, o previous hash pode nao ser o genesis caso haja outros logs globais
            if ($prontuarioId === null && $log->previous_hash !== $expectedPreviousHash) {
                return [
                    'valid' => false,
                    'broken_record_id' => $log->id,
                    'reason' => "Quebra de encadeamento no registro #{$log->id}: previous_hash [{$log->previous_hash}] difere do esperado [{$expectedPreviousHash}].",
                    'verified_count' => $verifiedCount,
                ];
            }

            // Recalcula o hash do registro com os dados persistidos
            $timestamp = $log->timestamp instanceof \DateTimeInterface
                ? $log->timestamp->format('c')
                : (string) $log->timestamp;

            $calculatedHash = $this->calculateRecordHash(
                $log->previous_hash,
                $log->prontuario_id,
                $log->user_id,
                $log->acao,
                $log->ip_address,
                $timestamp,
                $log->details ?? []
            );

            if (!hash_equals($log->current_hash, $calculatedHash)) {
                return [
                    'valid' => false,
                    'broken_record_id' => $log->id,
                    'reason' => "Adulteracao detectada no registro #{$log->id}: hash gravado [{$log->current_hash}] diverge do recalculado [{$calculatedHash}].",
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
            'message' => 'Trilha de auditoria 100% integra e sem violacoes.',
        ];
    }
}
