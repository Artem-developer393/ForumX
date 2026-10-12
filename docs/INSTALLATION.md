# Installation and local development

## Requirements
- Docker Engine / Docker Desktop with Docker Compose v2
- Available host port 8080 (or set `APP_PORT`)
- Working SMTP server credentials for email verification and password recovery

## First run
1. Copy `.env.example` to `.env`.
2. Replace every `CHANGE_ME` value with your own values. Generate a long random `JWT_SECRET` and two different database passwords. Do not commit `.env`.
3. Run `docker compose up --build -d`.
4. Check `docker compose ps` and `docker compose logs api`.
5. Test `curl -i http://localhost:8080/api/posts` (public route; response depends on application state).

MySQL creates all tables and the public lookup rows automatically **on the first start with an empty database volume**. Existing volumes are not reinitialized when SQL files change.

## Useful commands
```sh
docker compose up --build -d
docker compose ps
docker compose logs -f api
docker compose logs -f db
docker compose down
```

`docker compose down -v` **deletes the database and uploaded-file volumes**. Use only if you intend to destroy local data.

## Email
Registration and password recovery require real SMTP settings. The Docker stack does not include an SMTP test inbox; without SMTP these flows will not complete. Never use real credentials in shared examples.

## Testing
The Python integration scripts are in `tests/` and use a separate `credentials.json` containing local test accounts. Read each script's CLI options before running. **Some tests mutate data or perform administrative cleanup; never run against production or valuable databases.** Historical reports in `tests/reports/` are not a new test run.

## Security scope
This Compose setup is for **local development and evaluation**, not public production deployment. It does not provide TLS, production secret management, backups, rate limiting, or a production mail relay. Use HTTPS and proper server hardening before deploying publicly.
