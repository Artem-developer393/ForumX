<?php

namespace App\Repositories;

use App\Database\Database;
use App\Models\Report;
use PDO;

class ReportRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->pdo;
    }

    public function save(Report $report): Report
    {
        $stmt = $this->db->prepare("
            INSERT INTO reports (reporter_id, category_id, target_type, target_id, description, status, created_at)
            VALUES (:reporter_id, :category_id, :target_type, :target_id, :description, :status, NOW())
        ");

        $stmt->bindValue(':reporter_id', $report->reporterId, PDO::PARAM_INT);

        $stmt->bindValue(':category_id', $report->categoryId, PDO::PARAM_INT);

        $stmt->bindValue(':target_type', $report->targetType, PDO::PARAM_STR);

        $stmt->bindValue(':target_id', $report->targetId, PDO::PARAM_INT);

        if ($report->description === null) {
            $stmt->bindValue(':description', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':description', $report->description, PDO::PARAM_STR);
        }

        $stmt->bindValue(':status', $report->status, PDO::PARAM_STR);

        $stmt->execute();

        $id = (int)$this->db->lastInsertId();

        $savedReport = $this->findById($id);

        if (!$savedReport) {
            throw new \RuntimeException(
                'Failed to retrieve the created report'
            );
        }

        return $savedReport;
    }

    public function findById(int $id): ?Report
    {
        $stmt = $this->db->prepare("
            SELECT * FROM reports WHERE id = :id LIMIT 1
        ");

        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();

        return $row
            ? Report::fromArray($row)
            : null;
    }

    public function findAllForAdmin(
        int $limit = 20,
        int $offset = 0,
        ?string $status = null
    ): array {
        $whereSql = $status !== null
            ? 'WHERE r.status = :status'
            : '';

        $stmt = $this->db->prepare("
        SELECT
            r.*,

            rc.code AS category_code,
            rc.title AS category_title,

            reporter.login AS reporter_login,
            reviewer.login AS reviewer_login

        FROM reports r

        INNER JOIN report_categories rc
            ON r.category_id = rc.id

        LEFT JOIN users reporter
            ON r.reporter_id = reporter.id

        LEFT JOIN users reviewer
            ON r.reviewed_by = reviewer.id

        {$whereSql}

        ORDER BY
            CASE
                WHEN r.status = 'pending' THEN 0
                ELSE 1
            END,
            r.created_at DESC

        LIMIT :limit OFFSET :offset
    ");

        if ($status !== null) {
            $stmt->bindValue(
                ':status',
                $status,
                PDO::PARAM_STR
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
            r.*,

            rc.code AS category_code,
            rc.title AS category_title,

            reporter.login AS reporter_login,
            reviewer.login AS reviewer_login

        FROM reports r

        INNER JOIN report_categories rc
            ON r.category_id = rc.id

        LEFT JOIN users reporter
            ON r.reporter_id = reporter.id

        LEFT JOIN users reviewer
            ON r.reviewed_by = reviewer.id

        WHERE r.id = :id
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
        FROM reports
    ");

        return (int)$stmt->fetchColumn();
    }

    public function countForAdmin(
        ?string $status = null
    ): int {
        if ($status === null) {
            return $this->countAll();
        }

        $stmt = $this->db->prepare("
        SELECT COUNT(*)
        FROM reports
        WHERE status = :status
    ");

        $stmt->execute([
            'status' => $status
        ]);

        return (int)$stmt->fetchColumn();
    }

    public function resolve(int $id, int $adminId): bool
    {
        $stmt = $this->db->prepare("
        UPDATE reports
        SET
            status = 'resolved',
            reviewed_by = :reviewed_by,
            updated_at = NOW()
        WHERE id = :id
          AND status = 'pending'
    ");

        $stmt->bindValue(
            ':reviewed_by',
            $adminId,
            PDO::PARAM_INT
        );

        $stmt->bindValue(
            ':id',
            $id,
            PDO::PARAM_INT
        );

        return $stmt->execute();
    }

    public function reject(int $id, int $adminId): bool
    {
        $stmt = $this->db->prepare("
        UPDATE reports
        SET
            status = 'rejected',
            reviewed_by = :reviewed_by,
            updated_at = NOW()
        WHERE id = :id
          AND status = 'pending'
    ");

        $stmt->bindValue(
            ':reviewed_by',
            $adminId,
            PDO::PARAM_INT
        );

        $stmt->bindValue(
            ':id',
            $id,
            PDO::PARAM_INT
        );

        return $stmt->execute();
    }

    public function existsPendingReport(int $reporterId, string $targetType, int $targetId): bool
    {
        $stmt = $this->db->prepare("
        SELECT 1
        FROM reports
        WHERE reporter_id = :reporter_id
          AND target_type = :target_type
          AND target_id = :target_id
          AND status = 'pending'
        LIMIT 1
    ");

        $stmt->bindValue(':reporter_id', $reporterId, PDO::PARAM_INT);

        $stmt->bindValue(':target_type', $targetType, PDO::PARAM_STR);

        $stmt->bindValue(':target_id', $targetId, PDO::PARAM_INT);

        $stmt->execute();

        return (bool)$stmt->fetchColumn();
    }

    public function countPending(): int
    {
        $stmt = $this->db->query("
        SELECT COUNT(*)
        FROM reports
        WHERE status = 'pending'
    ");

        return (int)$stmt->fetchColumn();
    }


    public function deleteByPostWithComments(int $postId): bool
    {
        $stmt = $this->db->prepare("
        DELETE FROM reports
        WHERE
            (target_type = 'post' AND target_id = :post_id)
            OR
            (
                target_type = 'comment'
                AND target_id IN (
                    SELECT id
                    FROM comments
                    WHERE post_id = :comment_post_id
                )
            )
    ");

        $stmt->bindValue(
            ':post_id',
            $postId,
            PDO::PARAM_INT
        );

        $stmt->bindValue(
            ':comment_post_id',
            $postId,
            PDO::PARAM_INT
        );

        return $stmt->execute();
    }

    public function deleteBySubredditWithContent(int $subredditId): bool
    {
        $stmt = $this->db->prepare("
        DELETE FROM reports
        WHERE
            (
                target_type = 'subreddit'
                AND target_id = :subreddit_id
            )
            OR
            (
                target_type = 'post'
                AND target_id IN (
                    SELECT id
                    FROM posts
                    WHERE subreddit_id = :post_subreddit_id
                )
            )
            OR
            (
                target_type = 'comment'
                AND target_id IN (
                    SELECT c.id
                    FROM comments c
                    INNER JOIN posts p
                        ON p.id = c.post_id
                    WHERE p.subreddit_id = :comment_subreddit_id
                )
            )
    ");

        $stmt->bindValue(
            ':subreddit_id',
            $subredditId,
            PDO::PARAM_INT
        );

        $stmt->bindValue(
            ':post_subreddit_id',
            $subredditId,
            PDO::PARAM_INT
        );

        $stmt->bindValue(
            ':comment_subreddit_id',
            $subredditId,
            PDO::PARAM_INT
        );

        return $stmt->execute();
    }

    public function deleteByUserContent(int $userId): bool
    {
        $stmt = $this->db->prepare("
        DELETE FROM reports
        WHERE
            (
                target_type = 'subreddit'
                AND target_id IN (
                    SELECT id
                    FROM subreddits
                    WHERE by_created = :subreddit_user_id
                )
            )
            OR
            (
                target_type = 'post'
                AND target_id IN (
                    SELECT id
                    FROM posts
                    WHERE author_id = :post_user_id
                       OR subreddit_id IN (
                            SELECT id
                            FROM subreddits
                            WHERE by_created = :post_subreddit_user_id
                       )
                )
            )
            OR
            (
                target_type = 'comment'
                AND target_id IN (
                    WITH RECURSIVE comment_tree AS (
                        SELECT id
                        FROM comments
                        WHERE author_id = :comment_user_id

                        UNION

                        SELECT c.id
                        FROM comments c
                        INNER JOIN comment_tree ct
                            ON c.parent_id = ct.id
                    )
                    SELECT id
                    FROM comment_tree
                )
            )
            OR reporter_id = :reporter_user_id
            OR reviewed_by = :reviewer_user_id
    ");

        $stmt->bindValue(
            ':subreddit_user_id',
            $userId,
            PDO::PARAM_INT
        );

        $stmt->bindValue(
            ':post_user_id',
            $userId,
            PDO::PARAM_INT
        );

        $stmt->bindValue(
            ':post_subreddit_user_id',
            $userId,
            PDO::PARAM_INT
        );

        $stmt->bindValue(
            ':comment_user_id',
            $userId,
            PDO::PARAM_INT
        );

        $stmt->bindValue(
            ':reporter_user_id',
            $userId,
            PDO::PARAM_INT
        );

        $stmt->bindValue(
            ':reviewer_user_id',
            $userId,
            PDO::PARAM_INT
        );

        return $stmt->execute();
    }

    public function hasPendingAccountDeletionRequest(int $userId): bool
    {
        $stmt = $this->db->prepare("
        SELECT id
        FROM reports
        WHERE reporter_id = :user_id
          AND target_type = 'user'
          AND target_id = :target_id
          AND status = 'pending'
        LIMIT 1
    ");

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':target_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createAccountDeletionRequest(int $userId): bool
    {
        $stmt = $this->db->prepare("
        INSERT INTO reports (
            reporter_id,
            category_id,
            target_type,
            target_id,
            description,
            status
        )
        VALUES (
            :user_id,
            6,
            'user',
            :target_id,
            :description,
            'pending'
        )
    ");

        return $stmt->execute([
            'user_id' => $userId,
            'target_id' => $userId,
            'description' => 'Запрос на удаление аккаунта'
        ]);
    }
}
