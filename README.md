# Logpilot - Advanced Error & Activity Monitor

A modern, highly performant, and secure logpilot for WordPress. Developed by **Infynion**.

## Features

- **Decoupled Architecture**: Built with PSR-4 autoloading and Dependency Injection.
- **Error Interception**: Captures standard PHP errors, Uncaught Exceptions, Shutdown fatals, WP HTTP failures, and AJAX failures.
- **Encrypted Storage**: Uses AES-256-CBC encryption to obscure error payloads containing potentially sensitive user data.
- **Smart Grouping**: Similar errors are hashed and grouped by "occurrences", minimizing database bloat.

## Development & Contribution

This plugin uses Composer, WordPress Coding Standards (WPCS), and PHPUnit. 

### Prerequisites
- PHP 7.4+
- Composer

### Installation
1. Clone the repository into `wp-content/plugins/logpilot`
2. Run `composer install`
3. Activate the plugin in WordPress.

### Tooling

- **Linting**: Run `composer lint` to check your code against WPCS. 
- **Auto-fixing**: Run `composer lint:fix` to auto-fix styling errors.
- **Testing**: Run `composer test` to run the active PHPUnit tests.
- **Build**: Run `composer build` to package the plugin into a releasable `.zip` archive.
