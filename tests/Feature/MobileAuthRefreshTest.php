<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\MobileAccessToken;
use App\Models\ProntuarioAuditLog;
use Carbon\Carbon;

class MobileAuthRefreshTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    /**
     * Helper to authenticate as mobile user and retrieve Bearer token.
     */
    private function getMobileToken(string $login = 'lucas.santos@cidadao.es.gov.br', string $password = 'secret123'): string
    {
        $response = $this->postJson('/api/auth/login', [
            'login' => $login,
            'password' => $password,
            'device_name' => 'Test Mobile Device',
        ]);

        $response->assertStatus(200);
        $token = $response->json('access_token');
        $this->assertNotEmpty($token, 'Failed to acquire access_token during login helper.');

        return (string) $token;
    }

    /**
     * R2/M2: Valid token refresh exchanges current Bearer token for a fresh token,
     * invalidates old token, and allows new token to authenticate.
     */
    public function test_successful_refresh_exchanges_bearer_token_and_invalidates_old_token(): void
    {
        $oldToken = $this->getMobileToken();
        $oldTokenHash = hash('sha256', $oldToken);

        // Verify old token is valid and authenticated
        $meBefore = $this->withHeader('Authorization', 'Bearer ' . $oldToken)
            ->getJson('/api/auth/me');
        $meBefore->assertStatus(200);

        // Verify old token exists in database
        $this->assertDatabaseHas('mobile_access_tokens', [
            'token' => $oldTokenHash,
        ]);

        // Execute refresh
        $refreshResponse = $this->withHeader('Authorization', 'Bearer ' . $oldToken)
            ->postJson('/api/auth/refresh');

        $refreshResponse->assertStatus(200)
            ->assertJson([
                'status' => 'refreshed',
                'token_type' => 'Bearer',
                'user' => [
                    'email' => 'lucas.santos@cidadao.es.gov.br',
                    'role' => 'egresso',
                ],
            ]);

        $newToken = $refreshResponse->json('access_token');
        $this->assertNotEmpty($newToken);
        $this->assertNotEquals($oldToken, $newToken, 'Fresh token must differ from old token.');
        $this->assertStringStartsWith('ce_mob_', $newToken);

        $expiresAt = $refreshResponse->json('expires_at');
        $this->assertNotEmpty($expiresAt);
        $parsedExpiresAt = Carbon::parse($expiresAt);
        $this->assertGreaterThan(now()->addDays(28), $parsedExpiresAt);

        // Masked user PII verification
        $this->assertNotNull($refreshResponse->json('user.cpf_masked'));
        $this->assertStringContainsString('***', $refreshResponse->json('user.cpf_masked'));
        $this->assertTrue($refreshResponse->json('user.ativo'));

        // Verify old token is deleted from database
        $this->assertDatabaseMissing('mobile_access_tokens', [
            'token' => $oldTokenHash,
        ]);

        // Verify old token can NO LONGER authenticate
        $meWithOldToken = $this->withHeader('Authorization', 'Bearer ' . $oldToken)
            ->getJson('/api/auth/me');
        $meWithOldToken->assertStatus(401)
            ->assertJson(['code' => 'UNAUTHENTICATED']);

        // Verify old token CANNOT be used to refresh again (replay prevention)
        $refreshWithOldToken = $this->withHeader('Authorization', 'Bearer ' . $oldToken)
            ->postJson('/api/auth/refresh');
        $refreshWithOldToken->assertStatus(401)
            ->assertJson(['code' => 'UNAUTHENTICATED']);

        // Verify new token CAN authenticate successfully
        $meWithNewToken = $this->withHeader('Authorization', 'Bearer ' . $newToken)
            ->getJson('/api/auth/me');
        $meWithNewToken->assertStatus(200)
            ->assertJson([
                'email' => 'lucas.santos@cidadao.es.gov.br',
            ]);

        // Verify new token can be refreshed in the future
        $secondRefresh = $this->withHeader('Authorization', 'Bearer ' . $newToken)
            ->postJson('/api/auth/refresh');
        $secondRefresh->assertStatus(200)
            ->assertJson(['status' => 'refreshed']);
    }

    /**
     * R2/M2: Refresh with expired Bearer token returns 401 UNAUTHENTICATED.
     */
    public function test_refresh_with_expired_token_returns_401(): void
    {
        $token = $this->getMobileToken();
        $tokenHash = hash('sha256', $token);

        // Manually expire the token in database
        MobileAccessToken::where('token', $tokenHash)->update([
            'expires_at' => now()->subMinutes(10),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/auth/refresh');

        $response->assertStatus(401)
            ->assertJson([
                'code' => 'UNAUTHENTICATED',
            ]);
    }

    /**
     * R2/M2: Refresh with invalid or tampered Bearer token returns 401 UNAUTHENTICATED.
     */
    public function test_refresh_with_invalid_or_tampered_token_returns_401(): void
    {
        $tamperedToken = 'ce_mob_tampered_invalid_token_signature_deadbeef12345678';

        $response = $this->withHeader('Authorization', 'Bearer ' . $tamperedToken)
            ->postJson('/api/auth/refresh');

        $response->assertStatus(401)
            ->assertJson([
                'code' => 'UNAUTHENTICATED',
            ]);
    }

    /**
     * R2/M2: Refresh without Authorization header returns 401 UNAUTHENTICATED.
     */
    public function test_refresh_without_token_returns_401(): void
    {
        $response = $this->postJson('/api/auth/refresh');

        $response->assertStatus(401)
            ->assertJson([
                'code' => 'UNAUTHENTICATED',
            ]);
    }

    /**
     * R2/M2: Refresh with deactivated account returns 403 ACCOUNT_DEACTIVATED.
     */
    public function test_refresh_with_deactivated_account_returns_403(): void
    {
        $token = $this->getMobileToken();

        // Deactivate Lucas' account
        $user = User::where('email', 'lucas.santos@cidadao.es.gov.br')->firstOrFail();
        $user->ativo = false;
        $user->save();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/auth/refresh');

        $response->assertStatus(403)
            ->assertJson([
                'code' => 'ACCOUNT_DEACTIVATED',
            ]);
    }

    /**
     * R2/M2: Refresh logs an immutable audit event AUTH_TOKEN_REFRESHED.
     */
    public function test_refresh_records_audit_event(): void
    {
        $token = $this->getMobileToken();
        $user = User::where('email', 'lucas.santos@cidadao.es.gov.br')->firstOrFail();

        $auditCountBefore = ProntuarioAuditLog::where('acao', 'AUTH_TOKEN_REFRESHED')
            ->where('user_id', $user->id)
            ->count();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/auth/refresh');

        $response->assertStatus(200);

        $auditCountAfter = ProntuarioAuditLog::where('acao', 'AUTH_TOKEN_REFRESHED')
            ->where('user_id', $user->id)
            ->count();

        $this->assertEquals($auditCountBefore + 1, $auditCountAfter);
    }
}
