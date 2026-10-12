# Security and publishing notes

- `.env`, `credentials.json`, raw database exports and user uploads must never be committed.
- The included `database/01-schema.sql` contains **only DDL and non-personal lookup rows** (`roles`, `report_categories`).
- If a secret was ever pushed to a remote Git history, rotate/revoke it. Removing it from the latest commit is insufficient.
- Unexpected PHP exceptions are logged server-side and return a generic HTTP 500 error.
- Apache blocks source/configuration paths, but these controls require deployment-specific verification.
- This setup is not a production security certification. Add rate limits, secure HTTPS termination, monitoring and backups before public hosting.
