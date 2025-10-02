<?php

namespace Sodalitedana\LaravelBugsnagDownloadLogs\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

class LaravelBugsnagDownloadLogsCommand extends Command
{
    /**
     * Bugsnag API base URL
     */
    private const BUGSNAG_API_BASE_URL = 'https://api.bugsnag.com';

    /**
     * Number of errors to fetch per page
     */
    private const DEFAULT_PER_PAGE = 100;

    /**
     * Maximum number of errors to display in preview table
     */
    private const MAX_ERRORS_PREVIEW = 10;

    /**
     * Maximum characters for error message in table
     */
    private const MESSAGE_LIMIT = 50;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bugsnag:download-logs {--days=7 : Number of days to fetch logs for} {--status=open : Error status to filter by}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Select Bugsnag organization and project, then download error logs';

    /**
     * Bugsnag API token
     *
     * @var string
     */
    protected string $token;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        info('🐛 Bugsnag Organization & Projects Finder + Logs Downloader');

        $this->token = config('laravel-bugsnag-download-logs.token');

        if (! $this->token) {
            error('BUGSNAG_API_TOKEN not configured in .env');
            info('💡 Add to .env file: BUGSNAG_API_TOKEN = your_personal_auth_token');

            return 1;
        }

        $projectId = $this->selectProject();
        if (! $projectId) {
            return 1;
        }

        return $this->downloadErrors($projectId);
    }

    /**
     * Download errors from Bugsnag for a specific project
     *
     * @param  string  $projectId
     * @return int
     */
    private function downloadErrors(string $projectId): int
    {
        $days = $this->option('days');
        $status = $this->option('status');

        info("📥 Downloading {$status} errors from the last {$days} days...");

        try {
            $url = $this->buildApiUrl("/projects/{$projectId}/errors");
            $params = [
                'filters[error.status]' => $status,
                'filters[event.since]' => $days.'d',
                'per_page' => self::DEFAULT_PER_PAGE,
                'sort' => 'last_seen',
                'direction' => 'desc',
            ];

            $response = $this->makeApiRequest($url, $params);

            if (! $response) {
                error('Error retrieving logs from Bugsnag');

                return 1;
            }

            $errors = $response->json();
            $errorCount = count($errors);

            info("⚡ Processing {$errorCount} errors...");

            foreach ($errors as $error) {
                $logData = $this->formatErrorForLog($error);
                Log::error('Bugsnag Error: '.($error['error_class'] ?? 'Unknown'), $logData);
            }

            info("✅ Successfully saved {$errorCount} errors to laravel.log");

            $this->displayErrorsTable($errors);

            return 0;
        } catch (\Exception $e) {
            error('Error: '.$e->getMessage());

            return 1;
        }
    }

    /**
     * Format error data for logging
     *
     * @param  array  $error
     * @return array
     */
    private function formatErrorForLog(array $error): array
    {
        return [
            'error_class' => $error['error_class'] ?? 'Unknown',
            'message' => $error['message'] ?? 'No message',
            'context' => $error['context'] ?? null,
            'first_seen' => $error['first_seen'] ?? null,
            'grouping_fields' => [
                'errorClass' => $error['grouping_fields']['errorClass'] ?? null,
                'file' => $error['grouping_fields']['file'] ?? null,
                'code' => $error['grouping_fields']['code'] ?? null,
            ],
        ];
    }

    /**
     * Display errors table in console
     *
     * @param  array  $errors
     * @return void
     */
    private function displayErrorsTable(array $errors): void
    {
        $errorCount = count($errors);

        if ($errorCount === 0) {
            return;
        }

        table(
            ['Error Class', 'Message', 'First Seen', 'File'],
            collect($errors)->take(self::MAX_ERRORS_PREVIEW)->map(function ($error) {
                return [
                    $this->getErrorValue($error, 'error_class'),
                    Str::limit($this->getErrorValue($error, 'message', 'No message'), self::MESSAGE_LIMIT),
                    isset($error['first_seen']) ? Carbon::parse($error['first_seen'])->diffForHumans() : 'Unknown',
                    $error['grouping_fields']['file'] ?? 'Unknown',
                ];
            })->toArray()
        );

        if ($errorCount > self::MAX_ERRORS_PREVIEW) {
            info('... and '.($errorCount - self::MAX_ERRORS_PREVIEW).' more errors. Check laravel.log for complete details.');
        }
    }

    public function selectProject(): ?string
    {
        try {
            $organizationId = $this->selectOrganization();

            if (! $organizationId) {
                return null;
            }

            return $this->selectProjectFromOrganization($organizationId);

        } catch (\Exception $e) {
            error('Error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Select an organization from available organizations
     *
     * @return string|null
     */
    private function selectOrganization(): ?string
    {
        info('📋 Fetching organizations...');
        $orgResponse = $this->makeApiRequest($this->buildApiUrl('/user/organizations'), [], ['X-Version' => '2']);

        if (! $orgResponse) {
            error('Error fetching organizations');

            return null;
        }

        $organizations = $orgResponse->json();

        if (empty($organizations)) {
            error('No organizations found.');

            return null;
        }

        info('🏢 Available organizations:');
        $orgTableData = [];
        $orgOptions = [];
        foreach ($organizations as $org) {
            $orgTableData[] = [
                'Name' => $org['name'],
                'Slug' => $org['slug'],
                'ID' => $org['id'],
            ];
            $orgOptions[$org['id']] = $org['name'].' ('.$org['slug'].')';
        }

        table(['Name', 'Slug', 'ID'], $orgTableData);

        $selectedOrgId = select(
            label: 'Select an organization:',
            options: $orgOptions,
            required: true
        );

        $selectedOrg = collect($organizations)->firstWhere('id', $selectedOrgId);
        info("✅ Selected organization: {$selectedOrg['name']}");

        return $selectedOrgId;
    }

    /**
     * Select a project from a specific organization
     *
     * @param  string  $organizationId
     * @return string|null
     */
    private function selectProjectFromOrganization(string $organizationId): ?string
    {
        info('📁 Fetching projects...');

        $response = $this->makeApiRequest($this->buildApiUrl("/organizations/{$organizationId}/projects"));

        if (! $response) {
            error('Error fetching projects from Bugsnag');

            return null;
        }

        $projects = $response->json();

        if (empty($projects)) {
            error('No projects found.');

            return null;
        }

        info('📦 Available projects:');
        $this->displayProjectsTable($projects);

        $exampleProject = $projects[0] ?? null;
        $placeholder = $exampleProject
            ? "e.g. {$exampleProject['name']} or {$exampleProject['slug']}"
            : 'e.g. MyApp or my-app';

        $name = text(
            label: 'Enter project name (name or slug):',
            placeholder: $placeholder,
            required: true
        );

        $foundProject = $this->findProjectByName($projects, $name);

        if (! $foundProject) {
            error("❌ Project '{$name}' not found.");
            info('💡 Use one of the names or slugs shown in the table above.');

            return null;
        }

        info('✅ Project found:');
        info("Name: {$foundProject['name']}");
        info("Slug: {$foundProject['slug']}");
        info("ID: {$foundProject['id']}");
        info('Open errors: '.($foundProject['open_error_count'] ?? 0));

        return $foundProject['id'];
    }

    /**
     * Display projects table
     *
     * @param  array  $projects
     * @return void
     */
    private function displayProjectsTable(array $projects): void
    {
        $tableData = [];
        foreach ($projects as $project) {
            $tableData[] = [
                'Name' => $project['name'],
                'Slug' => $project['slug'],
                'ID' => $project['id'],
                'Errors' => $project['open_error_count'] ?? 0,
            ];
        }

        table(
            ['Name', 'Slug', 'ID', 'Open Errors'],
            $tableData
        );
    }

    /**
     * Find a project by name or slug
     *
     * @param  array  $projects
     * @param  string  $name
     * @return array|null
     */
    private function findProjectByName(array $projects, string $name): ?array
    {
        foreach ($projects as $project) {
            if (Str::lower($project['name']) === Str::lower($name) ||
                Str::lower($project['slug']) === Str::lower($name)) {
                return $project;
            }
        }

        return null;
    }

    /**
     * Build API URL with base URL
     *
     * @param  string  $endpoint
     * @return string
     */
    private function buildApiUrl(string $endpoint): string
    {
        return self::BUGSNAG_API_BASE_URL.$endpoint;
    }

    /**
     * Get error value with default fallback
     *
     * @param  array  $error
     * @param  string  $key
     * @param  string  $default
     * @return string
     */
    private function getErrorValue(array $error, string $key, string $default = 'Unknown'): string
    {
        return $error[$key] ?? $default;
    }

    /**
     * Get the default headers for Bugsnag API requests
     *
     * @param  array  $additionalHeaders
     * @return array
     */
    private function getHeaders(array $additionalHeaders = []): array
    {
        return array_merge([
            'Authorization' => 'token '.$this->token,
            'Content-Type' => 'application/json',
        ], $additionalHeaders);
    }

    /**
     * Make an API request to Bugsnag
     *
     * @param  string  $url
     * @param  array  $params
     * @param  array  $additionalHeaders
     * @return \Illuminate\Http\Client\Response|null
     */
    private function makeApiRequest(string $url, array $params = [], array $additionalHeaders = []): ?\Illuminate\Http\Client\Response
    {
        $response = Http::withHeaders($this->getHeaders($additionalHeaders))->get($url, $params);

        if ($response->failed()) {
            error('API request failed: '.$response->status());
            if ($response->body()) {
                error($response->body());
            }

            return null;
        }

        return $response;
    }
}

