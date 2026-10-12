<?php

namespace App\Repositories;

use App\Models\Post;
use App\Database\Database;
use PDO;

class PostRepository
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
            p.*,
            u.login AS author_login,
            u.avatar AS author_avatar,
            s.title AS subreddit_title,
            (
                SELECT COUNT(*)
                FROM comments c
                WHERE c.post_id = p.id
            ) AS comments_count
        FROM posts p
        LEFT JOIN users u ON p.author_id = u.id
        INNER JOIN subreddits s ON p.subreddit_id = s.id
    ";
    }

    public function save(Post $post): Post
    {
        $stmt = $this->db->prepare("
            INSERT INTO posts (subreddit_id, author_id, title, body, is_locked, created_at)
            VALUES (:subreddit_id, :author_id, :title, :body, :is_locked, NOW())
        ");

        $stmt->bindValue(':subreddit_id', $post->subredditId, \PDO::PARAM_INT);
        $stmt->bindValue(':author_id', $post->creatorId, \PDO::PARAM_INT);
        $stmt->bindValue(':title', $post->title, \PDO::PARAM_STR);
        $stmt->bindValue(':body', $post->body, \PDO::PARAM_STR);
        $stmt->bindValue(':is_locked', 0, \PDO::PARAM_INT);

        $stmt->execute();

        $id = (int)$this->db->lastInsertId();

        $savedPost = $this->findById($id);

        if (!$savedPost) {
            throw new \RuntimeException(
                'Failed to retrieve the created post'
            );
        }

        return $savedPost;
    }

    public function update(Post $post): bool
    {
        $stmt = $this->db->prepare("
        UPDATE posts
        SET title = :title, body = :body, updated_at = NOW()
        WHERE id = :id
    ");

        return $stmt->execute([
            ':title' => $post->title,
            ':body' => $post->body,
            ':id' => $post->id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM posts WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function islock(int $id): bool
    {
        $stmt = $this->db->prepare("
            SELECT is_locked
            FROM posts
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch();

        return $result ? (bool)$result['is_locked'] : false;
    }

    public function lock(int $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE posts
            SET is_locked = 1
            WHERE id = :id
        ");
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);

        return $stmt->execute();
    }
    public function unlock(int $id): bool
    {
        $stmt = $this->db->prepare("
        UPDATE posts
        SET is_locked = 0
        WHERE id = :id
    ");
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);

        return $stmt->execute();
    }



    public function findById(int $id): ?Post
    {
        $sql = $this->getEnrichedSelect() . "
        WHERE p.id = :id
        LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->bindValue(
            ':id',
            $id,
            PDO::PARAM_INT
        );

        $stmt->execute();

        $row = $stmt->fetch();

        return $row
            ? Post::fromArray($row)
            : null;
    }

    public function findBySubreddit(int $subredditId, int $limit = 20, int $offset = 0): array
    {
        $sql = $this->getEnrichedSelect() . "
        WHERE p.subreddit_id = :subreddit_id
        ORDER BY p.created_at DESC
        LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->bindValue(
            ':subreddit_id',
            $subredditId,
            PDO::PARAM_INT
        );

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
            fn(array $row) => Post::fromArray($row),
            $rows
        );
    }

    public function findAll(int $limit = 20, int $offset = 0, string $sort = 'new'): array
    {
        $orderBy = match ($sort) {
            'popular' => 'comments_count DESC, p.created_at DESC',
            default => 'p.created_at DESC'
        };

        $sql = $this->getEnrichedSelect() . "
        ORDER BY {$orderBy}
        LIMIT :limit OFFSET :offset
    ";

        $stmt = $this->db->prepare($sql);

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();

        $rows = $stmt->fetchAll();

        return array_map(
            fn(array $row) => Post::fromArray($row),
            $rows
        );
    }

    public function findSmartFeed(int $userId, int $limit = 20, int $offset = 0): array
    {
        $sql = "
            SELECT
                p.*,
                u.login AS author_login,
                u.avatar AS author_avatar,
                sr.title AS subreddit_title,

                (
                    SELECT COUNT(*)
                    FROM comments cc
                    WHERE cc.post_id = p.id
                ) AS comments_count,

                (
                    (
                        IF(
                            s.user_id IS NOT NULL,
                            150,
                            0
                        )
                        /
                        POW(
                            TIMESTAMPDIFF(
                                HOUR,
                                p.created_at,
                                NOW()
                            ) + 2,
                            1.2
                        )
                    )

                    +
                    -- 1. DYNAMIC SUBSCRIPTION BOOST:
                   -- Provides an initial 150 points, which decay over time.
                   -- After 24 hours, only about 3.2 of the initial 150 points remain.
                   -- This lets fresh or viral posts outside subscriptions outrank older subscribed posts.


                   -- 2. POPULARITY BOOST (comments):
                   -- Multiply the comment count by 3 and divide by age to the power of 1.5.
                   -- A viral post outside subscriptions with 30+ comments quickly achieves a high score
                   -- and naturally appears in the subscriptions feed.

                    (
                        (
                            SELECT COUNT(*)
                            FROM comments c
                            WHERE c.post_id = p.id
                        ) * 3
                        /
                        POW(
                            TIMESTAMPDIFF(
                                HOUR,
                                p.created_at,
                                NOW()
                            ) + 2,
                            1.5
                        )
                    )
                ) AS feed_score

            FROM posts p

            LEFT JOIN users u
                ON p.author_id = u.id

            INNER JOIN subreddits sr
                    ON p.subreddit_id = sr.id

            LEFT JOIN subscriptions s
                ON p.subreddit_id = s.subreddit_id
                AND s.user_id = :user_id

            ORDER BY feed_score DESC, p.created_at DESC

            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return array_map(fn($row) => Post::fromArray($row), $rows);

        /*
|--------------------------------------------------------------------------
| NUMERICAL SCORE CALCULATION EXAMPLES:
|--------------------------------------------------------------------------
|
| 1. Fresh subscribed post (0 hours, 0 comments):
|    Score = (150 / (0 + 2)^1.2) + 0 = 150 / 2.29 = 65.2
|
| 2. Viral unsubscribed post (1 hour, 60 comments):
|    Score = 0 + (60 * 3 / (1 + 2)^1.5) = 180 / 5.19 = 34.6
|    (Appears immediately after the newest subscribed posts)
|
| 3. Trending unsubscribed post (2 hours, 25 comments):
|    Score = 0 + (25 * 3 / (2 + 2)^1.5) = 75 / 8 = 9.37
|    (Above older subscribed posts but below newer ones)
|
| 4. Old subscribed post (48 hours, 2 comments):
|    Score = (150 / (48 + 2)^1.2) + (2 * 3 / (48 + 2)^1.5) = 1.37 + 0.01 = 1.38
|    (Drops to the bottom of the feed, making room for current trends)
|
*/
    }
    public function findAllForAdmin(
        int $limit = 20,
        int $offset = 0,
        string $query = '',
        ?bool $locked = null
    ): array {
        $where = [];

        if ($query !== '') {
            $where[] = "p.title LIKE :query";
        }

        if ($locked !== null) {
            $where[] = "p.is_locked = :locked";
        }

        $whereSql = $where
            ? 'WHERE ' . implode(' AND ', $where)
            : '';

        $stmt = $this->db->prepare("
        SELECT
            p.*,
            u.login AS author_login,
            s.title AS subreddit_title
        FROM posts p
        LEFT JOIN users u
            ON p.author_id = u.id
        INNER JOIN subreddits s
            ON p.subreddit_id = s.id

        {$whereSql}

        ORDER BY p.created_at DESC
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
            p.*,
            u.login AS author_login,
            s.title AS subreddit_title
        FROM posts p
        LEFT JOIN users u ON p.author_id = u.id
        INNER JOIN subreddits s ON p.subreddit_id = s.id
        WHERE p.id = :id
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
        FROM posts
    ");

        return (int)$stmt->fetchColumn();
    }

    public function countLocked(): int
    {
        $stmt = $this->db->query("
        SELECT COUNT(*)
        FROM posts
        WHERE is_locked = 1
    ");

        return (int)$stmt->fetchColumn();
    }

    public function searchByTitle(
        string $query,
        int $limit = 20,
        int $offset = 0
    ): array {
        $sql = $this->getEnrichedSelect() . "
        WHERE p.title LIKE :query
        ORDER BY p.created_at DESC
        LIMIT :limit OFFSET :offset
    ";

        $stmt = $this->db->prepare($sql);

        $stmt->bindValue(':query', '%' . $query . '%', PDO::PARAM_STR);

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();

        $rows = $stmt->fetchAll();

        return array_map(
            fn(array $row) => Post::fromArray($row),
            $rows
        );
    }

    public function countSearchByTitle(string $query): int
    {
        $stmt = $this->db->prepare("
        SELECT COUNT(*)
        FROM posts
        WHERE title LIKE :query
        ");

        $stmt->execute(['query' => '%' . $query . '%']);

        return (int)$stmt->fetchColumn();
    }

    public function findByUserId(int $userId, int $limit = 20, int $offset = 0): array
    {
        $sql = $this->getEnrichedSelect() . "
        WHERE p.author_id = :user_id
        ORDER BY p.created_at DESC
        LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();

        $rows = $stmt->fetchAll();

        return array_map(
            fn(array $row) => Post::fromArray($row),
            $rows
        );
    }

    public function countByUserId(int $userId): int
    {
        $stmt = $this->db->prepare("
        SELECT COUNT(*)
        FROM posts
        WHERE author_id = :user_id
        ");

        $stmt->execute(['user_id' => $userId]);

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
        FROM posts
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

    public function updateImage(int $postId, ?string $image): bool
    {
        $stmt = $this->db->prepare("
        UPDATE posts
        SET image = :image
        WHERE id = :id
    ");

        return $stmt->execute([
            'image' => $image,
            'id' => $postId
        ]);
    }

    public function findImagesBySubreddit(int $subredditId): array
    {
        $stmt = $this->db->prepare("
        SELECT image
        FROM posts
        WHERE subreddit_id = :subreddit_id
          AND image IS NOT NULL
    ");

        $stmt->bindValue(
            ':subreddit_id',
            $subredditId,
            PDO::PARAM_INT
        );

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function findImagesByUserId(int $userId): array
    {
        $stmt = $this->db->prepare("
        SELECT image
        FROM posts
        WHERE author_id = :user_id
          AND image IS NOT NULL
    ");

        $stmt->bindValue(
            ':user_id',
            $userId,
            PDO::PARAM_INT
        );

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function deleteByUserId(int $userId): bool
    {
        $stmt = $this->db->prepare("
        DELETE FROM posts
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
