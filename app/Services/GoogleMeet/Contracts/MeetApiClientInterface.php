<?php

namespace App\Services\GoogleMeet\Contracts;

use App\Services\GoogleMeet\Exceptions\GoogleMeetApiException;

interface MeetApiClientInterface
{
    /**
     * Create a new Google Meet virtual space.
     *
     * @param array $spaceData Initial space configuration payload
     * @return array Response JSON containing name, meetingUri, meetingCode, config, etc.
     * @throws GoogleMeetApiException
     */
    public function createSpace(array $spaceData = []): array;

    /**
     * Retrieve details of a Google Meet space by resource name.
     *
     * @param string $spaceName Format: spaces/{space}
     * @return array
     * @throws GoogleMeetApiException
     */
    public function getSpace(string $spaceName): array;

    /**
     * Retrieve macro details of a conference record.
     *
     * @param string $name Identifier format: conferenceRecords/{conferenceRecord}
     * @return array
     * @throws GoogleMeetApiException
     */
    public function getConferenceRecord(string $name): array;

    /**
     * List all participants who joined the conference.
     *
     * @param string $conferenceRecordName Format: conferenceRecords/{conferenceRecord}
     * @param array $params Query parameters (pageSize, pageToken, filter)
     * @return array
     * @throws GoogleMeetApiException
     */
    public function listParticipants(string $conferenceRecordName, array $params = []): array;

    /**
     * List all video recordings generated during the conference.
     *
     * @param string $conferenceRecordName Format: conferenceRecords/{conferenceRecord}
     * @param array $params Query parameters (pageSize, pageToken)
     * @return array
     * @throws GoogleMeetApiException
     */
    public function listRecordings(string $conferenceRecordName, array $params = []): array;

    /**
     * List all transcripts generated during the conference.
     *
     * @param string $conferenceRecordName Format: conferenceRecords/{conferenceRecord}
     * @param array $params Query parameters (pageSize, pageToken)
     * @return array
     * @throws GoogleMeetApiException
     */
    public function listTranscripts(string $conferenceRecordName, array $params = []): array;

    /**
     * List discrete spoken sentence entries for a transcript.
     *
     * @param string $transcriptName Format: conferenceRecords/{record}/transcripts/{transcript}
     * @param array $params Query parameters (pageSize, pageToken)
     * @return array
     * @throws GoogleMeetApiException
     */
    public function listTranscriptEntries(string $transcriptName, array $params = []): array;
}
