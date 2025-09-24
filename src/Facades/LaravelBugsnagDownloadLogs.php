<?php

namespace Sodalitedana\LaravelBugsnagDownloadLogs\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Sodalitedana\LaravelBugsnagDownloadLogs\LaravelBugsnagDownloadLogs
 */
class LaravelBugsnagDownloadLogs extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Sodalitedana\LaravelBugsnagDownloadLogs\LaravelBugsnagDownloadLogs::class;
    }
}
