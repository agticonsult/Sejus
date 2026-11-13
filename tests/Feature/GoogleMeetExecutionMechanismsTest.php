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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\Support\GoogleMeetFake;
use Tests\TestCase;

class GoogleMeetExecutionMechanismsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        GoogleMeetFake::reset();
    }

    /*
    |--------------------------------------------------------------------------
    | Artisan Console Command Tests (meet:sync-conference)
    |--------------------------------------------------------------------------
    */

    public function test_artisan_sync_conference_synchronous_success_outputs_table_and_persists_data(): void
    {
        $confId = 'conf-cli-001';
        $canonicalName = "conferenceRecords/{$confId}";
        GoogleMeetFake::fakeConferenceRecordWithFullData($confId);

        $this->artisan('meet:sync-conference', ['conferenceId' => $confId])
            ->assertExitCode(0)
            ->expectsOutputToContain($canonicalName);

        // Assert 1 conference record, 2 participants, 1 recording, 1 transcript
        $this->assertDatabaseCount('conference_records', 1);
        $this->assertDatabaseCount('conference_participants', 2);
        $this->assertDatabaseCount('conference_recordings', 1);
        $this->assertDatabaseCount('conference_transcripts', 1);

        $this->assertDatabaseHas('conference_records', [
            'conference_record_name' => $canonicalName,
            'space_name' => 'spaces/sE4kP9qLmNt8',
            'status' => 'ended',
        ]);

        $this->assertDatabaseHas('conference_participants', [
            'participant_name' => "{$canonicalName}/participants/part-1",
            'display_name' => 'Dr. Carlos Eduardo',
        ]);

        $this->assertDatabaseHas('conference_recordings', [
            'recording_name' => "{$canonicalName}/recordings/rec-1",
            'drive_file_id' => '1XyZ987AbCdEfGhIjKlMnOpQrStUvWxYz',
        ]);

        $this->assertDatabaseHas('conference_transcripts', [
            'transcript_name' => "{$canonicalName}/transcripts/trans-1",
            'docs_document_id' => '1DocId998877665544332211AABBCCDDEEFF',
        ]);
    }

    public function test_artisan_sync_conference_queue_option_pushes_job_without_immediate_db_writes(): void
    {
        Queue::fake();
        $confId = 'conf-cli-queue-002';

        $this->artisan('meet:sync-conference', [
            'conferenceId' => $confId,
            '--queue' => true,
        ])
            ->assertExitCode(0);

        Queue::assertPushed(SyncGoogleMeetConferenceJob::class, function ($job) use ($confId) {
            return str_contains($job->conferenceRecordName, $confId);
        });

        // Ensure synchronous DB sync did not happen
        $this->assertDatabaseCount('conference_records', 0);
    }

    public function test_artisan_sync_conference_failure_returns_exit_code_1_and_rolls_back(): void
    {
        $confId = 'conf-cli-fail-003';
        GoogleMeetFake::fakeConferenceRecord($confId, [], 500);

        $this->artisan('meet:sync-conference', ['conferenceId' => $confId])
            ->assertExitCode(1);

        $this->assertDatabaseCount('conference_records', 0);
        $this->assertDatabaseCount('conference_participants', 0);
    }

    public function test_artisan_sync_conference_handles_prefix_normalization(): void
    {
        $confId = 'conf-norm-004';
        GoogleMeetFake::fakeConferenceRecordWithFullData($confId);

        // Call with prefix
        $this->artisan('meet:sync-conference', ['conferenceId' => "conferenceRecords/{$confId}"])
            ->assertExitCode(0);

        $this->assertDatabaseHas('conference_records', [
            'conference_record_name' => "conferenceRecords/{$confId}",
        ]);

        // Call with nested space URL format
        $confNestedId = 'conf-norm-005';
        GoogleMeetFake::fakeConferenceRecordWithFullData($confNestedId);

        $this->artisan('meet:sync-conference', ['conferenceId' => "spaces/sE4kP9qLmNt8/conferences/{$confNestedId}"])
            ->assertExitCode(0);

        $this->assertDatabaseHas('conference_records', [
            'conference_record_name' => "conferenceRecords/{$confNestedId}",
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Queued Background Job Tests (SyncGoogleMeetConferenceJob)
    |--------------------------------------------------------------------------
    */

    public function test_queued_job_handle_executes_conference_sync_and_persists_data(): void
    {
        $confId = 'conf-job-001';
        $canonicalName = "conferenceRecords/{$confId}";
        GoogleMeetFake::fakeConferenceRecordWithFullData($confId);

        $job = new SyncGoogleMeetConferenceJob($confId);
        $job->handle(app(GoogleMeetService::class));

        $this->assertDatabaseCount('conference_records', 1);
        $this->assertDatabaseCount('conference_participants', 2);
        $this->assertDatabaseCount('conference_recordings', 1);
        $this->assertDatabaseCount('conference_transcripts', 1);

        $this->assertDatabaseHas('conference_records', [
            'conference_record_name' => $canonicalName,
            'status' => 'ended',
        ]);
    }

    public function test_queued_job_has_resilience_configuration_and_implements_should_queue(): void
    {
        $job = new SyncGoogleMeetConferenceJob('conf-retry-check');

        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 30, 60], $job->backoff);
        $this->assertSame(120, $job->timeout);
        $this->assertSame('conf-retry-check', $job->conferenceRecordName);
    }

    public function test_sync_google_meet_conference_job_failed_method_logs_error(): void
    {
        Log::spy();
        $job = new SyncGoogleMeetConferenceJob('conf-fail-log');

        $job->failed(new \RuntimeException('Google Meet API timeout test exception'));

        Log::shouldHaveReceived('error')->atLeast()->once();
    }

    /*
    |--------------------------------------------------------------------------
    | REST API Endpoint Tests (/api/meet/...)
    |--------------------------------------------------------------------------
    */

    public function test_api_create_restricted_room_returns_201_and_persists_database_room(): void
    {
        GoogleMeetFake::fakeSpaceCreation();

        $response = $this->postJson('/api/meet/rooms');

        $response->assertStatus(201);
        GoogleMeetFake::assertSpaceCreatedWithRestrictions();

        // Support either root JSON or data envelope
        $uri = $response->json('meeting_uri') ?? $response->json('data.meeting_uri');
        $code = $response->json('meeting_code') ?? $response->json('data.meeting_code');

        $this->assertSame('https://meet.google.com/zxc-vbnm-qwe', $uri);
        $this->assertSame('zxc-vbnm-qwe', $code);

        $this->assertDatabaseHas('meeting_rooms', [
            'space_name' => 'spaces/sE4kP9qLmNt8',
            'meeting_code' => 'zxc-vbnm-qwe',
            'access_type' => 'OPEN',
            'status' => 'active',
        ]);
    }

    public function test_api_sync_conference_synchronous_returns_200_and_persists_full_records(): void
    {
        $confId = 'conf-api-sync-001';
        GoogleMeetFake::fakeConferenceRecordWithFullData($confId);

        $response = $this->postJson('/api/meet/conferences/sync', [
            'conference_id' => $confId,
            'async' => false,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseCount('conference_records', 1);
        $this->assertDatabaseCount('conference_participants', 2);
        $this->assertDatabaseCount('conference_recordings', 1);
        $this->assertDatabaseCount('conference_transcripts', 1);

        $this->assertDatabaseHas('conference_records', [
            'conference_record_name' => "conferenceRecords/{$confId}",
            'status' => 'ended',
        ]);
    }

    public function test_api_sync_conference_async_returns_202_and_dispatches_queued_job(): void
    {
        Queue::fake();
        $confId = 'conf-api-async-002';

        $response = $this->postJson('/api/meet/conferences/sync', [
            'conference_id' => $confId,
            'async' => true,
        ]);

        $response->assertStatus(202);

        Queue::assertPushed(SyncGoogleMeetConferenceJob::class, function ($job) use ($confId) {
            return str_contains($job->conferenceRecordName, $confId);
        });

        $this->assertDatabaseCount('conference_records', 0);
    }

    public function test_api_get_conference_returns_200_with_relations_or_404_when_missing(): void
    {
        $confId = 'conf-api-show-001';
        GoogleMeetFake::fakeConferenceRecordWithFullData($confId);

        $service = app(GoogleMeetService::class);
        $record = $service->syncConferenceRecord($confId);

        $response = $this->getJson("/api/meet/conferences/{$record->id}");

        $response->assertStatus(200);

        // Support either direct root or data envelope
        $participants = $response->json('participants') ?? $response->json('data.participants');
        $recordings = $response->json('recordings') ?? $response->json('data.recordings');
        $transcripts = $response->json('transcripts') ?? $response->json('data.transcripts');

        $this->assertIsArray($participants);
        $this->assertCount(2, $participants);
        $this->assertIsArray($recordings);
        $this->assertCount(1, $recordings);
        $this->assertIsArray($transcripts);
        $this->assertCount(1, $transcripts);

        // Query by canonical resource name
        $responseByName = $this->getJson("/api/meet/conferences/{$record->conference_record_name}");
        $responseByName->assertStatus(200);

        // 404 assertions
        $this->getJson('/api/meet/conferences/999999')->assertStatus(404);
        $this->getJson('/api/meet/conferences/non-existent-conf')->assertStatus(404);
    }

    public function test_api_get_room_returns_200_or_404_when_missing(): void
    {
        GoogleMeetFake::fakeSpaceCreation();
        $room = app(GoogleMeetService::class)->createRestrictedRoom();

        $response = $this->getJson("/api/meet/rooms/{$room->id}");
        $response->assertStatus(200);

        $responseBySpace = $this->getJson("/api/meet/rooms/{$room->space_name}");
        $responseBySpace->assertStatus(200);

        $responseByCode = $this->getJson("/api/meet/rooms/{$room->meeting_code}");
        $responseByCode->assertStatus(200);

        $this->getJson('/api/meet/rooms/999999')->assertStatus(404);
    }

    public function test_api_endpoints_map_google_api_exceptions_to_proper_http_status_codes(): void
    {
        // 1. Room creation 500 error maps to 502 Bad Gateway
        GoogleMeetFake::fakeSpaceCreationFailure(500);
        $responseRoom = $this->postJson('/api/meet/rooms');
        $this->assertContains($responseRoom->status(), [500, 502]);
        $this->assertDatabaseCount('meeting_rooms', 0);

        // 2. Conference sync 500 error maps to 502 Bad Gateway
        $confId = 'conf-api-fail-500';
        GoogleMeetFake::fakeConferenceRecord($confId, [], 500);
        $responseSync = $this->postJson('/api/meet/conferences/sync', [
            'conference_id' => $confId,
            'async' => false,
        ]);
        $this->assertContains($responseSync->status(), [500, 502]);
        $this->assertDatabaseCount('conference_records', 0);

        // 3. Conference sync 404 error maps to 404 Not Found
        $confId404 = 'conf-api-fail-404';
        GoogleMeetFake::fakeConferenceRecord($confId404, [], 404);
        $responseSync404 = $this->postJson('/api/meet/conferences/sync', [
            'conference_id' => $confId404,
            'async' => false,
        ]);
        $this->assertSame(404, $responseSync404->status());

        // 4. Conference sync 429 error maps to 429 Too Many Requests
        $confId429 = 'conf-api-fail-429';
        GoogleMeetFake::fakeConferenceRecord($confId429, [], 429);
        $responseSync429 = $this->postJson('/api/meet/conferences/sync', [
            'conference_id' => $confId429,
            'async' => false,
        ]);
        $this->assertSame(429, $responseSync429->status());
    }

    public function test_api_post_conferences_sync_validation_rejects_missing_conference_id_with_422(): void
    {
        $response = $this->postJson('/api/meet/conferences/sync', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['conference_id']);
    }
}
