<?php

namespace App\Services\GoogleMeet\Clients;

use App\Services\GoogleMeet\Contracts\MeetApiClientInterface;
use App\Services\GoogleMeet\Exceptions\GoogleMeetApiException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class HttpMeetApiClient implements MeetApiClientInterface
{
    protected string $baseUrl;
    protected ?string $accessToken;
    protected ?string $clientId;
    protected ?string $clientSecret;
    protected ?string $refreshToken;
    protected string $tokenUrl;
    protected int $timeout;

    public function __construct(?array $config = null)
    {
        $config = $config ?? config('services.google_meet', []);
        $this->baseUrl = rtrim($config['base_url'] ?? 'https://meet.googleapis.com/v2', '/');
        $this->accessToken = $config['access_token'] ?? null;
        $this->clientId = $config['client_id'] ?? null;
        $this->clientSecret = $config['client_secret'] ?? null;
        $this->refreshToken = $config['refresh_token'] ?? null;
        $this->tokenUrl = $config['token_url'] ?? 'https://oauth2.googleapis.com/token';
        $this->timeout = (int) ($config['timeout'] ?? 15);
    }

    /**
     * Resolve OAuth2 Bearer token (static token, cached refresh token flow, or test mock).
     */
    public function getAccessToken(): string
    {
        if (!empty($this->accessToken)) {
            return $this->accessToken;
        }

        if (!empty($this->refreshToken) && !empty($this->clientId) && !empty($this->clientSecret)) {
            return Cache::remember('google_meet_oauth_token', 3500, function () {
                $response = Http::asForm()->timeout($this->timeout)->post($this->tokenUrl, [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'refresh_token' => $this->refreshToken,
                    'grant_type' => 'refresh_token',
                ]);

                if ($response->failed()) {
                    throw new GoogleMeetApiException(
                        'Failed to refresh Google Meet OAuth2 token: ' . ($response->json('error_description') ?? $response->body()),
                        $response->status(),
                        null,
                        $response->json() ?? []
                    );
                }

                return (string) $response->json('access_token');
            });
        }

        return 'mock-google-meet-token';
    }

    /**
     * Create base pending HTTP request with Bearer authorization and JSON headers.
     */
    protected function newRequest(): PendingRequest
    {
        return Http::timeout($this->timeout)
            ->withToken($this->getAccessToken())
            ->acceptJson()
            ->asJson();
    }

    /**
     * Process HTTP response and handle error payloads.
     */
    protected function handleResponse(Response $response, string $operation): array
    {
        if ($response->successful()) {
            return $response->json() ?? [];
        }

        $status = $response->status();
        $errorMessage = $response->json('error.message')
            ?? $response->json('message')
            ?? $response->body()
            ?? 'Unknown Google Meet API error';

        $errorStatus = $response->json('error.status') ?? 'HTTP_' . $status;

        Log::error("Google Meet API Error during {$operation}: [{$status} - {$errorStatus}] {$errorMessage}", [
            'status' => $status,
            'body' => $response->json() ?? $response->body(),
        ]);

        throw new GoogleMeetApiException(
            "Google Meet API error during {$operation} [{$status}]: {$errorMessage}",
            $status,
            null,
            $response->json() ?? []
        );
    }

    /**
     * {@inheritdoc}
     */
    public function createSpace(array $spaceData = []): array
    {
        $url = "{$this->baseUrl}/spaces";

        try {
            $response = $this->newRequest()->post($url, $spaceData);
            return $this->handleResponse($response, 'createSpace');
        } catch (GoogleMeetApiException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Google Meet API connection failure during createSpace: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            throw new GoogleMeetApiException(
                'Google Meet API connection failed during createSpace: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getSpace(string $spaceName): array
    {
        $normalizedName = ltrim($spaceName, '/');
        if (!str_starts_with($normalizedName, 'spaces/')) {
            $normalizedName = "spaces/{$normalizedName}";
        }

        $url = "{$this->baseUrl}/{$normalizedName}";

        try {
            $response = $this->newRequest()->get($url);
            return $this->handleResponse($response, 'getSpace');
        } catch (GoogleMeetApiException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new GoogleMeetApiException(
                'Google Meet API connection failed during getSpace: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getConferenceRecord(string $name): array
    {
        $normalizedName = ltrim($name, '/');
        if (!str_starts_with($normalizedName, 'conferenceRecords/')) {
            $normalizedName = "conferenceRecords/{$normalizedName}";
        }

        $url = "{$this->baseUrl}/{$normalizedName}";

        try {
            $response = $this->newRequest()->get($url);
            return $this->handleResponse($response, 'getConferenceRecord');
        } catch (GoogleMeetApiException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new GoogleMeetApiException(
                'Google Meet API connection failed during getConferenceRecord: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function listParticipants(string $conferenceRecordName, array $params = []): array
    {
        $normalizedName = ltrim($conferenceRecordName, '/');
        if (!str_starts_with($normalizedName, 'conferenceRecords/')) {
            $normalizedName = "conferenceRecords/{$normalizedName}";
        }

        $url = "{$this->baseUrl}/{$normalizedName}/participants";

        try {
            $response = $this->newRequest()->get($url, $params);
            return $this->handleResponse($response, 'listParticipants');
        } catch (GoogleMeetApiException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new GoogleMeetApiException(
                'Google Meet API connection failed during listParticipants: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function listRecordings(string $conferenceRecordName, array $params = []): array
    {
        $normalizedName = ltrim($conferenceRecordName, '/');
        if (!str_starts_with($normalizedName, 'conferenceRecords/')) {
            $normalizedName = "conferenceRecords/{$normalizedName}";
        }

        $url = "{$this->baseUrl}/{$normalizedName}/recordings";

        try {
            $response = $this->newRequest()->get($url, $params);
            return $this->handleResponse($response, 'listRecordings');
        } catch (GoogleMeetApiException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new GoogleMeetApiException(
                'Google Meet API connection failed during listRecordings: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function listTranscripts(string $conferenceRecordName, array $params = []): array
    {
        $normalizedName = ltrim($conferenceRecordName, '/');
        if (!str_starts_with($normalizedName, 'conferenceRecords/')) {
            $normalizedName = "conferenceRecords/{$normalizedName}";
        }

        $url = "{$this->baseUrl}/{$normalizedName}/transcripts";

        try {
            $response = $this->newRequest()->get($url, $params);
            return $this->handleResponse($response, 'listTranscripts');
        } catch (GoogleMeetApiException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new GoogleMeetApiException(
                'Google Meet API connection failed during listTranscripts: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function listTranscriptEntries(string $transcriptName, array $params = []): array
    {
        $normalizedName = ltrim($transcriptName, '/');
        $url = "{$this->baseUrl}/{$normalizedName}/entries";

        try {
            $response = $this->newRequest()->get($url, $params);
            return $this->handleResponse($response, 'listTranscriptEntries');
        } catch (GoogleMeetApiException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new GoogleMeetApiException(
                'Google Meet API connection failed during listTranscriptEntries: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }
}
