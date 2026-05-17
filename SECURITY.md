# Security Policy

## Reporting a Vulnerability

If you discover a security vulnerability in FlowForm, please report it responsibly:

**Email:** security@flowformhq.com

**Do not** report security vulnerabilities through public GitHub Issues, discussions, or pull requests.

## What to include

- A description of the vulnerability
- Steps to reproduce (or a proof of concept)
- The affected version(s)
- Any potential impact

## Response timeline

- **Acknowledgment:** Within 48 hours
- **Initial assessment:** Within 5 business days
- **Status updates:** Every 7 days until resolved
- **Fix:** Target within 30 days for confirmed vulnerabilities. Critical vulnerabilities are prioritized.

## Disclosure policy

- We practice **coordinated disclosure**. We ask that you give us a reasonable time to address the vulnerability before publishing details.
- We will credit researchers in our security advisories unless they prefer to remain anonymous.
- We do not pursue legal action against researchers who act in good faith.

## Supported versions

| Version | Supported |
| ------- | --------- |
| main (latest) | Yes |
| Previous minor releases | Best effort |
| Older versions | No |

## Security measures in FlowForm

- API authentication via Laravel Sanctum bearer tokens
- CSRF protection on all non-API routes
- Input validation and sanitization on all endpoints
- SQL injection prevention via Eloquent ORM parameter binding
- Rate limiting on API endpoints
- No form submission content is logged or stored outside the database
