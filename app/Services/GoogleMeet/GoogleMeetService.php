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

/**
 * Serviço de integração com o Google Meet para criação de salas restritas e sincronização de conferências.
 *
 * Implementa os requisitos R1 (criação de espaço com moderação estrita) e R2
 * (ingestão de registros de conferência, participantes, gravações e transcrições).
 */
class GoogleMeetService
{
    protected MeetApiClientInterface $client;
    protected ?AuditService $auditService;

    /**
     * Inicializa o serviço do Google Meet.
     *
     * @param  MeetApiClientInterface  $client  Cliente da API do Google Meet
     * @param  AuditService|null  $auditService  Serviço opcional de auditoria
     */
    public function __construct(MeetApiClientInterface $client, ?AuditService $auditService = null)
    {
        $this->client = $client;
        $this->auditService = $auditService;
    }

    /**
     * Retorna a instância do cliente da API subjacente.
     *
     * @return MeetApiClientInterface
     */
    public function getClient(): MeetApiClientInterface
    {
        return $this->client;
    }

    /**
     * Cria um espaço restrito no Google Meet atendendo ao Requisito R1 e persiste no banco de dados.
     *
     * Configuração estrita R1 garantida:
     * - accessType: 'OPEN'
     * - entryPointAccess: 'CREATOR_APP_ONLY'
     * - moderation: 'ON'
     * - chatRestriction: 'HOSTS_ONLY'
     * - presentRestriction: 'HOSTS_ONLY'
     * - defaultJoinAsViewerType: 'ON'
     *
     * @param  array  $options  Metadados opcionais (created_by/creator_id, prontuario_id, artifactConfig, etc.)
     * @return MeetingRoom  Instância do modelo persistido
     * @throws GoogleMeetApiException
     */
    public function createRestrictedRoom(array $options = []): MeetingRoom
    {
        // Monta e impõe estritamente o payload do requisito R1:
        $spacePayload = [
            'config' => [
                'accessType' => 'OPEN',
                'entryPointAccess' => 'ALL',
                'moderation' => 'ON',
                'moderationRestrictions' => [
                    'chatRestriction' => 'HOSTS_ONLY',
                    'presentRestriction' => 'HOSTS_ONLY',
                    'defaultJoinAsViewerType' => 'ON',
                ],
            ],
        ];

        // Mescla configurações opcionais de artefatos, se informadas
        if (isset($options['artifactConfig'])) {
            $spacePayload['config']['artifactConfig'] = $options['artifactConfig'];
        }

        // Chama a API v2 do Google Meet
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

        // Persiste a sala de forma transacional
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

            // Integração opcional com o log de auditoria
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
     * Sincroniza e persiste o histórico de conferência do Google Meet atendendo ao Requisito R2.
     *
     * Realiza a ingestão de:
     * - Registro macro da conferência (conferenceRecords.get)
     * - Participantes e horários de sessão (conferenceRecords.participants.list)
     * - Metadados de gravações de vídeo e links do Drive (conferenceRecords.recordings.list)
     * - Transcrições e entradas granulares de falas (conferenceRecords.transcripts.list + entries)
     *
     * Trata degradação graciosa quando gravações ou transcrições estão vazias.
     *
     * @param  string  $conferenceRecordName  Nome ou ID (ex.: 'conferenceRecords/conf-123' ou 'conf-123')
     * @param  int|null  $meetingRoomId  Sobrescrita opcional explícita do ID da sala de reunião
     * @return ConferenceRecord
     * @throws GoogleMeetApiException
     */
    public function syncConferenceRecord(string $conferenceRecordName, ?int $meetingRoomId = null): ConferenceRecord
    {
        $normalizedName = ltrim($conferenceRecordName, '/');
        if (!str_starts_with($normalizedName, 'conferenceRecords/')) {
            $normalizedName = "conferenceRecords/{$normalizedName}";
        }

        // 1. Busca o registro macro na API v2 do Google Meet
        $macro = $this->client->getConferenceRecord($normalizedName);
        $canonicalName = $macro['name'] ?? $normalizedName;
        $spaceName = $macro['space'] ?? '';

        // 2. Resolve a sala MeetingRoom associada caso não tenha sido informada explicitamente
        $meetingRoom = null;
        if ($meetingRoomId === null && !empty($spaceName)) {
            $meetingRoom = MeetingRoom::where('space_name', $spaceName)->first();
            $meetingRoomId = $meetingRoom?->id;
        } elseif ($meetingRoomId !== null) {
            $meetingRoom = MeetingRoom::find($meetingRoomId);
        }

        // 3. Persiste atomicamente a hierarquia da conferência em uma transação de banco de dados
        return DB::transaction(function () use ($macro, $canonicalName, $spaceName, $meetingRoom, $meetingRoomId) {
            $startTime = !empty($macro['startTime']) ? Carbon::parse($macro['startTime']) : null;
            $endTime = !empty($macro['endTime']) ? Carbon::parse($macro['endTime']) : null;
            $expireTime = !empty($macro['expireTime']) ? Carbon::parse($macro['expireTime']) : null;
            $status = !empty($endTime) ? 'ended' : 'active';

            // Se a sala estiver ativa e a conferência tiver terminado, atualiza o status da sala para encerrada
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

            // 4. Ingestão de Participantes (conferenceRecords.participants.list)
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

            // 5. Ingestão de Gravações (conferenceRecords.recordings.list)
            $recordingsList = [];
            try {
                $recordingsResponse = $this->client->listRecordings($canonicalName);
                $recordingsList = $recordingsResponse['recordings'] ?? [];
            } catch (GoogleMeetApiException $e) {
                if ($e->getStatusCode() === 404) {
                    // Recurso não encontrado ou gravação não habilitada na conferência
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

            // 6. Ingestão de Transcrições e Entradas (conferenceRecords.transcripts.list)
            $transcriptsList = [];
            try {
                $transcriptsResponse = $this->client->listTranscripts($canonicalName);
                $transcriptsList = $transcriptsResponse['transcripts'] ?? [];
            } catch (GoogleMeetApiException $e) {
                if ($e->getStatusCode() === 404) {
                    // Recurso não encontrado ou transcrição não habilitada na conferência
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

                // Busca as entradas individuais desta transcrição
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

                // Formata o texto consolidado do conteúdo
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

                // Persiste as entradas de transcrição no nível de sentença
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

            // 7. Integração opcional com o log de auditoria
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
