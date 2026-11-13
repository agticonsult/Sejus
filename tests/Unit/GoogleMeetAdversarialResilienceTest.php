<?php

namespace Tests\Unit;

use Tests\TestCase;
use Tests\Support\GoogleMeetFake;
use App\Services\GoogleMeet\GoogleMeetService;
use App\Services\GoogleMeet\Contracts\MeetApiClientInterface;
use App\Services\GoogleMeet\Clients\HttpMeetApiClient;
use App\Services\GoogleMeet\Exceptions\GoogleMeetApiException;
use App\Models\MeetingRoom;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

class GoogleMeetAdversarialResilienceTest extends TestCase
{
    protected GoogleMeetService $meetService;

    protected function setUp(): void
    {
        parent::setUp();

        if (!$this->app->bound(MeetApiClientInterface::class)) {
            $this->app->bind(MeetApiClientInterface::class, HttpMeetApiClient::class);
        }

        $this->meetService = $this->app->make(GoogleMeetService::class);
    }

    /**
     * Challenge 1a: HTTP 401 Unauthorized (Expired or invalid OAuth token).
     */
    public function test_google_meet_api_401_unauthorized_throws_exception_and_leaves_db_clean(): void
    {
        Http::fake([
            GoogleMeetFake::BASE_URL . '/spaces*' => Http::response([
                'error' => [
                    'code' => 401,
                    'message' => 'Request had invalid authentication credentials. Expected OAuth 2 access token, login cookie or other valid authentication credential.',
                    'status' => 'UNAUTHENTICATED',
                ],
            ], 401),
        ]);

        $this->assertDatabaseCount('meeting_rooms', 0);

        try {
            $this->meetService->createRestrictedRoom();
            $this->fail('Expected GoogleMeetApiException was not thrown on HTTP 401');
        } catch (GoogleMeetApiException $e) {
            $this->assertSame(401, $e->getStatusCode());
            $this->assertSame(401, $e->getHttpStatusCode());
            $this->assertStringContainsString('401', $e->getMessage());
            $this->assertStringContainsString('invalid authentication credentials', $e->getMessage());
            $this->assertIsArray($e->getResponseBody());
            $this->assertSame('UNAUTHENTICATED', $e->getResponseBody()['error']['status'] ?? null);
        }

        $this->assertDatabaseCount('meeting_rooms', 0);
    }

    /**
     * Challenge 1b: HTTP 403 Forbidden (Insufficient scopes or permissions).
     */
    public function test_google_meet_api_403_forbidden_throws_exception_and_leaves_db_clean(): void
    {
        Http::fake([
            GoogleMeetFake::BASE_URL . '/spaces*' => Http::response([
                'error' => [
                    'code' => 403,
                    'message' => 'The caller does not have permission to create spaces. Insufficient OAuth scope: https://www.googleapis.com/auth/meetings.space.created',
                    'status' => 'PERMISSION_DENIED',
                ],
            ], 403),
        ]);

        $this->assertDatabaseCount('meeting_rooms', 0);

        try {
            $this->meetService->createRestrictedRoom();
            $this->fail('Expected GoogleMeetApiException was not thrown on HTTP 403');
        } catch (GoogleMeetApiException $e) {
            $this->assertSame(403, $e->getStatusCode());
            $this->assertSame(403, $e->getHttpStatusCode());
            $this->assertStringContainsString('403', $e->getMessage());
            $this->assertStringContainsString('The caller does not have permission', $e->getMessage());
            $this->assertSame('PERMISSION_DENIED', $e->getResponseBody()['error']['status'] ?? null);
        }

        $this->assertDatabaseCount('meeting_rooms', 0);
    }

    /**
     * Challenge 1c: HTTP 429 Too Many Requests (Rate limit / quota exhaustion).
     */
    public function test_google_meet_api_429_too_many_requests_throws_exception_and_leaves_db_clean(): void
    {
        Http::fake([
            GoogleMeetFake::BASE_URL . '/spaces*' => Http::response([
                'error' => [
                    'code' => 429,
                    'message' => 'Quota exceeded for quota metric Spaces create requests per minute.',
                    'status' => 'RESOURCE_EXHAUSTED',
                ],
            ], 429),
        ]);

        $this->assertDatabaseCount('meeting_rooms', 0);

        try {
            $this->meetService->createRestrictedRoom();
            $this->fail('Expected GoogleMeetApiException was not thrown on HTTP 429');
        } catch (GoogleMeetApiException $e) {
            $this->assertSame(429, $e->getStatusCode());
            $this->assertSame(429, $e->getHttpStatusCode());
            $this->assertStringContainsString('429', $e->getMessage());
            $this->assertStringContainsString('Quota exceeded', $e->getMessage());
            $this->assertSame('RESOURCE_EXHAUSTED', $e->getResponseBody()['error']['status'] ?? null);
        }

        $this->assertDatabaseCount('meeting_rooms', 0);
    }

    /**
     * Challenge 1d: Empty JSON body response `{}` with HTTP 200 OK.
     */
    public function test_google_meet_api_empty_response_body_throws_exception_and_leaves_db_clean(): void
    {
        Http::fake([
            GoogleMeetFake::BASE_URL . '/spaces*' => Http::response([], 200),
        ]);

        $this->assertDatabaseCount('meeting_rooms', 0);

        try {
            $this->meetService->createRestrictedRoom();
            $this->fail('Expected GoogleMeetApiException was not thrown on empty body');
        } catch (GoogleMeetApiException $e) {
            $this->assertSame(500, $e->getStatusCode());
            $this->assertStringContainsString('missing space name or meeting URI', $e->getMessage());
            $this->assertSame([], $e->getResponseBody());
        }

        $this->assertDatabaseCount('meeting_rooms', 0);
    }

    /**
     * Challenge 1e: Missing 'name' field in JSON response (partial response).
     */
    public function test_google_meet_api_missing_name_field_throws_exception_and_leaves_db_clean(): void
    {
        Http::fake([
            GoogleMeetFake::BASE_URL . '/spaces*' => Http::response([
                'meetingUri' => 'https://meet.google.com/abc-defg-hij',
                'meetingCode' => 'abc-defg-hij',
            ], 200),
        ]);

        $this->assertDatabaseCount('meeting_rooms', 0);

        try {
            $this->meetService->createRestrictedRoom();
            $this->fail('Expected GoogleMeetApiException was not thrown when name is missing');
        } catch (GoogleMeetApiException $e) {
            $this->assertSame(500, $e->getStatusCode());
            $this->assertStringContainsString('missing space name or meeting URI', $e->getMessage());
        }

        $this->assertDatabaseCount('meeting_rooms', 0);
    }

    /**
     * Challenge 1f: Missing 'meetingUri' field in JSON response (partial response).
     */
    public function test_google_meet_api_missing_meeting_uri_field_throws_exception_and_leaves_db_clean(): void
    {
        Http::fake([
            GoogleMeetFake::BASE_URL . '/spaces*' => Http::response([
                'name' => 'spaces/sE4kP9qLmNt8',
                'meetingCode' => 'abc-defg-hij',
            ], 200),
        ]);

        $this->assertDatabaseCount('meeting_rooms', 0);

        try {
            $this->meetService->createRestrictedRoom();
            $this->fail('Expected GoogleMeetApiException was not thrown when meetingUri is missing');
        } catch (GoogleMeetApiException $e) {
            $this->assertSame(500, $e->getStatusCode());
            $this->assertStringContainsString('missing space name or meeting URI', $e->getMessage());
        }

        $this->assertDatabaseCount('meeting_rooms', 0);
    }

    /**
     * Challenge 1g: HTTP client ConnectionException (Network timeout, DNS resolution failure).
     */
    public function test_google_meet_api_network_timeout_connection_exception_throws_exception_and_leaves_db_clean(): void
    {
        Http::fake([
            GoogleMeetFake::BASE_URL . '/spaces*' => function () {
                throw new ConnectionException('cURL error 28: Operation timed out after 15000 milliseconds with 0 bytes received');
            },
        ]);

        $this->assertDatabaseCount('meeting_rooms', 0);

        try {
            $this->meetService->createRestrictedRoom();
            $this->fail('Expected GoogleMeetApiException was not thrown on network timeout');
        } catch (GoogleMeetApiException $e) {
            $this->assertSame(0, $e->getStatusCode());
            $this->assertStringContainsString('connection failed', $e->getMessage());
            $this->assertStringContainsString('timed out', $e->getMessage());
            $this->assertInstanceOf(ConnectionException::class, $e->getPrevious());
        }

        $this->assertDatabaseCount('meeting_rooms', 0);
    }

    /**
     * Challenge 2: Transaction rollback on database failure.
     * If the database encounters an unexpected error (e.g. storage error, hook failure),
     * the transaction must roll back cleanly without leaving corrupted state.
     */
    public function test_transaction_rollback_prevents_db_persistence_if_database_write_fails(): void
    {
        GoogleMeetFake::fakeSpaceCreation([
            'name' => 'spaces/rollback-test-space-999',
            'meetingUri' => 'https://meet.google.com/rol-lbck-999',
            'meetingCode' => 'rol-lbck-999',
        ]);

        $this->assertDatabaseCount('meeting_rooms', 0);

        MeetingRoom::saving(function () {
            throw new RuntimeException('Simulated database fatal lock or storage error');
        });

        try {
            $this->meetService->createRestrictedRoom();
            $this->fail('Expected RuntimeException was not thrown during DB transaction');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulated database fatal lock or storage error', $e->getMessage());
        } finally {
            // Unbind model event listener for subsequent tests
            MeetingRoom::flushEventListeners();
        }

        // Database must remain pristine
        $this->assertDatabaseCount('meeting_rooms', 0);
    }

    /**
     * Challenge 3: Rapid burst stress test alternating between failure modes.
     * Confirms that repeated failures in succession do not leak state across executions.
     */
    public function test_rapid_burst_of_adversarial_failures_leaves_zero_orphaned_records(): void
    {
        $this->assertDatabaseCount('meeting_rooms', 0);

        $scenarios = [
            function () {
                Http::fake([
                    GoogleMeetFake::BASE_URL . '/spaces*' => Http::response(['error' => ['code' => 401, 'message' => 'Unauthorized']], 401),
                ]);
            },
            function () {
                Http::fake([
                    GoogleMeetFake::BASE_URL . '/spaces*' => Http::response(['error' => ['code' => 403, 'message' => 'Forbidden']], 403),
                ]);
            },
            function () {
                Http::fake([
                    GoogleMeetFake::BASE_URL . '/spaces*' => Http::response(['error' => ['code' => 429, 'message' => 'Rate limit']], 429),
                ]);
            },
            function () {
                Http::fake([
                    GoogleMeetFake::BASE_URL . '/spaces*' => Http::response([], 200),
                ]);
            },
            function () {
                Http::fake([
                    GoogleMeetFake::BASE_URL . '/spaces*' => function () {
                        throw new ConnectionException('Timeout');
                    },
                ]);
            },
        ];

        $caughtCount = 0;

        for ($i = 0; $i < 20; $i++) {
            $scenario = $scenarios[$i % count($scenarios)];
            $scenario();

            try {
                $this->meetService->createRestrictedRoom();
            } catch (GoogleMeetApiException $e) {
                $caughtCount++;
            }
        }

        $this->assertSame(20, $caughtCount);
        $this->assertDatabaseCount('meeting_rooms', 0);
    }

    /**
     * Challenge 4: OAuth token refresh endpoint failure.
     * When configured to fetch token dynamically via refreshToken, upstream token failure throws
     * GoogleMeetApiException and leaves zero records in database.
     */
    public function test_oauth_token_refresh_failure_prevents_db_persistence(): void
    {
        $customClient = new HttpMeetApiClient([
            'access_token' => null,
            'client_id' => 'google-client-id-xyz',
            'client_secret' => 'google-client-secret-abc',
            'refresh_token' => 'google-refresh-token-123',
            'token_url' => 'https://oauth2.googleapis.com/token',
            'base_url' => GoogleMeetFake::BASE_URL,
        ]);

        $serviceWithCustomClient = new GoogleMeetService($customClient);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'error' => 'invalid_grant',
                'error_description' => 'Token has been expired or revoked.',
            ], 400),
        ]);

        $this->assertDatabaseCount('meeting_rooms', 0);

        try {
            $serviceWithCustomClient->createRestrictedRoom();
            $this->fail('Expected GoogleMeetApiException was not thrown on token refresh failure');
        } catch (GoogleMeetApiException $e) {
            $this->assertSame(400, $e->getStatusCode());
            $this->assertStringContainsString('Failed to refresh Google Meet OAuth2 token', $e->getMessage());
        }

        $this->assertDatabaseCount('meeting_rooms', 0);
    }
}
