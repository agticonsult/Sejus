<?php

namespace Tests\Feature;

use App\Jobs\SyncGoogleMeetConferenceJob;
use App\Models\ConferenceParticipant;
use App\Models\ConferenceRecord;
use App\Models\ConferenceRecording;
use App\Models\ConferenceTranscript;
use App\Models\MeetingRoom;
use App\Services\GoogleMeet\GoogleMeetService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\Support\GoogleMeetFake;
use Tests\TestCase;

class GoogleMeetMilestone3ChallengerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        GoogleMeetFake::reset();
    }

    /*
    |--------------------------------------------------------------------------
    | Dimension 1: REST API Upstream Error Status Code Mappings
    |--------------------------------------------------------------------------
    | We verify upstream 404, 400, 429, 500, 503, 401, 403 Google API errors
    | return appropriate HTTP status codes (404, 400, 429, 502) without
    | uncaught 500 exceptions and without database corruption or leaks.
    |--------------------------------------------------------------------------
    */

    public function test_api_sync_conference_maps_upstream_404_to_http_404_and_leaves_db_clean(): void
    {
        $confId = 'conf-upstream-404';
        GoogleMeetFake::fakeConferenceRecord($confId, [], 404);

        $response = $this->postJson('/api/meet/conferences/sync', [
            'conference_id' => $confId,
            'async' => false,
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => [
                    'type' => 'GoogleMeetApiException',
                    'upstream_status' => 404,
                ],
            ]);

        $this->assertDatabaseCount('conference_records', 0);
        $this->assertDatabaseCount('conference_participants', 0);
    }

    public function test_api_rooms_and_sync_map_upstream_400_to_http_400_and_leaves_db_clean(): void
    {
        // 1. Room creation upstream 400
        GoogleMeetFake::fakeSpaceCreationFailure(400);

        $responseRoom = $this->postJson('/api/meet/rooms');

        $responseRoom->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => [
                    'type' => 'GoogleMeetApiException',
                    'upstream_status' => 400,
                ],
            ]);

        $this->assertDatabaseCount('meeting_rooms', 0);

        // 2. Conference sync upstream 400
        $confId = 'conf-upstream-400';
        Http::fake([
            GoogleMeetFake::BASE_URL . "/conferenceRecords/{$confId}" => Http::response([
                'error' => [
                    'code' => 400,
                    'message' => 'Invalid conference ID argument format.',
                    'status' => 'INVALID_ARGUMENT',
                ],
            ], 400),
        ]);

        $responseSync = $this->postJson('/api/meet/conferences/sync', [
            'conference_id' => $confId,
            'async' => false,
        ]);

        $responseSync->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => [
                    'type' => 'GoogleMeetApiException',
                    'upstream_status' => 400,
                ],
            ]);

        $this->assertDatabaseCount('conference_records', 0);
    }

    public function test_api_rooms_and_sync_map_upstream_429_to_http_429(): void
    {
        // 1. Room creation upstream 429
        Http::fake([
            GoogleMeetFake::BASE_URL . '/spaces*' => Http::response([
                'error' => [
                    'code' => 429,
                    'message' => 'Rate limit exceeded: Resource exhausted.',
                    'status' => 'RESOURCE_EXHAUSTED',
                ],
            ], 429),
        ]);

        $responseRoom = $this->postJson('/api/meet/rooms');

        $responseRoom->assertStatus(429)
            ->assertJson([
                'success' => false,
                'error' => [
                    'type' => 'GoogleMeetApiException',
                    'upstream_status' => 429,
                ],
            ]);

        $this->assertDatabaseCount('meeting_rooms', 0);

        // 2. Conference sync upstream 429
        $confId = 'conf-upstream-429';
        Http::fake([
            GoogleMeetFake::BASE_URL . "/conferenceRecords/{$confId}" => Http::response([
                'error' => [
                    'code' => 429,
                    'message' => 'Rate limit exceeded on conferenceRecords endpoint.',
                    'status' => 'RESOURCE_EXHAUSTED',
                ],
            ], 429),
        ]);

        $responseSync = $this->postJson('/api/meet/conferences/sync', [
            'conference_id' => $confId,
            'async' => false,
        ]);

        $responseSync->assertStatus(429)
            ->assertJson([
                'success' => false,
                'error' => [
                    'type' => 'GoogleMeetApiException',
                    'upstream_status' => 429,
                ],
            ]);

        $this->assertDatabaseCount('conference_records', 0);
    }

    public function test_api_rooms_and_sync_map_upstream_500_and_503_to_http_502_bad_gateway(): void
    {
        // 1. Room creation upstream 500
        GoogleMeetFake::fakeSpaceCreationFailure(500);

        $responseRoom500 = $this->postJson('/api/meet/rooms');

        $responseRoom500->assertStatus(502)
            ->assertJson([
                'success' => false,
                'error' => [
                    'type' => 'GoogleMeetApiException',
                    'upstream_status' => 500,
                ],
            ]);

        $this->assertDatabaseCount('meeting_rooms', 0);

        // 2. Room creation upstream 503 Service Unavailable
        GoogleMeetFake::reset();
        GoogleMeetFake::fakeSpaceCreationFailure(503);

        $responseRoom503 = $this->postJson('/api/meet/rooms');

        $responseRoom503->assertStatus(502)
            ->assertJson([
                'success' => false,
                'error' => [
                    'type' => 'GoogleMeetApiException',
                    'upstream_status' => 503,
                ],
            ]);

        // 3. Conference sync upstream 500
        $confId = 'conf-upstream-500';
        GoogleMeetFake::fakeConferenceRecord($confId, [], 500);

        $responseSync = $this->postJson('/api/meet/conferences/sync', [
            'conference_id' => $confId,
            'async' => false,
        ]);

        $responseSync->assertStatus(502)
            ->assertJson([
                'success' => false,
                'error' => [
                    'type' => 'GoogleMeetApiException',
                    'upstream_status' => 500,
                ],
            ]);

        $this->assertDatabaseCount('conference_records', 0);
    }

    public function test_api_sync_maps_upstream_401_and_403_to_http_502_bad_gateway(): void
    {
        $confId401 = 'conf-upstream-401';
        Http::fake([
            GoogleMeetFake::BASE_URL . "/conferenceRecords/{$confId401}" => Http::response(
                GoogleMeetFake::sampleUnauthenticatedResponse(),
                401
            ),
        ]);

        $response401 = $this->postJson('/api/meet/conferences/sync', [
            'conference_id' => $confId401,
            'async' => false,
        ]);

        $response401->assertStatus(502)
            ->assertJson([
                'success' => false,
                'error' => [
                    'type' => 'GoogleMeetApiException',
                    'upstream_status' => 401,
                ],
            ]);

        $confId403 = 'conf-upstream-403';
        Http::fake([
            GoogleMeetFake::BASE_URL . "/conferenceRecords/{$confId403}" => Http::response([
                'error' => [
                    'code' => 403,
                    'message' => 'The caller does not have permission for the requested resource.',
                    'status' => 'PERMISSION_DENIED',
                ],
            ], 403),
        ]);

        $response403 = $this->postJson('/api/meet/conferences/sync', [
            'conference_id' => $confId403,
            'async' => false,
        ]);

        $response403->assertStatus(502)
            ->assertJson([
                'success' => false,
                'error' => [
                    'type' => 'GoogleMeetApiException',
                    'upstream_status' => 403,
                ],
            ]);

        $this->assertDatabaseCount('conference_records', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Dimension 2: Validation Failure Responses
    |--------------------------------------------------------------------------
    | Verify missing or invalid fields return HTTP 422 Unprocessable Content
    | with standard Laravel validation error structure.
    |--------------------------------------------------------------------------
    */

    public function test_api_sync_conference_validation_rejects_missing_conference_id_with_422(): void
    {
        $response = $this->postJson('/api/meet/conferences/sync', []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'The given data was invalid.',
            ])
            ->assertJsonValidationErrors(['conference_id']);
    }

    public function test_api_sync_conference_validation_rejects_empty_string_conference_id_with_422(): void
    {
        $response = $this->postJson('/api/meet/conferences/sync', [
            'conference_id' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['conference_id']);
    }

    public function test_api_sync_conference_validation_rejects_invalid_boolean_for_async_with_422(): void
    {
        $response = $this->postJson('/api/meet/conferences/sync', [
            'conference_id' => 'conf-test-valid',
            'async' => 'invalid-boolean-value',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['async']);
    }

    public function test_api_sync_conference_validation_rejects_invalid_meeting_room_id_type_with_422(): void
    {
        $response = $this->postJson('/api/meet/conferences/sync', [
            'conference_id' => 'conf-test-valid',
            'meeting_room_id' => 'not-an-integer',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['meeting_room_id']);
    }

    /*
    |--------------------------------------------------------------------------
    | Dimension 3: Resource Path Handling & Prefix Flexibility
    |--------------------------------------------------------------------------
    | Verify controllers and services accept IDs with or without
    | 'conferenceRecords/' and 'spaces/' prefixes, with leading slashes, etc.
    |--------------------------------------------------------------------------
    */

    public function test_api_get_conference_resolves_all_identifier_formats(): void
    {
        $confId = 'conf-path-test-999';
        GoogleMeetFake::fakeConferenceRecordWithFullData($confId);

        $service = app(GoogleMeetService::class);
        $record = $service->syncConferenceRecord($confId);

        // 1. Database primary key (numeric)
        $resPk = $this->getJson("/api/meet/conferences/{$record->id}");
        $resPk->assertStatus(200)
            ->assertJsonPath('data.id', $record->id);

        // 2. Canonical Google resource name: conferenceRecords/conf-path-test-999
        $resCanonical = $this->getJson("/api/meet/conferences/conferenceRecords/{$confId}");
        $resCanonical->assertStatus(200)
            ->assertJsonPath('data.conference_record_name', "conferenceRecords/{$confId}");

        // 3. Raw conference ID without prefix: conf-path-test-999
        $resRaw = $this->getJson("/api/meet/conferences/{$confId}");
        $resRaw->assertStatus(200)
            ->assertJsonPath('data.conference_record_name', "conferenceRecords/{$confId}");

        // 4. Non-existent conference ID returns 404
        $res404 = $this->getJson('/api/meet/conferences/conf-does-not-exist');
        $res404->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Conference record not found',
            ]);
    }

    public function test_api_get_room_resolves_all_identifier_formats(): void
    {
        GoogleMeetFake::fakeSpaceCreation();
        $room = app(GoogleMeetService::class)->createRestrictedRoom();

        // 1. Numeric database primary key
        $resPk = $this->getJson("/api/meet/rooms/{$room->id}");
        $resPk->assertStatus(200)
            ->assertJsonPath('data.id', $room->id);

        // 2. Canonical space name: spaces/sE4kP9qLmNt8
        $resSpace = $this->getJson("/api/meet/rooms/{$room->space_name}");
        $resSpace->assertStatus(200)
            ->assertJsonPath('data.space_name', 'spaces/sE4kP9qLmNt8');

        // 3. Raw space ID without 'spaces/' prefix: sE4kP9qLmNt8
        $rawSpaceId = str_replace('spaces/', '', $room->space_name);
        $resRaw = $this->getJson("/api/meet/rooms/{$rawSpaceId}");
        $resRaw->assertStatus(200)
            ->assertJsonPath('data.space_name', 'spaces/sE4kP9qLmNt8');

        // 4. Meeting code: zxc-vbnm-qwe
        $resCode = $this->getJson("/api/meet/rooms/{$room->meeting_code}");
        $resCode->assertStatus(200)
            ->assertJsonPath('data.meeting_code', 'zxc-vbnm-qwe');

        // 5. Non-existent room identifier returns 404
        $res404 = $this->getJson('/api/meet/rooms/non-existent-room-code');
        $res404->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Meeting room not found',
            ]);
    }

    public function test_api_sync_conference_accepts_prefixed_and_unprefixed_conference_ids(): void
    {
        // 1. Sync with raw ID
        $confRaw = 'conf-prefix-raw-01';
        GoogleMeetFake::fakeConferenceRecordWithFullData($confRaw);

        $resRaw = $this->postJson('/api/meet/conferences/sync', [
            'conference_id' => $confRaw,
            'async' => false,
        ]);
        $resRaw->assertStatus(200);
        $this->assertDatabaseHas('conference_records', [
            'conference_record_name' => "conferenceRecords/{$confRaw}",
        ]);

        // 2. Sync with canonical prefix
        $confPrefixed = 'conf-prefix-can-02';
        GoogleMeetFake::fakeConferenceRecordWithFullData($confPrefixed);

        $resPrefixed = $this->postJson('/api/meet/conferences/sync', [
            'conference_id' => "conferenceRecords/{$confPrefixed}",
            'async' => false,
        ]);
        $resPrefixed->assertStatus(200);
        $this->assertDatabaseHas('conference_records', [
            'conference_record_name' => "conferenceRecords/{$confPrefixed}",
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Dimension 4: Transactional Rollback & Database Integrity on Partial Failure
    |--------------------------------------------------------------------------
    | If conferenceRecords.get succeeds but participants.list fails (e.g. 500),
    | verify that the entire database transaction rolls back, leaving ZERO
    | orphaned records in the database.
    |--------------------------------------------------------------------------
    */

    public function test_sync_conference_rolls_back_atomically_if_participants_call_fails(): void
    {
        $confId = 'conf-partial-fail-part';
        GoogleMeetFake::fakeFullConferenceSync($confId, [
            'status' => 200,
            'participants_status' => 500, // participants fail
        ]);

        $response = $this->postJson('/api/meet/conferences/sync', [
            'conference_id' => $confId,
            'async' => false,
        ]);

        $response->assertStatus(502);

        // Entire transaction must be rolled back: 0 records
        $this->assertDatabaseCount('conference_records', 0);
        $this->assertDatabaseCount('conference_participants', 0);
        $this->assertDatabaseCount('conference_recordings', 0);
        $this->assertDatabaseCount('conference_transcripts', 0);
    }

    public function test_sync_conference_rolls_back_atomically_if_recordings_call_fails_with_500(): void
    {
        $confId = 'conf-partial-fail-rec';
        GoogleMeetFake::fakeFullConferenceSync($confId, [
            'status' => 200,
            'participants_status' => 200,
            'recordings_status' => 500, // recordings fail with 500
        ]);

        $response = $this->postJson('/api/meet/conferences/sync', [
            'conference_id' => $confId,
            'async' => false,
        ]);

        $response->assertStatus(502);

        // Entire transaction must be rolled back
        $this->assertDatabaseCount('conference_records', 0);
        $this->assertDatabaseCount('conference_participants', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Dimension 5: Hostile Inputs, SQL Injection & Path Traversal Probes
    |--------------------------------------------------------------------------
    | Verify endpoints safely handle hostile inputs without unhandled exceptions
    | or SQL injection vulnerabilities.
    |--------------------------------------------------------------------------
    */

    public function test_api_endpoints_gracefully_handle_adversarial_id_probes(): void
    {
        $probes = [
            "1' OR '1'='1",
            "1; DROP TABLE meeting_rooms; --",
            "../../etc/passwd",
            "<script>alert(1)</script>",
            "spaces/../../../etc/shadow",
            str_repeat('a', 500),
        ];

        foreach ($probes as $probe) {
            $confRes = $this->getJson('/api/meet/conferences/' . urlencode($probe));
            $this->assertSame(404, $confRes->status(), "Failed probe on conferences: {$probe}");
            $this->assertFalse($confRes->json('success'));

            $roomRes = $this->getJson('/api/meet/rooms/' . urlencode($probe));
            $this->assertSame(404, $roomRes->status(), "Failed probe on rooms: {$probe}");
            $this->assertFalse($roomRes->json('success'));
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Dimension 6: Artisan CLI Error Mapping & Exit Codes
    |--------------------------------------------------------------------------
    | Verify CLI sync command exits with code 1 and outputs structured error
    | on upstream 404 and 429 errors.
    |--------------------------------------------------------------------------
    */

    public function test_artisan_sync_conference_upstream_404_exits_with_code_1_and_leaves_db_clean(): void
    {
        $confId = 'conf-cli-404-check';
        GoogleMeetFake::fakeConferenceRecord($confId, [], 404);

        $this->artisan('meet:sync-conference', ['conferenceId' => $confId])
            ->assertExitCode(1)
            ->expectsOutputToContain('[Google Meet API 404]');

        $this->assertDatabaseCount('conference_records', 0);
    }

    public function test_artisan_sync_conference_upstream_429_exits_with_code_1_and_leaves_db_clean(): void
    {
        $confId = 'conf-cli-429-check';
        GoogleMeetFake::fakeConferenceRecord($confId, [], 429);

        $this->artisan('meet:sync-conference', ['conferenceId' => $confId])
            ->assertExitCode(1)
            ->expectsOutputToContain('[Google Meet API 429]');

        $this->assertDatabaseCount('conference_records', 0);
    }
}

