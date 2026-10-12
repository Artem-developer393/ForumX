# Security notes

ForumX includes several application-level protections, but its supplied Docker Compose configuration targets **local development**, not production hosting.

## Implemented controls

| Area | Current behavior |
| --- | --- |
| Access tokens | HS256 JWTs with issuer check and expiry |
| Session validity | Protected requests check the database-backed session family |
| Refresh tokens | Random tokens stored as SHA-256 hashes and rotated through repository logic |
| Authorization | `guest`, `user` and `admin` route levels; additional ownership checks in application code |
| Database | PDO with emulated prepared statements disabled |
| Password storage | PHP password hashing for demo users; authentication logic is in `AuthService` |
| Errors | Unhandled exceptions are logged server-side; clients receive generic HTTP 500 responses |
| Apache | `.htaccess` blocks direct access to source, config, tests, database files and common private extensions |

These controls are **not** a substitute for a full security audit.

## Local demo accounts

`tests/setup_demo.py --confirm-local` invokes a **CLI-only** PHP seed (`tests/seed_demo.php`) inside the API container. The seed requires explicit environment flags and creates verified demo identities, including an administrator. Passwords are randomly generated and saved locally in `credentials.json`; the seed may reset credentials/roles of matching demo accounts.

**Never run the demo seed against a production or valuable database.** It is not a public registration mechanism.

## Secrets and repository hygiene

- Commit `.env.example`, **never** `.env` or `credentials.json`.
- Do not commit uploaded user files, raw database dumps, private keys or logs containing credentials.
- `.gitignore` reduces accidental commits, but it does not protect secrets already committed or included in a manually created ZIP.
- If a secret was published, revoke/rotate it; deleting the latest copy does not erase Git history.
- Avoid logging access tokens, refresh tokens, passwords and SMTP credentials.

## Deployment limitations

The provided Compose stack does **not** set up TLS termination, production secret management, rate limiting, backups, centralized monitoring or a production SMTP relay. Do not expose this development configuration directly to the internet. A production deployment needs these controls and a separate review of authorization, uploads, CORS and operational security.

For setup commands, see [Installation](INSTALLATION.md). For authentication flow, see [Architecture](ARCHITECTURE.md).
