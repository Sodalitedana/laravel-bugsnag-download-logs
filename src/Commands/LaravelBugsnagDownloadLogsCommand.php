<?php

namespace Sodalitedana\LaravelBugsnagDownloadLogs\Commands;

use Illuminate\Console\Command;

class LaravelBugsnagDownloadLogsCommand extends Command
{
    public $signature = 'laravel-bugsnag-download-logs';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
