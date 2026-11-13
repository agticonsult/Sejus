<?php

namespace Tests\Unit;

use Tests\TestCase;
use Tests\Support\GoogleMeetFake;
use App\Services\GoogleMeet\GoogleMeetService;
use App\Services\GoogleMeet\Contracts\MeetApiClientInterface;
use App\Services\GoogleMeet\Clients\HttpMeetApiClient;
use App\Services\GoogleMeet\Exceptions\GoogleMeetApiException;
use App\Services\AuditService;
use App\Models\MeetingRoom;
use App\Models\User;
use App\Models\Prontuario;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Client\Request;
use Illuminate\Database\QueryException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Exception;

class GoogleMeetAdversarialTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected GoogleMeetService $meetService;

    protected function setUp(): void
    {
        parent::setUp();

        if (!$this->app->bound(MeetApiClientInterface::class)) {
            $this->app->bind(MeetApiClientInterface::class, HttpMeetApiClient::class);
        }

        $this->meetService = $this->app->make(GoogleMeetService::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Adversarial Test 1: Caller passes malicious and overriding options attempting to downgrade R1 security:
     * - config.accessType => RESTRICTED
     * - config.entryPointAccess => ALL
     * - config.moderation => OFF
     * - chatRestriction => NO_RESTRICTION
     * - presentRestriction => NO_RESTRICTION
     * - defaultJoinAsViewerType => OFF
     *
     * Verify that outgoing wire request and database record remain strictly enforced.
     */
    public function test_adversarial_caller_options_cannot_downgrade_or_override_r1_security_parameters(): void
    {
        GoogleMeetFake::fakeSpaceCreation();

        $maliciousOptions = [
            'config' => [
                'accessType' => 'RESTRICTED',
                'entryPointAccess' => 'ALL',
                'moderation' => 'OFF',
                'moderationRestrictions' => [
                    'chatRestriction' => 'NO_RESTRICTION',
                    'presentRestriction' => 'NO_RESTRICTION',
                    'defaultJoinAsViewerType' => 'OFF',
                ],
            ],
            'access_type' => 'RESTRICTED',
            'entry_point_access' => 'ALL',
            'moderation' => 'OFF',
            'chat_restriction' => 'NO_RESTRICTION',
            'present_restriction' => 'NO_RESTRICTION',
            'default_join_as_viewer_type' => 'OFF',
            'status' => 'compromised',
            'space_name' => 'spaces/malicious_hacked_space',
            'meeting_uri' => 'https://evil-phishing.test/hacked',
            'meeting_code' => 'hacked-room',
        ];

        $room = $this->meetService->createRestrictedRoom($maliciousOptions);

        // 1. Assert wire request payload sent to Google Meet API v2 is strictly enforced
        Http::assertSent(function (Request $request) {
            if ($request->method() !== 'POST' || !str_contains($request->url(), '/spaces')) {
                return false;
            }

            $body = $request->data();
            $config = $body['config'] ?? [];
            $restrictions = $config['moderationRestrictions'] ?? [];

            return $config['accessType'] === 'OPEN'
                && $config['entryPointAccess'] === 'CREATOR_APP_ONLY'
                && $config['moderation'] === 'ON'
                && $restrictions['chatRestriction'] === 'HOSTS_ONLY'
                && $restrictions['presentRestriction'] === 'HOSTS_ONLY'
                && $restrictions['defaultJoinAsViewerType'] === 'ON';
        });

        // 2. Assert persisted model did NOT adopt the malicious inputs
        $this->assertSame('OPEN', $room->access_type);
        $this->assertSame('CREATOR_APP_ONLY', $room->entry_point_access);
        $this->assertSame('ON', $room->moderation);
        $this->assertSame('HOSTS_ONLY', $room->chat_restriction);
        $this->assertSame('HOSTS_ONLY', $room->present_restriction);
        $this->assertSame('ON', $room->default_join_as_viewer_type);
        $this->assertSame('active', $room->status);
        $this->assertNotSame('compromised', $room->status);
        $this->assertNotSame('spaces/malicious_hacked_space', $room->space_name);
        $this->assertNotSame('https://evil-phishing.test/hacked', $room->meeting_uri);

        // 3. Assert database row in SQLite also has the strict R1 settings
        $this->assertDatabaseHas('meeting_rooms', [
            'id' => $room->id,
            'access_type' => 'OPEN',
            'entry_point_access' => 'CREATOR_APP_ONLY',
            'moderation' => 'ON',
            'chat_restriction' => 'HOSTS_ONLY',
            'present_restriction' => 'HOSTS_ONLY',
            'default_join_as_viewer_type' => 'ON',
            'status' => 'active',
        ]);
    }

    /**
     * Adversarial Test 2: Injected artifactConfig payload attempting to override config attributes.
     */
    public function test_artifact_config_option_cannot_corrupt_core_r1_restrictions(): void
    {
        GoogleMeetFake::fakeSpaceCreation();

        $options = [
            'artifactConfig' => [
                'recordingConfig' => ['autoRecordingGeneration' => 'OFF'],
                'transcriptionConfig' => ['autoTranscriptionGeneration' => 'OFF'],
                'accessType' => 'RESTRICTED', // attempt to inject inside artifactConfig
                'moderation' => 'OFF',
            ],
        ];

        $room = $this->meetService->createRestrictedRoom($options);

        Http::assertSent(function (Request $request) {
            $body = $request->data();
            $config = $body['config'] ?? [];
            $restrictions = $config['moderationRestrictions'] ?? [];
            $artifactConfig = $config['artifactConfig'] ?? [];

            return $config['accessType'] === 'OPEN'
                && $config['moderation'] === 'ON'
                && $restrictions['chatRestriction'] === 'HOSTS_ONLY'
                && isset($artifactConfig['recordingConfig']);
        });

        $this->assertSame('OPEN', $room->access_type);
        $this->assertSame('ON', $room->moderation);
    }

    /**
     * Adversarial Test 3: Multiple rapid room creations generate distinct spaces and do not collide in SQLite.
     */
    public function test_multiple_rapid_room_creations_generate_distinct_spaces_without_sqlite_collision(): void
    {
        $createdRoomsCount = 20;
        $generatedSpaces = [];

        // Setup sequential distinct mock responses
        for ($i = 1; $i <= $createdRoomsCount; $i++) {
            $spaceCode = sprintf('space-col-%03d', $i);
            $meetingCode = sprintf('abc-%03d-xyz', $i);
            $generatedSpaces[$i] = [
                'name' => "spaces/{$spaceCode}",
                'meetingUri' => "https://meet.google.com/{$meetingCode}",
                'meetingCode' => $meetingCode,
                'config' => [
                    'accessType' => 'OPEN',
                    'entryPointAccess' => 'CREATOR_APP_ONLY',
                    'moderation' => 'ON',
                    'moderationRestrictions' => [
                        'chatRestriction' => 'HOSTS_ONLY',
                        'presentRestriction' => 'HOSTS_ONLY',
                        'defaultJoinAsViewerType' => 'ON',
                    ],
                ],
            ];
        }

        $counter = 0;
        Http::fake([
            'https://meet.googleapis.com/v2/spaces*' => function (Request $request) use (&$counter, $generatedSpaces) {
                $counter++;
                $data = $generatedSpaces[$counter] ?? $generatedSpaces[1];
                return Http::response($data, 200);
            },
        ]);

        $rooms = [];
        for ($i = 1; $i <= $createdRoomsCount; $i++) {
            $rooms[] = $this->meetService->createRestrictedRoom([
                'title' => "Sala Sessao {$i}",
            ]);
        }

        // Assert 20 distinct rooms were persisted
        $this->assertCount($createdRoomsCount, $rooms);
        $this->assertDatabaseCount('meeting_rooms', $createdRoomsCount);

        // Verify all space names, codes, URIs and IDs are strictly unique
        $spaceNames = array_map(fn ($r) => $r->space_name, $rooms);
        $meetingCodes = array_map(fn ($r) => $r->meeting_code, $rooms);
        $meetingUris = array_map(fn ($r) => $r->meeting_uri, $rooms);
        $ids = array_map(fn ($r) => $r->id, $rooms);

        $this->assertCount($createdRoomsCount, array_unique($spaceNames), 'Space names collided!');
        $this->assertCount($createdRoomsCount, array_unique($meetingCodes), 'Meeting codes collided!');
        $this->assertCount($createdRoomsCount, array_unique($meetingUris), 'Meeting URIs collided!');
        $this->assertCount($createdRoomsCount, array_unique($ids), 'Database IDs collided!');

        // Query each room individually to assert database consistency
        foreach ($rooms as $index => $room) {
            $dbRoom = MeetingRoom::findOrFail($room->id);
            $this->assertSame($generatedSpaces[$index + 1]['name'], $dbRoom->space_name);
            $this->assertSame($generatedSpaces[$index + 1]['meetingCode'], $dbRoom->meeting_code);
            $this->assertSame('OPEN', $dbRoom->access_type);
            $this->assertTrue($dbRoom->isActive());
            $this->assertTrue($dbRoom->isRestricted());
            $this->assertTrue($dbRoom->isViewerMode());
        }
    }

    /**
     * Adversarial Test 4: Collision testing on space_name unique constraint in SQLite.
     * What happens if Google API returns an already-existing space_name?
     * SQLite must throw QueryException, the transaction must roll back, and existing data remains untampered.
     */
    public function test_space_name_collision_triggers_rollback_and_preserves_database_integrity(): void
    {
        $duplicateSpace = 'spaces/collision-duplicate-999';

        GoogleMeetFake::fakeSpaceCreation([
            'name' => $duplicateSpace,
            'meetingUri' => 'https://meet.google.com/first-room-aaa',
            'meetingCode' => 'first-room-aaa',
        ]);

        // Create first room successfully
        $firstRoom = $this->meetService->createRestrictedRoom();
        $this->assertDatabaseCount('meeting_rooms', 1);

        // Attempt to create second room with identical space_name
        GoogleMeetFake::fakeSpaceCreation([
            'name' => $duplicateSpace,
            'meetingUri' => 'https://meet.google.com/second-room-bbb',
            'meetingCode' => 'second-room-bbb',
        ]);

        try {
            $this->meetService->createRestrictedRoom();
            $this->fail('Expected QueryException due to unique constraint on space_name');
        } catch (QueryException $e) {
            $this->assertStringContainsString('UNIQUE constraint failed', $e->getMessage());
        }

        // Database must still have exactly 1 record, uncorrupted
        $this->assertDatabaseCount('meeting_rooms', 1);
        $persisted = MeetingRoom::first();
        $this->assertSame('first-room-aaa', $persisted->meeting_code);
    }

    /**
     * Adversarial Test 5: Returned MeetingRoom model accurately reflects Google Meet API response.
     */
    public function test_returned_model_accurately_reflects_full_api_response_and_helper_methods(): void
    {
        $apiSpace = [
            'name' => 'spaces/fidelity-space-xyz789',
            'meetingUri' => 'https://meet.google.com/fid-elity-xyz',
            'meetingCode' => 'fid-elity-xyz',
            'config' => [
                'accessType' => 'OPEN',
                'entryPointAccess' => 'CREATOR_APP_ONLY',
                'moderation' => 'ON',
                'moderationRestrictions' => [
                    'chatRestriction' => 'HOSTS_ONLY',
                    'presentRestriction' => 'HOSTS_ONLY',
                    'defaultJoinAsViewerType' => 'ON',
                ],
            ],
        ];

        GoogleMeetFake::fakeSpaceCreation($apiSpace);

        $room = $this->meetService->createRestrictedRoom();

        // Check model attributes
        $this->assertSame($apiSpace['name'], $room->space_name);
        $this->assertSame($apiSpace['meetingUri'], $room->meeting_uri);
        $this->assertSame($apiSpace['meetingCode'], $room->meeting_code);
        $this->assertSame('OPEN', $room->access_type);
        $this->assertSame('CREATOR_APP_ONLY', $room->entry_point_access);
        $this->assertSame('ON', $room->moderation);
        $this->assertSame('HOSTS_ONLY', $room->chat_restriction);
        $this->assertSame('HOSTS_ONLY', $room->present_restriction);
        $this->assertSame('ON', $room->default_join_as_viewer_type);
        $this->assertSame('active', $room->status);

        // Check raw_config serialization
        $this->assertIsArray($room->raw_config);
        $this->assertSame('OPEN', $room->raw_config['accessType']);

        // Check model helper methods
        $this->assertTrue($room->isRestricted());
        $this->assertTrue($room->isViewerMode());
        $this->assertTrue($room->isOpenAccess());
        $this->assertTrue($room->isActive());
        $this->assertSame($apiSpace['meetingUri'], $room->join_url);
        $this->assertTrue($room->viewer_mode);
        $this->assertSame($room->raw_config, $room->restrictions_configured);

        // Check query scopes
        $this->assertSame(1, MeetingRoom::bySpaceName($apiSpace['name'])->count());
        $this->assertSame(1, MeetingRoom::byMeetingCode($apiSpace['meetingCode'])->count());
        $this->assertSame(1, MeetingRoom::active()->count());
        $this->assertSame(1, MeetingRoom::openAccess()->count());
    }

    /**
     * Adversarial Test 6: API returns invalid or missing space_name or meetingUri.
     */
    public function test_api_returning_missing_space_name_or_uri_throws_500_exception_and_leaves_db_clean(): void
    {
        // 1. Missing name
        GoogleMeetFake::fakeSpaceCreation([
            'name' => '',
            'meetingUri' => 'https://meet.google.com/test-uri',
        ]);

        try {
            $this->meetService->createRestrictedRoom();
            $this->fail('Expected GoogleMeetApiException when space name is empty');
        } catch (GoogleMeetApiException $e) {
            $this->assertSame(500, $e->getStatusCode());
            $this->assertStringContainsString('missing space name or meeting URI', $e->getMessage());
        }

        $this->assertDatabaseCount('meeting_rooms', 0);

        // 2. Missing meetingUri
        GoogleMeetFake::fakeSpaceCreation([
            'name' => 'spaces/test-valid-name',
            'meetingUri' => '',
        ]);

        try {
            $this->meetService->createRestrictedRoom();
            $this->fail('Expected GoogleMeetApiException when meetingUri is empty');
        } catch (GoogleMeetApiException $e) {
            $this->assertSame(500, $e->getStatusCode());
            $this->assertStringContainsString('missing space name or meeting URI', $e->getMessage());
        }

        $this->assertDatabaseCount('meeting_rooms', 0);
    }

    /**
     * Adversarial Test 7a: API returns HTTP 401 Unauthorized.
     */
    public function test_api_returning_401_throws_proper_exception_with_zero_db_leak(): void
    {
        GoogleMeetFake::fakeSpaceCreationFailure(401, GoogleMeetFake::sampleUnauthenticatedResponse());

        try {
            $this->meetService->createRestrictedRoom();
            $this->fail('Expected GoogleMeetApiException for HTTP 401');
        } catch (GoogleMeetApiException $e) {
            $this->assertSame(401, $e->getStatusCode());
            $this->assertSame('UNAUTHENTICATED', $e->getResponseBody()['error']['status'] ?? null);
            $this->assertStringContainsString('401', $e->getMessage());
        }
        $this->assertDatabaseCount('meeting_rooms', 0);
    }

    /**
     * Adversarial Test 7b: API returns HTTP 403 Forbidden.
     */
    public function test_api_returning_403_throws_proper_exception_with_zero_db_leak(): void
    {
        GoogleMeetFake::fakeSpaceCreationFailure(403, [
            'error' => [
                'code' => 403,
                'message' => 'The caller does not have permission',
                'status' => 'PERMISSION_DENIED',
            ],
        ]);

        try {
            $this->meetService->createRestrictedRoom();
            $this->fail('Expected GoogleMeetApiException for HTTP 403');
        } catch (GoogleMeetApiException $e) {
            $this->assertSame(403, $e->getStatusCode());
            $this->assertSame('PERMISSION_DENIED', $e->getResponseBody()['error']['status'] ?? null);
            $this->assertStringContainsString('403', $e->getMessage());
        }
        $this->assertDatabaseCount('meeting_rooms', 0);
    }

    /**
     * Adversarial Test 8a: API returns HTTP 429 Rate Limit Exceeded.
     */
    public function test_api_returning_429_rate_limit_throws_proper_exception(): void
    {
        GoogleMeetFake::fakeSpaceCreationFailure(429, [
            'error' => [
                'code' => 429,
                'message' => 'Resource has been exhausted (quota limit reached).',
                'status' => 'RESOURCE_EXHAUSTED',
            ],
        ]);

        try {
            $this->meetService->createRestrictedRoom();
            $this->fail('Expected GoogleMeetApiException for HTTP 429');
        } catch (GoogleMeetApiException $e) {
            $this->assertSame(429, $e->getStatusCode());
            $this->assertSame('RESOURCE_EXHAUSTED', $e->getResponseBody()['error']['status'] ?? null);
            $this->assertStringContainsString('429', $e->getMessage());
        }
        $this->assertDatabaseCount('meeting_rooms', 0);
    }

    /**
     * Adversarial Test 8b: API returns HTTP 503 Unavailable.
     */
    public function test_api_returning_503_unavailable_throws_proper_exception(): void
    {
        GoogleMeetFake::fakeSpaceCreationFailure(503, [
            'error' => [
                'code' => 503,
                'message' => 'The service is temporarily unavailable.',
                'status' => 'UNAVAILABLE',
            ],
        ]);

        try {
            $this->meetService->createRestrictedRoom();
            $this->fail('Expected GoogleMeetApiException for HTTP 503');
        } catch (GoogleMeetApiException $e) {
            $this->assertSame(503, $e->getStatusCode());
            $this->assertSame('UNAVAILABLE', $e->getResponseBody()['error']['status'] ?? null);
            $this->assertStringContainsString('503', $e->getMessage());
        }
        $this->assertDatabaseCount('meeting_rooms', 0);
    }

    /**
     * Adversarial Test 9: AuditService failure does not abort or rollback room creation,
     * while respecting SQLite foreign key relationships.
     */
    public function test_audit_service_failure_does_not_rollback_room_creation(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $prontuario = Prontuario::first();
        $this->assertNotNull($prontuario, 'Prontuario seeded record should exist');

        GoogleMeetFake::fakeSpaceCreation([
            'name' => 'spaces/audit-fail-space-01',
            'meetingUri' => 'https://meet.google.com/aud-fail-001',
            'meetingCode' => 'aud-fail-001',
        ]);

        $mockAuditService = Mockery::mock(AuditService::class);
        $mockAuditService->shouldReceive('log')
            ->once()
            ->andThrow(new Exception('Audit database connection timeout!'));

        // Instantiate service with failing AuditService
        $serviceWithFailingAudit = new GoogleMeetService(
            $this->app->make(MeetApiClientInterface::class),
            $mockAuditService
        );

        // Room creation should succeed despite AuditService exception
        $room = $serviceWithFailingAudit->createRestrictedRoom([
            'prontuario_id' => $prontuario->id,
        ]);

        $this->assertInstanceOf(MeetingRoom::class, $room);
        $this->assertSame('spaces/audit-fail-space-01', $room->space_name);
        $this->assertSame($prontuario->id, $room->prontuario_id);
        $this->assertDatabaseHas('meeting_rooms', [
            'id' => $room->id,
            'space_name' => 'spaces/audit-fail-space-01',
            'prontuario_id' => $prontuario->id,
        ]);

        // Verify relationship navigation
        $this->assertSame($prontuario->id, $room->prontuario->id);
    }

    /**
     * Adversarial Test 10: Missing moderationRestrictions in API response falls back to secure R1 defaults.
     */
    public function test_missing_moderation_restrictions_in_response_defaults_to_safe_r1_values(): void
    {
        // Response without moderationRestrictions sub-array
        GoogleMeetFake::fakeSpaceCreation([
            'name' => 'spaces/sparse-config-space',
            'meetingUri' => 'https://meet.google.com/spa-rse-cfg',
            'meetingCode' => 'spa-rse-cfg',
            'config' => [
                'accessType' => 'OPEN',
                'entryPointAccess' => 'CREATOR_APP_ONLY',
                'moderation' => 'ON',
                // moderationRestrictions omitted by upstream
            ],
        ]);

        $room = $this->meetService->createRestrictedRoom();

        // Model must safely adopt R1 defaults
        $this->assertSame('HOSTS_ONLY', $room->chat_restriction);
        $this->assertSame('HOSTS_ONLY', $room->present_restriction);
        $this->assertSame('ON', $room->default_join_as_viewer_type);
        $this->assertTrue($room->isRestricted());
        $this->assertTrue($room->isViewerMode());
    }

    /**
     * Adversarial Test 11: OAuth2 refresh token failure in HttpMeetApiClient.
     */
    public function test_oauth2_refresh_token_failure_throws_google_meet_api_exception(): void
    {
        $client = new HttpMeetApiClient([
            'base_url' => 'https://meet.googleapis.com/v2',
            'access_token' => null, // force refresh flow
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
            'refresh_token' => 'invalid-expired-refresh-token',
            'token_url' => 'https://oauth2.googleapis.com/token',
            'timeout' => 5,
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'error' => 'invalid_grant',
                'error_description' => 'Token has been expired or revoked.',
            ], 400),
        ]);

        $this->expectException(GoogleMeetApiException::class);
        $this->expectExceptionMessage('Failed to refresh Google Meet OAuth2 token');

        $client->getAccessToken();
    }

    /**
     * Adversarial Test 12: SQL Injection and XSS payloads in options array.
     * Verifies that parameters are properly bound and no database injection or corruption occurs.
     */
    public function test_sql_injection_and_xss_payloads_do_not_corrupt_database(): void
    {
        GoogleMeetFake::fakeSpaceCreation([
            'name' => 'spaces/sqli-safe-space',
            'meetingUri' => 'https://meet.google.com/sql-safe-001',
            'meetingCode' => 'sql-safe-001',
        ]);

        $hostileOptions = [
            'title' => "'); DROP TABLE meeting_rooms; -- <script>alert('xss')</script>",
            'custom_notes' => "' OR '1'='1",
        ];

        $room = $this->meetService->createRestrictedRoom($hostileOptions);

        $this->assertInstanceOf(MeetingRoom::class, $room);
        $this->assertDatabaseCount('meeting_rooms', 1);

        // Verify meeting_rooms table still exists and record is intact
        $persisted = MeetingRoom::findOrFail($room->id);
        $this->assertSame('spaces/sqli-safe-space', $persisted->space_name);
        $this->assertSame('OPEN', $persisted->access_type);
    }

    /**
     * Adversarial Test 13: 50 sequential room creations stress test.
     */
    public function test_fifty_sequential_room_creations_stress_test(): void
    {
        $totalRooms = 50;
        $counter = 0;

        Http::fake([
            'https://meet.googleapis.com/v2/spaces*' => function (Request $request) use (&$counter) {
                $counter++;
                return Http::response([
                    'name' => sprintf('spaces/stress-50-%04d', $counter),
                    'meetingUri' => sprintf('https://meet.google.com/str-%04d-ess', $counter),
                    'meetingCode' => sprintf('str-%04d-ess', $counter),
                    'config' => [
                        'accessType' => 'OPEN',
                        'entryPointAccess' => 'CREATOR_APP_ONLY',
                        'moderation' => 'ON',
                        'moderationRestrictions' => [
                            'chatRestriction' => 'HOSTS_ONLY',
                            'presentRestriction' => 'HOSTS_ONLY',
                            'defaultJoinAsViewerType' => 'ON',
                        ],
                    ],
                ], 200);
            },
        ]);

        for ($i = 1; $i <= $totalRooms; $i++) {
            $this->meetService->createRestrictedRoom();
        }

        $this->assertDatabaseCount('meeting_rooms', $totalRooms);
        $this->assertSame($totalRooms, MeetingRoom::distinct('space_name')->count('space_name'));
        $this->assertSame($totalRooms, MeetingRoom::where('access_type', 'OPEN')->count());
        $this->assertSame($totalRooms, MeetingRoom::where('chat_restriction', 'HOSTS_ONLY')->count());
    }

    /**
     * Adversarial Test 14: Upstream API returns empty JSON object `{}` with HTTP 200.
     */
    public function test_api_returning_empty_json_throws_exception_with_zero_db_entries(): void
    {
        Http::fake([
            'https://meet.googleapis.com/v2/spaces*' => Http::response([], 200),
        ]);

        try {
            $this->meetService->createRestrictedRoom();
            $this->fail('Expected GoogleMeetApiException for empty response body');
        } catch (GoogleMeetApiException $e) {
            $this->assertSame(500, $e->getStatusCode());
            $this->assertStringContainsString('missing space name or meeting URI', $e->getMessage());
        }

        $this->assertDatabaseCount('meeting_rooms', 0);
    }

    /**
     * Adversarial Test 15: Network timeout / connection exception handling.
     */
    public function test_network_connection_timeout_throws_google_meet_api_exception(): void
    {
        Http::fake([
            'https://meet.googleapis.com/v2/spaces*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timed out after 15000 milliseconds');
            },
        ]);

        try {
            $this->meetService->createRestrictedRoom();
            $this->fail('Expected GoogleMeetApiException on connection timeout');
        } catch (GoogleMeetApiException $e) {
            $this->assertStringContainsString('connection failed', $e->getMessage());
            $this->assertSame(0, $e->getStatusCode());
        }

        $this->assertDatabaseCount('meeting_rooms', 0);
    }
}
