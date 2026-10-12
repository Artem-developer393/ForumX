<div align="center">

# ForumX

### A Reddit-inspired REST API built with PHP and MySQL

**Communities · Discussions · Authentication · Moderation**

![PHP](https://img.shields.io/badge/PHP-8.1-777BB4?style=flat-square&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=flat-square&logo=mysql&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?style=flat-square&logo=docker&logoColor=white)
![Python](https://img.shields.io/badge/Tests-Python-3776AB?style=flat-square&logo=python&logoColor=white)
![API](https://img.shields.io/badge/API-REST-20232A?style=flat-square)

*A backend-focused portfolio project exploring API architecture, secure session management, and community moderation.*

[Features](#features) · [Quick start](#quick-start) · [API overview](#api-overview) · [Testing](#automated-testing) · [Architecture](#architecture) · [Documentation](#documentation)

</div>

---

## Overview

**ForumX** is a Reddit-inspired community platform backend implemented as a REST API using **PHP 8.1**, **MySQL 8**, and **PDO**. It supports user accounts, email verification, communities (subreddits), posts, comments, subscriptions, reporting, and administrative moderation.

The application uses a layered structure with controllers, services, repositories, validators, and input/output DTOs. Authentication combines JWT access tokens with database-backed session state and refresh-token rotation.

> **Project scope:** The PHP REST API is implemented. A Kotlin Android client is planned but is **not included** in this repository. The supplied Docker setup is intended for **local development and evaluation**, not production deployment.

## Features

| Area | Capabilities |
| --- | --- |
| **Authentication** | Registration, email verification, login, JWT access tokens, refresh-token rotation, logout, logout from all sessions, password recovery and password changes |
| **Users** | Public profiles, profile updates, avatars, user posts and comments, account deletion requests |
| **Communities** | Create, browse, edit and delete subreddits; subscribe/unsubscribe; community icons |
| **Posts** | Create, browse, edit and delete posts; post images; discussion locking; general and personalized feeds |
| **Comments** | Browse, create, update and delete comments |
| **Discovery** | Search across supported content |
| **Reporting** | Submit reports and browse report categories |
| **Administration** | Manage users, bans and roles; moderate posts, comments, communities and reports; dashboard and refresh-token maintenance |
| **Testing** | Python standard-library API suites covering regression, authorization, API flows and refresh-token behavior |

## Tech stack

| Layer | Technology |
| --- | --- |
| Runtime | PHP 8.1, Apache |
| Database | MySQL 8.0, PDO |
| Dependencies | Composer |
| Authentication | JWT (HS256), database-backed refresh-token sessions |
| Infrastructure | Docker, Docker Compose |
| Integration testing | Python 3.10+ (standard library) |

## Architecture

```text
HTTP request
     │
     ▼
index.php → src/router.php
     │
     ▼
Authentication / authorization middleware
     │
     ▼
Controller → Input DTO / Validator
     │
     ▼
Service (application rules)
     │
     ▼
Repository → PDO → MySQL
     │
     ▼
Output DTO / HTTP response
```

Key implementation details:

- **Separation of concerns:** request handling, validation, application rules, and persistence are kept in distinct layers.
- **Session-aware JWT authentication:** access tokens are tied to database-backed session families.
- **Refresh-token rotation:** refresh tokens are hashed in storage and rotated transactionally, with replay detection and session revocation.
- **Authorization boundaries:** routes distinguish public, authenticated-user, and administrator access.

Read [Architecture](docs/ARCHITECTURE.md) for more details.

## Quick start

### Requirements

- Docker Desktop or Docker Engine with **Docker Compose v2**
- Available local port **8080**, or an alternative configured through `APP_PORT`

### 1. Configure environment

Clone the repository (replace the URL with the actual GitHub URL after publication):

```bash
git clone https://github.com/YOUR_USERNAME/ForumX.git
cd ForumX
```

Create a local environment file:

**PowerShell:**

```powershell
Copy-Item .env.example .env
```

**Linux / macOS:**

```bash
cp .env.example .env
```

Edit `.env` and replace **all** `CHANGE_ME` placeholders. Set strong, different values for `DB_PASS` and `DB_ROOT_PASSWORD`, and generate a long random `JWT_SECRET`.

> Real SMTP settings are required for email verification and password recovery. The local demo-account setup below does not require real email addresses.

### 2. Start the application

```bash
docker compose up --build -d
docker compose ps
```

The API is available at **http://localhost:8080** by default.

### 3. Verify the public API

```bash
curl http://localhost:8080/api/posts
```

A fresh database should return an empty post collection in a JSON response. The MySQL schema and initial lookup data are imported automatically **only when the database volume is first initialized**.

> **Data safety:** `docker compose down -v` deletes the database and upload volumes. Do not use it unless you intend to destroy that local data.

See [Installation](docs/INSTALLATION.md) for operational details.

## API overview

The following routes are representative examples from `src/router.php`.

| Method | Endpoint | Access | Purpose |
| --- | --- | --- | --- |
| `POST` | `/api/auth/register` | Public | Register an account |
| `POST` | `/api/auth/verify-email` | Public | Verify email |
| `POST` | `/api/auth/login` | Public | Authenticate |
| `POST` | `/api/auth/refresh` | Public | Rotate refresh token |
| `POST` | `/api/auth/logout-all` | User | Revoke all sessions |
| `GET` | `/api/auth/me` | User | Current account |
| `GET` | `/api/posts` | Public | Browse posts |
| `GET` | `/api/posts/feed` | User | Personalized feed |
| `POST` | `/api/posts` | User | Create post |
| `GET` | `/api/posts/{id}` | Public | View post |
| `GET` | `/api/subreddits` | Public | Browse communities |
| `POST` | `/api/subreddits` | User | Create community |
| `POST` | `/api/subreddits/{id}/toggle-sub` | User | Toggle subscription |
| `GET` | `/api/posts/{id}/comments` | Public | List comments |
| `POST` | `/api/posts/{id}/comments` | User | Add comment |
| `GET` | `/api/search` | Public | Search |
| `POST` | `/api/reports` | User | Submit report |
| `GET` | `/api/admin/dashboard` | Admin | Moderation dashboard |
| `PATCH` | `/api/admin/users/{id}/ban` | Admin | Ban user |

The router also defines profile management, file uploads, moderation, password recovery, and other operations. Consult [`src/router.php`](src/router.php) for the complete route inventory. A dedicated endpoint-by-endpoint API reference is planned.

## Automated testing

ForumX includes three Python integration-test scripts. They use **Python 3.10+ standard library only** and target a running API instance.

### Prepare local demo identities

Start Docker Compose first, then run the explicit local seed command:

```powershell
py -3.14 tests/setup_demo.py --confirm-local
```

On Linux/macOS, use `python3` instead of `py -3.14`.

The setup creates three **email-verified local fixtures**:

| Login | Role | Email |
| --- | --- | --- |
| `demo_alice` | User | `demo_alice@example.test` |
| `demo_bob` | User | `demo_bob@example.test` |
| `demo_admin` | Admin | `demo_admin@example.test` |

It generates a random password and writes it to a **Git-ignored** `credentials.json`. The `.test` email addresses are intentionally non-deliverable. Regular registration still requires email verification; no public endpoint bypasses it.

> **Important:** Run demo seeding **only on a disposable local database**. It creates an administrator. The script refuses to overwrite an existing `credentials.json`; reseeding after removing that file rotates demo passwords.

### Run the suites

**Windows PowerShell:**

```powershell
py -3.14 tests/test_api_precision.py --base-url http://localhost:8080 --credentials credentials.json
py -3.14 tests/test_api_full.py --base-url http://localhost:8080 --credentials credentials.json
py -3.14 tests/test_api_refresh.py --base-url http://localhost:8080 --credentials credentials.json
```

**Linux / macOS:** substitute `python3` for `py -3.14`.

| Suite | Focus | Data changes |
| --- | --- | --- |
| `test_api_precision.py` | Strict regression and authorization checks | Read-only |
| `test_api_full.py` | Broader API integration flows | Yes |
| `test_api_refresh.py` | Refresh-token lifecycle and session behavior | Yes |

**Local verification:** The project owner reported successful runs of all three suites on October 12, 2026. These are local results, **not** a hosted CI status or a guarantee that every future environment will pass.

Never run the mutable suites against production or a database containing valuable data.

## Repository structure

```text
ForumX/
├── database/
│   └── 01-schema.sql         # MySQL schema and lookup data
├── docs/
│   ├── ARCHITECTURE.md
│   ├── INSTALLATION.md
│   └── SECURITY.md
├── src/
│   ├── Controllers/
│   ├── DTOs/
│   ├── Database/
│   ├── Http/
│   ├── Middleware/
│   ├── Models/
│   ├── Repositories/
│   ├── Services/
│   ├── Validators/
│   └── router.php
├── tests/
│   ├── seed_demo.php
│   ├── setup_demo.py
│   ├── test_api_precision.py
│   ├── test_api_full.py
│   └── test_api_refresh.py
├── uploads/                # Tracked placeholders; actual uploads are ignored
├── .env.example
├── compose.yaml
├── Dockerfile
├── composer.json
└── README.md
```

## Security and deployment notes

- Never commit `.env`, `credentials.json`, or real uploaded user content.
- Access to sensitive paths is restricted by the Apache configuration; do not rely on this as a complete security audit.
- Use demo seeding only in local/disposable environments.
- The provided Compose stack is **not production-hardened**: it does not include TLS termination, production secret management, backups, or rate limiting.
- For production use, additional infrastructure hardening, operational controls, and security review are required.

See [Security](docs/SECURITY.md).

## Roadmap

- [x] REST API for accounts, communities, posts, comments, subscriptions, reports, and moderation
- [x] JWT authentication and refresh-token session management
- [x] Docker-based local environment and MySQL initialization
- [x] Local demo-account fixtures and Python integration suites
- [ ] Detailed endpoint-by-endpoint API reference
- [ ] Automated CI pipeline
- [ ] Kotlin Android client

## Documentation

- [Installation & Docker](docs/INSTALLATION.md)
- [Architecture](docs/ARCHITECTURE.md)
- [Security notes](docs/SECURITY.md)
- [Database schema](database/01-schema.sql)
- [HTTP route definitions](src/router.php)

---

<div align="center">

**ForumX** · Backend engineering portfolio project

*Built to explore API design, authentication, and real-world community workflows.*

</div>
