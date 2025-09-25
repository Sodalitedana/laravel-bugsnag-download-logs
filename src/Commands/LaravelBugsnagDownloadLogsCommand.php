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
     * Execute the console command.
     */
    public function handle(): int
    {
        info('🐛 Bugsnag Organization & Projects Finder + Logs Downloader');

        $token = config('laravel-bugsnag-download-logs.token');

        if (! $token) {
            error('BUGSNAG_API_TOKEN not configured in .env');
            info('💡 Add to .env file: BUGSNAG_API_TOKEN = your_personal_auth_token');

            return 1;
        }

        $projectId = $this->selectProject($token);
        if (! $projectId) {
            return 1;
        }

        $days = $this->option('days');
        $status = $this->option('status');

        info("📥 Downloading {$status} errors from the last {$days} days...");

        try {
            $url = "https://api.bugsnag.com/projects/{$projectId}/errors";
            $params = [
                'filters[error.status]' => $status,
                'filters[event.since]' => $days.'d',
                'per_page' => 100,
                'sort' => 'last_seen',
                'direction' => 'desc',
            ];

            // info("🌐 Chiamata API: {$url}?" . http_build_query($params));

            $response = Http::withHeaders([
                'Authorization' => 'token '.$token,
                'Content-Type' => 'application/json',
            ])->get($url, $params);

            if ($response->failed()) {
                error('Error retrieving logs from Bugsnag: '.$response->status());
                error($response->body());

                return 1;
            }

            $errors = $response->json();
            $errorCount = count($errors);

            info("⚡ Processing {$errorCount} errors...");

            foreach ($errors as $error) {
                $logData = [
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

                Log::error('Bugsnag Error: '.($error['error_class'] ?? 'Unknown'), $logData);
            }

            info("✅ Successfully saved {$errorCount} errors to laravel.log");

            if ($errorCount > 0) {
                table(
                    ['Error Class', 'Message', 'First Seen', 'File'],
                    collect($errors)->take(10)->map(function ($error) {
                        return [
                            $error['error_class'] ?? 'Unknown',
                            Str::limit($error['message'] ?? 'No message', 50),
                            isset($error['first_seen']) ? Carbon::parse($error['first_seen'])->diffForHumans() : 'Unknown',
                            $error['grouping_fields']['file'] ?? 'Unknown',
                        ];
                    })->toArray()
                );

                if ($errorCount > 10) {
                    info('... and '.($errorCount - 10).' more errors. Check laravel.log for complete details.');
                }
            }

            return 0;
        } catch (\Exception $e) {
            error('Error: '.$e->getMessage());

            return 1;
        }
    }

    public function selectProject(string $token): ?string
    {
        try {

            info('📋 Fetching organizations...');
            $orgResponse = Http::withHeaders([
                'Authorization' => 'token '.$token,
                'X-Version' => '2',
                'Content-Type' => 'application/json',
            ])->get('https://api.bugsnag.com/user/organizations');

            if ($orgResponse->failed()) {
                error('Error fetching organizations: '.$orgResponse->status());

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
            info('📁 Fetching projects...');

            $response = Http::withHeaders([
                'Authorization' => 'token '.$token,
                'Content-Type' => 'application/json',
            ])->get("https://api.bugsnag.com/organizations/{$selectedOrgId}/projects");

            if ($response->failed()) {
                error('Error fetching projects from Bugsnag: '.$response->status());

                return null;
            }

            $projects = $response->json();

            if (empty($projects)) {
                error('No projects found.');

                return null;
            }

            info('📦 Available projects:');
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

            $exampleProject = $projects[0] ?? null;
            $placeholder = $exampleProject
                ? "e.g. {$exampleProject['name']} or {$exampleProject['slug']}"
                : 'e.g. MyApp or my-app';

            $name = text(
                label: 'Enter project name (name or slug):',
                placeholder: $placeholder,
                required: true
            );

            $foundProject = null;
            foreach ($projects as $project) {
                if (Str::lower($project['name']) === Str::lower($name) ||
                    Str::lower($project['slug']) === Str::lower($name)) {
                    $foundProject = $project;
                    break;
                }
            }

            if ($foundProject) {
                info('✅ Project found:');
                info("Name: {$foundProject['name']}");
                info("Slug: {$foundProject['slug']}");
                info("ID: {$foundProject['id']}");
                info('Open errors: '.($foundProject['open_error_count'] ?? 0));

                return $foundProject['id'];
            } else {
                error("❌ Project '{$name}' not found.");
                info('💡 Use one of the names or slugs shown in the table above.');

                return null;
            }

        } catch (\Exception $e) {
            error('Error: '.$e->getMessage());

            return null;
        }
    }
}
