<?php

namespace App\Providers;

use App\Services\AuditService;
use App\Services\GoogleMeet\Clients\HttpMeetApiClient;
use App\Services\GoogleMeet\Contracts\MeetApiClientInterface;
use App\Services\GoogleMeet\GoogleMeetService;
use Illuminate\Support\ServiceProvider;

class GoogleMeetServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(MeetApiClientInterface::class, function ($app) {
            return new HttpMeetApiClient(config('services.google_meet', []));
        });

        $this->app->singleton(GoogleMeetService::class, function ($app) {
            return new GoogleMeetService(
                $app->make(MeetApiClientInterface::class),
                $app->has(AuditService::class) ? $app->make(AuditService::class) : null
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Console\Commands\SyncGoogleMeetConferenceCommand::class,
            ]);
        }
    }
}
