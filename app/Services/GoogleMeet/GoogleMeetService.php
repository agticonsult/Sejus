<?php

namespace App\Services\GoogleMeet;

use App\Models\ConferenceParticipant;
use App\Models\ConferenceRecord;
use App\Models\ConferenceRecording;
use App\Models\ConferenceTranscript;
use App\Models\ConferenceTranscriptEntry;
use App\Models\MeetingRoom;
use App\Services\AuditService;
use App\Services\GoogleMeet\Contracts\MeetApiClientInterface;
use App\Services\GoogleMeet\Exceptions\GoogleMeetApiException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class GoogleMeetService
{
    protected MeetApiClientInterface $client;
    protected ?AuditService $auditService;

    public function __construct(MeetApiClientInterface $client, ?AuditService $auditService = null)
    {
        $this->client = $client;
        $this->auditService = $auditService;
    }

    /**
     * Get underlying API client instance.
     */
    public function getClient(): MeetApiClientInterface
    {
        return $this->client;
    }

    /**
     * Create a restricted Google Meet space satisfying Requirement R1 and persist to database.
     *
     * Strict R1 Configuration guaranteed:
     * - accessType: 'OPEN'
     * - entryPointAccess: 'CREATOR_APP_ONLY'
     * - moderation: 'ON'
     * - chatRestriction: 'HOSTS_ONLY'
     * - presentRestriction: 'HOSTS_ONLY'
     * - defaultJoinAsViewerType: 'ON'
     *
     * @param array $options Optional metadata (created_by/creator_id, prontuario_id, artifactConfig, etc.)
     * @return MeetingRoom Persisted model instance
     * @throws GoogleMeetApiException
     */
    public function createRestrictedRoom(array $options = []): MeetingRoom
    {
        // Strictly assemble and enforce the R1 payload:
        $spacePayload = [
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

        // Merge optional artifact configurations if provided
        if (isset($options['artifactConfig'])) {
            $spacePayload['config']['artifactConfig'] = $options['artifactConfig'];
        }

        // Call Google Meet API v2
        $response = $this->client->createSpace($spacePayload);

        $spaceName = $response['name'] ?? null;
        $meetingUri = $response['meetingUri'] ?? null;
        $meetingCode = $response['meetingCode'] ?? null;

        if (empty($spaceName) || empty($meetingUri)) {
            throw new GoogleMeetApiException(
                'Invalid response from Google Meet API: missing space name or meeting URI.',
                500,
                null,
                $response
            );
        }

        $config = $response['config'] ?? $response;
        $restrictions = $config['moderationRestrictions'] ?? [];

        // Transactionally persist room
        return DB::transaction(function () use ($response, $spaceName, $meetingUri, $meetingCode, $config, $restrictions, $options) {
            $room = MeetingRoom::create([
                'space_name' => $spaceName,
                'meeting_uri' => $meetingUri,
                'meeting_code' => $meetingCode,
                'access_type' => $config['accessType'] ?? 'OPEN',
                'entry_point_access' => $config['entryPointAccess'] ?? 'CREATOR_APP_ONLY',
                'moderation' => $config['moderation'] ?? 'ON',
                'chat_restriction' => $restrictions['chatRestriction'] ?? 'HOSTS_ONLY',
                'present_restriction' => $restrictions['presentRestriction'] ?? 'HOSTS_ONLY',
                'default_join_as_viewer_type' => $restrictions['defaultJoinAsViewerType'] ?? 'ON',
                'status' => 'active',
                'raw_config' => $response['config'] ?? $config,
                'created_by' => $options['created_by'] ?? $options['creator_id'] ?? null,
                'prontuario_id' => $options['prontuario_id'] ?? null,
            ]);

            // Optional audit log integration
            if ($this->auditService && !empty($options['prontuario_id'])) {
                try {
                    $this->auditService->log(
                        prontuarioId: (int) $options['prontuario_id'],
                        acao: 'google_meet.room.created',
                        details: [
                            'meeting_room_id' => $room->id,
                            'space_name' => $room->space_name,
                            'meeting_uri' => $room->meeting_uri,
                            'meeting_code' => $room->meeting_code,
                        ],
                        userId: $options['created_by'] ?? $options['creator_id'] ?? null
                    );
                } catch (Throwable $e) {
                    Log::warning('Failed to append audit log for Google Meet room: ' . $e->getMessage());
                }
            }

            return $room;
        });
    }

    /**
     * Synchronize and persist Google Meet conference history satisfying Requirement R2.
     *
     * Ingests:
     * - Macro conference record (conferenceRecords.get)
     * - Participants and session timestamps (conferenceRecords.participants.list)
     * - Video recordings metadata & Drive links (conferenceRecords.recordings.list)
     * - Transcripts & granular spoken sentence entries (conferenceRecords.transcripts.list + entries)
     *
     * Handles graceful degradation when recordings or transcripts are empty.
     *
     * @param string $conferenceRecordName Name or ID (e.g. 'conferenceRecords/conf-123' or 'conf-123')
     * @param int|null $meetingRoomId Optional explicit meeting room ID override
     * @return ConferenceRecord
     * @throws GoogleMeetApiException
     */
    public function syncConferenceRecord(string $conferenceRecordName, ?int $meetingRoomId = null): ConferenceRecord
    {
        $normalizedName = ltrim($conferenceRecordName, '/');
        if (!str_starts_with($normalizedName, 'conferenceRecords/')) {
            $normalizedName = "conferenceRecords/{$normalizedName}";
        }

        // 1. Fetch macro record from Google Meet API v2
        $macro = $this->client->getConferenceRecord($normalizedName);
        $canonicalName = $macro['name'] ?? $normalizedName;
        $spaceName = $macro['space'] ?? '';

        // 2. Resolve associated MeetingRoom if not explicitly passed
        $meetingRoom = null;
        if ($meetingRoomId === null && !empty($spaceName)) {
            $meetingRoom = MeetingRoom::where('space_name', $spaceName)->first();
            $meetingRoomId = $meetingRoom?->id;
        } elseif ($meetingRoomId !== null) {
            $meetingRoom = MeetingRoom::find($meetingRoomId);
        }

        // 3. Atomically persist conference hierarchy within a database transaction
        return DB::transaction(function () use ($macro, $canonicalName, $spaceName, $meetingRoom, $meetingRoomId) {
            $startTime = !empty($macro['startTime']) ? Carbon::parse($macro['startTime']) : null;
            $endTime = !empty($macro['endTime']) ? Carbon::parse($macro['endTime']) : null;
            $expireTime = !empty($macro['expireTime']) ? Carbon::parse($macro['expireTime']) : null;
            $status = !empty($endTime) ? 'ended' : 'active';

            // If meeting room is active and conference ended, update room to ended
            if ($meetingRoom && !empty($endTime) && $meetingRoom->status === 'active') {
                $meetingRoom->update(['status' => 'ended']);
            }

            /** @var ConferenceRecord $conferenceRecord */
            $conferenceRecord = ConferenceRecord::updateOrCreate(
                ['conference_record_name' => $canonicalName],
                [
                    'meeting_room_id' => $meetingRoomId,
                    'space_name' => $spaceName,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'expire_time' => $expireTime,
                    'status' => $status,
                    'synced_at' => now(),
                    'raw_metadata' => $macro,
                    'raw_data' => $macro,
                ]
            );

            // 4. Ingest Participants (conferenceRecords.participants.list)
            $participantSpeakerMap = [];
            try {
                $participantsResponse = $this->client->listParticipants($canonicalName);
                $participantsList = $participantsResponse['participants'] ?? [];

                foreach ($participantsList as $pData) {
                    $pName = $pData['name'] ?? null;
                    if (!$pName) {
                        continue;
                    }

                    $signedIn = $pData['signedinUser'] ?? null;
                    $anon = $pData['anonymousUser'] ?? null;
                    $phone = $pData['phoneUser'] ?? null;

                    $displayName = $signedIn['displayName']
                        ?? $anon['displayName']
                        ?? $phone['displayName']
                        ?? $pData['displayName']
                        ?? 'Participante';

                    $userIdentifier = $signedIn['user'] ?? null;
                    $userType = $signedIn ? 'signedin' : ($anon ? 'anonymous' : ($phone ? 'phone' : 'signedin'));

                    $participantSpeakerMap[$pName] = $displayName;

                    $earliestStart = !empty($pData['earliestStartTime']) ? Carbon::parse($pData['earliestStartTime']) : null;
                    $latestEnd = !empty($pData['latestEndTime']) ? Carbon::parse($pData['latestEndTime']) : null;
                    $duration = ($earliestStart && $latestEnd) ? abs($latestEnd->diffInSeconds($earliestStart)) : null;

                    ConferenceParticipant::updateOrCreate(
                        [
                            'conference_record_id' => $conferenceRecord->id,
                            'participant_name' => $pName,
                        ],
                        [
                            'display_name' => $displayName,
                            'user_identifier' => $userIdentifier,
                            'user_type' => $userType,
                            'earliest_start_time' => $earliestStart,
                            'latest_end_time' => $latestEnd,
                            'duration_seconds' => $duration,
                            'raw_metadata' => $pData,
                            'raw_data' => $pData,
                        ]
                    );
                }
            } catch (GoogleMeetApiException $e) {
                Log::error("Failed to sync participants for conference {$canonicalName} [{$e->getStatusCode()}]: " . $e->getMessage(), [
                    'conference_record' => $canonicalName,
                    'status_code' => $e->getStatusCode(),
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

            // 5. Ingest Recordings (conferenceRecords.recordings.list)
            $recordingsList = [];
            try {
                $recordingsResponse = $this->client->listRecordings($canonicalName);
                $recordingsList = $recordingsResponse['recordings'] ?? [];
            } catch (GoogleMeetApiException $e) {
                if ($e->getStatusCode() === 404) {
                    // Resource not found or recording feature not enabled on conference
                    Log::info("No recordings found/enabled for {$canonicalName} (HTTP 404).");
                    $recordingsList = [];
                } else {
                    Log::error("Failed to sync recordings for {$canonicalName} [{$e->getStatusCode()}]: " . $e->getMessage());
                    throw $e;
                }
            }

            foreach ($recordingsList as $rData) {
                $rName = $rData['name'] ?? null;
                if (!$rName) {
                    continue;
                }

                $recStart = !empty($rData['startTime']) ? Carbon::parse($rData['startTime']) : null;
                $recEnd = !empty($rData['endTime']) ? Carbon::parse($rData['endTime']) : null;
                $driveDest = $rData['driveDestination'] ?? [];

                ConferenceRecording::updateOrCreate(
                    [
                        'conference_record_id' => $conferenceRecord->id,
                        'recording_name' => $rName,
                    ],
                    [
                        'state' => $rData['state'] ?? 'FILE_GENERATED',
                        'drive_file_id' => $driveDest['file'] ?? null,
                        'export_uri' => $driveDest['exportUri'] ?? null,
                        'start_time' => $recStart,
                        'end_time' => $recEnd,
                        'raw_metadata' => $rData,
                        'raw_data' => $rData,
                    ]
                );
            }

            // 6. Ingest Transcripts & Entries (conferenceRecords.transcripts.list)
            $transcriptsList = [];
            try {
                $transcriptsResponse = $this->client->listTranscripts($canonicalName);
                $transcriptsList = $transcriptsResponse['transcripts'] ?? [];
            } catch (GoogleMeetApiException $e) {
                if ($e->getStatusCode() === 404) {
                    // Resource not found or transcription feature not enabled on conference
                    Log::info("No transcripts found/enabled for {$canonicalName} (HTTP 404).");
                    $transcriptsList = [];
                } else {
                    Log::error("Failed to sync transcripts for {$canonicalName} [{$e->getStatusCode()}]: " . $e->getMessage());
                    throw $e;
                }
            }

            foreach ($transcriptsList as $tData) {
                $tName = $tData['name'] ?? null;
                if (!$tName) {
                    continue;
                }

                $tranStart = !empty($tData['startTime']) ? Carbon::parse($tData['startTime']) : null;
                $tranEnd = !empty($tData['endTime']) ? Carbon::parse($tData['endTime']) : null;
                $docsDest = $tData['docsDestination'] ?? [];

                // Fetch discrete entries for this transcript
                $rawEntries = [];
                try {
                    $entriesResponse = $this->client->listTranscriptEntries($tName);
                    $rawEntries = $entriesResponse['transcriptEntries'] ?? [];
                } catch (GoogleMeetApiException $e) {
                    if ($e->getStatusCode() === 404) {
                        Log::info("No transcript entries found for {$tName} (HTTP 404).");
                        $rawEntries = [];
                    } else {
                        Log::error("Failed to sync entries for transcript {$tName} [{$e->getStatusCode()}]: " . $e->getMessage());
                        throw $e;
                    }
                }

                // Format consolidated content text
                $compiledLines = [];
                foreach ($rawEntries as $entry) {
                    $speaker = $participantSpeakerMap[$entry['participant'] ?? '']
                        ?? $entry['speakerName']
                        ?? 'Participante';
                    $timestamp = !empty($entry['startTime']) ? Carbon::parse($entry['startTime'])->format('H:i:s') : '';
                    $text = trim($entry['text'] ?? '');
                    if ($timestamp !== '') {
                        $compiledLines[] = "[{$timestamp}] {$speaker}: {$text}";
                    } else {
                        $compiledLines[] = "{$speaker}: {$text}";
                    }
                }
                $contentText = !empty($compiledLines) ? implode("\n", $compiledLines) : null;

                /** @var ConferenceTranscript $transcript */
                $transcript = ConferenceTranscript::updateOrCreate(
                    [
                        'conference_record_id' => $conferenceRecord->id,
                        'transcript_name' => $tName,
                    ],
                    [
                        'state' => $tData['state'] ?? 'FILE_GENERATED',
                        'docs_document_id' => $docsDest['document'] ?? null,
                        'export_uri' => $docsDest['exportUri'] ?? null,
                        'full_text' => $contentText,
                        'content_text' => $contentText,
                        'entries_count' => count($rawEntries),
                        'raw_entries' => $rawEntries,
                        'start_time' => $tranStart,
                        'end_time' => $tranEnd,
                        'raw_metadata' => $tData,
                        'raw_data' => $tData,
                    ]
                );

                // Persist sentence-level transcript entries
                foreach ($rawEntries as $entry) {
                    $entryName = $entry['name'] ?? null;
                    if (!$entryName) {
                        continue;
                    }

                    $partRef = $entry['participant'] ?? null;
                    $speaker = $participantSpeakerMap[$partRef] ?? $entry['speakerName'] ?? 'Participante';

                    ConferenceTranscriptEntry::updateOrCreate(
                        [
                            'conference_transcript_id' => $transcript->id,
                            'entry_name' => $entryName,
                        ],
                        [
                            'participant_name' => $partRef,
                            'speaker_name' => $speaker,
                            'text' => $entry['text'] ?? '',
                            'language_code' => $entry['languageCode'] ?? 'pt-BR',
                            'start_time' => !empty($entry['startTime']) ? Carbon::parse($entry['startTime']) : null,
                            'end_time' => !empty($entry['endTime']) ? Carbon::parse($entry['endTime']) : null,
                            'raw_metadata' => $entry,
                            'raw_data' => $entry,
                        ]
                    );
                }
            }

            // 7. Optional audit log integration
            if ($this->auditService && $meetingRoom && !empty($meetingRoom->prontuario_id)) {
                try {
                    $this->auditService->log(
                        prontuarioId: (int) $meetingRoom->prontuario_id,
                        acao: 'google_meet.conference.synced',
                        details: [
                            'conference_record_id' => $conferenceRecord->id,
                            'conference_record_name' => $conferenceRecord->conference_record_name,
                            'space_name' => $conferenceRecord->space_name,
                            'meeting_room_id' => $meetingRoom->id,
                        ]
                    );
                } catch (Throwable $e) {
                    Log::warning('Failed to append audit log for conference sync: ' . $e->getMessage());
                }
            }

            return $conferenceRecord->load(['meetingRoom', 'participants', 'recordings', 'transcripts.entries']);
        });
    }
}
