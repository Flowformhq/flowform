# FlowForge TypeScript SDK

Zero-dependency TypeScript client for the FlowForge API. Uses the native `fetch` API.

## Installation

Copy `flowforge.ts` into your project, or install from the repository:

```bash
cp sdk/typescript/flowforge.ts src/lib/flowforge.ts
```

## Quick Start

```typescript
import { FlowForgeClient } from "./flowforge";

const client = new FlowForgeClient("http://localhost", "your-api-token");

// Fetch a form schema
const { data: schema } = await client.getFormSchema("form-uuid-here");
console.log(schema.steps);

// Create a submission and fill in values
const { data: submission } = await client.createSubmission(schema.uuid);
await client.storeValues(submission.uuid, [
  { field_code: "name", value: "Alice" },
  { field_code: "email", value: "alice@example.com" },
]);

// Complete the submission
await client.updateSubmission(submission.uuid, { status: "completed" });
```

## API Reference

### Constructor

```typescript
new FlowForgeClient(baseUrl: string, token?: string)
```

- `baseUrl` — Your FlowForge server URL (e.g. `http://localhost`)
- `token` — Optional Sanctum bearer token for authenticated endpoints

### Public Endpoints (no token required)

| Method | Description |
|--------|-------------|
| `getForms(page?)` | List active forms (paginated) |
| `getForm(uuid)` | Get form by UUID |
| `getFormBySlug(slug)` | Get form by slug |
| `getFormSchema(uuid)` | Get full form schema with steps, fields, and conditions |

### Authenticated Endpoints

| Method | Description |
|--------|-------------|
| `createSubmission(formUuid)` | Create a new draft submission |
| `getSubmission(uuid)` | Get submission with current values |
| `updateSubmission(uuid, { status?, meta? })` | Update submission status or metadata |
| `storeValues(uuid, values)` | Upsert field values (`[{ field_code, value }]`) |
| `advanceStep(uuid)` | Move to the next step |
| `retreatStep(uuid)` | Move to the previous step |
| `getConditions(uuid)` | Get field visibility and required states |

### Error Handling

All methods throw `FlowForgeError` on non-2xx responses:

```typescript
import { FlowForgeError } from "./flowforge";

try {
  await client.getForm("invalid-uuid");
} catch (err) {
  if (err instanceof FlowForgeError) {
    console.error(err.status); // 404
    console.error(err.body);   // { message: "Not Found" }
  }
}
```
