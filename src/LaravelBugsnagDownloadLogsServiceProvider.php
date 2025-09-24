<?php

namespace Sodalitedana\LaravelBugsnagDownloadLogs;

use Sodalitedana\LaravelBugsnagDownloadLogs\Commands\LaravelBugsnagDownloadLogsCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

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
            ->hasConfigFile('laravel-bugsnag-download-logs')
            ->hasCommand(LaravelBugsnagDownloadLogsCommand::class);
    }
}
