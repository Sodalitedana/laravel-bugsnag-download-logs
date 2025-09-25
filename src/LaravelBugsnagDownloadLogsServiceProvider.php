<?php

namespace Sodalitedana\LaravelBugsnagDownloadLogs;

use Sodalitedana\LaravelBugsnagDownloadLogs\Commands\LaravelBugsnagDownloadLogsCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelBugsnagDownloadLogsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-bugsnag-download-logs')
            ->hasConfigFile()
            ->hasCommand(LaravelBugsnagDownloadLogsCommand::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/laravel-bugsnag-download-logs.php' => config_path('laravel-bugsnag-download-logs.php'),
        ]);
    }
}
