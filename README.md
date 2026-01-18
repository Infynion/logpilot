# Logpilot by Infynion

**Logpilot** is a robust, modular system logger for WordPress, designed to track PHP errors, exceptions, and custom event logs directly in your database. Built with a Service-Oriented Architecture, it ensures reliability, security, and performance without bloating your site.

## Features

*   **Robust Error Catching**: Automatically captures PHP errors, warnings, notices, exceptions, and fatal errors.
*   **Database Storage**: Stores logs in a dedicated custom table (`wp_infynion_logpilot_logs`) for better persistence and querying.
*   **Secure Encryption**: Encrypts sensitive log data using `AES-256-CBC` before storage.
*   **Smart Deduplication**: Groups identical errors to prevent database clutter, tracking occurrences instead of duplicate rows.
*   **Email Notifications**: Instant alerts for critical errors and weekly summaries for general site health.
*   **Auto-Cleanup**: Automatically removes logs older than a configured number of days to keep the database size in check.
*   **Admin Dashboard**: A clean, native WordPress admin interface to view, filter, and manage logs.

## Installation

1.  Upload the `logpilot` directory to the `/wp-content/plugins/` directory.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  Go to **Logpilot Logs** > **Settings** to configure your preferences (enable logging, email notifications, retention period).

## Usage

### Viewing Logs
Navigate to **Logpilot Logs** in your WordPress admin dashboard. You will see a list of all captured errors. Click "View Details" on any log entry to see the full stack trace and details.

### Custom Logging
You can use the `logpilot` hook to save your own custom logs from other plugins or themes:

```php
do_action( 'logpilot_log', 'Your custom message here', 'info', __FILE__, __LINE__ );
```

**Parameters:**
1.  `$message` (string|array): The data to log. Arrays are automatically JSON encoded.
2.  `$level` (string): The log level (e.g., 'error', 'warning', 'info', 'debug'). Default: 'error'.
3.  `$file` (string): Optional. The file path where the event occurred.
4.  `$line` (int): Optional. The line number.

## Requirements

*   WordPress 5.0 or higher
*   PHP 7.4 or higher

## Author

**Infynion**  
[https://infynion.com](https://infynion.com)

## License

This project is licensed under the GPL-2.0+ License - see the [LICENSE](LICENSE) file for details.
