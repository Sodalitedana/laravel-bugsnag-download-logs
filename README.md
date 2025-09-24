# Laravel Bugsnag Download Logs

[![Latest Version on Packagist](https://img.shields.io/packagist/v/sodalitedana/laravel-bugsnag-download-logs.svg?style=flat-square)](https://packagist.org/packages/sodalitedana/laravel-bugsnag-download-logs)
[![Total Downloads](https://img.shields.io/packagist/dt/sodalitedana/laravel-bugsnag-download-logs.svg?style=flat-square)](https://packagist.org/packages/sodalitedana/laravel-bugsnag-download-logs)

A Laravel package that provides an Artisan command to download error logs from Bugsnag and save them to your application's `laravel.log` file.

## Features

- 🔍 **Select organization and project** via interactive interface
- 📥 **Download errors from Bugsnag** with customizable filters
- 📝 **Save to laravel.log** with structured format
- 🎯 **Customizable error status filter** via `--status` option (open, resolved, etc.)
- 📅 **Configurable time period** via `--days` option (default: last 7 days)
- 📊 **Tabular preview** of downloaded errors

## Requirements

- PHP ^8.3
- Laravel ^11.0|^12.0
- Bugsnag API Token (Personal Auth Token)

## Installation

Install the package via Composer:

```bash
composer require sodalitedana/laravel-bugsnag-download-logs
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=laravel-bugsnag-download-logs-config
```

## Configuration

### 1. Bugsnag API Token

Add your Bugsnag Personal Auth Token to your `.env` file:

```env
BUGSNAG_API_TOKEN=your_personal_auth_token_here
```

**How to get the token:**
1. Go to [Bugsnag Settings → My Account](https://app.bugsnag.com/settings/personal-auth-tokens)
2. Click "Generate new token"
3. Copy the generated token

### 2. Configuration File

The configuration file is published to `config/laravel-bugsnag-download-logs.php`:

```php
<?php

return [
    'token' => env('BUGSNAG_API_TOKEN'),
];
```

## Usage

### Basic Command

Run the command to download errors:

```bash
php artisan bugsnag:download-logs
```

The command will guide you through:
1. **Organization selection** from your available Bugsnag organizations
2. **Project selection** by entering the project name or slug
3. **Automatic download** of errors to `laravel.log`

### Available Options

```bash
# Download errors from the last 30 days
php artisan bugsnag:download-logs --days=30

# Download only resolved errors
php artisan bugsnag:download-logs --status=resolved

# Combine options
php artisan bugsnag:download-logs --days=7 --status=open
```

**Available options:**
- `--days=N` : Number of days to retrieve errors from (default: 7)
- `--status=X` : Error status to filter by (default: open)
  - Possible values: `open`, `resolved`, `ignored`, `snoozed`

## Examples

### Example Output

```bash
$ php artisan bugsnag:download-logs

🐛 Bugsnag Organization & Projects Finder + Logs Downloader

📋 Fetching organizations...

🏢 Available organizations:
┌─────────────┬─────────────┬──────────────────────────┐
│ Name        │ Slug        │ ID                       │
├─────────────┼─────────────┼──────────────────────────┤
│ My Company  │ my-company  │ 507f1f77bcf86cd799439011 │
└─────────────┴─────────────┴──────────────────────────┘

Select an organization: My Company (my-company)

✅ Selected organization: My Company
📁 Fetching projects...

📦 Available projects:
┌─────────────┬─────────────┬──────────────────────────┬─────────────┐
│ Name        │ Slug        │ ID                       │ Open Errors │
├─────────────┼─────────────┼──────────────────────────┼─────────────┤
│ My App      │ my-app      │ 507f1f77bcf86cd799439012 │ 15          │
└─────────────┴─────────────┴──────────────────────────┴─────────────┘

Enter project name (name or slug): my-app

✅ Project found:
Name: My App
Slug: my-app
ID: 507f1f77bcf86cd799439012
Open errors: 15

📥 Downloading open errors from the last 7 days...
⚡ Processing 15 errors...
✅ Successfully saved 15 errors to laravel.log

┌─────────────────────┬───────────────────────────────┬─────────────────┬─────────────────────┐
│ Error Class         │ Message                       │ First Seen      │ File                │
├─────────────────────┼───────────────────────────────┼─────────────────┼─────────────────────┤
│ RuntimeException    │ Database connection failed    │ 2 hours ago     │ app/Models/User.php │
│ InvalidArgumentEx.. │ Invalid email format          │ 5 hours ago     │ app/Http/Contro...  │
└─────────────────────┴───────────────────────────────┴─────────────────┴─────────────────────┘

... and 5 more errors. Check laravel.log for complete details.
```

### Error Log Format

Errors are saved to `laravel.log` in this format:

```
[2024-09-24 14:30:22] local.ERROR: Bugsnag Error: RuntimeException {
    "error_class": "RuntimeException",
    "message": "Database connection failed",
    "context": "production",
    "first_seen": "2024-09-24T12:30:22.000Z",
    "grouping_fields": {
        "errorClass": "RuntimeException",
        "file": "app/Models/User.php",
        "code": "42"
    }
}
```

## Troubleshooting

### Token Not Configured

```
❌ BUGSNAG_API_TOKEN not configured in .env
💡 Add to .env file: BUGSNAG_API_TOKEN = your_personal_auth_token
```

**Solution:** Verify the token is present in your `.env` file and the configuration file has been published.

### Project Not Found

```
❌ Project 'project-name' not found.
💡 Use one of the names or slugs shown in the table above.
```

**Solution:** Use exactly the name or slug shown in the available projects table.

### Bugsnag API Error

```
❌ Error retrieving logs from Bugsnag: 401
```

**Solutions:**
- Verify the token is valid and not expired
- Check you have permissions to access the project
- Ensure the token has read permissions for errors

## Contributing

Pull requests are welcome! For major changes, please open an issue first to discuss what you would like to change.

## Security Vulnerabilities

If you discover a security vulnerability, please send an email to sodalite.dana@gmail.com.

## Credits

- [Sodalitedana](https://github.com/sodalitedana)
- [FrankFlow](https://github.com/FrankFlow)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.