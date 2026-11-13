<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Request;

class GoogleMeetFake
{
    public const BASE_URL = 'https://meet.googleapis.com/v2';

    /**
     * Normalize conference record ID (strip leading slashes or 'conferenceRecords/').
     */
    public static function normalizeConferenceRecordId(string $id): string
    {
        $trimmed = trim($id, '/');
        if (str_starts_with($trimmed, 'conferenceRecords/')) {
            return substr($trimmed, strlen('conferenceRecords/'));
        }
        return $trimmed;
    }

    /**
     * Normalize space name (e.g. 'spaces/abc' or 'abc').
     */
    public static function normalizeSpaceName(string $name): string
    {
        $trimmed = trim($name, '/');
        if (str_starts_with($trimmed, 'spaces/')) {
            return $trimmed;
        }
        return "spaces/{$trimmed}";
    }

    /**
     * Setup Http::fake for space creation with a realistic HTTP 200 OK response.
     */
    public static function fakeSpaceCreation(array $overrides = [], int $status = 200): void
    {
        Http::fake([
            self::BASE_URL . '/spaces*' => function (Request $request) use ($overrides, $status) {
                if ($request->method() === 'POST') {
                    return Http::response(self::sampleSpaceResponse($overrides), $status);
                }
                return Http::response(self::sampleSpaceResponse(), 200);
            },
        ]);
    }

    /**
     * Setup Http::fake for space creation failure.
     */
    public static function fakeSpaceCreationFailure(int $status = 400, ?array $errorResponse = null): void
    {
        $responseBody = $errorResponse ?? ($status >= 500 
            ? self::sampleInternalErrorResponse() 
            : self::sampleBadRequestResponse());

        Http::fake([
            self::BASE_URL . '/spaces*' => function (Request $request) use ($status, $responseBody) {
                return Http::response($responseBody, $status);
            },
        ]);
    }

    /**
     * Setup Http::fake for a specific Conference Record (macro details).
     */
    public static function fakeConferenceRecord(string $conferenceRecordId, array $overrides = [], int $status = 200): void
    {
        $cleanId = self::normalizeConferenceRecordId($conferenceRecordId);
        $fullName = "conferenceRecords/{$cleanId}";

        $body = ($status >= 400)
            ? ($status === 404 ? self::sampleNotFoundResponse($fullName) : self::sampleInternalErrorResponse())
            : self::sampleConferenceRecordResponse(array_merge(['name' => $fullName], $overrides), $cleanId);

        Http::fake([
            self::BASE_URL . "/conferenceRecords/{$cleanId}" => function (Request $request) use ($status, $body) {
                return Http::response($body, $status);
            },
        ]);
    }

    /**
     * Setup Http::fake for Conference Participants list.
     * Supports multiple participants with different entry/exit timestamps, signed-in users, anonymous users, phone users.
     */
    public static function fakeConferenceParticipants(string $conferenceRecordId, ?array $participants = null, int $status = 200): void
    {
        $cleanId = self::normalizeConferenceRecordId($conferenceRecordId);
        $fullName = "conferenceRecords/{$cleanId}";

        if ($status >= 400) {
            $body = ($status === 404)
                ? self::sampleNotFoundResponse("{$fullName}/participants")
                : self::sampleInternalErrorResponse();
        } else {
            $body = ($participants === null)
                ? self::sampleParticipantsResponse([], $cleanId)
                : ['participants' => $participants, 'totalSize' => count($participants)];
        }

        Http::fake([
            self::BASE_URL . "/conferenceRecords/{$cleanId}/participants*" => function (Request $request) use ($status, $body) {
                return Http::response($body, $status);
            },
        ]);
    }

    /**
     * Setup Http::fake for Conference Recordings list.
     * Supports Google Drive file ID, exportUri, state FILE_GENERATED, or empty array.
     */
    public static function fakeConferenceRecordings(string $conferenceRecordId, ?array $recordings = null, int $status = 200): void
    {
        $cleanId = self::normalizeConferenceRecordId($conferenceRecordId);
        $fullName = "conferenceRecords/{$cleanId}";

        if ($status >= 400) {
            $body = ($status === 404)
                ? self::sampleNotFoundResponse("{$fullName}/recordings")
                : self::sampleInternalErrorResponse();
        } else {
            $body = ($recordings === null)
                ? self::sampleRecordingsResponse([], $cleanId)
                : ['recordings' => $recordings];
        }

        Http::fake([
            self::BASE_URL . "/conferenceRecords/{$cleanId}/recordings*" => function (Request $request) use ($status, $body) {
                return Http::response($body, $status);
            },
        ]);
    }

    /**
     * Setup Http::fake for Conference Transcripts list.
     * Supports Google Docs document ID, exportUri, state FILE_GENERATED, or empty array.
     */
    public static function fakeConferenceTranscripts(string $conferenceRecordId, ?array $transcripts = null, int $status = 200): void
    {
        $cleanId = self::normalizeConferenceRecordId($conferenceRecordId);
        $fullName = "conferenceRecords/{$cleanId}";

        if ($status >= 400) {
            $body = ($status === 404)
                ? self::sampleNotFoundResponse("{$fullName}/transcripts")
                : self::sampleInternalErrorResponse();
        } else {
            $body = ($transcripts === null)
                ? self::sampleTranscriptsResponse([], $cleanId)
                : ['transcripts' => $transcripts];
        }

        Http::fake([
            self::BASE_URL . "/conferenceRecords/{$cleanId}/transcripts*" => function (Request $request) use ($status, $body) {
                return Http::response($body, $status);
            },
        ]);
    }

    /**
     * Setup Http::fake for Transcript Entries list (dialogue text and timestamps).
     */
    public static function fakeTranscriptEntries(string $transcriptId, ?array $entries = null, int $status = 200): void
    {
        $cleanId = trim($transcriptId, '/');
        $pattern = str_contains($cleanId, '/') ? $cleanId : "*/transcripts/{$cleanId}";

        if ($status >= 400) {
            $body = ($status === 404)
                ? self::sampleNotFoundResponse("{$cleanId}/entries")
                : self::sampleInternalErrorResponse();
        } else {
            $body = ($entries === null)
                ? self::sampleTranscriptEntriesResponse([], $cleanId)
                : ['transcriptEntries' => $entries];
        }

        Http::fake([
            self::BASE_URL . "/{$pattern}/entries*" => function (Request $request) use ($status, $body) {
                return Http::response($body, $status);
            },
        ]);
    }

    /**
     * Reset all registered Http fakes and stubs.
     */
    public static function reset(): void
    {
        $factory = app(\Illuminate\Http\Client\Factory::class);
        $refProp = new \ReflectionProperty($factory, 'stubCallbacks');
        $refProp->setAccessible(true);
        $refProp->setValue($factory, collect());
    }

    /**
     * One-shot convenience helper to wire all sub-resource mocks simultaneously.
     * Sets up exact route order ensuring entries and sub-resources are matched without shadowing.
     */
    public static function fakeFullConferenceSync(string $conferenceRecordId, array $overrides = []): void
    {
        self::reset();

        $cleanId = self::normalizeConferenceRecordId($conferenceRecordId);
        $fullName = "conferenceRecords/{$cleanId}";

        // Macro record status and body
        $macroStatus = $overrides['status'] ?? 200;
        $macroBody = ($macroStatus >= 400)
            ? ($macroStatus === 404 ? self::sampleNotFoundResponse($fullName) : self::sampleInternalErrorResponse())
            : self::sampleConferenceRecordResponse(array_merge(['name' => $fullName], $overrides['conference'] ?? []), $cleanId);

        // Sub-resource statuses
        $participantsStatus = $overrides['participants_status'] ?? 200;
        $recordingsStatus = $overrides['recordings_status'] ?? 200;
        $transcriptsStatus = $overrides['transcripts_status'] ?? 200;
        $entriesStatus = $overrides['entries_status'] ?? 200;

        // Sub-resource payloads (handling explicit empty array [] for resilience tests)
        $participantsBody = ($participantsStatus >= 400)
            ? ($participantsStatus === 404 ? self::sampleNotFoundResponse("{$fullName}/participants") : self::sampleInternalErrorResponse())
            : (array_key_exists('participants', $overrides)
                ? ['participants' => $overrides['participants'], 'totalSize' => count($overrides['participants'])]
                : self::sampleParticipantsResponse([], $cleanId));

        $recordingsBody = ($recordingsStatus >= 400)
            ? ($recordingsStatus === 404 ? self::sampleNotFoundResponse("{$fullName}/recordings") : self::sampleInternalErrorResponse())
            : (array_key_exists('recordings', $overrides)
                ? ['recordings' => $overrides['recordings']]
                : self::sampleRecordingsResponse([], $cleanId));

        $transcriptsBody = ($transcriptsStatus >= 400)
            ? ($transcriptsStatus === 404 ? self::sampleNotFoundResponse("{$fullName}/transcripts") : self::sampleInternalErrorResponse())
            : (array_key_exists('transcripts', $overrides)
                ? ['transcripts' => $overrides['transcripts']]
                : self::sampleTranscriptsResponse([], $cleanId));

        $entriesBody = ($entriesStatus >= 400)
            ? ($entriesStatus === 404 ? self::sampleNotFoundResponse("{$fullName}/transcripts/*/entries") : self::sampleInternalErrorResponse())
            : (array_key_exists('entries', $overrides)
                ? ['transcriptEntries' => $overrides['entries']]
                : self::sampleTranscriptEntriesResponse([], $cleanId));

        Http::fake([
            // 1. Most specific route first: transcript entries
            self::BASE_URL . "/conferenceRecords/{$cleanId}/transcripts/*/entries*" => function (Request $request) use ($entriesStatus, $entriesBody) {
                return Http::response($entriesBody, $entriesStatus);
            },

            // 2. Transcripts list
            self::BASE_URL . "/conferenceRecords/{$cleanId}/transcripts*" => function (Request $request) use ($transcriptsStatus, $transcriptsBody) {
                return Http::response($transcriptsBody, $transcriptsStatus);
            },

            // 3. Recordings list
            self::BASE_URL . "/conferenceRecords/{$cleanId}/recordings*" => function (Request $request) use ($recordingsStatus, $recordingsBody) {
                return Http::response($recordingsBody, $recordingsStatus);
            },

            // 4. Participants list
            self::BASE_URL . "/conferenceRecords/{$cleanId}/participants*" => function (Request $request) use ($participantsStatus, $participantsBody) {
                return Http::response($participantsBody, $participantsStatus);
            },

            // 5. Macro conference record
            self::BASE_URL . "/conferenceRecords/{$cleanId}" => function (Request $request) use ($macroStatus, $macroBody) {
                return Http::response($macroBody, $macroStatus);
            },

            // 6. Space retrieval or creation fallback
            self::BASE_URL . '/spaces*' => function (Request $request) use ($overrides) {
                return Http::response(self::sampleSpaceResponse($overrides['space'] ?? []), 200);
            },
        ]);
    }

    /**
     * Setup Http::fake for a Conference Record with complete sample data
     * producing exactly 1 conference record, 2 participants, 1 recording, and 1 transcript.
     */
    public static function fakeConferenceRecordWithFullData(string $conferenceRecordId, array $overrides = []): void
    {
        $cleanId = self::normalizeConferenceRecordId($conferenceRecordId);
        $defaults = [
            'conference' => [
                'name' => "conferenceRecords/{$cleanId}",
                'space' => 'spaces/sE4kP9qLmNt8',
                'startTime' => '2026-11-02T14:00:00.000Z',
                'endTime' => '2026-11-02T15:00:00.000Z',
                'expireTime' => '2026-12-02T15:00:00.000Z',
            ],
            'participants' => [
                [
                    'name' => "conferenceRecords/{$cleanId}/participants/part-1",
                    'signedinUser' => [
                        'user' => 'users/1001',
                        'displayName' => 'Dr. Carlos Eduardo',
                    ],
                    'earliestStartTime' => '2026-11-02T14:00:00.000Z',
                    'latestEndTime' => '2026-11-02T15:00:00.000Z',
                ],
                [
                    'name' => "conferenceRecords/{$cleanId}/participants/part-2",
                    'signedinUser' => [
                        'user' => 'users/1002',
                        'displayName' => 'Marcos Vinicius',
                    ],
                    'earliestStartTime' => '2026-11-02T14:05:00.000Z',
                    'latestEndTime' => '2026-11-02T14:55:00.000Z',
                ],
            ],
            'recordings' => [
                [
                    'name' => "conferenceRecords/{$cleanId}/recordings/rec-1",
                    'state' => 'FILE_GENERATED',
                    'startTime' => '2026-11-02T14:01:00.000Z',
                    'endTime' => '2026-11-02T15:00:00.000Z',
                    'driveDestination' => [
                        'file' => '1XyZ987AbCdEfGhIjKlMnOpQrStUvWxYz',
                        'exportUri' => 'https://drive.google.com/file/d/1XyZ987AbCdEfGhIjKlMnOpQrStUvWxYz/view',
                    ],
                ],
            ],
            'transcripts' => [
                [
                    'name' => "conferenceRecords/{$cleanId}/transcripts/trans-1",
                    'state' => 'FILE_GENERATED',
                    'startTime' => '2026-11-02T14:01:05.000Z',
                    'endTime' => '2026-11-02T14:59:55.000Z',
                    'docsDestination' => [
                        'document' => '1DocId998877665544332211AABBCCDDEEFF',
                        'exportUri' => 'https://docs.google.com/document/d/1DocId998877665544332211AABBCCDDEEFF/view',
                    ],
                ],
            ],
            'entries' => [
                [
                    'name' => "conferenceRecords/{$cleanId}/transcripts/trans-1/entries/e1",
                    'participant' => "conferenceRecords/{$cleanId}/participants/part-1",
                    'speakerName' => 'Dr. Carlos Eduardo',
                    'text' => 'Início do atendimento.',
                    'languageCode' => 'pt-BR',
                    'startTime' => '2026-11-02T14:01:10.000Z',
                    'endTime' => '2026-11-02T14:01:15.000Z',
                ],
                [
                    'name' => "conferenceRecords/{$cleanId}/transcripts/trans-1/entries/e2",
                    'participant' => "conferenceRecords/{$cleanId}/participants/part-2",
                    'speakerName' => 'Marcos Vinicius',
                    'text' => 'Presente.',
                    'languageCode' => 'pt-BR',
                    'startTime' => '2026-11-02T14:01:16.000Z',
                    'endTime' => '2026-11-02T14:01:20.000Z',
                ],
            ],
        ];

        self::fakeFullConferenceSync($cleanId, array_replace_recursive($defaults, $overrides));
    }

    /**
     * Setup comprehensive fakes across all Google Meet API v2 endpoints.
     */
    public static function fakeAll(array $overrides = []): void
    {
        Http::fake([
            self::BASE_URL . '/spaces*' => function (Request $request) use ($overrides) {
                if ($request->method() === 'POST') {
                    return Http::response(self::sampleSpaceResponse($overrides['space'] ?? []), 200);
                }
                return Http::response(self::sampleSpaceResponse(), 200);
            },

            self::BASE_URL . '/conferenceRecords/*/transcripts/*/entries*' => function () use ($overrides) {
                return Http::response(self::sampleTranscriptEntriesResponse($overrides['entries'] ?? []), 200);
            },

            self::BASE_URL . '/conferenceRecords/*/transcripts*' => function () use ($overrides) {
                return Http::response(self::sampleTranscriptsResponse($overrides['transcripts'] ?? []), 200);
            },

            self::BASE_URL . '/conferenceRecords/*/recordings*' => function () use ($overrides) {
                return Http::response(self::sampleRecordingsResponse($overrides['recordings'] ?? []), 200);
            },

            self::BASE_URL . '/conferenceRecords/*/participants*' => function (Request $request) use ($overrides) {
                return Http::response(self::sampleParticipantsResponse($overrides['participants'] ?? []), 200);
            },

            self::BASE_URL . '/conferenceRecords/*' => function () use ($overrides) {
                return Http::response(self::sampleConferenceRecordResponse($overrides['conference'] ?? []), 200);
            },
        ]);
    }

    /**
     * Assert that the space creation HTTP request was sent with strict R1 restrictions.
     */
    public static function assertSpaceCreatedWithRestrictions(?callable $additionalCallback = null): void
    {
        Http::assertSent(function (Request $request) use ($additionalCallback) {
            if ($request->method() !== 'POST') {
                return false;
            }

            if (!str_starts_with($request->url(), self::BASE_URL . '/spaces')) {
                return false;
            }

            $data = $request->data();
            $config = $data['config'] ?? [];
            $restrictions = $config['moderationRestrictions'] ?? [];

            $matchesR1 = ($config['accessType'] ?? null) === 'OPEN'
                && ($config['entryPointAccess'] ?? null) === 'CREATOR_APP_ONLY'
                && ($config['moderation'] ?? null) === 'ON'
                && ($restrictions['chatRestriction'] ?? null) === 'HOSTS_ONLY'
                && ($restrictions['presentRestriction'] ?? null) === 'HOSTS_ONLY'
                && ($restrictions['defaultJoinAsViewerType'] ?? null) === 'ON';

            if (!$matchesR1) {
                return false;
            }

            if ($additionalCallback !== null) {
                return (bool) $additionalCallback($request);
            }

            return true;
        });
    }

    /**
     * Assert that conference record macro endpoint was requested.
     */
    public static function assertConferenceRecordRequested(string $conferenceRecordId, ?callable $callback = null): void
    {
        $cleanId = self::normalizeConferenceRecordId($conferenceRecordId);
        Http::assertSent(function (Request $request) use ($cleanId, $callback) {
            $expectedUrl = self::BASE_URL . "/conferenceRecords/{$cleanId}";
            if ($request->url() !== $expectedUrl || $request->method() !== 'GET') {
                return false;
            }
            return $callback ? (bool) $callback($request) : true;
        });
    }

    /**
     * Assert that conference participants endpoint was requested.
     */
    public static function assertParticipantsRequested(string $conferenceRecordId, ?callable $callback = null): void
    {
        $cleanId = self::normalizeConferenceRecordId($conferenceRecordId);
        Http::assertSent(function (Request $request) use ($cleanId, $callback) {
            $expectedPrefix = self::BASE_URL . "/conferenceRecords/{$cleanId}/participants";
            if (!str_starts_with($request->url(), $expectedPrefix) || $request->method() !== 'GET') {
                return false;
            }
            return $callback ? (bool) $callback($request) : true;
        });
    }

    /**
     * Assert that conference recordings endpoint was requested.
     */
    public static function assertRecordingsRequested(string $conferenceRecordId, ?callable $callback = null): void
    {
        $cleanId = self::normalizeConferenceRecordId($conferenceRecordId);
        Http::assertSent(function (Request $request) use ($cleanId, $callback) {
            $expectedPrefix = self::BASE_URL . "/conferenceRecords/{$cleanId}/recordings";
            if (!str_starts_with($request->url(), $expectedPrefix) || $request->method() !== 'GET') {
                return false;
            }
            return $callback ? (bool) $callback($request) : true;
        });
    }

    /**
     * Assert that conference transcripts endpoint was requested.
     */
    public static function assertTranscriptsRequested(string $conferenceRecordId, ?callable $callback = null): void
    {
        $cleanId = self::normalizeConferenceRecordId($conferenceRecordId);
        Http::assertSent(function (Request $request) use ($cleanId, $callback) {
            $expectedPrefix = self::BASE_URL . "/conferenceRecords/{$cleanId}/transcripts";
            if (!str_starts_with($request->url(), $expectedPrefix) || $request->method() !== 'GET') {
                return false;
            }
            return $callback ? (bool) $callback($request) : true;
        });
    }

    /**
     * Assert that transcript entries endpoint was requested.
     */
    public static function assertTranscriptEntriesRequested(string $transcriptName, ?callable $callback = null): void
    {
        $cleanName = trim($transcriptName, '/');
        Http::assertSent(function (Request $request) use ($cleanName, $callback) {
            if (!str_contains($request->url(), "{$cleanName}/entries") || $request->method() !== 'GET') {
                return false;
            }
            return $callback ? (bool) $callback($request) : true;
        });
    }

    /**
     * Generate realistic Google Meet Space JSON response.
     */
    public static function sampleSpaceResponse(array $overrides = []): array
    {
        $defaults = [
            'name' => 'spaces/sE4kP9qLmNt8',
            'meetingUri' => 'https://meet.google.com/zxc-vbnm-qwe',
            'meetingCode' => 'zxc-vbnm-qwe',
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
            'activeConference' => null,
            'phoneAccess' => [],
            'gatewaySipAccess' => [],
        ];

        return array_replace_recursive($defaults, $overrides);
    }

    /**
     * Generate sample Conference Record JSON response.
     */
    public static function sampleConferenceRecordResponse(array $overrides = [], ?string $conferenceRecordId = null): array
    {
        $cleanId = $conferenceRecordId ? self::normalizeConferenceRecordId($conferenceRecordId) : 'conf-892347201';
        $defaults = [
            'name' => "conferenceRecords/{$cleanId}",
            'startTime' => '2026-11-02T14:00:00.000Z',
            'endTime' => '2026-11-02T15:05:32.120Z',
            'expireTime' => '2026-12-02T15:05:32.120Z',
            'space' => 'spaces/sE4kP9qLmNt8',
        ];

        return array_replace_recursive($defaults, $overrides);
    }

    /**
     * Generate sample Participants JSON response with signed-in and anonymous users.
     */
    public static function sampleParticipantsResponse(array $participants = [], ?string $conferenceRecordId = null): array
    {
        $cleanId = $conferenceRecordId ? self::normalizeConferenceRecordId($conferenceRecordId) : 'conf-892347201';

        if (empty($participants)) {
            $participants = [
                [
                    'name' => "conferenceRecords/{$cleanId}/participants/part-usr-001",
                    'signedinUser' => [
                        'user' => 'users/109823471029384712039',
                        'displayName' => 'Dr. Carlos Eduardo (Técnico SEJUS)',
                    ],
                    'earliestStartTime' => '2026-11-02T13:58:45.000Z',
                    'latestEndTime' => '2026-11-02T15:05:30.000Z',
                ],
                [
                    'name' => "conferenceRecords/{$cleanId}/participants/part-usr-002",
                    'signedinUser' => [
                        'user' => 'users/114928374019283746152',
                        'displayName' => 'Marcos Vinicius (Egresso)',
                    ],
                    'earliestStartTime' => '2026-11-02T14:02:10.000Z',
                    'latestEndTime' => '2026-11-02T15:04:15.000Z',
                ],
                [
                    'name' => "conferenceRecords/{$cleanId}/participants/part-anon-003",
                    'anonymousUser' => [
                        'displayName' => 'Familiar Convidado (Maria Santos)',
                    ],
                    'earliestStartTime' => '2026-11-02T14:05:00.000Z',
                    'latestEndTime' => '2026-11-02T14:45:20.000Z',
                ],
            ];
        }

        return [
            'participants' => $participants,
            'totalSize' => count($participants),
        ];
    }

    /**
     * Generate sample Recordings JSON response with Google Drive destination.
     */
    public static function sampleRecordingsResponse(array $recordings = [], ?string $conferenceRecordId = null): array
    {
        $cleanId = $conferenceRecordId ? self::normalizeConferenceRecordId($conferenceRecordId) : 'conf-892347201';

        if (empty($recordings)) {
            $recordings = [
                [
                    'name' => "conferenceRecords/{$cleanId}/recordings/rec-771829",
                    'state' => 'FILE_GENERATED',
                    'startTime' => '2026-11-02T14:01:00.000Z',
                    'endTime' => '2026-11-02T15:05:00.000Z',
                    'driveDestination' => [
                        'file' => '1XyZ987AbCdEfGhIjKlMnOpQrStUvWxYz',
                        'exportUri' => 'https://drive.google.com/file/d/1XyZ987AbCdEfGhIjKlMnOpQrStUvWxYz/view',
                    ],
                ],
            ];
        }

        return ['recordings' => $recordings];
    }

    /**
     * Generate sample Transcripts JSON response with Google Docs destination.
     */
    public static function sampleTranscriptsResponse(array $transcripts = [], ?string $conferenceRecordId = null): array
    {
        $cleanId = $conferenceRecordId ? self::normalizeConferenceRecordId($conferenceRecordId) : 'conf-892347201';

        if (empty($transcripts)) {
            $transcripts = [
                [
                    'name' => "conferenceRecords/{$cleanId}/transcripts/trans-442109",
                    'state' => 'FILE_GENERATED',
                    'startTime' => '2026-11-02T14:01:05.000Z',
                    'endTime' => '2026-11-02T15:04:55.000Z',
                    'docsDestination' => [
                        'document' => '1DocId998877665544332211AABBCCDDEEFF',
                        'exportUri' => 'https://docs.google.com/document/d/1DocId998877665544332211AABBCCDDEEFF/view',
                    ],
                ],
            ];
        }

        return ['transcripts' => $transcripts];
    }

    /**
     * Generate sample Transcript Entries JSON response.
     */
    public static function sampleTranscriptEntriesResponse(array $entries = [], ?string $parentOrId = null): array
    {
        $cleanId = $parentOrId ? self::normalizeConferenceRecordId($parentOrId) : 'conf-892347201';

        if (empty($entries)) {
            $entries = [
                [
                    'name' => "conferenceRecords/{$cleanId}/transcripts/trans-442109/entries/entry-0001",
                    'participant' => "conferenceRecords/{$cleanId}/participants/part-usr-001",
                    'speakerName' => 'Dr. Carlos Eduardo (Técnico SEJUS)',
                    'text' => 'Iniciamos o atendimento psicossocial pelo Conecta Egresso.',
                    'languageCode' => 'pt-BR',
                    'startTime' => '2026-11-02T14:01:15.000Z',
                    'endTime' => '2026-11-02T14:01:21.000Z',
                ],
                [
                    'name' => "conferenceRecords/{$cleanId}/transcripts/trans-442109/entries/entry-0002",
                    'participant' => "conferenceRecords/{$cleanId}/participants/part-usr-002",
                    'speakerName' => 'Marcos Vinicius (Egresso)',
                    'text' => 'Boa tarde, doutor. Estou ouvindo perfeitamente.',
                    'languageCode' => 'pt-BR',
                    'startTime' => '2026-11-02T14:01:24.000Z',
                    'endTime' => '2026-11-02T14:01:27.000Z',
                ],
            ];
        }

        return ['transcriptEntries' => $entries];
    }

    /**
     * Generate standard Google 404 Not Found error response.
     */
    public static function sampleNotFoundResponse(string $resource = 'conferenceRecord'): array
    {
        return [
            'error' => [
                'code' => 404,
                'message' => "Resource '{$resource}' was not found.",
                'status' => 'NOT_FOUND',
                'details' => [
                    [
                        '@type' => 'type.googleapis.com/google.rpc.ResourceInfo',
                        'resourceType' => 'meet.googleapis.com/ConferenceRecord',
                        'resourceName' => $resource,
                        'description' => 'The requested Google Meet resource does not exist or has expired.',
                    ],
                ],
            ],
        ];
    }

    /**
     * Generate standard Google 400 Bad Request error response.
     */
    public static function sampleBadRequestResponse(string $message = "Field 'config.accessType' value is invalid"): array
    {
        return [
            'error' => [
                'code' => 400,
                'message' => $message,
                'status' => 'INVALID_ARGUMENT',
                'details' => [
                    [
                        '@type' => 'type.googleapis.com/google.rpc.BadRequest',
                        'fieldViolations' => [
                            [
                                'field' => 'config.accessType',
                                'description' => 'Must be one of: OPEN, TRUSTED, RESTRICTED',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Generate standard Google 500 Internal Server Error response.
     */
    public static function sampleInternalErrorResponse(string $message = 'Internal error encountered.'): array
    {
        return [
            'error' => [
                'code' => 500,
                'message' => $message,
                'status' => 'INTERNAL',
            ],
        ];
    }

    /**
     * Generate standard Google 401 Unauthenticated error response.
     */
    public static function sampleUnauthenticatedResponse(): array
    {
        return [
            'error' => [
                'code' => 401,
                'message' => 'Request had invalid authentication credentials. Expected OAuth 2 access token.',
                'status' => 'UNAUTHENTICATED',
            ],
        ];
    }
}
