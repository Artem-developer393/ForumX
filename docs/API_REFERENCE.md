# ForumX API reference

> Based on `webForumRedit(20261012-095928).zip`. The PHP router and controllers are authoritative; Postman collections are historical test scenarios.

## Quick start

Base URL: `http://localhost:8080`. All routes below are relative to this origin. JSON request bodies use `Content-Type: application/json`; authenticated requests use `Authorization: Bearer <access_token>`.

```bash
curl http://localhost:8080/api/posts
```

## Response format

Successful requests use the `Response::success()` envelope:

```json
{"data": {}, "meta": null, "message": null}
```

Errors use `{"error":{"message":"...","status":422,"code":"VALIDATION_ERROR","fields":{}}}`; `fields` is present for field validation failures. Pagination numbers may be extracted into `meta` (for example `page`, `limit`, `total`).

## Authentication

1. Register with `POST /api/auth/register`, then verify the email code with `POST /api/auth/verify-email` (SMTP is required for normal registration).
2. Log in with `POST /api/auth/login`; read `data.token` (JWT access token), `data.refresh_token` and `data.user`.
3. Send the access token in the `Authorization` header for user/admin routes.
4. Exchange the refresh token at `POST /api/auth/refresh` using JSON `{"refresh_token":"<64-character-hex-token>"}`. Replace **both** tokens with those returned.
5. Use logout or logout-all to invalidate the relevant session(s). Store tokens securely in a mobile application.

## Operations

**Access:** `guest` = public, `user` = valid user access token, `admin` = administrator. Route access does not imply that every user can modify every resource: ownership/moderation rules are enforced in application logic.

### Administration

| Method | Path | Access | Request body |
|---|---|---|---|
| `GET` | `/api/admin/refresh-tokens/stats` | admin | — |
| `POST` | `/api/admin/refresh-tokens/cleanup` | admin | — |
| `GET` | `/api/admin/users` | admin | — |
| `GET` | `/api/admin/users/{id}` | admin | — |
| `DELETE` | `/api/admin/users/{id}` | admin | — |
| `PATCH` | `/api/admin/users/{id}/ban` | admin | — |
| `PATCH` | `/api/admin/users/{id}/unban` | admin | — |
| `PATCH` | `/api/admin/users/{id}/role` | admin | `InputAdminUpdateUserRoleDTO` |
| `GET` | `/api/admin/posts` | admin | — |
| `GET` | `/api/admin/posts/{id}` | admin | — |
| `DELETE` | `/api/admin/posts/{id}` | admin | — |
| `PATCH` | `/api/admin/posts/{id}/lock` | admin | — |
| `PATCH` | `/api/admin/posts/{id}/unlock` | admin | — |
| `GET` | `/api/admin/comments` | admin | — |
| `GET` | `/api/admin/comments/{id}` | admin | — |
| `DELETE` | `/api/admin/comments/{id}` | admin | — |
| `GET` | `/api/admin/subreddits` | admin | — |
| `GET` | `/api/admin/subreddits/{id}` | admin | — |
| `DELETE` | `/api/admin/subreddits/{id}` | admin | — |
| `PATCH` | `/api/admin/subreddits/{id}/lock` | admin | — |
| `PATCH` | `/api/admin/subreddits/{id}/unlock` | admin | — |
| `GET` | `/api/admin/reports` | admin | — |
| `GET` | `/api/admin/reports/{id}` | admin | — |
| `PATCH` | `/api/admin/reports/{id}/resolve` | admin | — |
| `PATCH` | `/api/admin/reports/{id}/reject` | admin | — |
| `GET` | `/api/admin/dashboard` | admin | — |

### Authentication

| Method | Path | Access | Request body |
|---|---|---|---|
| `POST` | `/api/auth/register` | guest | `InputRegisterDTO` |
| `POST` | `/api/auth/verify-email` | guest | `InputVerifyEmailDTO` |
| `POST` | `/api/auth/verify-email/resend` | guest | `InputResendVerificationCodeDTO` |
| `POST` | `/api/auth/refresh` | guest | application/json |
| `POST` | `/api/auth/login` | guest | `InputLoginDTO` |
| `POST` | `/api/auth/logout` | user/admin | — |
| `POST` | `/api/auth/logout-all` | user/admin | — |
| `GET` | `/api/auth/me` | user/admin | — |
| `POST` | `/api/auth/forgot-password` | guest | `InputForgotPasswordDTO` |
| `POST` | `/api/auth/reset-password` | guest | `InputResetPasswordDTO` |
| `PATCH` | `/api/auth/change-password` | user/admin | `InputChangePasswordDTO` |

### Comments

| Method | Path | Access | Request body |
|---|---|---|---|
| `GET` | `/api/users/{id}/comments` | guest | — |
| `POST` | `/api/posts/{id}/comments` | user/admin | `InputCreateCommentDTO` |
| `GET` | `/api/posts/{id}/comments` | guest | — |
| `PUT` | `/api/comments/{id}` | user/admin | `InputUpdateCommentDTO` |
| `DELETE` | `/api/comments/{id}` | user/admin | — |

### Posts

| Method | Path | Access | Request body |
|---|---|---|---|
| `GET` | `/api/posts/feed` | user/admin | — |
| `GET` | `/api/posts` | guest | — |
| `POST` | `/api/posts` | user/admin | `InputCreatePostDTO` |
| `GET` | `/api/posts/{id}` | guest | — |
| `PUT` | `/api/posts/{id}` | user/admin | `InputUpdatePostDTO` |
| `DELETE` | `/api/posts/{id}` | user/admin | — |
| `PATCH` | `/api/posts/{id}/lock` | user/admin | — |
| `POST` | `/api/posts/{id}/image` | user/admin | multipart/form-data |
| `DELETE` | `/api/posts/{id}/image` | user/admin | — |

### Reports

| Method | Path | Access | Request body |
|---|---|---|---|
| `POST` | `/api/reports` | user/admin | `InputCreateReportDTO` |
| `GET` | `/api/reports/categories` | user/admin | — |

### Search

| Method | Path | Access | Request body |
|---|---|---|---|
| `GET` | `/api/search` | guest | — |

### Subreddits

| Method | Path | Access | Request body |
|---|---|---|---|
| `POST` | `/api/subreddits` | user/admin | `InputCreateSubredditDTO` |
| `GET` | `/api/subreddits` | guest | — |
| `GET` | `/api/subreddits/{id}` | guest | — |
| `PUT` | `/api/subreddits/{id}` | user/admin | `InputUpdateSubredditDTO` |
| `DELETE` | `/api/subreddits/{id}` | user/admin | — |
| `POST` | `/api/subreddits/{id}/icon` | user/admin | multipart/form-data |
| `DELETE` | `/api/subreddits/{id}/icon` | user/admin | — |
| `GET` | `/api/subreddits/{id}/posts` | guest | — |

### Subscriptions

| Method | Path | Access | Request body |
|---|---|---|---|
| `GET` | `/api/users/me/subscriptions` | user/admin | — |
| `POST` | `/api/subreddits/{id}/toggle-sub` | user/admin | — |

### Users

| Method | Path | Access | Request body |
|---|---|---|---|
| `GET` | `/api/users/{id}` | guest | — |
| `PUT` | `/api/users` | user/admin | `InputUpdateUserDTO` |
| `POST` | `/api/users/avatar` | user/admin | multipart/form-data |
| `DELETE` | `/api/users/avatar` | user/admin | — |
| `GET` | `/api/users/{id}/posts` | guest | — |
| `POST` | `/api/users/deletion-request` | user/admin | — |

## Confirmed request examples

**Login** — `POST /api/auth/login`:

```json
{"login_or_email":"demo_alice","password":"YOUR_PASSWORD"}
```

**Report** — `POST /api/reports` (Bearer token required):

```json
{"targetType":"post","targetId":1,"categoryId":1,"description":"Example reason"}
```

`categoryId` is an integer, not a string. Valid report targets are `post`, `comment`, `subreddit`, and `user`.

**Create comment** — `POST /api/posts/{id}/comments` (Bearer token required):

```json
{"body":"Example comment","parentId":null}
```

`parentId` is optional; omit it for a top-level comment.

## DTOs and response models

All field types and declared input DTOs are in [`openapi.yaml`](openapi.yaml), including output DTO property shapes. Important: PHP input names are not consistently snake_case: for example, post creation uses `subreddit_id`, whereas comment creation uses `parentId`. Do not rename JSON keys in the Kotlin client.

## Images and uploads

The source contains multipart uploads for user avatar (`avatar`), subreddit icon (`icon`), and post image (`image`). Send multipart form data, not a JSON file path. Returned image URLs are constructed from `APP_URL`; Android emulators and physical devices need a reachable API host rather than `localhost` on the phone.

## Validation and limitations

- Input validators are the authority for length, requiredness and format; the specification contains constraints verified for post creation/update, with additional registration, login, comment and report rules, but not all validator rules have been transcribed.
- Response `data` shapes vary by action. The generic envelope is verified, but the OpenAPI operation response schemas are intentionally broad where the exact controller return was not mapped.
- Some error statuses are conditional (permission, ownership, not found, duplicate data, invalid token). The default error schema documents their common structure, not a promise of identical statuses across operations.
- Postman URLs and IDs may be stale. Use the current Docker host and seed-generated demo credentials.

## Kotlin integration notes

Use Retrofit/OkHttp (or Ktor) with Kotlin serialization/Moshi. Model the envelope explicitly; serialize field names exactly; implement refresh-token rotation atomically to avoid multiple simultaneous refresh requests reusing an old token. Prefer `openapi.yaml` as a navigable contract, but verify action-specific response models before enabling client code generation.

## Sources

- `src/router.php` — route and access list
- `src/Controllers/` — status codes and response data
- `src/DTOs/Input/` and `src/DTOs/Output/` — field names/types
- `src/Validators/` — validation rules
- `src/Http/Response.php` — success/error envelopes
- `WEBForum_English_Manual_Full.postman_collection.json` — supplemental test examples
