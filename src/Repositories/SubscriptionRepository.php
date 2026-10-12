<?php

namespace App\Repositories;


use App\Database\Database;
use PDO;

class SubscriptionRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->pdo;
    }

    public function isSubscripted(int $userId, int $subredditId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM subscriptions
            WHERE user_id = :user_id AND subreddit_id = :subreddit_id
        ");
        $stmt->execute([
            'user_id' => $userId,
            'subreddit_id' => $subredditId
        ]);

        return (bool)$stmt->fetchColumn();
    }

    public function subscribe(int $userId, int $subredditId): bool
    {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO subscriptions (user_id, subreddit_id, created_at)
            VALUES (:user_id, :subreddit_id, NOW())
        ");

        return $stmt->execute([
            'user_id' => $userId,
            'subreddit_id' => $subredditId,
        ]);
    }
    public function unsubscribe(int $userId, int $subredditId): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM subscriptions
            WHERE user_id = :user_id AND subreddit_id = :subreddit_id
        ");

        return $stmt->execute([
            'user_id' => $userId,
            'subreddit_id' => $subredditId,
        ]);
    }

    public function findByUserId(int $userId, int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->db->prepare("
        SELECT
            s.*,
            (
                SELECT COUNT(*)
                FROM subscriptions sub
                WHERE sub.subreddit_id = s.id
            ) AS subscribers_count

        FROM subreddits s

        INNER JOIN subscriptions us
                ON us.subreddit_id = s.id

        WHERE us.user_id = :user_id

        ORDER BY s.title ASC

        LIMIT :limit OFFSET :offset
       ");

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();

        $rows = $stmt->fetchAll();

        return array_map(
            fn(array $row) =>
            \App\Models\Subreddit::fromArray($row),
            $rows
        );
    }

    public function countByUserId(int $userId): int
    {
        $stmt = $this->db->prepare("
        SELECT COUNT(*)
        FROM subscriptions
        WHERE user_id = :user_id
        ");

        $stmt->execute(['user_id' => $userId]);

        return (int)$stmt->fetchColumn();
    }
}
