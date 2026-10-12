<?php

namespace App\Repositories;

use App\Models\UserModel;
use App\Database\Database;
use PDO;

class UserRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }



    public function save(UserModel $user): UserModel
    {
        $stmt = $this->db->prepare("
            INSERT into users (login, email, password, role, is_banned)
            VALUES (:login, :email, :password, :role, :is_banned)
        ");
        $stmt->execute([
            'login' => $user->login,
            'email' => $user->email,
            'password' => $user->password,
            'role' => $user->role,
            'is_banned' => (int)$user->isBanned
        ]);
        $id = (int)$this->db->lastInsertId();

        return new UserModel(
            id: $id,
            login: $user->login,
            email: $user->email,
            password: $user->password,
            role: $user->role,
            isBanned: $user->isBanned
        );
    }

    public function update(int $userId, string $login, string $email): ?UserModel
    {
        $stmt = $this->db->prepare("
        UPDATE users
        SET login = :login,
            email = :email
        WHERE id = :id
    ");

        $stmt->execute([
            'id' => $userId,
            'login' => $login,
            'email' => $email
        ]);

        return $this->findById($userId);
    }

    public function ban(int $id): bool
    {
        $stmt = $this->db->prepare("
        UPDATE users
        SET is_banned = 1
        WHERE id = :id
    ");

        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function unban(int $id): bool
    {
        $stmt = $this->db->prepare("
        UPDATE users
        SET is_banned = 0
        WHERE id = :id
    ");

        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function updateRole(int $id, int $role): bool
    {
        $stmt = $this->db->prepare("
        UPDATE users
        SET role = :role
        WHERE id = :id
    ");

        $stmt->bindValue(':role', $role, \PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("
        DELETE FROM users
        WHERE id = :id
    ");

        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function findById(int $id): ?UserModel
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);

        $data = $stmt->fetch();
        return $data ? UserModel::fromArray($data) : null;
    }

    public function findByEmailOrLoginExceptUser(string $email, string $login, int $userId): ?UserModel
    {
        $stmt = $this->db->prepare("
        SELECT *
        FROM users
        WHERE (email = :email OR login = :login)
          AND id != :id
        LIMIT 1
    ");

        $stmt->execute([
            'email' => $email,
            'login' => $login,
            'id' => $userId
        ]);

        $data = $stmt->fetch();

        return $data ? UserModel::fromArray($data) : null;
    }

    public function findByEmailOrLogin(string $identifierOrEmail, ?string $login = null): ?UserModel
    {
        $emailParam = $identifierOrEmail;
        $loginParam = $login ?? $identifierOrEmail;

        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = :email OR login = :login LIMIT 1");
        $stmt->execute([
            'email' => $emailParam,
            'login' => $loginParam
        ]);

        $data = $stmt->fetch();

        return $data ? UserModel::fromArray($data) : null;
    }

    public function updateAvatar(int $userId, ?string $avatar): ?UserModel
    {
        $stmt = $this->db->prepare("
        UPDATE users
        SET avatar = :avatar
        WHERE id = :id
    ");

        $stmt->execute([
            'avatar' => $avatar,
            'id' => $userId
        ]);

        return $this->findById($userId);
    }

    public function findAll(int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->db->prepare("
        SELECT *
        FROM users
        ORDER BY id DESC
        LIMIT :limit OFFSET :offset
    ");

        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);

        $stmt->execute();

        $rows = $stmt->fetchAll();

        return array_map(
            fn(array $row) => UserModel::fromArray($row),
            $rows
        );
    }

    public function searchByLogin(string $query, int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->db->prepare("
        SELECT
            u.id,
            u.login,
            u.avatar,
            u.created_at,

            (
                SELECT COUNT(*)
                FROM posts p
                WHERE p.author_id = u.id
            ) AS posts_count,

            (
                SELECT COUNT(*)
                FROM comments c
                WHERE c.author_id = u.id
                  AND c.is_deleted = 0
            ) AS comments_count

        FROM users u
        WHERE u.login LIKE :query
        ORDER BY u.login ASC
        LIMIT :limit OFFSET :offset
    ");

        $stmt->bindValue(':query', '%' . $query . '%', PDO::PARAM_STR);

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countSearchByLogin(string $query): int
    {
        $stmt = $this->db->prepare("
        SELECT COUNT(*)
        FROM users
        WHERE login LIKE :query
    ");

        $stmt->execute([
            'query' => '%' . $query . '%'
        ]);

        return (int)$stmt->fetchColumn();
    }

    public function countAll(): int
    {
        $stmt = $this->db->query("
        SELECT COUNT(*)
        FROM users
    ");

        return (int)$stmt->fetchColumn();
    }

    public function countBanned(): int
    {
        $stmt = $this->db->query("
        SELECT COUNT(*)
        FROM users
        WHERE is_banned = 1
    ");

        return (int)$stmt->fetchColumn();
    }

    public function saveEmailVerificationCode(int $userId, string $codeHash, string $expiresAt): bool
    {
        $stmt = $this->db->prepare("
        UPDATE users
        SET email_verification_code_hash = :code_hash,
            email_verification_expires_at = :expires_at
        WHERE id = :id
    ");

        return $stmt->execute([
            'code_hash' => $codeHash,
            'expires_at' => $expiresAt,
            'id' => $userId
        ]);
    }

    public function getEmailVerificationData(string $email): ?array
    {
        $stmt = $this->db->prepare("
        SELECT
            id,
            email,
            email_verified_at,
            email_verification_code_hash,
            email_verification_expires_at
        FROM users
        WHERE email = :email
        LIMIT 1
    ");

        $stmt->execute([
            'email' => $email
        ]);

        $data = $stmt->fetch();

        return $data ?: null;
    }

    public function getPublicProfileData(
        int $userId
    ): ?array {
        $stmt = $this->db->prepare("
        SELECT
            u.id,
            u.login,
            u.avatar,
            u.created_at,

            (
                SELECT COUNT(*)
                FROM posts p
                WHERE p.author_id = u.id
            ) AS posts_count,

            (
                SELECT COUNT(*)
                FROM comments c
                WHERE c.author_id = u.id
                  AND c.is_deleted = 0
            ) AS comments_count

        FROM users u
        WHERE u.id = :id
        LIMIT 1
    ");

        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);

        $stmt->execute();

        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function verifyEmail(int $userId): bool
    {
        $stmt = $this->db->prepare("
        UPDATE users
        SET email_verified_at = CURRENT_TIMESTAMP,
            email_verification_code_hash = NULL,
            email_verification_expires_at = NULL
        WHERE id = :id
    ");

        return $stmt->execute([
            'id' => $userId
        ]);
    }

    public function resetEmailVerification(int $userId): bool
    {
        $stmt = $this->db->prepare("
        UPDATE users
        SET email_verified_at = NULL,
            email_verification_code_hash = NULL,
            email_verification_expires_at = NULL
        WHERE id = :id
    ");

        return $stmt->execute([
            'id' => $userId
        ]);
    }

    public function savePasswordResetCode(int $userId, string $codeHash, string $expiresAt): bool
    {
        $stmt = $this->db->prepare("
        UPDATE users
        SET password_reset_code_hash = :code_hash,
            password_reset_expires_at = :expires_at
        WHERE id = :id
    ");

        return $stmt->execute([
            'code_hash' => $codeHash,
            'expires_at' => $expiresAt,
            'id' => $userId
        ]);
    }

    public function getPasswordResetData(string $email): ?array
    {
        $stmt = $this->db->prepare("
        SELECT
            id,
            email,
            password_reset_code_hash,
            password_reset_expires_at
        FROM users
        WHERE email = :email
        LIMIT 1
    ");

        $stmt->execute([
            'email' => $email
        ]);

        $data = $stmt->fetch();

        return $data ?: null;
    }


    public function resetPassword(int $userId, string $passwordHash): bool
    {
        $stmt = $this->db->prepare("
        UPDATE users
        SET password = :password,
            password_reset_code_hash = NULL,
            password_reset_expires_at = NULL
        WHERE id = :id
    ");

        return $stmt->execute([
            'password' => $passwordHash,
            'id' => $userId
        ]);
    }

    public function updatePassword(int $userId, string $passwordHash): bool
    {
        $stmt = $this->db->prepare("
        UPDATE users
        SET password = :password
        WHERE id = :id
    ");

        return $stmt->execute([
            'password' => $passwordHash,
            'id' => $userId
        ]);
    }

    public function findAllForAdmin(
        int $limit = 20,
        int $offset = 0,
        string $query = '',
        ?int $role = null,
        ?bool $banned = null
    ): array {
        $where = [];

        if ($query !== '') {
            $where[] = "(u.login LIKE :query_login OR u.email LIKE :query_email)";
        }

        if ($role !== null) {
            $where[] = "u.role = :role";
        }

        if ($banned !== null) {
            $where[] = "u.is_banned = :banned";
        }

        $whereSql = !empty($where)
            ? 'WHERE ' . implode(' AND ', $where)
            : '';

        $stmt = $this->db->prepare("
        SELECT u.*
        FROM users u
        {$whereSql}
        ORDER BY u.id DESC
        LIMIT :limit OFFSET :offset
    ");

        if ($query !== '') {
            $search = '%' . $query . '%';

            $stmt->bindValue(
                ':query_login',
                $search,
                PDO::PARAM_STR
            );

            $stmt->bindValue(
                ':query_email',
                $search,
                PDO::PARAM_STR
            );
        }

        if ($role !== null) {
            $stmt->bindValue(
                ':role',
                $role,
                PDO::PARAM_INT
            );
        }

        if ($banned !== null) {
            $stmt->bindValue(
                ':banned',
                $banned ? 1 : 0,
                PDO::PARAM_INT
            );
        }

        $stmt->bindValue(
            ':limit',
            $limit,
            PDO::PARAM_INT
        );

        $stmt->bindValue(
            ':offset',
            $offset,
            PDO::PARAM_INT
        );

        $stmt->execute();

        $rows = $stmt->fetchAll();

        return array_map(
            fn(array $row) => UserModel::fromArray($row),
            $rows
        );
    }
    public function countForAdmin(
        string $query = '',
        ?int $role = null,
        ?bool $banned = null
    ): int {
        $where = [];

        if ($query !== '') {
            $where[] = "(u.login LIKE :query_login OR u.email LIKE :query_email)";
        }

        if ($role !== null) {
            $where[] = "u.role = :role";
        }

        if ($banned !== null) {
            $where[] = "u.is_banned = :banned";
        }

        $whereSql = !empty($where)
            ? 'WHERE ' . implode(' AND ', $where)
            : '';

        $stmt = $this->db->prepare("
        SELECT COUNT(*)
        FROM users u
        {$whereSql}
    ");

        if ($query !== '') {
            $search = '%' . $query . '%';

            $stmt->bindValue(
                ':query_login',
                $search,
                PDO::PARAM_STR
            );

            $stmt->bindValue(
                ':query_email',
                $search,
                PDO::PARAM_STR
            );
        }

        if ($role !== null) {
            $stmt->bindValue(
                ':role',
                $role,
                PDO::PARAM_INT
            );
        }

        if ($banned !== null) {
            $stmt->bindValue(
                ':banned',
                $banned ? 1 : 0,
                PDO::PARAM_INT
            );
        }

        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }
}
