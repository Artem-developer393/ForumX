# Local installation

## Requirements

- Docker Desktop / Docker Engine with Docker Compose v2
- Free local port `8080` (or choose another `APP_PORT`)
- Python 3.10+ **only** for the optional API test scripts
- Working SMTP credentials **only** if testing registration email verification or password recovery

## Start the API

From the repository root:

```powershell
Copy-Item .env.example .env
```

On Linux/macOS, use `cp .env.example .env` instead. Edit `.env`: replace the `CHANGE_ME` placeholders, set distinct `DB_PASS` and `DB_ROOT_PASSWORD`, and use a long random `JWT_SECRET`. Set `APP_PORT` if 8080 is occupied.

```sh
docker compose up --build -d
docker compose ps
curl -i http://localhost:8080/api/posts
```

The last command should reach a public JSON endpoint; content depends on the database. Change the URL if you changed `APP_PORT`.

MySQL runs `database/01-schema.sql` **only when `db_data` is initialized for the first time**. Updating the SQL file does not migrate an existing database.

## Prepare local demo accounts

The normal registration flow sends an email verification code through SMTP. For a disposable local database, demo accounts can instead be created explicitly:

```powershell
py -3 tests/setup_demo.py --confirm-local
```

On systems where Python is named `python3`, run `python3 tests/setup_demo.py --confirm-local`. Docker containers must already be running.

The script creates verified `demo_alice`, `demo_bob` and `demo_admin` accounts (`example.test` addresses), generates a random password, and writes **ignored** `credentials.json` in the project root. This step **changes database records**; it is not part of the normal `docker compose up` flow. It refuses to overwrite an existing credentials file. Keep the file private.

## Run tests

```powershell
py -3 tests/test_api_precision.py --base-url http://localhost:8080 --credentials credentials.json
py -3 tests/test_api_full.py --base-url http://localhost:8080 --credentials credentials.json
py -3 tests/test_api_refresh.py --base-url http://localhost:8080 --credentials credentials.json
```

The precision suite describes itself as read-only; the full and refresh suites can change data and sessions. Run them **only on a disposable local database**. Run `--help` for additional options. The tests use the Python standard library.

## Operations

```sh
docker compose logs -f api
docker compose logs -f db
docker compose restart
docker compose down
```

`docker compose down` stops/removes containers but retains named volumes. **Do not run `docker compose down -v` unless you intend to delete the MySQL database and uploaded files.**

## Troubleshooting

| Symptom | Check |
| --- | --- |
| API does not respond | `docker compose ps`, then `docker compose logs api` |
| Database not ready | `docker compose logs db`; check `.env` DB values |
| Registration cannot verify email | Configure `MAIL_*` with a working SMTP provider |
| Demo setup refuses to run | Ensure Docker is running, pass `--confirm-local`, and check for an existing `credentials.json` |
| Changed schema does not appear | Existing `db_data` is not automatically reinitialized; use a deliberate migration or fresh disposable database |

This Compose setup is for **local development and evaluation**, not a hardened public deployment.
