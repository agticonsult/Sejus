<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SyncGoogleMeetConferenceJob;
use App\Models\ConferenceRecord;
use App\Models\MeetingRoom;
use App\Services\GoogleMeet\Exceptions\GoogleMeetApiException;
use App\Services\GoogleMeet\GoogleMeetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class GoogleMeetController extends Controller
{
    public function __construct(
        protected GoogleMeetService $meetService
    ) {}

    /**
     * POST /api/meet/rooms
     * Create restricted Google Meet room (Requirement R1).
     *
     * Returns HTTP 201 with meeting details (name, meeting_uri, meeting_code).
     */
    public function createRoom(Request $request, ?GoogleMeetService $service = null): JsonResponse
    {
        $service = $service ?? $this->meetService;

        try {
            $room = $service->createRestrictedRoom($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Restricted meeting room created successfully',
                'name' => $room->space_name,
                'meeting_uri' => $room->meeting_uri,
                'meeting_code' => $room->meeting_code,
                'data' => $room,
            ], 201);
        } catch (GoogleMeetApiException $e) {
            return $this->handleGoogleMeetApiException($e);
        } catch (Throwable $e) {
            return $this->handleGenericException($e);
        }
    }

    /**
     * POST /api/meet/conferences/sync
     * Trigger synchronization of a finalized Google Meet conference record (Requirement R2).
     *
     * Accepts payload: { "conference_id": "...", "async": true/false }
     * - If async=true: Dispatches SyncGoogleMeetConferenceJob, returns HTTP 202.
     * - If async=false: Executes syncConferenceRecord synchronously, returns HTTP 200.
     */
    public function syncConference(Request $request, ?GoogleMeetService $service = null): JsonResponse
    {
        $service = $service ?? $this->meetService;

        $validator = Validator::make($request->all(), [
            'conference_id' => 'required|string',
            'meeting_room_id' => 'nullable|integer',
            'async' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $conferenceId = (string) $request->input('conference_id');
        $meetingRoomId = $request->filled('meeting_room_id') ? (int) $request->input('meeting_room_id') : null;

        if ($request->boolean('async')) {
            SyncGoogleMeetConferenceJob::dispatch($conferenceId, $meetingRoomId);

            return response()->json([
                'success' => true,
                'message' => 'Conference sync queued',
                'conference_id' => $conferenceId,
                'data' => [
                    'conference_id' => $conferenceId,
                    'meeting_room_id' => $meetingRoomId,
                    'queued' => true,
                    'async' => true,
                ],
            ], 202);
        }

        try {
            $record = $service->syncConferenceRecord($conferenceId, $meetingRoomId);

            return response()->json([
                'success' => true,
                'message' => 'Conference record synchronized successfully',
                'conference_id' => $conferenceId,
                'data' => $record,
            ], 200);
        } catch (GoogleMeetApiException $e) {
            return $this->handleGoogleMeetApiException($e);
        } catch (Throwable $e) {
            return $this->handleGenericException($e);
        }
    }

    /**
     * GET /api/meet/conferences/{id}
     * Retrieve conference record with eager-loaded relations (participants, recordings, transcripts).
     *
     * Returns HTTP 200 or 404 if not found.
     */
    public function getConference(string $id): JsonResponse
    {
        $cleanId = trim($id);
        $record = null;

        // 1. Try finding by database primary key if numeric
        if (is_numeric($cleanId)) {
            $record = ConferenceRecord::with([
                'meetingRoom',
                'participants',
                'recordings',
                'transcripts.entries',
            ])->find((int) $cleanId);
        }

        // 2. Try finding by Google conference_record_name
        if (!$record) {
            $normalized = ltrim($cleanId, '/');
            if (!str_starts_with($normalized, 'conferenceRecords/')) {
                $normalized = "conferenceRecords/{$normalized}";
            }

            $record = ConferenceRecord::with([
                'meetingRoom',
                'participants',
                'recordings',
                'transcripts.entries',
            ])->where('conference_record_name', $normalized)
              ->orWhere('conference_record_name', $cleanId)
              ->first();
        }

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'Conference record not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Conference record retrieved successfully',
            'data' => $record,
            // Top-level convenience accessors for direct JSON testing
            'participants' => $record->participants,
            'recordings' => $record->recordings,
            'transcripts' => $record->transcripts,
        ], 200);
    }

    /**
     * GET /api/meet/rooms/{id}
     * Retrieve meeting room by database ID, space name, or meeting code.
     *
     * Returns HTTP 200 or 404 if not found.
     */
    public function getRoom(string $id): JsonResponse
    {
        $cleanId = trim($id);
        $room = null;

        // 1. Try finding by database primary key if numeric
        if (is_numeric($cleanId)) {
            $room = MeetingRoom::with(['conferenceRecords', 'creator', 'prontuario'])->find((int) $cleanId);
        }

        // 2. Try finding by space_name or meeting_code
        if (!$room) {
            $spaceName = $cleanId;
            if (!str_starts_with($spaceName, 'spaces/')) {
                $spaceName = "spaces/{$spaceName}";
            }

            $room = MeetingRoom::with(['conferenceRecords', 'creator', 'prontuario'])
                ->where('space_name', $spaceName)
                ->orWhere('space_name', $cleanId)
                ->orWhere('meeting_code', $cleanId)
                ->first();
        }

        if (!$room) {
            return response()->json([
                'success' => false,
                'message' => 'Meeting room not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Meeting room retrieved successfully',
            'data' => $room,
            'meeting_uri' => $room->meeting_uri,
            'meeting_code' => $room->meeting_code,
            'name' => $room->space_name,
        ], 200);
    }

    /**
     * Map GoogleMeetApiException to appropriate client HTTP status codes.
     */
    protected function handleGoogleMeetApiException(GoogleMeetApiException $e): JsonResponse
    {
        $upstreamStatus = $e->getStatusCode();

        $httpStatus = match (true) {
            $upstreamStatus === 404 => 404,
            $upstreamStatus === 400 => 400,
            $upstreamStatus === 422 => 422,
            $upstreamStatus === 429 => 429,
            $upstreamStatus === 401 || $upstreamStatus === 403 => 502,
            $upstreamStatus >= 500 => 502,
            default => 502,
        };

        Log::error("Google Meet API error [{$upstreamStatus}]: {$e->getMessage()}", [
            'status_code' => $upstreamStatus,
            'response_body' => $e->getResponseBody(),
        ]);

        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
            'error' => [
                'type' => 'GoogleMeetApiException',
                'upstream_status' => $upstreamStatus,
                'message' => $e->getMessage(),
                'details' => $e->getResponseBody(),
            ],
        ], $httpStatus);
    }

    /**
     * Handle unexpected exceptions gracefully.
     */
    protected function handleGenericException(Throwable $e): JsonResponse
    {
        Log::error('Unexpected error in GoogleMeetController: ' . $e->getMessage(), [
            'exception' => $e,
        ]);

        return response()->json([
            'success' => false,
            'message' => 'An unexpected internal error occurred.',
            'error' => [
                'type' => class_basename($e),
                'message' => config('app.debug') ? $e->getMessage() : 'Internal Server Error',
            ],
        ], 500);
    }
}
