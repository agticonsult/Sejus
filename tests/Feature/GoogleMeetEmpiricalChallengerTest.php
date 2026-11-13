<?php

namespace Tests\Feature;

use App\Jobs\SyncGoogleMeetConferenceJob;
use App\Models\ConferenceParticipant;
use App\Models\ConferenceRecord;
use App\Models\ConferenceRecording;
use App\Models\ConferenceTranscript;
use App\Models\ConferenceTranscriptEntry;
use App\Models\MeetingRoom;
use App\Services\GoogleMeet\GoogleMeetService;
use Illuminate\Support\Facades\Queue;
use Tests\Support\GoogleMeetFake;
use Tests\TestCase;

class GoogleMeetEmpiricalChallengerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        GoogleMeetFake::reset();
    }

    /**
     * EMPIRICAL TEST 1: Full CLI sync populates ALL 5 conference tables, outputs formatted table, and returns exit code 0.
     */
    public function test_cli_sync_populates_all_five_tables_and_outputs_table(): void
    {
        $confId = 'conf-empirical-all-5';
        $canonical = "conferenceRecords/{$confId}";
        GoogleMeetFake::fakeConferenceRecordWithFullData($confId);

        $exitCode = \Illuminate\Support\Facades\Artisan::call('meet:sync-conference', [
            'conferenceId' => $confId,
        ]);

        $this->assertSame(0, $exitCode);

        $output = \Illuminate\Support\Facades\Artisan::output();
        $this->assertStringContainsString($canonical, $output);
        $this->assertStringContainsString('Property', $output);
        $this->assertStringContainsString('Value', $output);
        $this->assertStringContainsString('Conference Record', $output);
        $this->assertStringContainsString('Space Name', $output);
        $this->assertStringContainsString('Status', $output);
        $this->assertStringContainsString('Participants Count', $output);
        $this->assertStringContainsString('Recordings Count', $output);
        $this->assertStringContainsString('Transcripts Count', $output);

        // Verify counts across all 5 conference tables
        $this->assertDatabaseCount('conference_records', 1);
        $this->assertDatabaseCount('conference_participants', 2);
        $this->assertDatabaseCount('conference_recordings', 1);
        $this->assertDatabaseCount('conference_transcripts', 1);
        $this->assertDatabaseCount('conference_transcript_entries', 2);

        // Verify relational integrity
        $record = ConferenceRecord::where('conference_record_name', $canonical)->first();
        $this->assertNotNull($record);
        $this->assertCount(2, $record->participants);
        $this->assertCount(1, $record->recordings);
        $this->assertCount(1, $record->transcripts);

        $transcript = $record->transcripts->first();
        $this->assertCount(2, $transcript->entries);
        $this->assertDatabaseHas('conference_transcript_entries', [
            'conference_transcript_id' => $transcript->id,
            'speaker_name' => 'Dr. Carlos Eduardo',
            'text' => 'Início do atendimento.',
        ]);
        $this->assertDatabaseHas('conference_transcript_entries', [
            'conference_transcript_id' => $transcript->id,
            'speaker_name' => 'Marcos Vinicius',
            'text' => 'Presente.',
        ]);
    }

    /**
     * EMPIRICAL TEST 2: Queued execution via CLI --queue pushes job, outputs notice, zero immediate writes.
     */
    public function test_cli_sync_with_queue_option_pushes_job_and_leaves_all_tables_empty(): void
    {
        Queue::fake();
        $confId = 'conf-cli-queue-emp';

        $exitCode = $this->artisan('meet:sync-conference', [
            'conferenceId' => $confId,
            '--queue' => true,
        ])
            ->expectsOutputToContain('[QUEUED] Conference sync queued for processing')
            ->run();

        $this->assertSame(0, $exitCode);

        Queue::assertPushed(SyncGoogleMeetConferenceJob::class, function (SyncGoogleMeetConferenceJob $job) use ($confId) {
            return $job->conferenceRecordName === "conferenceRecords/{$confId}";
        });

        // Ensure zero DB writes occurred across all tables
        $this->assertDatabaseCount('conference_records', 0);
        $this->assertDatabaseCount('conference_participants', 0);
        $this->assertDatabaseCount('conference_recordings', 0);
        $this->assertDatabaseCount('conference_transcripts', 0);
        $this->assertDatabaseCount('conference_transcript_entries', 0);
    }

    /**
     * EMPIRICAL TEST 3: Queued job execution in worker environment persists full data across all 5 tables.
     */
    public function test_queued_job_execution_in_worker_context_persists_all_five_tables(): void
    {
        $confId = 'conf-worker-exec-emp';
        GoogleMeetFake::fakeConferenceRecordWithFullData($confId);

        $job = new SyncGoogleMeetConferenceJob("conferenceRecords/{$confId}");
        $job->handle(app(GoogleMeetService::class));

        $this->assertDatabaseCount('conference_records', 1);
        $this->assertDatabaseCount('conference_participants', 2);
        $this->assertDatabaseCount('conference_recordings', 1);
        $this->assertDatabaseCount('conference_transcripts', 1);
        $this->assertDatabaseCount('conference_transcript_entries', 2);
    }

    /**
     * EMPIRICAL TEST 4: API POST /api/meet/conferences/sync with async=true returns 202 and pushes job.
     */
    public function test_api_async_sync_returns_202_and_pushes_job(): void
    {
        Queue::fake();
        $confId = 'conf-api-async-emp';

        $response = $this->postJson('/api/meet/conferences/sync', [
            'conference_id' => $confId,
            'async' => true,
        ]);

        $response->assertStatus(202)
            ->assertJson([
                'success' => true,
                'message' => 'Conference sync queued',
                'conference_id' => $confId,
                'data' => [
                    'conference_id' => $confId,
                    'queued' => true,
                    'async' => true,
                ],
            ]);

        Queue::assertPushed(SyncGoogleMeetConferenceJob::class, function (SyncGoogleMeetConferenceJob $job) use ($confId) {
            return $job->conferenceRecordName === $confId;
        });

        $this->assertDatabaseCount('conference_records', 0);
    }

    /**
     * EMPIRICAL TEST 5: Atomic transaction rollback when recordings API fails with 500.
     * Macro and participants must be rolled back completely — zero orphaned rows in DB.
     */
    public function test_atomic_transaction_rollback_when_recordings_fail_midway(): void
    {
        $confId = 'conf-fail-rec-midway';
        GoogleMeetFake::fakeFullConferenceSync($confId, [
            'recordings_status' => 500,
        ]);

        $exitCode = $this->artisan('meet:sync-conference', ['conferenceId' => $confId])
            ->expectsOutputToContain('Failed to sync conference')
            ->run();

        $this->assertSame(1, $exitCode);

        // Strict verification: zero records in ANY of the 5 tables
        $this->assertDatabaseCount('conference_records', 0);
        $this->assertDatabaseCount('conference_participants', 0);
        $this->assertDatabaseCount('conference_recordings', 0);
        $this->assertDatabaseCount('conference_transcripts', 0);
        $this->assertDatabaseCount('conference_transcript_entries', 0);
    }

    /**
     * EMPIRICAL TEST 6: Atomic transaction rollback when transcript entries API fails with 500.
     * Macro, participants, recordings, and transcripts must be rolled back completely.
     */
    public function test_atomic_transaction_rollback_when_transcript_entries_fail(): void
    {
        $confId = 'conf-fail-entries-midway';
        GoogleMeetFake::fakeFullConferenceSync($confId, [
            'entries_status' => 500,
        ]);

        $exitCode = $this->artisan('meet:sync-conference', ['conferenceId' => $confId])
            ->expectsOutputToContain('Failed to sync conference')
            ->run();

        $this->assertSame(1, $exitCode);

        // Strict verification: zero records in ANY of the 5 tables
        $this->assertDatabaseCount('conference_records', 0);
        $this->assertDatabaseCount('conference_participants', 0);
        $this->assertDatabaseCount('conference_recordings', 0);
        $this->assertDatabaseCount('conference_transcripts', 0);
        $this->assertDatabaseCount('conference_transcript_entries', 0);
    }

    /**
     * EMPIRICAL TEST 7: Race condition stress test — Rapid concurrent/repeated synchronization of same conference.
     * Asserts strictly idempotent update without duplicate keys, deadlocks, or row multiplication.
     */
    public function test_race_condition_and_idempotency_under_repeated_synchronization(): void
    {
        $confId = 'conf-race-repeat-001';
        GoogleMeetFake::fakeConferenceRecordWithFullData($confId);

        // Sync #1
        $exitCode1 = $this->artisan('meet:sync-conference', ['conferenceId' => $confId])->run();
        $this->assertSame(0, $exitCode1);

        // Sync #2 (immediate repeat)
        $exitCode2 = $this->artisan('meet:sync-conference', ['conferenceId' => $confId])->run();
        $this->assertSame(0, $exitCode2);

        // Sync #3 via API
        $response = $this->postJson('/api/meet/conferences/sync', [
            'conference_id' => $confId,
            'async' => false,
        ]);
        $response->assertStatus(200);

        // Counts must remain strictly 1 record, 2 participants, 1 recording, 1 transcript, 2 entries
        $this->assertDatabaseCount('conference_records', 1);
        $this->assertDatabaseCount('conference_participants', 2);
        $this->assertDatabaseCount('conference_recordings', 1);
        $this->assertDatabaseCount('conference_transcripts', 1);
        $this->assertDatabaseCount('conference_transcript_entries', 2);
    }

    /**
     * EMPIRICAL TEST 8A: Prefix and whitespace normalization without trailing slash.
     */
    public function test_cli_normalization_handles_spaces_and_leading_slashes(): void
    {
        // Leading/trailing spaces and leading slashes
        $confId1 = 'conf-norm-space-01';
        GoogleMeetFake::fakeConferenceRecordWithFullData($confId1);
        $exitCode1 = $this->artisan('meet:sync-conference', ['conferenceId' => "  /conferenceRecords/{$confId1}  "])->run();
        $this->assertSame(0, $exitCode1);
        $this->assertDatabaseHas('conference_records', ['conference_record_name' => "conferenceRecords/{$confId1}"]);

        // Nested space format with whitespace
        $confId2 = 'conf-norm-nested-02';
        GoogleMeetFake::fakeConferenceRecordWithFullData($confId2);
        $exitCode2 = $this->artisan('meet:sync-conference', ['conferenceId' => " spaces/sE4kP9qLmNt8/conferences/{$confId2} "])->run();
        $this->assertSame(0, $exitCode2);
        $this->assertDatabaseHas('conference_records', ['conference_record_name' => "conferenceRecords/{$confId2}"]);
    }

    /**
     * EMPIRICAL TEST 8B: Trailing slash in conferenceId input behavior.
     * Note: normalizeConferenceId strips leading slashes with ltrim, but not trailing slashes.
     * When trailing slash is present, Google Meet API rejects it (or mock returns 404/failure), returning exit code 1.
     */
    public function test_cli_normalization_with_trailing_slash_returns_exit_code_1_when_unmatched(): void
    {
        $confId = 'conf-norm-trailing-03';
        GoogleMeetFake::fakeConferenceRecordWithFullData($confId);

        $exitCode = $this->artisan('meet:sync-conference', ['conferenceId' => "conferenceRecords/{$confId}/"])->run();
        
        // Assert that unhandled trailing slash causes failure exit code 1 without corrupting database
        $this->assertSame(1, $exitCode);
        $this->assertDatabaseCount('conference_records', 0);
    }

    /**
     * EMPIRICAL TEST 9: Graceful degradation when optional recordings and transcripts return 404 (disabled features).
     */
    public function test_graceful_degradation_when_recordings_and_transcripts_are_404(): void
    {
        $confId = 'conf-optional-404';
        GoogleMeetFake::fakeFullConferenceSync($confId, [
            'recordings_status' => 404,
            'transcripts_status' => 404,
        ]);

        $exitCode = $this->artisan('meet:sync-conference', ['conferenceId' => $confId])->run();
        $this->assertSame(0, $exitCode);

        // Macro and participants should be persisted successfully (sampleParticipantsResponse returns 3 participants)
        $this->assertDatabaseCount('conference_records', 1);
        $this->assertDatabaseCount('conference_participants', 3);
        $this->assertDatabaseCount('conference_recordings', 0);
        $this->assertDatabaseCount('conference_transcripts', 0);
        $this->assertDatabaseCount('conference_transcript_entries', 0);
    }

    /**
     * EMPIRICAL TEST 10: API validation rejects invalid payloads with 422.
     */
    public function test_api_sync_validation_rejects_invalid_inputs(): void
    {
        // 1. Missing conference_id
        $this->postJson('/api/meet/conferences/sync', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['conference_id']);

        // 2. Invalid meeting_room_id type
        $this->postJson('/api/meet/conferences/sync', [
            'conference_id' => 'conf-valid-id',
            'meeting_room_id' => 'not-an-integer',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['meeting_room_id']);

        // 3. Non-boolean async value
        $this->postJson('/api/meet/conferences/sync', [
            'conference_id' => 'conf-valid-id',
            'async' => 'not-a-bool',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['async']);
    }

    /**
     * EMPIRICAL TEST 11: API GET /api/meet/conferences/{id} resolves by DB ID, canonical name, and raw ID.
     */
    public function test_api_get_conference_resolution_routes(): void
    {
        $confId = 'conf-show-route-test';
        GoogleMeetFake::fakeConferenceRecordWithFullData($confId);

        $record = app(GoogleMeetService::class)->syncConferenceRecord($confId);

        // 1. By integer ID
        $this->getJson("/api/meet/conferences/{$record->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.conference_record_name', "conferenceRecords/{$confId}");

        // 2. By canonical name
        $this->getJson("/api/meet/conferences/{$record->conference_record_name}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $record->id);

        // 3. By raw ID without conferenceRecords/ prefix
        $this->getJson("/api/meet/conferences/{$confId}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $record->id);
    }

    /**
     * EMPIRICAL TEST 12: API GET /api/meet/rooms/{id} resolves by DB ID, space name, and meeting code.
     */
    public function test_api_get_room_resolution_routes(): void
    {
        GoogleMeetFake::fakeSpaceCreation();
        $room = app(GoogleMeetService::class)->createRestrictedRoom();

        // 1. By integer ID
        $this->getJson("/api/meet/rooms/{$room->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.space_name', $room->space_name);

        // 2. By space_name with spaces/
        $this->getJson("/api/meet/rooms/{$room->space_name}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $room->id);

        // 3. By meeting_code
        $this->getJson("/api/meet/rooms/{$room->meeting_code}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $room->id);
    }
}
