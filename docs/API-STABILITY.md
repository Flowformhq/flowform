# FlowForm API Stability Promise

**Last updated:** May 2026

## v1 Stable Endpoints

All endpoints under `GET /api/v1/*` are designated as **v1 stable**. This means:

1. **No breaking changes** will be introduced to these endpoints without a minimum **6-month deprecation window**.
2. Breaking changes are announced via:
   - A `Deprecation` HTTP header in the API response
   - A `X-Sunset` HTTP header indicating the removal date
   - A changelog entry in the GitHub releases
   - An email to registered API consumers (if opted in)
3. After the deprecation window, the breaking change ships in a new major API version (e.g., `v2`).

## What counts as a breaking change

- Removing an endpoint
- Removing or renaming a request parameter or response field
- Changing a field's type
- Changing authentication requirements
- Changing error response formats
- Adding a new required parameter

## What does NOT count as a breaking change

- Adding a new optional request parameter
- Adding a new field to a response body
- Adding a new endpoint
- Adding a new enum value to a field (unless the field documents a closed set)
- Changing the order of fields in a JSON response
- Changing error message text (not structure)

## Stable endpoint list

The following endpoints are covered by this promise:

| Endpoint | Method | Status |
|---|---|---|
| `/api/v1/forms` | GET | Stable |
| `/api/v1/forms/{uuid}` | GET | Stable |
| `/api/v1/forms/{slug}/by-slug` | GET | Stable |
| `/api/v1/forms/{uuid}/schema` | GET | Stable |
| `/api/v1/submissions` | POST | Stable |
| `/api/v1/submissions/{uuid}` | GET | Stable |
| `/api/v1/submissions/{uuid}` | PATCH | Stable |
| `/api/v1/submissions/{uuid}/values` | POST | Stable |
| `/api/v1/submissions/{uuid}/advance` | POST | Stable |
| `/api/v1/submissions/{uuid}/retreat` | POST | Stable |
| `/api/v1/submissions/{uuid}/conditions` | GET | Stable |

## Versioning strategy

FlowForm uses **URL-based versioning** (`/api/v1/`, `/api/v2/`). We do not plan to use header-based versioning.

When `v2` is introduced, `v1` will continue to be supported for at least 12 months from the `v2` release date.

## Feedback

If you have concerns about an upcoming change, open a GitHub Issue with the `api` label.
