<?php

namespace Tests\Unit;

use Tests\TestCase;
use Tests\Support\GoogleMeetFake;
use App\Services\GoogleMeet\GoogleMeetService;
use App\Services\GoogleMeet\Contracts\MeetApiClientInterface;
use App\Services\GoogleMeet\Clients\HttpMeetApiClient;
use App\Services\GoogleMeet\Exceptions\GoogleMeetApiException;
use App\Models\MeetingRoom;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Request;

class GoogleMeetRoomCreationTest extends TestCase
{
    protected GoogleMeetService $meetService;

    protected function setUp(): void
    {
        parent::setUp();

        // Bind contract to Http client if not already bound in service provider
        if (!$this->app->bound(MeetApiClientInterface::class)) {
            $this->app->bind(MeetApiClientInterface::class, HttpMeetApiClient::class);
        }

        $this->meetService = $this->app->make(GoogleMeetService::class);
    }

    /**
     * Test 1: Verifies createRestrictedRoom() sends the exact required JSON structure:
     * - config.accessType = 'OPEN'
     * - config.entryPointAccess = 'CREATOR_APP_ONLY'
     * - config.moderation = 'ON'
     * - config.moderationRestrictions.chatRestriction = 'HOSTS_ONLY'
     * - config.moderationRestrictions.presentRestriction = 'HOSTS_ONLY'
     * - config.moderationRestrictions.defaultJoinAsViewerType = 'ON'
     */
    public function test_create_restricted_room_sends_exact_required_json_structure(): void
    {
        GoogleMeetFake::fakeSpaceCreation();

        $this->meetService->createRestrictedRoom();

        // 1. Assertion via helper method
        GoogleMeetFake::assertSpaceCreatedWithRestrictions();

        // 2. Direct granular wire-level assertions on Http request payload
        Http::assertSent(function (Request $request) {
            $isSpacesPost = $request->method() === 'POST'
                && str_contains($request->url(), '/spaces');

            if (!$isSpacesPost) {
                return false;
            }

            $body = $request->data();
            $config = $body['config'] ?? [];
            $restrictions = $config['moderationRestrictions'] ?? [];

            return ($config['accessType'] ?? null) === 'OPEN'
                && ($config['entryPointAccess'] ?? null) === 'CREATOR_APP_ONLY'
                && ($config['moderation'] ?? null) === 'ON'
                && ($restrictions['chatRestriction'] ?? null) === 'HOSTS_ONLY'
                && ($restrictions['presentRestriction'] ?? null) === 'HOSTS_ONLY'
                && ($restrictions['defaultJoinAsViewerType'] ?? null) === 'ON';
        });
    }

    /**
     * Test 2: Verifies that returned MeetingRoom model contains valid meeting_uri
     * (https://meet.google.com/...), meeting_code, and space_name (spaces/...).
     */
    public function test_create_restricted_room_returns_valid_meeting_room_model_attributes(): void
    {
        $expectedSpace = 'spaces/sE4kP9qLmNt8';
        $expectedUri = 'https://meet.google.com/zxc-vbnm-qwe';
        $expectedCode = 'zxc-vbnm-qwe';

        GoogleMeetFake::fakeSpaceCreation([
            'name' => $expectedSpace,
            'meetingUri' => $expectedUri,
            'meetingCode' => $expectedCode,
        ]);

        $room = $this->meetService->createRestrictedRoom();

        $this->assertInstanceOf(MeetingRoom::class, $room);
        $this->assertSame($expectedSpace, $room->space_name);
        $this->assertSame($expectedUri, $room->meeting_uri);
        $this->assertSame($expectedCode, $room->meeting_code);

        // Verification of URI and code formats
        $this->assertStringStartsWith('https://meet.google.com/', $room->meeting_uri);
        $this->assertStringStartsWith('spaces/', $room->space_name);
        $this->assertMatchesRegularExpression('/^[a-z0-9]+-[a-z0-9]+-[a-z0-9]+$/', $room->meeting_code);

        // Verification of stored configuration states
        $this->assertSame('OPEN', $room->access_type);
        $this->assertSame('CREATOR_APP_ONLY', $room->entry_point_access);
        $this->assertSame('ON', $room->moderation);
        $this->assertSame('HOSTS_ONLY', $room->chat_restriction);
        $this->assertSame('HOSTS_ONLY', $room->present_restriction);
        $this->assertSame('ON', $room->default_join_as_viewer_type);
        $this->assertSame('active', $room->status);
    }

    /**
     * Test 3: Verifies proper persistence into SQLite database table meeting_rooms.
     */
    public function test_create_restricted_room_persists_properly_in_sqlite_database(): void
    {
        $spaceName = 'spaces/db-test-room-777';
        $meetingUri = 'https://meet.google.com/abc-defg-hij';
        $meetingCode = 'abc-defg-hij';

        GoogleMeetFake::fakeSpaceCreation([
            'name' => $spaceName,
            'meetingUri' => $meetingUri,
            'meetingCode' => $meetingCode,
        ]);

        $this->assertDatabaseCount('meeting_rooms', 0);

        $room = $this->meetService->createRestrictedRoom([
            'title' => 'Atendimento Psicossocial SEJUS',
        ]);

        // Assert 1 record created
        $this->assertDatabaseCount('meeting_rooms', 1);

        // Assert exact database values in SQLite
        $this->assertDatabaseHas('meeting_rooms', [
            'id' => $room->id,
            'space_name' => $spaceName,
            'meeting_uri' => $meetingUri,
            'meeting_code' => $meetingCode,
            'access_type' => 'OPEN',
            'entry_point_access' => 'CREATOR_APP_ONLY',
            'moderation' => 'ON',
            'chat_restriction' => 'HOSTS_ONLY',
            'present_restriction' => 'HOSTS_ONLY',
            'default_join_as_viewer_type' => 'ON',
            'status' => 'active',
        ]);

        // Verify fresh reload from SQLite database
        $reloaded = MeetingRoom::findOrFail($room->id);
        $this->assertSame($spaceName, $reloaded->space_name);
        $this->assertIsArray($reloaded->raw_config);
        $this->assertSame('OPEN', $reloaded->raw_config['accessType'] ?? $reloaded->raw_config['config']['accessType'] ?? null);
    }

    /**
     * Test 4: Verifies error handling when Google API returns 400 Bad Request
     * (proper exception thrown, no corrupted DB records).
     */
    public function test_create_restricted_room_handles_400_bad_request_and_prevents_db_corruption(): void
    {
        GoogleMeetFake::fakeSpaceCreationFailure(400, GoogleMeetFake::sampleBadRequestResponse());

        $this->assertDatabaseCount('meeting_rooms', 0);

        try {
            $this->meetService->createRestrictedRoom();
            $this->fail('Expected GoogleMeetApiException was not thrown for HTTP 400');
        } catch (GoogleMeetApiException $e) {
            $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : $e->getCode();
            $this->assertSame(400, $statusCode);
            $this->assertStringContainsString('config.accessType', $e->getMessage());
        }

        // Database must remain completely clean with 0 records
        $this->assertDatabaseCount('meeting_rooms', 0);
    }

    /**
     * Test 4 (continued): Verifies error handling when Google API returns 500 Internal Error
     * (proper exception thrown, no corrupted DB records).
     */
    public function test_create_restricted_room_handles_500_internal_error_and_prevents_db_corruption(): void
    {
        GoogleMeetFake::fakeSpaceCreationFailure(500, GoogleMeetFake::sampleInternalErrorResponse());

        $this->assertDatabaseCount('meeting_rooms', 0);

        try {
            $this->meetService->createRestrictedRoom();
            $this->fail('Expected GoogleMeetApiException was not thrown for HTTP 500');
        } catch (GoogleMeetApiException $e) {
            $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : $e->getCode();
            $this->assertSame(500, $statusCode);
            $this->assertStringContainsString('Internal error', $e->getMessage());
        }

        // Database must remain completely clean with 0 records
        $this->assertDatabaseCount('meeting_rooms', 0);
    }
}
