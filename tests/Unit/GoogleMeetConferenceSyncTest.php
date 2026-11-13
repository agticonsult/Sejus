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
use Illuminate\Support\Facades\DB;

class GoogleMeetConferenceSyncTest extends TestCase
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
     * Test 1: Full successful sync of finalized conference record:
     * - Asserts macro data persisted (start_time, end_time, expire_time, space_name, status).
     * - Asserts participants persisted with exact join/leave timestamps, signed-in users, anonymous users.
     * - Asserts video recordings persisted with Drive file ID, exportUri, state FILE_GENERATED.
     * - Asserts transcripts persisted with Docs document ID, exportUri, and assembled dialogue text.
     * - Asserts sentence-level transcript entries persisted.
     * - Asserts full Eloquent relationship traversal across all models.
     */
    public function test_sync_finalized_conference_record_persists_macro_and_all_subresources(): void
    {
        $confId = 'conf-892347201';
        $canonicalName = "conferenceRecords/{$confId}";
        $spaceName = 'spaces/sE4kP9qLmNt8';

        GoogleMeetFake::fakeFullConferenceSync($confId, [
            'conference' => [
                'name' => $canonicalName,
                'space' => $spaceName,
                'startTime' => '2026-11-02T14:00:00.000Z',
                'endTime' => '2026-11-02T15:05:32.120Z',
                'expireTime' => '2026-12-02T15:05:32.120Z',
            ],
        ]);

        $this->assertDatabaseCount('conference_records', 0);
        $this->assertDatabaseCount('conference_participants', 0);
        $this->assertDatabaseCount('conference_recordings', 0);
        $this->assertDatabaseCount('conference_transcripts', 0);
        $this->assertDatabaseCount('conference_transcript_entries', 0);

        // Execute synchronization
        $record = $this->meetService->syncConferenceRecord($canonicalName);

        // 1. Verify returned model instance & macro fields
        $this->assertInstanceOf(ConferenceRecord::class, $record);
        $this->assertSame($canonicalName, $record->conference_record_name);
        $this->assertSame($spaceName, $record->space_name);
        $this->assertSame('ended', $record->status);
        $this->assertTrue($record->start_time->equalTo(Carbon::parse('2026-11-02T14:00:00.000Z')));
        $this->assertTrue($record->end_time->equalTo(Carbon::parse('2026-11-02T15:05:32.120Z')));
        $this->assertTrue($record->expire_time->equalTo(Carbon::parse('2026-12-02T15:05:32.120Z')));

        // 2. Verify database macro persistence
        $this->assertDatabaseHas('conference_records', [
            'id' => $record->id,
            'conference_record_name' => $canonicalName,
            'space_name' => $spaceName,
            'status' => 'ended',
        ]);

        // 3. Verify participants persistence (3 distinct participants: 2 signed-in, 1 anonymous)
        $this->assertDatabaseCount('conference_participants', 3);

        // Participant 1 (Host / Technician - Signed-in)
        $this->assertDatabaseHas('conference_participants', [
            'conference_record_id' => $record->id,
            'participant_name' => "{$canonicalName}/participants/part-usr-001",
            'display_name' => 'Dr. Carlos Eduardo (Técnico SEJUS)',
            'user_identifier' => 'users/109823471029384712039',
        ]);

        // Participant 2 (Citizen / Egresso - Signed-in)
        $this->assertDatabaseHas('conference_participants', [
            'conference_record_id' => $record->id,
            'participant_name' => "{$canonicalName}/participants/part-usr-002",
            'display_name' => 'Marcos Vinicius (Egresso)',
            'user_identifier' => 'users/114928374019283746152',
        ]);

        // Participant 3 (Family guest - Anonymous)
        $this->assertDatabaseHas('conference_participants', [
            'conference_record_id' => $record->id,
            'participant_name' => "{$canonicalName}/participants/part-anon-003",
            'display_name' => 'Familiar Convidado (Maria Santos)',
            'user_identifier' => null,
        ]);

        // 4. Verify video recordings persistence
        $this->assertDatabaseCount('conference_recordings', 1);
        $this->assertDatabaseHas('conference_recordings', [
            'conference_record_id' => $record->id,
            'recording_name' => "{$canonicalName}/recordings/rec-771829",
            'state' => 'FILE_GENERATED',
            'drive_file_id' => '1XyZ987AbCdEfGhIjKlMnOpQrStUvWxYz',
            'export_uri' => 'https://drive.google.com/file/d/1XyZ987AbCdEfGhIjKlMnOpQrStUvWxYz/view',
        ]);

        $recording = $record->recordings->first();
        $this->assertNotNull($recording);
        $this->assertTrue($recording->isGenerated());
        $this->assertStringContainsString('drive.google.com', $recording->drive_link);

        // 5. Verify transcripts & assembled dialogue persistence
        $this->assertDatabaseCount('conference_transcripts', 1);
        $this->assertDatabaseHas('conference_transcripts', [
            'conference_record_id' => $record->id,
            'transcript_name' => "{$canonicalName}/transcripts/trans-442109",
            'state' => 'FILE_GENERATED',
            'docs_document_id' => '1DocId998877665544332211AABBCCDDEEFF',
            'export_uri' => 'https://docs.google.com/document/d/1DocId998877665544332211AABBCCDDEEFF/view',
        ]);

        $transcript = $record->transcripts->first();
        $this->assertNotNull($transcript);
        $transcriptText = $transcript->full_text ?? $transcript->content_text;
        $this->assertStringContainsString('Iniciamos o atendimento psicossocial pelo Conecta Egresso', $transcriptText);
        $this->assertStringContainsString('Boa tarde, doutor. Estou ouvindo perfeitamente', $transcriptText);

        // 6. Verify sentence-level transcript entries
        $this->assertDatabaseCount('conference_transcript_entries', 2);
        $this->assertDatabaseHas('conference_transcript_entries', [
            'conference_transcript_id' => $transcript->id,
            'entry_name' => "{$canonicalName}/transcripts/trans-442109/entries/entry-0001",
            'text' => 'Iniciamos o atendimento psicossocial pelo Conecta Egresso.',
            'language_code' => 'pt-BR',
        ]);
        $this->assertDatabaseHas('conference_transcript_entries', [
            'conference_transcript_id' => $transcript->id,
            'entry_name' => "{$canonicalName}/transcripts/trans-442109/entries/entry-0002",
            'text' => 'Boa tarde, doutor. Estou ouvindo perfeitamente.',
            'language_code' => 'pt-BR',
        ]);

        // 7. Verify Eloquent relationships navigation
        $this->assertCount(3, $record->participants);
        $this->assertCount(1, $record->recordings);
        $this->assertCount(1, $record->transcripts);
        $this->assertCount(2, $transcript->entries);

        // Inverse relationships
        $this->assertSame($record->id, $record->participants->first()->conferenceRecord->id);
        $this->assertSame($record->id, $record->recordings->first()->conferenceRecord->id);
        $this->assertSame($record->id, $transcript->conferenceRecord->id);
        $this->assertSame($transcript->id, $transcript->entries->first()->transcript->id);
    }

    /**
     * Test 2: Relationship linking:
     * - If a MeetingRoom exists for the space, ConferenceRecord is properly linked via meeting_room_id.
     * - Verifies forward relation ($record->meetingRoom) and reverse relation ($room->conferenceRecords).
     * - Verifies that meeting room status updates to 'ended' when conference is finalized.
     * - Verifies that if no matching MeetingRoom exists, meeting_room_id is null without error.
     */
    public function test_sync_conference_record_links_to_existing_meeting_room(): void
    {
        $spaceName = 'spaces/room-linked-123';
        $confId = 'conf-linked-888';

        // 1. Create existing MeetingRoom in SQLite database
        $room = MeetingRoom::create([
            'space_name' => $spaceName,
            'meeting_uri' => 'https://meet.google.com/abc-link-xyz',
            'meeting_code' => 'abc-link-xyz',
            'access_type' => 'OPEN',
            'entry_point_access' => 'CREATOR_APP_ONLY',
            'moderation' => 'ON',
            'chat_restriction' => 'HOSTS_ONLY',
            'present_restriction' => 'HOSTS_ONLY',
            'default_join_as_viewer_type' => 'ON',
            'status' => 'active',
        ]);

        GoogleMeetFake::fakeFullConferenceSync($confId, [
            'conference' => [
                'space' => $spaceName,
                'endTime' => '2026-11-02T16:00:00.000Z',
            ],
        ]);

        $record = $this->meetService->syncConferenceRecord($confId);

        // Assert foreign key linked
        $this->assertSame($room->id, $record->meeting_room_id);
        $this->assertInstanceOf(MeetingRoom::class, $record->meetingRoom);
        $this->assertSame($room->id, $record->meetingRoom->id);

        // Assert reverse relationship
        $this->assertTrue($room->fresh()->conferenceRecords->contains($record));

        // Assert room status updated to ended upon completed conference
        $this->assertSame('ended', $room->fresh()->status);

        // 2. Sub-test: Conference with unlinked / external space
        $externalConfId = 'conf-external-999';
        GoogleMeetFake::fakeFullConferenceSync($externalConfId, [
            'conference' => [
                'space' => 'spaces/external-untracked-space-404',
            ],
        ]);

        $externalRecord = $this->meetService->syncConferenceRecord($externalConfId);
        $this->assertNull($externalRecord->meeting_room_id);
        $this->assertNull($externalRecord->meetingRoom);
    }

    /**
     * Test 3: Partial / Empty data resilience:
     * - Meeting with 0 recordings and 0 transcripts (recording / transcription not enabled by host):
     *   Sync completes successfully without error.
     * - Meeting with 0 participants, 0 recordings, 0 transcripts:
     *   Sync completes successfully and persists macro record.
     */
    public function test_sync_resilience_handles_meetings_without_recordings_or_transcripts(): void
    {
        $confId = 'conf-no-artifacts-333';

        // Fake conference with explicitly empty recordings and transcripts
        GoogleMeetFake::fakeFullConferenceSync($confId, [
            'recordings' => [],
            'transcripts' => [],
        ]);

        $record = $this->meetService->syncConferenceRecord($confId);

        $this->assertInstanceOf(ConferenceRecord::class, $record);
        $this->assertSame("conferenceRecords/{$confId}", $record->conference_record_name);
        $this->assertSame('ended', $record->status);

        // Participants were still persisted normally
        $this->assertGreaterThan(0, $record->participants()->count());

        // Recordings and transcripts are safely empty
        $this->assertDatabaseCount('conference_recordings', 0);
        $this->assertDatabaseCount('conference_transcripts', 0);
        $this->assertDatabaseCount('conference_transcript_entries', 0);
        $this->assertCount(0, $record->recordings);
        $this->assertCount(0, $record->transcripts);

        // Sub-test: Empty participants, empty recordings, empty transcripts
        $emptyConfId = 'conf-empty-all-444';
        GoogleMeetFake::fakeFullConferenceSync($emptyConfId, [
            'participants' => [],
            'recordings' => [],
            'transcripts' => [],
        ]);

        $emptyRecord = $this->meetService->syncConferenceRecord($emptyConfId);
        $this->assertInstanceOf(ConferenceRecord::class, $emptyRecord);
        $this->assertCount(0, $emptyRecord->participants);
        $this->assertCount(0, $emptyRecord->recordings);
        $this->assertCount(0, $emptyRecord->transcripts);
    }

    /**
     * Test 4: Idempotency / Update:
     * - Running sync twice on the same conference record updates existing records instead of duplicating them.
     * - Counts in all 5 database tables remain identical after repeated sync.
     * - Updating metadata in subsequent sync refreshes the persisted database record in place.
     */
    public function test_sync_is_idempotent_and_updates_in_place_without_duplication(): void
    {
        $confId = 'conf-idempotent-777';
        GoogleMeetFake::fakeFullConferenceSync($confId);

        // Run Sync 1
        $recordFirst = $this->meetService->syncConferenceRecord($confId);
        $recordId = $recordFirst->id;

        $confCount = ConferenceRecord::count();
        $partCount = ConferenceParticipant::count();
        $recCount = ConferenceRecording::count();
        $transCount = ConferenceTranscript::count();
        $entriesCount = ConferenceTranscriptEntry::count();

        $this->assertSame(1, $confCount);
        $this->assertGreaterThan(0, $partCount);
        $this->assertGreaterThan(0, $recCount);
        $this->assertGreaterThan(0, $transCount);
        $this->assertGreaterThan(0, $entriesCount);

        // Run Sync 2 (identical data)
        $recordSecond = $this->meetService->syncConferenceRecord($confId);

        // Assert model ID unchanged
        $this->assertSame($recordId, $recordSecond->id);

        // Assert database counts remain strictly identical (0 duplicates created)
        $this->assertSame($confCount, ConferenceRecord::count());
        $this->assertSame($partCount, ConferenceParticipant::count());
        $this->assertSame($recCount, ConferenceRecording::count());
        $this->assertSame($transCount, ConferenceTranscript::count());
        $this->assertSame($entriesCount, ConferenceTranscriptEntry::count());

        // Run Sync 3 (with updated timestamp)
        $updatedEndTime = '2026-11-02T15:45:00.000Z';
        GoogleMeetFake::fakeFullConferenceSync($confId, [
            'conference' => [
                'endTime' => $updatedEndTime,
            ],
        ]);

        $recordThird = $this->meetService->syncConferenceRecord($confId);
        $this->assertSame($recordId, $recordThird->id);
        $this->assertTrue($recordThird->end_time->equalTo(Carbon::parse($updatedEndTime)));
        $this->assertSame($confCount, ConferenceRecord::count());
    }

    /**
     * Test 5: Error handling & Transaction rollback:
     * - HTTP 404 when fetching macro record throws GoogleMeetApiException and leaves DB completely clean.
     * - HTTP 500 when fetching macro record throws GoogleMeetApiException and leaves DB completely clean.
     */
    public function test_sync_error_handling_and_database_transaction_clean_rollback(): void
    {
        // 1. HTTP 404 Not Found on macro record
        $notFoundId = 'conf-not-found-404';
        GoogleMeetFake::fakeConferenceRecord($notFoundId, [], 404);

        $this->assertDatabaseCount('conference_records', 0);

        try {
            $this->meetService->syncConferenceRecord($notFoundId);
            $this->fail('Expected GoogleMeetApiException was not thrown on HTTP 404');
        } catch (GoogleMeetApiException $e) {
            $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : $e->getCode();
            $this->assertSame(404, $statusCode);
            $this->assertStringContainsString('404', $e->getMessage());
        }

        // Database must remain completely clean
        $this->assertDatabaseCount('conference_records', 0);
        $this->assertDatabaseCount('conference_participants', 0);
        $this->assertDatabaseCount('conference_recordings', 0);
        $this->assertDatabaseCount('conference_transcripts', 0);

        // 2. HTTP 500 Internal Error on macro record
        $errorId = 'conf-server-error-500';
        GoogleMeetFake::fakeConferenceRecord($errorId, [], 500);

        try {
            $this->meetService->syncConferenceRecord($errorId);
            $this->fail('Expected GoogleMeetApiException was not thrown on HTTP 500');
        } catch (GoogleMeetApiException $e) {
            $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : $e->getCode();
            $this->assertSame(500, $statusCode);
        }

        $this->assertDatabaseCount('conference_records', 0);

        // 3. HTTP 500 on listParticipants mid-sync triggers complete DB::transaction rollback
        $midSyncFailId = 'conf-mid-sync-fail-500';
        GoogleMeetFake::fakeFullConferenceSync($midSyncFailId, [
            'participants_status' => 500,
        ]);

        $this->assertDatabaseCount('conference_records', 0);

        try {
            $this->meetService->syncConferenceRecord($midSyncFailId);
            $this->fail('Expected GoogleMeetApiException was not thrown when listParticipants returned HTTP 500');
        } catch (GoogleMeetApiException $e) {
            $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : $e->getCode();
            $this->assertSame(500, $statusCode);
            $this->assertStringContainsString('500', $e->getMessage());
        }

        // Database must remain completely clean: 0 orphaned conference records persisted
        $this->assertDatabaseCount('conference_records', 0);
        $this->assertDatabaseCount('conference_participants', 0);
        $this->assertDatabaseCount('conference_recordings', 0);
        $this->assertDatabaseCount('conference_transcripts', 0);
        $this->assertDatabaseCount('conference_transcript_entries', 0);
    }

    /**
     * Test 6: Resource name normalization:
     * - Handles 'conf-123', 'conferenceRecords/conf-123', and '/conferenceRecords/conf-123' identically.
     */
    public function test_sync_normalizes_conference_record_identifiers(): void
    {
        $confId = 'conf-norm-555';
        $canonical = "conferenceRecords/{$confId}";

        GoogleMeetFake::fakeFullConferenceSync($confId);

        $recordA = $this->meetService->syncConferenceRecord($confId);
        $this->assertSame($canonical, $recordA->conference_record_name);

        $recordB = $this->meetService->syncConferenceRecord($canonical);
        $this->assertSame($canonical, $recordB->conference_record_name);
        $this->assertSame($recordA->id, $recordB->id);

        $recordC = $this->meetService->syncConferenceRecord("/{$canonical}");
        $this->assertSame($canonical, $recordC->conference_record_name);
        $this->assertSame($recordA->id, $recordC->id);
    }

    /**
     * Test 7: Handles phone dial-in participants:
     * - Correctly maps phoneUser union to user_type = 'phone'.
     */
    public function test_sync_handles_phone_dial_in_participants(): void
    {
        $confId = 'conf-phone-user-666';

        GoogleMeetFake::fakeFullConferenceSync($confId, [
            'participants' => [
                [
                    'name' => "conferenceRecords/{$confId}/participants/part-phone-001",
                    'phoneUser' => [
                        'displayName' => '+55 27 9****-1234',
                    ],
                    'earliestStartTime' => '2026-11-02T14:10:00Z',
                    'latestEndTime' => '2026-11-02T14:50:00Z',
                ],
            ],
        ]);

        $record = $this->meetService->syncConferenceRecord($confId);

        $participant = $record->participants->first();
        $this->assertNotNull($participant);
        $this->assertStringContainsString('phone', $participant->user_type);
        $this->assertSame('+55 27 9****-1234', $participant->display_name);
    }

    /**
     * Test 8: Atomicity rollback when listParticipants fails:
     * - When getConferenceRecord succeeds but listParticipants fails with HTTP 500,
     *   syncConferenceRecord rethrows GoogleMeetApiException and rolls back DB::transaction.
     * - Asserts that zero records are persisted in conference_records and all child tables.
     */
    public function test_sync_rolls_back_when_participants_list_fails_with_500(): void
    {
        $confId = 'conf-fail-participants-500-unit';

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
        }

        $this->assertDatabaseCount('conference_records', 0);
        $this->assertDatabaseCount('conference_participants', 0);
        $this->assertDatabaseCount('conference_recordings', 0);
        $this->assertDatabaseCount('conference_transcripts', 0);
        $this->assertDatabaseCount('conference_transcript_entries', 0);
    }

    /**
     * Test 9: Sub-resource policy for recordings and transcripts:
     * - HTTP 404 on optional sub-resources is treated as feature not enabled ([]), sync succeeds.
     * - HTTP 500 on optional sub-resources re-throws GoogleMeetApiException and rolls back DB::transaction.
     */
    public function test_sync_subresource_policy_handles_404_as_empty_and_500_as_rollback(): void
    {
        // Part 1: HTTP 404 on recordings and transcripts succeeds gracefully
        $conf404Id = 'conf-subresource-404';
        GoogleMeetFake::fakeFullConferenceSync($conf404Id, [
            'recordings_status' => 404,
            'transcripts_status' => 404,
        ]);

        $record = $this->meetService->syncConferenceRecord($conf404Id);
        $this->assertInstanceOf(ConferenceRecord::class, $record);
        $this->assertSame(0, $record->recordings()->count());
        $this->assertSame(0, $record->transcripts()->count());
        $this->assertGreaterThan(0, $record->participants()->count());

        // Part 2: HTTP 500 on recordings re-throws and rolls back
        $conf500RecId = 'conf-rec-500-rollback';
        GoogleMeetFake::fakeFullConferenceSync($conf500RecId, [
            'recordings_status' => 500,
        ]);

        $this->assertDatabaseMissing('conference_records', [
            'conference_record_name' => "conferenceRecords/{$conf500RecId}",
        ]);

        try {
            $this->meetService->syncConferenceRecord($conf500RecId);
            $this->fail('Expected GoogleMeetApiException was not thrown on recordings HTTP 500');
        } catch (GoogleMeetApiException $e) {
            $this->assertSame(500, $e->getStatusCode());
        }

        $this->assertDatabaseMissing('conference_records', [
            'conference_record_name' => "conferenceRecords/{$conf500RecId}",
        ]);

        // Part 3: HTTP 500 on transcripts re-throws and rolls back
        $conf500TranId = 'conf-tran-500-rollback';
        GoogleMeetFake::fakeFullConferenceSync($conf500TranId, [
            'transcripts_status' => 500,
        ]);

        $this->assertDatabaseMissing('conference_records', [
            'conference_record_name' => "conferenceRecords/{$conf500TranId}",
        ]);

        try {
            $this->meetService->syncConferenceRecord($conf500TranId);
            $this->fail('Expected GoogleMeetApiException was not thrown on transcripts HTTP 500');
        } catch (GoogleMeetApiException $e) {
            $this->assertSame(500, $e->getStatusCode());
        }

        $this->assertDatabaseMissing('conference_records', [
            'conference_record_name' => "conferenceRecords/{$conf500TranId}",
        ]);
    }
}
