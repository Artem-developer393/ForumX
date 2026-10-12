# Architecture

`index.php` routes incoming HTTP requests through `src/router.php` and `AuthMiddleware` to Controllers. Controllers coordinate input DTOs and validation with Services, which implement application rules. Repositories access MySQL via PDO; output DTOs and HTTP response helpers format API responses.

The authentication flow uses HS256 JWT access tokens tied to database-backed session families. Refresh tokens are stored as hashes and rotated transactionally. Session-family revocation invalidates otherwise unexpired access tokens.

This repository preserves the existing application structure rather than introducing a framework or replacing its domain terminology (`subreddit`).
