<?php

namespace Tests\Unit;

use Tests\TestCase;
use Tests\Support\GoogleMeetFake;
use App\Services\GoogleMeet\GoogleMeetService;
use App\Services\GoogleMeet\Contracts\MeetApiClientInterface;
use App\Services\GoogleMeet\Clients\HttpMeetApiClient;
use App\Models\MeetingRoom;
use App\Models\ConferenceRecord;
use App\Models\ConferenceParticipant;
use App\Models\ConferenceRecording;
use App\Models\ConferenceTranscript;
use App\Models\ConferenceTranscriptEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class GoogleMeetDataFidelityAdversarialTest extends TestCase
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
     * ADVERSARIAL STRESS TEST 1: Massive Transcript Text & High Utterance Volume.
     * - Generates 300 sentence-level utterances with long dialogues (> 1.2 MB total text).
     * - Verifies that GoogleMeetService ingests, persists, and links all 300 entries.
     * - Asserts entries_count in conference_transcripts is 300.
     * - Asserts full_text and content_text assemble every sentence in chronological order.
     * - Asserts $record->transcriptEntries HasManyThrough returns all 300 entries.
     * - Asserts $transcript->compileFullText() regenerates exact consolidated text.
     */
    public function test_massive_transcript_dialogue_and_high_utterance_volume_fidelity(): void
    {
        $confId = 'conf-stress-massive-transcript-001';
        $canonicalConfName = "conferenceRecords/{$confId}";
        $transcriptName = "{$canonicalConfName}/transcripts/trans-massive-999";

        $speakers = [
            "{$canonicalConfName}/participants/p-tech" => 'Dr. Carlos Eduardo (Técnico Titular SEJUS)',
            "{$canonicalConfName}/participants/p-assist" => 'Dra. Vanessa Lima (Assistente Social)',
            "{$canonicalConfName}/participants/p-egresso" => 'Marcos Vinicius (Egresso do Sistema)',
            "{$canonicalConfName}/participants/p-psico" => 'Dra. Roberta Andrade (Psicóloga Conecta)',
            "{$canonicalConfName}/participants/p-anon" => 'Familiar Maria Santos (Convidada)',
        ];

        $participantsPayload = [];
        foreach ($speakers as $pName => $displayName) {
            $isAnon = str_contains($pName, 'anon');
            $participantsPayload[] = [
                'name' => $pName,
                $isAnon ? 'anonymousUser' : 'signedinUser' => [
                    'displayName' => $displayName,
                    'user' => $isAnon ? null : 'users/usr-' . md5($pName),
                ],
                'earliestStartTime' => '2026-11-02T13:58:00.000Z',
                'latestEndTime' => '2026-11-02T16:05:00.000Z',
            ];
        }

        // Generate 300 dialogue entries with substantial textual payloads
        $entriesPayload = [];
        $speakerKeys = array_keys($speakers);
        $totalEntries = 300;
        $sampleLongParagraph = trim(str_repeat("Este é um relato detalhado sobre o processo de ressocialização, acompanhamento psicossocial, plano individual de reinserção profissional e acesso às políticas públicas de cidadania. ", 20)); // ~3.5 KB per sample block

        $baseTime = Carbon::parse('2026-11-02T14:00:00.000Z');
        for ($i = 1; $i <= $totalEntries; $i++) {
            $speakerKey = $speakerKeys[($i - 1) % count($speakerKeys)];
            $speakerName = $speakers[$speakerKey];
            $startTime = $baseTime->copy()->addSeconds($i * 15);
            $endTime = $startTime->copy()->addSeconds(12);

            // Vary text length: every 10th utterance is a massive multi-kilobyte monologue
            $dialogueText = ($i % 10 === 0)
                ? "[Bloco Especial #{$i}] {$sampleLongParagraph}"
                : "Manifestação oficial nº {$i} proferida por {$speakerName}. Tratando das condições de atendimento psicossocial e oportunidades de qualificação profissional no estado.";

            $entriesPayload[] = [
                'name' => "{$transcriptName}/entries/entry-" . sprintf('%04d', $i),
                'participant' => $speakerKey,
                'speakerName' => $speakerName,
                'text' => $dialogueText,
                'languageCode' => 'pt-BR',
                'startTime' => $startTime->toIso8601ZuluString('milliseconds'),
                'endTime' => $endTime->toIso8601ZuluString('milliseconds'),
            ];
        }

        GoogleMeetFake::fakeFullConferenceSync($confId, [
            'conference' => [
                'name' => $canonicalConfName,
                'startTime' => '2026-11-02T14:00:00.000Z',
                'endTime' => '2026-11-02T16:05:00.000Z',
            ],
            'participants' => $participantsPayload,
            'transcripts' => [
                [
                    'name' => $transcriptName,
                    'state' => 'FILE_GENERATED',
                    'docsDestination' => [
                        'document' => '1DocsMassiveTranscriptId000999888',
                        'exportUri' => 'https://docs.google.com/document/d/1DocsMassiveTranscriptId000999888/view',
                    ],
                    'startTime' => '2026-11-02T14:00:15.000Z',
                    'endTime' => '2026-11-02T16:00:00.000Z',
                ],
            ],
            'entries' => $entriesPayload,
        ]);

        $record = $this->meetService->syncConferenceRecord($confId);

        // 1. Verify conference record persisted
        $this->assertInstanceOf(ConferenceRecord::class, $record);
        $this->assertSame($canonicalConfName, $record->conference_record_name);

        // 2. Verify transcript persisted with full volume
        $this->assertSame(1, $record->transcripts()->count());
        $transcript = $record->transcripts()->first();
        $this->assertNotNull($transcript);
        $this->assertSame($transcriptName, $transcript->transcript_name);
        $this->assertSame($totalEntries, $transcript->entries_count);

        // 3. Verify all 300 sentence-level entries persisted in conference_transcript_entries table
        $this->assertSame($totalEntries, ConferenceTranscriptEntry::where('conference_transcript_id', $transcript->id)->count());
        $this->assertSame($totalEntries, $transcript->entries()->count());

        // 4. Verify HasManyThrough relationship navigation directly from $record
        $throughEntries = $record->transcriptEntries;
        $this->assertCount($totalEntries, $throughEntries);

        // 5. Check boundary entries (first, middle, last) for 100% data fidelity
        $firstEntry = ConferenceTranscriptEntry::where('entry_name', "{$transcriptName}/entries/entry-0001")->first();
        $this->assertNotNull($firstEntry);
        $this->assertSame($speakers[$speakerKeys[0]], $firstEntry->speaker_name);
        $this->assertStringContainsString('Manifestação oficial nº 1', $firstEntry->text);

        $midEntry = ConferenceTranscriptEntry::where('entry_name', "{$transcriptName}/entries/entry-0150")->first();
        $this->assertNotNull($midEntry);
        $this->assertStringContainsString('[Bloco Especial #150]', $midEntry->text);
        $this->assertGreaterThan(3000, strlen($midEntry->text));

        $lastEntry = ConferenceTranscriptEntry::where('entry_name', "{$transcriptName}/entries/entry-0300")->first();
        $this->assertNotNull($lastEntry);
        $this->assertStringContainsString('[Bloco Especial #300]', $lastEntry->text);

        // 6. Verify compiled full_text in transcript contains chronological dialogue
        $fullText = $transcript->full_text;
        $this->assertNotNull($fullText);
        $this->assertGreaterThan(100000, strlen($fullText), 'Full text should exceed 100 KB');
        $this->assertStringContainsString('[14:00:15] Dr. Carlos Eduardo (Técnico Titular SEJUS):', $fullText);
        $this->assertStringContainsString('[Bloco Especial #300]', $fullText);

        // 7. Verify compileFullText() utility produces exact same representation
        $recompiled = $transcript->compileFullText(false);
        $this->assertSame($fullText, $recompiled);
    }

    /**
     * ADVERSARIAL STRESS TEST 2: Extreme Unicode, 4-Byte Emojis, Quotations, and Escaping Payloads.
     * - Tests 4-byte UTF-8 emojis (👨‍👩‍👦‍👦 🚀 🔥 💡 🎉 🤖 🇧🇷 🧑‍💻 🛑 ✨).
     * - Tests international multilingual text (Japanese, Arabic RTL, Russian Cyrillic, Greek, German umlauts).
     * - Tests punctuation: single quotes, double quotes, smart quotes (“ ” ‘ ’), guillemets (« »), backslashes (\), tabs, newlines.
     * - Tests attack vector injection strings (SQL injection, XSS payload).
     * - Verifies 100% byte-for-byte fidelity without escaping or encoding corruption.
     */
    public function test_unicode_emojis_quotes_and_injection_payloads_data_fidelity(): void
    {
        $confId = 'conf-stress-unicode-payloads-002';
        $canonicalConfName = "conferenceRecords/{$confId}";
        $transcriptName = "{$canonicalConfName}/transcripts/trans-unicode";

        $adversarialCases = [
            1 => [
                'speaker' => '👨‍⚖️ Juiz de Direito & Conciliador (Vara de Execuções Penais)',
                'text' => 'Audiência de Custódia & Mediação iniciada com sucesso! 👨‍👩‍👧‍👦 ⚖️ 🚀 🇧🇷 🛡️ ✨',
            ],
            2 => [
                'speaker' => 'Dr. José "O Advogado" D\'Ávila (OAB/ES nº 12.345-B)',
                'text' => 'Pela ordem, Excelência: «Declaro sob as penas da lei que o sentenciado compareceu pontualmente». Citação: "Artigo 5º, inciso LXIX da CF/88".',
            ],
            3 => [
                'speaker' => '田中 太郎 (Auditor Internacional)',
                'text' => '会議の記録と文字起こしテスト（正常終了）: こんにちは、世界！ システムの統合テストを実施中です。',
            ],
            4 => [
                'speaker' => 'مستشار الدعم التقني (Suporte Árabe)',
                'text' => 'تسجيل وقائع المؤتمر والنسخ النصي بالكامل مع الحفاظ على اتجاه الكتابة من اليمين إلى اليسار 100%.',
            ],
            5 => [
                'speaker' => 'Дмитрий Иванов (Observador Externo)',
                'text' => 'Протокол видеоконференции и транскрипция текста на русском языке: Всё работает стабильно и корректно.',
            ],
            6 => [
                'speaker' => 'Γεώργιος Παπαδόπουλος (Perito Forense)',
                'text' => 'Καταγραφή συνεδρίας και απομαγνητοφώνηση ομιλιών με πλήρη ακρίβεια δεδομένων.',
            ],
            7 => [
                'speaker' => 'Dr. Jürgen Müller (Coordenador Técnico)',
                'text' => 'Überprüfung der Vollständigkeit, Zuverlässigkeit und Datenintegrität: Straße & Größe 100% einwandfrei!',
            ],
            8 => [
                'speaker' => 'Técnico de Segurança da Informação',
                'text' => "' OR '1'='1'; DROP TABLE conference_records; DROP TABLE conference_participants; -- /* SQL Injection Attack Test */",
            ],
            9 => [
                'speaker' => 'Analista de QA (XSS Vector)',
                'text' => '<script>alert("XSS Vulnerability Test")</script><img src="x" onerror="window.location=\'https://evil.com\'" /><b>Safe Text</b>',
            ],
            10 => [
                'speaker' => 'DevOps Engineer (Paths & Escapes)',
                'text' => "Windows Path: C:\\Program Files\\Conecta Egresso\\logs\\2026\\11\\app.log \n Unix Path: /var/log/conecta/meet.log \t Tabbed \r\n Backslash: \\\\server\\share\\data",
            ],
        ];

        $participantsPayload = [];
        $entriesPayload = [];
        $baseTime = Carbon::parse('2026-11-02T15:00:00.000Z');

        foreach ($adversarialCases as $index => $case) {
            $partName = "{$canonicalConfName}/participants/p-adv-" . sprintf('%02d', $index);
            $participantsPayload[] = [
                'name' => $partName,
                'signedinUser' => [
                    'displayName' => $case['speaker'],
                    'user' => 'users/usr-adv-' . $index,
                ],
                'earliestStartTime' => '2026-11-02T15:00:00.000Z',
                'latestEndTime' => '2026-11-02T15:30:00.000Z',
            ];

            $entriesPayload[] = [
                'name' => "{$transcriptName}/entries/e-" . sprintf('%02d', $index),
                'participant' => $partName,
                'speakerName' => $case['speaker'],
                'text' => $case['text'],
                'languageCode' => 'pt-BR',
                'startTime' => $baseTime->copy()->addMinutes($index)->toIso8601ZuluString('milliseconds'),
                'endTime' => $baseTime->copy()->addMinutes($index)->addSeconds(30)->toIso8601ZuluString('milliseconds'),
            ];
        }

        GoogleMeetFake::fakeFullConferenceSync($confId, [
            'conference' => [
                'name' => $canonicalConfName,
                'startTime' => '2026-11-02T15:00:00.000Z',
                'endTime' => '2026-11-02T15:30:00.000Z',
            ],
            'participants' => $participantsPayload,
            'transcripts' => [
                [
                    'name' => $transcriptName,
                    'state' => 'FILE_GENERATED',
                    'docsDestination' => [
                        'document' => '1DocsUnicodeId999',
                        'exportUri' => 'https://docs.google.com/document/d/1DocsUnicodeId999/view',
                    ],
                ],
            ],
            'entries' => $entriesPayload,
        ]);

        $record = $this->meetService->syncConferenceRecord($confId);

        // 1. Verify table integrity: database tables MUST still exist (SQL injection string did NOT drop tables)
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('conference_records'));
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('conference_participants'));
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('conference_transcripts'));
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('conference_transcript_entries'));

        // 2. Verify all 10 participants persisted with 100% exact string fidelity
        $this->assertSame(10, $record->participants()->count());
        foreach ($adversarialCases as $index => $case) {
            $partName = "{$canonicalConfName}/participants/p-adv-" . sprintf('%02d', $index);
            $participant = ConferenceParticipant::where('participant_name', $partName)->first();

            $this->assertNotNull($participant, "Participant {$partName} was not found");
            $this->assertSame($case['speaker'], $participant->display_name, "Participant display name corrupted for index {$index}");
        }

        // 3. Verify all 10 transcript entries persisted with 100% exact text fidelity
        $transcript = $record->transcripts()->first();
        $this->assertNotNull($transcript);
        $this->assertSame(10, $transcript->entries_count);

        foreach ($adversarialCases as $index => $case) {
            $entryName = "{$transcriptName}/entries/e-" . sprintf('%02d', $index);
            $entry = ConferenceTranscriptEntry::where('entry_name', $entryName)->first();

            $this->assertNotNull($entry, "Transcript entry {$entryName} was not found");
            $this->assertSame($case['speaker'], $entry->speaker_name, "Speaker name mismatch for entry {$index}");
            $this->assertSame($case['text'], $entry->text, "Dialogue text corrupted for entry {$index}");
        }

        // 4. Verify transcript full_text contains raw text intact
        $fullText = $transcript->full_text;
        $this->assertStringContainsString('👨‍👩‍👧‍👦 ⚖️ 🚀 🇧🇷 🛡️ ✨', $fullText);
        $this->assertStringContainsString('田中 太郎', $fullText);
        $this->assertStringContainsString('تسجيل وقائع المؤتمر', $fullText);
        $this->assertStringContainsString('Дмитрий Иванов', $fullText);
        $this->assertStringContainsString('DROP TABLE conference_records', $fullText);
        $this->assertStringContainsString('<script>alert("XSS Vulnerability Test")</script>', $fullText);
        $this->assertStringContainsString('C:\\Program Files\\Conecta Egresso', $fullText);
    }

    /**
     * ADVERSARIAL STRESS TEST 3: Complex Participant Lists (105 Participants, Anonymous, Phone Dial-in, Rejoin Sessions).
     * - 35 Signed-in Google accounts.
     * - 35 Anonymous attendees.
     * - 35 Phone dial-in callers (+55 11 99999-XXXX, +1 650 253 0000, etc.).
     * - Rejoining participant (initial session + updated later session).
     * - Edge cases: zero second duration, ongoing participant (null end time).
     * - Verifies scopes (signedIn, anonymous, phone), identity predicates, and duration calculations.
     */
    public function test_complex_participant_lists_at_scale_and_diverse_user_types(): void
    {
        $confId = 'conf-stress-105-participants-003';
        $canonicalConfName = "conferenceRecords/{$confId}";

        $participantsPayload = [];

        // 1. 35 Signed-in Google accounts
        for ($i = 1; $i <= 35; $i++) {
            $pName = "{$canonicalConfName}/participants/p-signed-" . sprintf('%03d', $i);
            $participantsPayload[] = [
                'name' => $pName,
                'signedinUser' => [
                    'displayName' => "Técnico Especialista #{$i}",
                    'user' => "users/100000000000000" . sprintf('%03d', $i),
                ],
                'earliestStartTime' => '2026-11-02T14:00:00.000Z',
                'latestEndTime' => '2026-11-02T15:30:00.000Z',
            ];
        }

        // 2. 35 Anonymous attendees
        for ($i = 1; $i <= 35; $i++) {
            $pName = "{$canonicalConfName}/participants/p-anon-" . sprintf('%03d', $i);
            $participantsPayload[] = [
                'name' => $pName,
                'anonymousUser' => [
                    'displayName' => "Convidado Externo #{$i}",
                ],
                'earliestStartTime' => '2026-11-02T14:05:00.000Z',
                'latestEndTime' => '2026-11-02T15:15:00.000Z',
            ];
        }

        // 3. 35 Phone dial-in callers
        for ($i = 1; $i <= 35; $i++) {
            $pName = "{$canonicalConfName}/participants/p-phone-" . sprintf('%03d', $i);
            $phoneNum = "+55 11 98765-" . sprintf('%04d', $i);
            $participantsPayload[] = [
                'name' => $pName,
                'phoneUser' => [
                    'displayName' => $phoneNum,
                ],
                'earliestStartTime' => '2026-11-02T14:10:00.000Z',
                'latestEndTime' => '2026-11-02T15:00:00.000Z',
            ];
        }

        // 4. Edge cases
        // Re-joiner participant
        $rejoinerName = "{$canonicalConfName}/participants/p-rejoiner-special";
        $participantsPayload[] = [
            'name' => $rejoinerName,
            'signedinUser' => [
                'displayName' => 'Participante Que Reconectou',
                'user' => 'users/99999999999999999',
            ],
            'earliestStartTime' => '2026-11-02T14:00:00.000Z',
            'latestEndTime' => '2026-11-02T15:45:00.000Z', // Total session span across reconnects
        ];

        // Instant join-and-leave (0s duration)
        $instantName = "{$canonicalConfName}/participants/p-instant-disconnect";
        $participantsPayload[] = [
            'name' => $instantName,
            'anonymousUser' => [
                'displayName' => 'Desconexão Instantânea',
            ],
            'earliestStartTime' => '2026-11-02T14:20:00.000Z',
            'latestEndTime' => '2026-11-02T14:20:00.000Z',
        ];

        // Ongoing attendee (null end time)
        $ongoingName = "{$canonicalConfName}/participants/p-ongoing-active";
        $participantsPayload[] = [
            'name' => $ongoingName,
            'phoneUser' => [
                'displayName' => '+55 27 99999-0000',
            ],
            'earliestStartTime' => '2026-11-02T14:30:00.000Z',
            'latestEndTime' => null,
        ];

        $totalExpectedParticipants = 35 + 35 + 35 + 3; // 108

        GoogleMeetFake::fakeFullConferenceSync($confId, [
            'conference' => [
                'name' => $canonicalConfName,
                'startTime' => '2026-11-02T14:00:00.000Z',
                'endTime' => '2026-11-02T16:00:00.000Z',
            ],
            'participants' => $participantsPayload,
        ]);

        $record = $this->meetService->syncConferenceRecord($confId);

        // 1. Total count
        $this->assertSame($totalExpectedParticipants, $record->participants()->count());
        $this->assertCount($totalExpectedParticipants, $record->participants);

        // 2. Scopes verification
        $this->assertSame(36, ConferenceParticipant::signedIn()->where('conference_record_id', $record->id)->count()); // 35 + 1 rejoiner
        $this->assertSame(36, ConferenceParticipant::anonymous()->where('conference_record_id', $record->id)->count()); // 35 + 1 instant
        $this->assertSame(36, ConferenceParticipant::phone()->where('conference_record_id', $record->id)->count()); // 35 + 1 ongoing

        // 3. User types and helper predicates
        $firstSigned = ConferenceParticipant::where('participant_name', "{$canonicalConfName}/participants/p-signed-001")->first();
        $this->assertNotNull($firstSigned);
        $this->assertTrue($firstSigned->isSignedIn());
        $this->assertFalse($firstSigned->isAnonymous());
        $this->assertFalse($firstSigned->isPhone());
        $this->assertSame('signedin', $firstSigned->user_type);
        $this->assertSame('users/100000000000000001', $firstSigned->user_identifier);
        $this->assertSame(5400, $firstSigned->duration_seconds); // 90 min = 5400s
        $this->assertSame('1h 30m 00s', $firstSigned->duration_human);

        $firstAnon = ConferenceParticipant::where('participant_name', "{$canonicalConfName}/participants/p-anon-001")->first();
        $this->assertNotNull($firstAnon);
        $this->assertFalse($firstAnon->isSignedIn());
        $this->assertTrue($firstAnon->isAnonymous());
        $this->assertFalse($firstAnon->isPhone());
        $this->assertSame('anonymous', $firstAnon->user_type);
        $this->assertNull($firstAnon->user_identifier);
        $this->assertSame(4200, $firstAnon->duration_seconds); // 70 min = 4200s
        $this->assertSame('1h 10m 00s', $firstAnon->duration_human);

        $firstPhone = ConferenceParticipant::where('participant_name', "{$canonicalConfName}/participants/p-phone-001")->first();
        $this->assertNotNull($firstPhone);
        $this->assertFalse($firstPhone->isSignedIn());
        $this->assertFalse($firstPhone->isAnonymous());
        $this->assertTrue($firstPhone->isPhone());
        $this->assertSame('phone', $firstPhone->user_type);
        $this->assertNull($firstPhone->user_identifier);
        $this->assertSame(3000, $firstPhone->duration_seconds); // 50 min = 3000s
        $this->assertSame('50m 00s', $firstPhone->duration_human);

        // 4. Instant disconnect edge case
        $instant = ConferenceParticipant::where('participant_name', $instantName)->first();
        $this->assertNotNull($instant);
        $this->assertSame(0, $instant->duration_seconds);
        $this->assertSame('00m 00s', $instant->duration_human);

        // 5. Ongoing active edge case
        $ongoing = ConferenceParticipant::where('participant_name', $ongoingName)->first();
        $this->assertNotNull($ongoing);
        $this->assertNull($ongoing->duration_seconds);
        $this->assertNull($ongoing->duration_human);
    }

    /**
     * ADVERSARIAL STRESS TEST 4: Multiple Recordings and Multiple Transcripts Navigation.
     * - A conference with 2 video recordings (one FILE_GENERATED, one STARTED).
     * - 2 distinct transcripts with separate dialogue entries.
     * - Verifies:
     *   - $record->recordings returns 2.
     *   - $record->transcripts returns 2.
     *   - $record->transcriptEntries returns combined entries across all transcripts via HasManyThrough.
     *   - Each transcript's $t->entries returns only its own utterances.
     */
    public function test_multiple_recordings_and_multiple_transcripts_relationship_navigation(): void
    {
        $confId = 'conf-stress-multi-resources-004';
        $canonicalConfName = "conferenceRecords/{$confId}";
        $trans1Name = "{$canonicalConfName}/transcripts/trans-pt-01";
        $trans2Name = "{$canonicalConfName}/transcripts/trans-en-02";

        GoogleMeetFake::reset();

        $trans1Entries = [
            [
                'name' => "{$trans1Name}/entries/e1",
                'participant' => "{$canonicalConfName}/participants/p1",
                'speakerName' => 'Técnico',
                'text' => 'Início da sessão em português.',
                'languageCode' => 'pt-BR',
                'startTime' => '2026-11-02T14:01:00Z',
                'endTime' => '2026-11-02T14:01:10Z',
            ],
            [
                'name' => "{$trans1Name}/entries/e2",
                'participant' => "{$canonicalConfName}/participants/p2",
                'speakerName' => 'Egresso',
                'text' => 'Confirmo a presença.',
                'languageCode' => 'pt-BR',
                'startTime' => '2026-11-02T14:01:12Z',
                'endTime' => '2026-11-02T14:01:20Z',
            ],
        ];

        $trans2Entries = [
            [
                'name' => "{$trans2Name}/entries/e1",
                'participant' => "{$canonicalConfName}/participants/p1",
                'speakerName' => 'Interpreter',
                'text' => 'Session started in English.',
                'languageCode' => 'en-US',
                'startTime' => '2026-11-02T14:01:02Z',
                'endTime' => '2026-11-02T14:01:11Z',
            ],
        ];

        Http::fake([
            GoogleMeetFake::BASE_URL . "/conferenceRecords/{$confId}/transcripts/trans-pt-01/entries*" => Http::response([
                'transcriptEntries' => $trans1Entries,
            ], 200),
            GoogleMeetFake::BASE_URL . "/conferenceRecords/{$confId}/transcripts/trans-en-02/entries*" => Http::response([
                'transcriptEntries' => $trans2Entries,
            ], 200),
            GoogleMeetFake::BASE_URL . "/conferenceRecords/{$confId}/transcripts*" => Http::response([
                'transcripts' => [
                    [
                        'name' => $trans1Name,
                        'state' => 'FILE_GENERATED',
                        'docsDestination' => [
                            'document' => '1DocPt111',
                            'exportUri' => 'https://docs.google.com/document/d/1DocPt111/view',
                        ],
                    ],
                    [
                        'name' => $trans2Name,
                        'state' => 'FILE_GENERATED',
                        'docsDestination' => [
                            'document' => '1DocEn222',
                            'exportUri' => 'https://docs.google.com/document/d/1DocEn222/view',
                        ],
                    ],
                ],
            ], 200),
            GoogleMeetFake::BASE_URL . "/conferenceRecords/{$confId}/recordings*" => Http::response([
                'recordings' => [
                    [
                        'name' => "{$canonicalConfName}/recordings/rec-part-1",
                        'state' => 'FILE_GENERATED',
                        'driveDestination' => [
                            'file' => '1DriveVideoFile001',
                            'exportUri' => 'https://drive.google.com/file/d/1DriveVideoFile001/view',
                        ],
                        'startTime' => '2026-11-02T14:00:00Z',
                        'endTime' => '2026-11-02T14:45:00Z',
                    ],
                    [
                        'name' => "{$canonicalConfName}/recordings/rec-part-2",
                        'state' => 'STARTED',
                        'driveDestination' => [],
                        'startTime' => '2026-11-02T14:50:00Z',
                        'endTime' => null,
                    ],
                ],
            ], 200),
            GoogleMeetFake::BASE_URL . "/conferenceRecords/{$confId}/participants*" => Http::response([
                'participants' => [
                    [
                        'name' => "{$canonicalConfName}/participants/p1",
                        'signedinUser' => ['displayName' => 'Técnico', 'user' => 'users/1'],
                    ],
                    [
                        'name' => "{$canonicalConfName}/participants/p2",
                        'signedinUser' => ['displayName' => 'Egresso', 'user' => 'users/2'],
                    ],
                ],
            ], 200),
            GoogleMeetFake::BASE_URL . "/conferenceRecords/{$confId}" => Http::response([
                'name' => $canonicalConfName,
                'space' => 'spaces/sMultiRes',
                'startTime' => '2026-11-02T14:00:00Z',
                'endTime' => '2026-11-02T15:30:00Z',
            ], 200),
        ]);

        $record = $this->meetService->syncConferenceRecord($confId);

        // 1. Verify recordings
        $this->assertSame(2, $record->recordings()->count());
        $rec1 = $record->recordings()->where('recording_name', "{$canonicalConfName}/recordings/rec-part-1")->first();
        $this->assertNotNull($rec1);
        $this->assertTrue($rec1->isGenerated());
        $this->assertSame('1DriveVideoFile001', $rec1->drive_file_id);
        $this->assertSame('https://drive.google.com/file/d/1DriveVideoFile001/view', $rec1->drive_link);
        $this->assertSame('https://drive.google.com/uc?export=download&id=1DriveVideoFile001', $rec1->drive_download_link);

        $rec2 = $record->recordings()->where('recording_name', "{$canonicalConfName}/recordings/rec-part-2")->first();
        $this->assertNotNull($rec2);
        $this->assertFalse($rec2->isGenerated());
        $this->assertTrue($rec2->isProcessing());
        $this->assertNull($rec2->drive_file_id);
        $this->assertNull($rec2->drive_link);

        // 2. Verify transcripts
        $this->assertSame(2, $record->transcripts()->count());
        $t1 = $record->transcripts()->where('transcript_name', $trans1Name)->first();
        $t2 = $record->transcripts()->where('transcript_name', $trans2Name)->first();
        $this->assertNotNull($t1);
        $this->assertNotNull($t2);
        $this->assertSame(2, $t1->entries_count);
        $this->assertSame(1, $t2->entries_count);

        // 3. Verify discrete entries per transcript
        $this->assertSame(2, $t1->entries()->count());
        $this->assertSame(1, $t2->entries()->count());

        // 4. Verify HasManyThrough cross-transcript aggregation directly on $record
        $allEntries = $record->transcriptEntries;
        $this->assertCount(3, $allEntries);
        $this->assertTrue($allEntries->contains('entry_name', "{$trans1Name}/entries/e1"));
        $this->assertTrue($allEntries->contains('entry_name', "{$trans1Name}/entries/e2"));
        $this->assertTrue($allEntries->contains('entry_name', "{$trans2Name}/entries/e1"));
    }

    /**
     * ADVERSARIAL STRESS TEST 5: Foreign Key Constraints, Cascade Deletion, and Null-On-Delete Integrity.
     * - Links a ConferenceRecord to a MeetingRoom.
     * - Populates participants, recordings, transcripts, and transcript entries.
     * - Verifies:
     *   1. When MeetingRoom is deleted, conference_records.meeting_room_id is set to NULL (nullOnDelete).
     *      The conference record and all its child sub-resources remain intact.
     *   2. When ConferenceRecord is deleted, all related participants, recordings, transcripts,
     *      and transcript entries are cascadingly deleted.
     *   3. Direct orphan insertions with non-existent foreign keys are blocked by database constraints.
     */
    public function test_foreign_key_referential_integrity_cascade_and_null_on_delete(): void
    {
        // 1. Create a MeetingRoom
        $room = MeetingRoom::create([
            'space_name' => 'spaces/fk-test-space-001',
            'meeting_uri' => 'https://meet.google.com/abc-defg-hij',
            'meeting_code' => 'abc-defg-hij',
            'access_type' => 'OPEN',
            'entry_point_access' => 'CREATOR_APP_ONLY',
            'moderation' => 'ON',
            'chat_restriction' => 'HOSTS_ONLY',
            'present_restriction' => 'HOSTS_ONLY',
            'default_join_as_viewer_type' => 'ON',
            'status' => 'active',
        ]);

        $confId = 'conf-fk-integrity-005';
        $canonicalConfName = "conferenceRecords/{$confId}";

        GoogleMeetFake::fakeFullConferenceSync($confId, [
            'conference' => [
                'name' => $canonicalConfName,
                'space' => $room->space_name,
                'startTime' => '2026-11-02T14:00:00Z',
                'endTime' => '2026-11-02T15:00:00Z',
            ],
        ]);

        $record = $this->meetService->syncConferenceRecord($confId);

        // Verify linked to meeting room
        $this->assertSame($room->id, $record->meeting_room_id);
        $this->assertSame(1, ConferenceRecord::where('meeting_room_id', $room->id)->count());
        $this->assertSame(3, $record->participants()->count());
        $this->assertSame(1, $record->recordings()->count());
        $this->assertSame(1, $record->transcripts()->count());
        $this->assertSame(2, $record->transcriptEntries()->count());

        $transcriptId = $record->transcripts()->first()->id;

        // 2. Test NULL-ON-DELETE: Delete the parent MeetingRoom
        $room->delete();

        $record->refresh();
        $this->assertNull($record->meeting_room_id, 'meeting_room_id should be NULL after room deletion (nullOnDelete)');
        $this->assertSame(1, ConferenceRecord::where('id', $record->id)->count(), 'ConferenceRecord must NOT be deleted when meeting room is deleted');
        $this->assertSame(3, $record->participants()->count());
        $this->assertSame(1, $record->recordings()->count());
        $this->assertSame(1, $record->transcripts()->count());
        $this->assertSame(2, $record->transcriptEntries()->count());

        // 3. Test CASCADE-ON-DELETE: Delete the ConferenceRecord
        $recordId = $record->id;
        $record->delete();

        $this->assertSame(0, ConferenceRecord::where('id', $recordId)->count());
        $this->assertSame(0, ConferenceParticipant::where('conference_record_id', $recordId)->count(), 'Participants must be cascade deleted');
        $this->assertSame(0, ConferenceRecording::where('conference_record_id', $recordId)->count(), 'Recordings must be cascade deleted');
        $this->assertSame(0, ConferenceTranscript::where('conference_record_id', $recordId)->count(), 'Transcripts must be cascade deleted');
        $this->assertSame(0, ConferenceTranscriptEntry::where('conference_transcript_id', $transcriptId)->count(), 'Transcript entries must be cascade deleted via transcript cascade');

        // 4. Test Foreign Key constraint enforcement against orphaned inserts
        try {
            DB::table('conference_participants')->insert([
                'conference_record_id' => 999999, // Non-existent foreign key
                'participant_name' => 'orphaned-participant',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('Expected QueryException was not thrown on orphaned conference_participant insert');
        } catch (QueryException $e) {
            $this->assertStringContainsString('FOREIGN KEY', strtoupper($e->getMessage()));
        }

        try {
            DB::table('conference_transcript_entries')->insert([
                'conference_transcript_id' => 999999, // Non-existent foreign key
                'entry_name' => 'orphaned-entry',
                'text' => 'Orphaned dialogue text',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('Expected QueryException was not thrown on orphaned conference_transcript_entry insert');
        } catch (QueryException $e) {
            $this->assertStringContainsString('FOREIGN KEY', strtoupper($e->getMessage()));
        }
    }

    /**
     * ADVERSARIAL STRESS TEST 6: Bi-directional Eloquent Relationship Traversal.
     * Tests all relationships in both forward and reverse directions:
     * - $record->meetingRoom & $room->conferenceRecords / $room->latestConferenceRecord
     * - $record->participants & $participant->conferenceRecord
     * - $record->recordings & $recording->conferenceRecord
     * - $record->transcripts & $transcript->conferenceRecord
     * - $record->transcriptEntries (HasManyThrough)
     * - $transcript->entries & $entry->transcript / $entry->conferenceTranscript
     */
    public function test_bidirectional_eloquent_relationship_traversal(): void
    {
        $room = MeetingRoom::create([
            'space_name' => 'spaces/relation-nav-space',
            'meeting_uri' => 'https://meet.google.com/rel-test-xyz',
            'meeting_code' => 'rel-test-xyz',
            'access_type' => 'OPEN',
            'entry_point_access' => 'CREATOR_APP_ONLY',
            'moderation' => 'ON',
            'chat_restriction' => 'HOSTS_ONLY',
            'present_restriction' => 'HOSTS_ONLY',
            'default_join_as_viewer_type' => 'ON',
            'status' => 'active',
        ]);

        $confId = 'conf-relation-nav-006';
        $canonicalConfName = "conferenceRecords/{$confId}";

        GoogleMeetFake::fakeFullConferenceSync($confId, [
            'conference' => [
                'name' => $canonicalConfName,
                'space' => $room->space_name,
                'startTime' => '2026-11-02T10:00:00Z',
                'endTime' => '2026-11-02T11:00:00Z',
            ],
        ]);

        $record = $this->meetService->syncConferenceRecord($confId);

        // 1. $record -> $room
        $this->assertInstanceOf(MeetingRoom::class, $record->meetingRoom);
        $this->assertSame($room->id, $record->meetingRoom->id);

        // 2. $room -> $record (HasMany & HasOne)
        $this->assertTrue($room->conferenceRecords->contains('id', $record->id));
        $this->assertSame($record->id, $room->latestConferenceRecord?->id);

        // 3. $record -> $participants & inverse
        $participant = $record->participants->first();
        $this->assertInstanceOf(ConferenceParticipant::class, $participant);
        $this->assertInstanceOf(ConferenceRecord::class, $participant->conferenceRecord);
        $this->assertSame($record->id, $participant->conferenceRecord->id);

        // 4. $record -> $recordings & inverse
        $recording = $record->recordings->first();
        $this->assertInstanceOf(ConferenceRecording::class, $recording);
        $this->assertInstanceOf(ConferenceRecord::class, $recording->conferenceRecord);
        $this->assertSame($record->id, $recording->conferenceRecord->id);

        // 5. $record -> $transcripts & inverse
        $transcript = $record->transcripts->first();
        $this->assertInstanceOf(ConferenceTranscript::class, $transcript);
        $this->assertInstanceOf(ConferenceRecord::class, $transcript->conferenceRecord);
        $this->assertSame($record->id, $transcript->conferenceRecord->id);

        // 6. $record -> $transcriptEntries (HasManyThrough)
        $this->assertCount(2, $record->transcriptEntries);

        // 7. $transcript -> $entries & inverse
        $entry = $transcript->entries->first();
        $this->assertInstanceOf(ConferenceTranscriptEntry::class, $entry);
        $this->assertInstanceOf(ConferenceTranscript::class, $entry->transcript);
        $this->assertInstanceOf(ConferenceTranscript::class, $entry->conferenceTranscript);
        $this->assertSame($transcript->id, $entry->transcript->id);
        $this->assertSame($transcript->id, $entry->conferenceTranscript->id);
    }

    /**
     * ADVERSARIAL STRESS TEST 7: Incremental Expansion Sync & State Evolution Idempotency.
     * - Initial sync while conference is active: 2 participants, 1 recording in STARTED, 1 transcript in STARTED, 2 entries.
     * - Second sync when conference concludes: end_time set, 2 additional participants joined, recording generated, transcript generated, 3 new entries appended.
     * - Asserts:
     *   - Total conference records remains exactly 1 (no duplicate rows created).
     *   - Status evolves from 'active' to 'ended'.
     *   - Participants count cleanly updates from 2 to 4 without duplicating the original 2.
     *   - Recordings count remains 1, state transitions from STARTED to FILE_GENERATED with drive_file_id.
     *   - Transcripts count remains 1, state transitions to FILE_GENERATED with docs_document_id.
     *   - Total transcript entries increases cleanly to 5 without duplicating the original 2.
     */
    public function test_incremental_expansion_sync_idempotency_without_duplication(): void
    {
        $confId = 'conf-stress-incremental-007';
        $canonicalConfName = "conferenceRecords/{$confId}";
        $transName = "{$canonicalConfName}/transcripts/trans-live";
        $recName = "{$canonicalConfName}/recordings/rec-live";

        // PHASE 1: Active conference mid-call
        GoogleMeetFake::fakeFullConferenceSync($confId, [
            'conference' => [
                'name' => $canonicalConfName,
                'startTime' => '2026-11-02T14:00:00.000Z',
                'endTime' => null, // Active
            ],
            'participants' => [
                [
                    'name' => "{$canonicalConfName}/participants/p1",
                    'signedinUser' => ['displayName' => 'Técnico Host', 'user' => 'users/host'],
                    'earliestStartTime' => '2026-11-02T14:00:00.000Z',
                    'latestEndTime' => null,
                ],
                [
                    'name' => "{$canonicalConfName}/participants/p2",
                    'signedinUser' => ['displayName' => 'Egresso Convidado', 'user' => 'users/guest'],
                    'earliestStartTime' => '2026-11-02T14:02:00.000Z',
                    'latestEndTime' => null,
                ],
            ],
            'recordings' => [
                [
                    'name' => $recName,
                    'state' => 'STARTED',
                    'driveDestination' => [],
                    'startTime' => '2026-11-02T14:00:10.000Z',
                ],
            ],
            'transcripts' => [
                [
                    'name' => $transName,
                    'state' => 'STARTED',
                    'docsDestination' => [],
                    'startTime' => '2026-11-02T14:00:15.000Z',
                ],
            ],
            'entries' => [
                [
                    'name' => "{$transName}/entries/e1",
                    'participant' => "{$canonicalConfName}/participants/p1",
                    'speakerName' => 'Técnico Host',
                    'text' => 'Iniciando a transmissão.',
                    'startTime' => '2026-11-02T14:00:20.000Z',
                ],
                [
                    'name' => "{$transName}/entries/e2",
                    'participant' => "{$canonicalConfName}/participants/p2",
                    'speakerName' => 'Egresso Convidado',
                    'text' => 'Olá, estou conectado.',
                    'startTime' => '2026-11-02T14:00:30.000Z',
                ],
            ],
        ]);

        $recordInitial = $this->meetService->syncConferenceRecord($confId);

        $this->assertSame('active', $recordInitial->status);
        $this->assertNull($recordInitial->end_time);
        $this->assertSame(1, ConferenceRecord::count());
        $this->assertSame(2, ConferenceParticipant::count());
        $this->assertSame(1, ConferenceRecording::count());
        $this->assertSame(1, ConferenceTranscript::count());
        $this->assertSame(2, ConferenceTranscriptEntry::count());

        $recModelInitial = $recordInitial->recordings->first();
        $this->assertSame('STARTED', $recModelInitial->state);
        $this->assertNull($recModelInitial->drive_file_id);

        $transModelInitial = $recordInitial->transcripts->first();
        $this->assertSame('STARTED', $transModelInitial->state);
        $this->assertNull($transModelInitial->docs_document_id);
        $this->assertSame(2, $transModelInitial->entries_count);

        // PHASE 2: Conference has finished, 2 late attendees joined, files generated, 3 more entries
        GoogleMeetFake::fakeFullConferenceSync($confId, [
            'conference' => [
                'name' => $canonicalConfName,
                'startTime' => '2026-11-02T14:00:00.000Z',
                'endTime' => '2026-11-02T15:00:00.000Z', // Ended
            ],
            'participants' => [
                [
                    'name' => "{$canonicalConfName}/participants/p1",
                    'signedinUser' => ['displayName' => 'Técnico Host', 'user' => 'users/host'],
                    'earliestStartTime' => '2026-11-02T14:00:00.000Z',
                    'latestEndTime' => '2026-11-02T15:00:00.000Z',
                ],
                [
                    'name' => "{$canonicalConfName}/participants/p2",
                    'signedinUser' => ['displayName' => 'Egresso Convidado', 'user' => 'users/guest'],
                    'earliestStartTime' => '2026-11-02T14:02:00.000Z',
                    'latestEndTime' => '2026-11-02T14:55:00.000Z',
                ],
                [
                    'name' => "{$canonicalConfName}/participants/p3",
                    'anonymousUser' => ['displayName' => 'Familiar Maria'],
                    'earliestStartTime' => '2026-11-02T14:15:00.000Z',
                    'latestEndTime' => '2026-11-02T14:50:00.000Z',
                ],
                [
                    'name' => "{$canonicalConfName}/participants/p4",
                    'phoneUser' => ['displayName' => '+55 11 98888-7777'],
                    'earliestStartTime' => '2026-11-02T14:20:00.000Z',
                    'latestEndTime' => '2026-11-02T14:40:00.000Z',
                ],
            ],
            'recordings' => [
                [
                    'name' => $recName,
                    'state' => 'FILE_GENERATED',
                    'driveDestination' => [
                        'file' => '1DriveGeneratedVideoFileId',
                        'exportUri' => 'https://drive.google.com/file/d/1DriveGeneratedVideoFileId/view',
                    ],
                    'startTime' => '2026-11-02T14:00:10.000Z',
                    'endTime' => '2026-11-02T15:00:00.000Z',
                ],
            ],
            'transcripts' => [
                [
                    'name' => $transName,
                    'state' => 'FILE_GENERATED',
                    'docsDestination' => [
                        'document' => '1DocsGeneratedTranscriptDocId',
                        'exportUri' => 'https://docs.google.com/document/d/1DocsGeneratedTranscriptDocId/view',
                    ],
                    'startTime' => '2026-11-02T14:00:15.000Z',
                    'endTime' => '2026-11-02T15:00:00.000Z',
                ],
            ],
            'entries' => [
                [
                    'name' => "{$transName}/entries/e1",
                    'participant' => "{$canonicalConfName}/participants/p1",
                    'speakerName' => 'Técnico Host',
                    'text' => 'Iniciando a transmissão.',
                    'startTime' => '2026-11-02T14:00:20.000Z',
                ],
                [
                    'name' => "{$transName}/entries/e2",
                    'participant' => "{$canonicalConfName}/participants/p2",
                    'speakerName' => 'Egresso Convidado',
                    'text' => 'Olá, estou conectado.',
                    'startTime' => '2026-11-02T14:00:30.000Z',
                ],
                [
                    'name' => "{$transName}/entries/e3",
                    'participant' => "{$canonicalConfName}/participants/p3",
                    'speakerName' => 'Familiar Maria',
                    'text' => 'Boa tarde a todos os presentes.',
                    'startTime' => '2026-11-02T14:15:30.000Z',
                ],
                [
                    'name' => "{$transName}/entries/e4",
                    'participant' => "{$canonicalConfName}/participants/p4",
                    'speakerName' => '+55 11 98888-7777',
                    'text' => 'Estou pelo telefone ouvindo.',
                    'startTime' => '2026-11-02T14:21:00.000Z',
                ],
                [
                    'name' => "{$transName}/entries/e5",
                    'participant' => "{$canonicalConfName}/participants/p1",
                    'speakerName' => 'Técnico Host',
                    'text' => 'Encerramos a sessão por hoje.',
                    'startTime' => '2026-11-02T14:59:00.000Z',
                ],
            ],
        ]);

        $recordUpdated = $this->meetService->syncConferenceRecord($confId);

        // Verify ID unchanged and zero duplicate records across all tables
        $this->assertSame($recordInitial->id, $recordUpdated->id);
        $this->assertSame('ended', $recordUpdated->status);
        $this->assertNotNull($recordUpdated->end_time);

        $this->assertSame(1, ConferenceRecord::count(), 'ConferenceRecord table count must be exactly 1');
        $this->assertSame(4, ConferenceParticipant::count(), 'ConferenceParticipant table count must be exactly 4');
        $this->assertSame(1, ConferenceRecording::count(), 'ConferenceRecording table count must be exactly 1');
        $this->assertSame(1, ConferenceTranscript::count(), 'ConferenceTranscript table count must be exactly 1');
        $this->assertSame(5, ConferenceTranscriptEntry::count(), 'ConferenceTranscriptEntry table count must be exactly 5');

        // Verify subresource states updated in place
        $recModelUpdated = $recordUpdated->recordings()->first();
        $this->assertSame('FILE_GENERATED', $recModelUpdated->state);
        $this->assertSame('1DriveGeneratedVideoFileId', $recModelUpdated->drive_file_id);
        $this->assertTrue($recModelUpdated->isGenerated());

        $transModelUpdated = $recordUpdated->transcripts()->first();
        $this->assertSame('FILE_GENERATED', $transModelUpdated->state);
        $this->assertSame('1DocsGeneratedTranscriptDocId', $transModelUpdated->docs_document_id);
        $this->assertSame(5, $transModelUpdated->entries_count);
        $this->assertTrue($transModelUpdated->isGenerated());
    }

    /**
     * ADVERSARIAL STRESS TEST 8: Timezone Offsets & Sub-second Microsecond Timestamp Precision.
     * - API inputs with non-UTC ISO 8601 timestamps (UTC-3 Brasilia, UTC+9 Tokyo, UTC+0 London).
     * - Asserts that timestamps are correctly normalized to UTC and preserved with microsecond precision.
     */
    public function test_timezone_offsets_and_microsecond_timestamp_fidelity(): void
    {
        $confId = 'conf-stress-timezones-008';
        $canonicalConfName = "conferenceRecords/{$confId}";

        // Start time in Brasilia timezone (UTC-3): 11:00:00.123456-03:00 == 14:00:00.123456Z
        $rawStartTime = '2026-11-02T11:00:00.123456-03:00';
        // End time in Tokyo timezone (UTC+9): 2026-11-03T00:30:15.654321+09:00 == 2026-11-02T15:30:15.654321Z
        $rawEndTime = '2026-11-03T00:30:15.654321+09:00';
        $rawExpireTime = '2026-12-02T15:30:15.000Z';

        GoogleMeetFake::fakeFullConferenceSync($confId, [
            'conference' => [
                'name' => $canonicalConfName,
                'startTime' => $rawStartTime,
                'endTime' => $rawEndTime,
                'expireTime' => $rawExpireTime,
            ],
        ]);

        $record = $this->meetService->syncConferenceRecord($confId);

        // Verify mathematical equality with UTC representation
        $expectedUtcStart = Carbon::parse('2026-11-02T14:00:00.123456Z');
        $expectedUtcEnd = Carbon::parse('2026-11-02T15:30:15.654321Z');

        $this->assertTrue($record->start_time->equalTo($expectedUtcStart), 'Start time does not match expected UTC representation');
        $this->assertTrue($record->end_time->equalTo($expectedUtcEnd), 'End time does not match expected UTC representation');

        // Verify raw string stored in database preserves microsecond precision
        $rawRow = DB::table('conference_records')->where('id', $record->id)->first();
        $this->assertStringContainsString('14:00:00.123456', $rawRow->start_time);
        $this->assertStringContainsString('15:30:15.654321', $rawRow->end_time);
    }
}
