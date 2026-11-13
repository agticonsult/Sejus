<?php

namespace App\Jobs;

use App\Services\GoogleMeet\GoogleMeetService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncGoogleMeetConferenceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job (exponential backoff).
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     *
     * @param string $conferenceRecordName Raw or canonical conference record name (e.g. 'conf-123' or 'conferenceRecords/conf-123')
     * @param int|null $meetingRoomId Optional local meeting room ID override
     */
    public function __construct(
        public string $conferenceRecordName,
        public ?int $meetingRoomId = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(GoogleMeetService $service): void
    {
        Log::info("Starting queued conference sync for [{$this->conferenceRecordName}].", [
            'attempt' => $this->attempts(),
            'meeting_room_id' => $this->meetingRoomId,
        ]);

        $record = $service->syncConferenceRecord($this->conferenceRecordName, $this->meetingRoomId);

        Log::info("Successfully synchronized Google Meet conference record [{$record->conference_record_name}] via queued job.", [
            'conference_record_id' => $record->id,
            'meeting_room_id' => $record->meeting_room_id,
            'space_name' => $record->space_name,
            'status' => $record->status,
            'participants_count' => $record->participants->count(),
            'recordings_count' => $record->recordings->count(),
            'transcripts_count' => $record->transcripts->count(),
        ]);
    }

    /**
     * Handle a job failure after all retries have been exhausted.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error("Queued job SyncGoogleMeetConferenceJob failed permanently for [{$this->conferenceRecordName}]: " . ($exception?->getMessage() ?? 'Unknown error'), [
            'conference_record_name' => $this->conferenceRecordName,
            'meeting_room_id' => $this->meetingRoomId,
            'exception_class' => $exception ? get_class($exception) : null,
            'error_code' => $exception?->getCode(),
            'trace' => $exception?->getTraceAsString(),
        ]);
    }
}
