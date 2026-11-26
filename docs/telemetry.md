# FlowForm Telemetry

Anonymous install-level telemetry in FlowForm is **disabled by default**. Fresh self-hosted installs do not send any outbound telemetry requests unless the operator explicitly enables `FLOWFORM_TELEMETRY_ENABLED=true` and confirms `php artisan flowform:telemetry opt-in`.

## What Is Sent

When telemetry is both enabled in configuration and opted into by the operator, FlowForm sends only the following allowlisted JSON fields (`App\Services\Telemetry\TelemetryService::ALLOWED_PAYLOAD_FIELDS`):

| Field | Description | Example |
| :--- | :--- | :--- |
| `install_id` | Randomly generated install UUID (created once per install) | `550e8400-e29b-41d4-a716-446655440000` |
| `version` | FlowForm version (`FLOWFORM_VERSION`) | `0.1.0` |
| `php_version` | PHP major.minor version | `8.4` |
| `database_driver` | Configured database connection driver | `sqlite` |
| `response_volume_bucket` | Coarsened 30-day submission bucket (`0`, `1-99`, `100-999`, `1000-9999`, `10000+`), never an exact count | `1-99` |
| `country` | Non-default `app.locale` setting if changed from `en`, never IP geolocation | `null` |
| `enabled_integrations` | Boolean presence names for configured OAuth/notification services (`postmark`, `resend`, `slack`, `github_oauth`, `google_oauth`), never keys or credentials | `["slack"]` |
| `pinged_at` | ISO-8601 UTC timestamp of the ping | `2026-11-27T00:00:00+00:00` |

**Never collected or sent:** form schemas, form names or slugs, submission payloads, respondent or operator email addresses, hostnames, URLs, IP addresses, API tokens, or exact submission/user counts.

## When It Is Sent

- The Laravel scheduler runs `php artisan flowform:telemetry:ping` once daily (`routes/console.php`).
- If `FLOWFORM_TELEMETRY_ENABLED` is `false` (default) or the instance has not opted in, the command exits early without making any HTTP request.

## Where It Is Sent

- Pings are sent as a single HTTPS `POST` request with a 5-second timeout to `FLOWFORM_TELEMETRY_ENDPOINT` (defaults to `https://telemetry.flowformhq.com/api/v1/ping`).
- Failed or unreachable requests are logged at `debug` level and never interrupt application requests or form submissions.

## How to Opt In or Disable

1. **Permanently disable via environment variable (default):**

```env
FLOWFORM_TELEMETRY_ENABLED=false
```

2. **Manage opt-in state via Artisan:**

```bash
php artisan flowform:telemetry status
php artisan flowform:telemetry opt-in
php artisan flowform:telemetry opt-out
```

3. **In automated tests:** Bind `App\Services\Telemetry\TelemetryContract` to `App\Services\Telemetry\NullTelemetryService` to suppress database and network access cleanly.

## Data Retention

Anonymous telemetry pings contain no personal data or tenant identifiers. Aggregated install-level version and driver counts are retained solely to prioritize PHP/database compatibility and release planning. Disabling telemetry or running `php artisan flowform:telemetry opt-out` stops all further transmission immediately.
