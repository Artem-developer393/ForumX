<?php

namespace App\Repositories;

use App\Database\Database;
use App\Models\Subreddit;
use PDO;

class SubredditRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->pdo;
    }

    private function getEnrichedSelect(): string
    {
        return "
        SELECT
            s.*,
            (
                SELECT COUNT(*)
                FROM subscriptions sub
                WHERE sub.subreddit_id = s.id
            ) AS subscribers_count
        FROM subreddits s
    ";
    }

    public function save(Subreddit $subreddit): Subreddit
    {
        $stmt = $this->db->prepare("
            INSERT INTO subreddits (title, description, by_created)
            VALUES (:title, :description, :by_created)
        ");

        $stmt->execute([
            'title' => $subreddit->title,
            'description' => $subreddit->description,
            'by_created' => $subreddit->creatorId,
        ]);

        $id = (int)$this->db->lastInsertId();

        $savedSubreddit = $this->findById($id);

        if (!$savedSubreddit) {
            throw new \RuntimeException(
                'Failed to retrieve the created subreddit'
            );
        }

        return $savedSubreddit;
    }


    public function findByTitle(string $title): ?Subreddit
    {
        $sql = $this->getEnrichedSelect() . "
        WHERE s.title = :title
        LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute(['title' => $title]);

        $row = $stmt->fetch();

        return $row ? Subreddit::fromArray($row) : null;
    }

    public function findAll(): array
    {
        $sql = $this->getEnrichedSelect() . "
        ORDER BY s.id DESC
        ";

        $stmt = $this->db->query($sql);

        $rows = $stmt->fetchAll();

        return array_map(
            fn(array $row) => Subreddit::fromArray($row),
            $rows
        );
    }

    public function findById(int $id): ?Subreddit
    {
        $sql = $this->getEnrichedSelect() . "
        WHERE s.id = :id
        LIMIT 1
    ";

        $stmt = $this->db->prepare($sql);

        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();

        return $row ? Subreddit::fromArray($row) : null;
    }

    public function update(Subreddit $subreddit): bool
    {
        $stmt = $this->db->prepare("
        UPDATE subreddits
        SET title = :title, description = :description
        WHERE id = :id
    ");
        return $stmt->execute([
            'title' => $subreddit->title,
            'description' => $subreddit->description,
            'id' => $subreddit->id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM subreddits WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function findAllForAdmin(
        int $limit = 20,
        int $offset = 0,
        string $query = '',
        ?bool $locked = null
    ): array {
        $where = [];

        if ($query !== '') {
            $where[] = "s.title LIKE :query";
        }

        if ($locked !== null) {
            $where[] = "s.is_locked = :locked";
        }

        $whereSql = $where
            ? 'WHERE ' . implode(' AND ', $where)
            : '';

        $stmt = $this->db->prepare("
        SELECT
            s.*,
            u.login AS creator_login
        FROM subreddits s
        LEFT JOIN users u
            ON s.by_created = u.id

        {$whereSql}

        ORDER BY s.created_at DESC
        LIMIT :limit OFFSET :offset
    ");

        if ($query !== '') {
            $stmt->bindValue(
                ':query',
                '%' . $query . '%',
                PDO::PARAM_STR
            );
        }

        if ($locked !== null) {
            $stmt->bindValue(
                ':locked',
                $locked ? 1 : 0,
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
            s.*,
            u.login AS creator_login
        FROM subreddits s
        LEFT JOIN users u ON s.by_created = u.id
        WHERE s.id = :id
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
        FROM subreddits
    ");

        return (int)$stmt->fetchColumn();
    }

    public function countForAdmin(
        string $query = '',
        ?bool $locked = null
    ): int {
        $where = [];

        if ($query !== '') {
            $where[] = "title LIKE :query";
        }

        if ($locked !== null) {
            $where[] = "is_locked = :locked";
        }

        $whereSql = $where
            ? 'WHERE ' . implode(' AND ', $where)
            : '';

        $stmt = $this->db->prepare("
        SELECT COUNT(*)
        FROM subreddits
        {$whereSql}
    ");

        if ($query !== '') {
            $stmt->bindValue(
                ':query',
                '%' . $query . '%',
                PDO::PARAM_STR
            );
        }

        if ($locked !== null) {
            $stmt->bindValue(
                ':locked',
                $locked ? 1 : 0,
                PDO::PARAM_INT
            );
        }

        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }

    public function lock(int $id): bool
    {
        $stmt = $this->db->prepare("
        UPDATE subreddits
        SET is_locked = 1
        WHERE id = :id
    ");

        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function unlock(int $id): bool
    {
        $stmt = $this->db->prepare("
        UPDATE subreddits
        SET is_locked = 0
        WHERE id = :id
    ");

        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function countLocked(): int
    {
        $stmt = $this->db->query("
        SELECT COUNT(*)
        FROM subreddits
        WHERE is_locked = 1
    ");

        return (int)$stmt->fetchColumn();
    }

    public function searchByTitle(string $query, int $limit = 20, int $offset = 0): array
    {
        $sql = $this->getEnrichedSelect() . "
        WHERE s.title LIKE :query
        ORDER BY s.title ASC
        LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->bindValue(':query', '%' . $query . '%', PDO::PARAM_STR);

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();

        $rows = $stmt->fetchAll();

        return array_map(
            fn(array $row) => Subreddit::fromArray($row),
            $rows
        );
    }

    public function countSearchByTitle(string $query): int
    {
        $stmt = $this->db->prepare("
        SELECT COUNT(*)
        FROM subreddits
        WHERE title LIKE :query
         ");

        $stmt->execute(['query' => '%' . $query . '%']);

        return (int)$stmt->fetchColumn();
    }

    public function updateIcon(int $subredditId, ?string $icon): bool
    {
        $stmt = $this->db->prepare("
        UPDATE subreddits
        SET icon = :icon
        WHERE id = :id
    ");

        return $stmt->execute([
            'icon' => $icon,
            'id' => $subredditId
        ]);
    }

    public function findByCreatorId(int $userId): array
    {
        $stmt = $this->db->prepare("
        SELECT *
        FROM subreddits
        WHERE by_created = :user_id
    ");

        $stmt->bindValue(
            ':user_id',
            $userId,
            PDO::PARAM_INT
        );

        $stmt->execute();

        $rows = $stmt->fetchAll();

        return array_map(
            fn(array $row) => Subreddit::fromArray($row),
            $rows
        );
    }
}
