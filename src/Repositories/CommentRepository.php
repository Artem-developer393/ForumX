<?php

namespace App\Repositories;

use App\Models\Comment;
use App\Database\Database;
use PDO;

class CommentRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->pdo;
    }

    public function save(Comment $comment): Comment
    {
        $stmt = $this->db->prepare("
            INSERT INTO comments (
                post_id,
                author_id,
                parent_id,
                body,
                created_at
            )VALUES (
                :post_id,
                :author_id,
                :parent_id,
                :body,
                NOW()
            )
        ");

        $stmt->bindValue(
            ':post_id',
            $comment->postId,
            PDO::PARAM_INT
        );

        $stmt->bindValue(
            ':author_id',
            $comment->authorId,
            PDO::PARAM_INT
        );

        if ($comment->parentId === null) {
            $stmt->bindValue(
                ':parent_id',
                null,
                PDO::PARAM_NULL
            );
        } else {
            $stmt->bindValue(
                ':parent_id',
                $comment->parentId,
                PDO::PARAM_INT
            );
        }

        $stmt->bindValue(
            ':body',
            $comment->body,
            PDO::PARAM_STR
        );

        $stmt->execute();

        $id = (int)$this->db->lastInsertId();

        $savedComment = $this->findById($id);

        if (!$savedComment) {
            throw new \RuntimeException(
                'Failed to retrieve the created comment'
            );
        }

        return $savedComment;
    }

    public function update(Comment $comment): bool
    {
        $stmt = $this->db->prepare("
        UPDATE comments SET body = :body, updated_at = NOW() WHERE id = :id
    ");

        return $stmt->execute([
            ':body' => $comment->body,
            ':id' => $comment->id
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("
        UPDATE comments
        SET is_deleted = 1,
            body = '',
            updated_at = NOW()
        WHERE id = :id
    ");

        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function findById(int $id): ?Comment
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM comments
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();

        return $row ? Comment::fromArray($row) : null;
    }

    public function findByPostId(int $postId): array //MAYBE REMOVE LATER
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM comments
            WHERE post_id = :post_id
            ORDER BY created_at ASC
        ");

        $stmt->bindValue(
            ':post_id',
            $postId,
            PDO::PARAM_INT
        );

        $stmt->execute();

        $rows = $stmt->fetchAll();

        return array_map(
            fn(array $row) => Comment::fromArray($row),
            $rows
        );
    }

    public function findByPostIdWithAuthors(int $postId): array
    {
        $stmt = $this->db->prepare("
        SELECT
            c.*,
            u.login AS author_login,
            u.avatar AS author_avatar
        FROM comments c
        LEFT JOIN users u ON c.author_id = u.id
        WHERE c.post_id = :post_id
        ORDER BY c.created_at ASC
    ");

        $stmt->bindValue(
            ':post_id',
            $postId,
            PDO::PARAM_INT
        );

        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function findAllForAdmin(
        int $limit = 20,
        int $offset = 0,
        string $query = '',
        ?bool $deleted = null
    ): array {
        $where = [];

        if ($query !== '') {
            $where[] = "c.body LIKE :query";
        }

        if ($deleted !== null) {
            $where[] = "c.is_deleted = :deleted";
        }

        $whereSql = $where
            ? 'WHERE ' . implode(' AND ', $where)
            : '';

        $stmt = $this->db->prepare("
        SELECT
            c.*,
            u.login AS author_login,
            p.title AS post_title
        FROM comments c
        LEFT JOIN users u
            ON c.author_id = u.id
        INNER JOIN posts p
            ON c.post_id = p.id

        {$whereSql}

        ORDER BY c.created_at DESC
        LIMIT :limit OFFSET :offset
    ");

        if ($query !== '') {
            $stmt->bindValue(
                ':query',
                '%' . $query . '%',
                PDO::PARAM_STR
            );
        }

        if ($deleted !== null) {
            $stmt->bindValue(
                ':deleted',
                $deleted ? 1 : 0,
                PDO::PARAM_INT
            );
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function findByIdForAdmin(int $id): ?array
    {
        $stmt = $this->db->prepare("
        SELECT
            c.*,
            u.login AS author_login,
            p.title AS post_title
        FROM comments c
        LEFT JOIN users u ON c.author_id = u.id
        INNER JOIN posts p ON c.post_id = p.id
        WHERE c.id = :id
        LIMIT 1
    ");

        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function countAll(): int
    {
        $stmt = $this->db->query("
        SELECT COUNT(*)
        FROM comments
    ");

        return (int)$stmt->fetchColumn();
    }

    public function countActive(): int
    {
        $stmt = $this->db->query("
        SELECT COUNT(*)
        FROM comments
        WHERE is_deleted = 0
    ");

        return (int)$stmt->fetchColumn();
    }

    public function findByUserId(int $userId, int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->db->prepare("
        SELECT
            c.*,
            u.login AS author_login,
            u.avatar AS author_avatar
        FROM comments c
        LEFT JOIN users u
               ON c.author_id = u.id
        WHERE c.author_id = :user_id
          AND c.is_deleted = 0
        ORDER BY c.created_at DESC
        LIMIT :limit OFFSET :offset
    ");

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countByUserId(int $userId): int
    {
        $stmt = $this->db->prepare("
        SELECT COUNT(*)
        FROM comments
        WHERE author_id = :user_id
          AND is_deleted = 0
        ");

        $stmt->execute(['user_id' => $userId]);

        return (int)$stmt->fetchColumn();
    }

    public function countForAdmin(
        string $query = '',
        ?bool $deleted = null
    ): int {
        $where = [];

        if ($query !== '') {
            $where[] = "body LIKE :query";
        }

        if ($deleted !== null) {
            $where[] = "is_deleted = :deleted";
        }

        $whereSql = $where
            ? 'WHERE ' . implode(' AND ', $where)
            : '';

        $stmt = $this->db->prepare("
        SELECT COUNT(*)
        FROM comments
        {$whereSql}
    ");

        if ($query !== '') {
            $stmt->bindValue(
                ':query',
                '%' . $query . '%',
                PDO::PARAM_STR
            );
        }

        if ($deleted !== null) {
            $stmt->bindValue(
                ':deleted',
                $deleted ? 1 : 0,
                PDO::PARAM_INT
            );
        }

        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }

    public function deleteTreesByUserId(int $userId): bool
    {
        $stmt = $this->db->prepare("
        DELETE FROM comments
        WHERE author_id = :user_id
    ");

        $stmt->bindValue(
            ':user_id',
            $userId,
            PDO::PARAM_INT
        );

        return $stmt->execute();
    }
}
