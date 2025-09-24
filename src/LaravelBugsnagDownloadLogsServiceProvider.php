<?php

namespace Sodalitedana\LaravelBugsnagDownloadLogs;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Sodalitedana\LaravelBugsnagDownloadLogs\Commands\LaravelBugsnagDownloadLogsCommand;

class LaravelBugsnagDownloadLogsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-bugsnag-download-logs')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_bugsnag_download_logs_table')
            ->hasCommand(LaravelBugsnagDownloadLogsCommand::class);
    }
}
