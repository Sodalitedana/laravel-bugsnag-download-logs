<?php

namespace Sodalitedana\LaravelBugsnagDownloadLogs;

use Illuminate\Support\ServiceProvider;
use Sodalitedana\LaravelBugsnagDownloadLogs\Commands\LaravelBugsnagDownloadLogsCommand;

class LaravelBugsnagDownloadLogsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/laravel-bugsnag-download-logs.php' => config_path('laravel-bugsnag-download-logs.php'),
        ], 'laravel-bugsnag-download-logs-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                LaravelBugsnagDownloadLogsCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/laravel-bugsnag-download-logs.php',
            'laravel-bugsnag-download-logs'
        );
    }
}
