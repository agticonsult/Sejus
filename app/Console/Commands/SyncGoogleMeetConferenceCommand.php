<?php

namespace App\Console\Commands;

use App\Jobs\SyncGoogleMeetConferenceJob;
use App\Services\GoogleMeet\Exceptions\GoogleMeetApiException;
use App\Services\GoogleMeet\GoogleMeetService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncGoogleMeetConferenceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'meet:sync-conference 
                            {conferenceId : The Google Meet conference record ID or resource name (e.g., conf-123, conferenceRecords/conf-123, or spaces/AAA/conferences/BBB)}
                            {--queue : Dispatch the conference synchronization job to the background queue}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize Google Meet conference records, participants, recordings, and transcripts.';

    /**
     * Execute the console command.
     */
    public function handle(GoogleMeetService $service): int
    {
        $rawInput = (string) $this->argument('conferenceId');
        $normalizedId = $this->normalizeConferenceId($rawInput);

        if ($this->option('queue')) {
            SyncGoogleMeetConferenceJob::dispatch($normalizedId);

            $this->info("[QUEUED] Conference sync queued for processing: {$normalizedId}");

            return Command::SUCCESS;
        }

        $this->info("Synchronizing Google Meet conference record [{$normalizedId}]...");

        try {
            $record = $service->syncConferenceRecord($normalizedId);

            $this->info("Conference record synchronized successfully: {$record->conference_record_name}");

            $this->table(
                ['Property', 'Value'],
                [
                    ['Conference Record', $record->conference_record_name],
                    ['Space Name', $record->space_name ?: 'N/A'],
                    ['Status', $record->status],
                    ['Start Time', $record->start_time?->toIso8601String() ?? 'N/A'],
                    ['End Time', $record->end_time?->toIso8601String() ?? 'N/A'],
                    ['Duration', $record->duration_human ?? 'N/A'],
                    ['Participants Count', (string) $record->participants->count()],
                    ['Recordings Count', (string) $record->recordings->count()],
                    ['Transcripts Count', (string) $record->transcripts->count()],
                    ['Synced At', $record->synced_at?->toIso8601String() ?? 'N/A'],
                ]
            );

            return Command::SUCCESS;
        } catch (GoogleMeetApiException $e) {
            $this->error("Failed to sync conference: [Google Meet API {$e->getStatusCode()}] {$e->getMessage()}");

            if (!empty($e->getResponseBody())) {
                $this->line('<fg=gray>' . json_encode($e->getResponseBody(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . '</>');
            }

            Log::error("meet:sync-conference failed with GoogleMeetApiException: {$e->getMessage()}", [
                'conference_id' => $rawInput,
                'normalized_id' => $normalizedId,
                'status_code' => $e->getStatusCode(),
                'response_body' => $e->getResponseBody(),
            ]);

            return Command::FAILURE;
        } catch (Throwable $e) {
            $this->error("Failed to sync conference: {$e->getMessage()}");

            Log::error("meet:sync-conference failed unexpectedly: {$e->getMessage()}", [
                'conference_id' => $rawInput,
                'normalized_id' => $normalizedId,
                'exception' => $e,
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Normalize conference identifier into standard canonical resource name: conferenceRecords/{id}.
     */
    protected function normalizeConferenceId(string $id): string
    {
        $trimmed = trim($id);

        // Handle space nested conference format: spaces/{space}/conferences/{conferenceId}
        if (preg_match('~conferences/([^/?#]+)~', $trimmed, $matches)) {
            return "conferenceRecords/{$matches[1]}";
        }

        // Strip leading slashes
        $trimmed = ltrim($trimmed, '/');

        // Check if already prefixed
        if (str_starts_with($trimmed, 'conferenceRecords/')) {
            return $trimmed;
        }

        return "conferenceRecords/{$trimmed}";
    }
}
