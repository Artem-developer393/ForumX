# ForumX

**ForumX** is a Reddit-inspired community application. Its PHP 8.1 REST API is implemented; a Kotlin Android client is planned.

## Highlights
- Layered request processing: Router → Middleware → Controller → Service → Repository
- JWT authentication with database-backed session validation
- Transactional refresh-token rotation, replay detection and session revocation
- Users, subreddits, posts, comments, subscriptions, reporting and moderation
- MySQL persistence via PDO; integration tests for HTTP flows and token lifecycle

## Tech stack
PHP 8.1 · Apache · MySQL 8 · PDO · Composer · Docker Compose · Python integration tests

## Quick start
1. Install Docker Desktop or Docker Engine with Compose v2.
2. Copy `.env.example` to `.env` and replace all `CHANGE_ME` values with your own secrets and SMTP settings.
3. Run `docker compose up --build -d`.
4. Open `http://localhost:8080/api/posts`.

The database schema and lookup rows are initialized automatically on first startup. Registration and password recovery require working SMTP configuration.

See [Installation](docs/INSTALLATION.md) for troubleshooting and safety notes.

## Documentation
- [Installation and Docker](docs/INSTALLATION.md)
- [Architecture](docs/ARCHITECTURE.md)
- [Security and publishing](docs/SECURITY.md)
- [Database initialization](database/01-schema.sql)

## Status
- [x] PHP REST API and core community functionality
- [x] Database schema and Docker Compose setup (configuration added; runtime verification still required)
- [ ] Independently verify a clean Docker startup and integration test run
- [ ] Publish complete endpoint-by-endpoint API documentation
- [ ] Build the Android client in Kotlin

> Educational portfolio project. The Docker configuration is intended for local evaluation, not production deployment.

## Local demo accounts and automated tests

After starting Docker Compose, prepare three **verified** demo identities (two users and an admin) in your **local/disposable** MySQL database:

```powershell
py -3.14 tests/setup_demo.py --confirm-local
py -3.14 tests/test_api_precision.py --base-url http://localhost:8080 --credentials credentials.json
py -3.14 tests/test_api_full.py --base-url http://localhost:8080 --credentials credentials.json
py -3.14 tests/test_api_refresh.py --base-url http://localhost:8080 --credentials credentials.json
```

The setup script runs `tests/seed_demo.php` **inside the API container**, generates a random password, and writes a git-ignored `credentials.json` on your computer. Demo accounts are `demo_alice`, `demo_bob`, and `demo_admin`, with reserved `.test` email addresses. They are pre-verified **only by this explicit local fixture command**; normal registration still requires real email verification. No public API endpoint bypasses verification.

**Security:** Do not run the seed against production/shared databases. It creates an admin, and rerunning after deleting `credentials.json` resets the demo account passwords. The setup refuses to overwrite an existing `credentials.json`. Test suites `full` and `refresh` modify test data/sessions. The default `docker compose up` does **not** seed demo users.
