<?php

namespace Tests\Unit;

use Tests\TestCase;
use Tests\Support\GoogleMeetFake;
use App\Services\GoogleMeet\GoogleMeetService;
use App\Services\GoogleMeet\Contracts\MeetApiClientInterface;
use App\Services\GoogleMeet\Clients\HttpMeetApiClient;
use App\Services\GoogleMeet\Exceptions\GoogleMeetApiException;
use App\Models\MeetingRoom;
use App\Models\ConferenceRecord;
use App\Models\ConferenceParticipant;
use App\Models\ConferenceRecording;
use App\Models\ConferenceTranscript;
use App\Models\ConferenceTranscriptEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class AdversarialConferenceSyncTest extends TestCase
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
     * ADVERSARIAL STRESS TEST 1: Rapid Succession Idempotency (10 rapid syncs)
     * Triggers sync 10 times in rapid succession.
     * Asserts that exactly 1 conference record, 3 participants, 1 recording, 1 transcript, and 2 entries exist.
     * Asserts no duplicate records are created in any of the 5 tables.
     */
    public function test_rapid_succession_sync_is_strictly_idempotent(): void
    {
        $confId = 'conf-stress-rapid-001';
        GoogleMeetFake::fakeFullConferenceSync($confId);

        // Execute sync 10 times consecutively
        $initialRecord = null;
        for ($i = 0; $i < 10; $i++) {
            $record = $this->meetService->syncConferenceRecord($confId);
            if ($i === 0) {
                $initialRecord = $record;
            } else {
                $this->assertSame($initialRecord->id, $record->id, "Record ID changed on iteration {$i}");
            }
        }

        // Verify zero duplicates in any table
        $this->assertSame(1, ConferenceRecord::count(), 'conference_records count should be exactly 1');
        $this->assertSame(3, ConferenceParticipant::count(), 'conference_participants count should be exactly 3');
        $this->assertSame(1, ConferenceRecording::count(), 'conference_recordings count should be exactly 1');
        $this->assertSame(1, ConferenceTranscript::count(), 'conference_transcripts count should be exactly 1');
        $this->assertSame(2, ConferenceTranscriptEntry::count(), 'conference_transcript_entries count should be exactly 2');
    }

    /**
     * ADVERSARIAL STRESS TEST 2: Empty Collections Resilience
     * Test when recordings.list or transcripts.list returns an empty array [] or missing keys.
     * Sync must complete without throwing errors and persist valid empty collections.
     */
    public function test_empty_recordings_and_transcripts_payloads_do_not_crash(): void
    {
        $confId = 'conf-stress-empty-002';
        GoogleMeetFake::fakeFullConferenceSync($confId, [
            'recordings' => [],
            'transcripts' => [],
        ]);

        $record = $this->meetService->syncConferenceRecord($confId);

        $this->assertInstanceOf(ConferenceRecord::class, $record);
        $this->assertSame("conferenceRecords/{$confId}", $record->conference_record_name);
        $this->assertSame(0, $record->recordings()->count());
        $this->assertSame(0, $record->transcripts()->count());
        $this->assertSame(0, ConferenceRecording::count());
        $this->assertSame(0, ConferenceTranscript::count());
        $this->assertSame(0, ConferenceTranscriptEntry::count());
        $this->assertFalse($record->hasRecordings());
        $this->assertFalse($record->hasTranscripts());
    }

    /**
     * ADVERSARIAL STRESS TEST 3: Completely empty responses (missing all collection keys)
     */
    public function test_completely_empty_api_responses_without_keys(): void
    {
        $confId = 'conf-stress-bare-003';
        GoogleMeetFake::reset();

        // Custom mocks returning bare empty arrays without 'participants', 'recordings', 'transcripts' keys
        Http::fake([
            GoogleMeetFake::BASE_URL . "/conferenceRecords/{$confId}/participants*" => Http::response([], 200),
            GoogleMeetFake::BASE_URL . "/conferenceRecords/{$confId}/recordings*" => Http::response([], 200),
            GoogleMeetFake::BASE_URL . "/conferenceRecords/{$confId}/transcripts*" => Http::response([], 200),
            GoogleMeetFake::BASE_URL . "/conferenceRecords/{$confId}" => Http::response([
                'name' => "conferenceRecords/{$confId}",
                'space' => 'spaces/bare-space',
                'startTime' => '2026-11-02T10:00:00Z',
                'endTime' => '2026-11-02T10:30:00Z',
            ], 200),
        ]);

        $record = $this->meetService->syncConferenceRecord($confId);

        $this->assertInstanceOf(ConferenceRecord::class, $record);
        $this->assertSame(1, ConferenceRecord::count());
        $this->assertSame(0, ConferenceParticipant::count());
        $this->assertSame(0, ConferenceRecording::count());
        $this->assertSame(0, ConferenceTranscript::count());
    }

    /**
     * ADVERSARIAL STRESS TEST 4: Atomicity & Transaction Rollback on Participants API Error (HTTP 500)
     * When listParticipants fails with HTTP 500, syncConferenceRecord must rethrow GoogleMeetApiException
     * and DB::transaction must roll back completely so 0 orphaned records are persisted in conference_records.
     */
    public function test_participants_500_rethrows_exception_and_rolls_back_transaction(): void
    {
        $confId = 'conf-fail-participants-500';

        GoogleMeetFake::fakeFullConferenceSync($confId, [
            'participants_status' => 500,
        ]);

        $this->assertDatabaseCount('conference_records', 0);

        try {
            $this->meetService->syncConferenceRecord($confId);
            $this->fail('Expected GoogleMeetApiException was not thrown on participants HTTP 500');
        } catch (GoogleMeetApiException $e) {
            $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : $e->getCode();
            $this->assertSame(500, $statusCode);
            $this->assertStringContainsString('500', $e->getMessage());
        }

        // Assert DB::transaction rolled back cleanly: exactly 0 records persisted across all tables
        $this->assertDatabaseCount('conference_records', 0);
        $this->assertDatabaseCount('conference_participants', 0);
        $this->assertDatabaseCount('conference_recordings', 0);
        $this->assertDatabaseCount('conference_transcripts', 0);
        $this->assertDatabaseCount('conference_transcript_entries', 0);
    }

    /**
     * ADVERSARIAL STRESS TEST 5: Atomicity & Transaction Rollback on Participants Connection Timeout
     * When listParticipants encounters a network timeout / ConnectionException,
     * it must bubble up as GoogleMeetApiException and roll back DB::transaction completely.
     */
    public function test_participants_timeout_rethrows_exception_and_rolls_back_transaction(): void
    {
        $confId = 'conf-fail-participants-timeout';
        GoogleMeetFake::reset();

        Http::fake([
            GoogleMeetFake::BASE_URL . "/conferenceRecords/{$confId}/participants*" => function () {
                throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out after 15000 milliseconds');
            },
            GoogleMeetFake::BASE_URL . "/conferenceRecords/{$confId}/recordings*" => Http::response(
                GoogleMeetFake::sampleRecordingsResponse([], $confId), 200
            ),
            GoogleMeetFake::BASE_URL . "/conferenceRecords/{$confId}/transcripts*" => Http::response(
                GoogleMeetFake::sampleTranscriptsResponse([], $confId), 200
            ),
            GoogleMeetFake::BASE_URL . "/conferenceRecords/{$confId}/transcripts/*/entries*" => Http::response(
                GoogleMeetFake::sampleTranscriptEntriesResponse([], $confId), 200
            ),
            GoogleMeetFake::BASE_URL . "/conferenceRecords/{$confId}" => Http::response(
                GoogleMeetFake::sampleConferenceRecordResponse([], $confId), 200
            ),
        ]);

        $this->assertDatabaseCount('conference_records', 0);

        try {
            $this->meetService->syncConferenceRecord($confId);
            $this->fail('Expected GoogleMeetApiException was not thrown on participants connection timeout');
        } catch (GoogleMeetApiException $e) {
            $this->assertStringContainsString('connection failed', strtolower($e->getMessage()));
        }

        // Assert DB::transaction rolled back cleanly: exactly 0 records persisted
        $this->assertDatabaseCount('conference_records', 0);
        $this->assertDatabaseCount('conference_participants', 0);
        $this->assertDatabaseCount('conference_recordings', 0);
        $this->assertDatabaseCount('conference_transcripts', 0);
        $this->assertDatabaseCount('conference_transcript_entries', 0);
    }

    /**
     * ADVERSARIAL STRESS TEST 6: Adversarial string inputs & SQL injection robustness
     * Asserts that participant names with single quotes, dashes, unicode emojis, and SQL injection payloads
     * are safely escaped and persisted without syntax errors or corrupting queries.
     */
    public function test_adversarial_participant_and_speaker_inputs_are_safely_persisted(): void
    {
        $confId = 'conf-adv-inputs-008';
        $maliciousParticipant = "conferenceRecords/{$confId}/participants/part-sql-injection-'; DROP TABLE conference_records;--";
        $adversarialDisplayName = "Dr. Robert'); DROP TABLE users;-- 🧑‍⚖️ \"'\\ <script>alert(1)</script>";

        GoogleMeetFake::fakeFullConferenceSync($confId, [
            'participants' => [
                [
                    'name' => $maliciousParticipant,
                    'signedinUser' => [
                        'user' => 'users/sql-injection-attack',
                        'displayName' => $adversarialDisplayName,
                    ],
                    'earliestStartTime' => '2026-11-02T14:00:00Z',
                    'latestEndTime' => '2026-11-02T15:00:00Z',
                ],
            ],
            'transcripts' => [
                [
                    'name' => "conferenceRecords/{$confId}/transcripts/trans-injection",
                    'state' => 'FILE_GENERATED',
                    'docsDestination' => [
                        'document' => "doc-inject-';--",
                        'exportUri' => "https://docs.google.com/document/d/doc-inject/view?x=1';--",
                    ],
                ],
            ],
            'entries' => [
                [
                    'name' => "conferenceRecords/{$confId}/transcripts/trans-injection/entries/entry-adv-1",
                    'participant' => $maliciousParticipant,
                    'text' => "Declaro aberta a sessão. O réu alegou: 'OR '1'='1 -- e emojis 👨‍⚖️🎉",
                    'languageCode' => 'pt-BR',
                    'startTime' => '2026-11-02T14:01:00Z',
                    'endTime' => '2026-11-02T14:01:10Z',
                ],
            ],
        ]);

        $record = $this->meetService->syncConferenceRecord($confId);

        $this->assertInstanceOf(ConferenceRecord::class, $record);
        $this->assertSame(1, ConferenceRecord::count(), 'conference_records table was not dropped');
        
        $participant = ConferenceParticipant::where('conference_record_id', $record->id)->first();
        $this->assertNotNull($participant);
        $this->assertSame($maliciousParticipant, $participant->participant_name);
        $this->assertSame($adversarialDisplayName, $participant->display_name);

        $entry = ConferenceTranscriptEntry::first();
        $this->assertNotNull($entry);
        $this->assertSame($adversarialDisplayName, $entry->speaker_name);
        $this->assertStringContainsString("Declaro aberta a sessão. O réu alegou: 'OR '1'='1 -- e emojis 👨‍⚖️🎉", $entry->text);
    }

    /**
     * ADVERSARIAL STRESS TEST 7: Active conference synchronization without premature room termination
     * If conference has no endTime (still ongoing), room status must remain 'active' and record status must be 'active'.
     */
    public function test_ongoing_conference_keeps_room_and_record_active(): void
    {
        $spaceName = 'spaces/ongoing-space-007';
        $confId = 'conf-ongoing-007';

        $room = MeetingRoom::create([
            'space_name' => $spaceName,
            'meeting_uri' => 'https://meet.google.com/ongoing-test',
            'meeting_code' => 'ongoing-test',
            'access_type' => 'OPEN',
            'entry_point_access' => 'CREATOR_APP_ONLY',
            'moderation' => 'ON',
            'status' => 'active',
        ]);

        GoogleMeetFake::fakeFullConferenceSync($confId, [
            'conference' => [
                'space' => $spaceName,
                'startTime' => '2026-11-02T14:00:00Z',
                'endTime' => null, // Conference in progress
            ],
        ]);

        $record = $this->meetService->syncConferenceRecord($confId);

        $this->assertSame('active', $record->status);
        $this->assertNull($record->end_time);
        $this->assertSame('active', $room->fresh()->status, 'Meeting room must NOT be marked ended while conference is ongoing');
    }

    /**
     * ADVERSARIAL STRESS TEST 8: Null and missing participant timestamps do not cause PHP Fatal error
     */
    public function test_participant_with_null_start_and_end_times(): void
    {
        $confId = 'conf-null-times-008';

        GoogleMeetFake::fakeFullConferenceSync($confId, [
            'participants' => [
                [
                    'name' => "conferenceRecords/{$confId}/participants/part-null-times",
                    'signedinUser' => [
                        'user' => 'users/null-times-user',
                        'displayName' => 'User Without Timestamps',
                    ],
                    'earliestStartTime' => null,
                    'latestEndTime' => null,
                ],
            ],
        ]);

        $record = $this->meetService->syncConferenceRecord($confId);

        $participant = $record->participants->first();
        $this->assertNotNull($participant);
        $this->assertNull($participant->earliest_start_time);
        $this->assertNull($participant->latest_end_time);
        $this->assertNull($participant->duration_seconds);
        $this->assertNull($participant->duration_human);
    }
}
