# FlowForm

**Build workflows, not forms.**

FlowForm is a headless, API-first form and workflow engine for developers. Define multi-step forms with conditional logic in the admin panel, then consume them via a clean REST API — no frontend coupling.

## Features

- Multi-step forms with drag-and-drop field ordering
- Conditional field visibility and required rules (show/hide/require)
- Schema-driven API — fetch the full form structure in one call
- Step-by-step submission with progress tracking
- Field value upsert (create or update in one call)
- Filament admin panel with export, dashboard widgets, and organized navigation
- TypeScript SDK included
- OpenAPI spec and interactive API docs

## Quick Start

```bash
# Clone and install
git clone https://github.com/flowformhq/flowform.git
cd flowform
composer install

# Configure environment
cp .env.example .env
php artisan key:generate

# Set up the database (SQLite by default)
touch database/database.sqlite
php artisan migrate --seed

# Publish Filament assets and start the server
php artisan filament:assets
php artisan serve
```

- **Admin panel:** http://localhost:8000/admin
- **API docs:** http://localhost:8000/docs

## API Overview

All endpoints are prefixed with `/api/v1/`.

### Forms (public, no auth required)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/forms` | List active forms (paginated) |
| GET | `/forms/{uuid}` | Get form by UUID |
| GET | `/forms/{slug}/by-slug` | Get form by slug |
| GET | `/forms/{uuid}/schema` | Full schema (steps, fields, options, conditions) |

### Submissions (requires Bearer token)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/submissions` | Create a draft submission |
| GET | `/submissions/{uuid}` | Get submission with values |
| PATCH | `/submissions/{uuid}` | Update status or metadata |
| POST | `/submissions/{uuid}/values` | Upsert field values |
| POST | `/submissions/{uuid}/advance` | Move to next step |
| POST | `/submissions/{uuid}/retreat` | Move to previous step |
| GET | `/submissions/{uuid}/conditions` | Evaluate field visibility/required states |

### Authentication

FlowForm uses [Laravel Sanctum](https://laravel.com/docs/sanctum) for API authentication. Generate a token:

```php
$token = $user->createToken('my-app')->plainTextToken;
```

Then pass it as a Bearer token:

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" http://localhost:8000/api/v1/submissions
```

## TypeScript SDK

A zero-dependency TypeScript client is included in [`sdk/typescript/`](sdk/typescript/).

```typescript
import { FlowFormClient } from "./flowform";

const client = new FlowFormClient("http://localhost:8000", "your-token");

// Fetch form schema
const { data: schema } = await client.getFormSchema("form-uuid");

// Create and complete a submission
const { data: sub } = await client.createSubmission(schema.uuid);
await client.storeValues(sub.uuid, [
  { field_code: "email", value: "alice@example.com" },
]);
await client.updateSubmission(sub.uuid, { status: "completed" });
```

See the [SDK README](sdk/typescript/README.md) for the full method reference.

## Examples

- [`examples/simple-form.ts`](examples/simple-form.ts) — Minimal: fetch form, submit values, complete
- [`examples/multi-step-form.ts`](examples/multi-step-form.ts) — Full: multi-step navigation with conditional fields

Run with:

```bash
FLOWFORM_URL=http://localhost:8000 FLOWFORM_TOKEN=your-token npx tsx examples/simple-form.ts
```

## Tech Stack

- **PHP 8.3** / **Laravel 13** — Backend framework
- **Filament 4** — Admin panel
- **Laravel Sanctum** — API authentication
- **Pest PHP** — Testing
- **Laravel Pint** — Code style

## Development

```bash
# Run tests
vendor/bin/pest

# Fix code style
vendor/bin/pint

# Regenerate API docs
php artisan scribe:generate
```

## License

The FlowForm engine is dual-licensed:

- **AGPLv3-or-later** for open-source use. See [LICENSE](LICENSE).
- **Commercial license** available for organizations that cannot accept AGPLv3. Contact licensing@flowformhq.com.

Starter kits and the TypeScript SDK are licensed under the [MIT license](https://opensource.org/licenses/MIT).

See [LICENSE-PROMISE.md](LICENSE-PROMISE.md) for our commitment to keeping the engine open.
