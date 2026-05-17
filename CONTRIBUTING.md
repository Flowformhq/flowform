# Contributing to FlowForm

Thank you for your interest in contributing to FlowForm! This document covers the process for contributing to the project.

## Quick Start

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/my-feature`)
3. Make your changes
4. Ensure tests pass (`vendor/bin/pest`)
5. Ensure code style is clean (`vendor/bin/pint --test`)
6. Commit with a clear message
7. Open a pull request

## Development Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan filament:assets
```

## Code Style

FlowForm uses [Laravel Pint](https://laravel.com/docs/pint) for PHP code style. Run the checker before submitting:

```bash
vendor/bin/pint --test
```

Auto-fix:

```bash
vendor/bin/pint
```

## Testing

We use [Pest PHP](https://pestphp.com/). All changes must include appropriate tests:

```bash
vendor/bin/pest
```

Tests are organized into:

- `tests/Feature/` — HTTP and integration tests
- `tests/Unit/` — Pure unit tests

When adding a new feature, add both feature tests (API behavior) and unit tests (service logic) as appropriate.

## Pull Request Guidelines

- **One concern per PR.** Keep PRs focused on a single change.
- **Tests required.** PRs without tests will not be merged.
- **Document public API changes.** If you add or change an API endpoint, update the OpenAPI spec by running `php artisan scribe:generate`.
- **No breaking changes** to documented v1 API endpoints without a deprecation period. If you must break something, flag it in the PR description.

## Contributor License Agreement

Before we can merge your first pull request, you will need to sign a Contributor License Agreement (CLA). The CLA Assistant bot will prompt you automatically when you open your first PR. This is required for the dual-license model (AGPLv3 + commercial) to work correctly.

The CLA confirms that you have the right to contribute your code under the project's license.

## Reporting Issues

- Use GitHub Issues for bug reports and feature requests.
- Include steps to reproduce for bugs.
- Include your PHP version, Laravel version, and database driver.

## Security Vulnerabilities

Do not report security vulnerabilities through public GitHub Issues. See [SECURITY.md](SECURITY.md) for responsible disclosure instructions.

## Code of Conduct

This project follows the [Contributor Covenant Code of Conduct](CODE_OF_CONDUCT.md). By participating, you agree to uphold it.

## Questions?

Open a GitHub Discussion or reach out on Discord (link coming soon).
