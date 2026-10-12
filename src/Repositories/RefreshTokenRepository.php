<?php

namespace App\Repositories;

use App\Database\Database;
use PDO;

class RefreshTokenRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function save(int $userId, string $tokenHash, string $expiresAt, string $familyId, string $absoluteExpiresAt): bool
    {
        $stmt = $this->db->prepare("INSERT INTO refresh_tokens (user_id, token_hash, expires_at, family_id, absolute_expires_at) VALUES (:user_id, :token_hash, :expires_at, :family_id, :absolute_expires_at)");
        return $stmt->execute(['user_id' => $userId, 'token_hash' => $tokenHash, 'expires_at' => $expiresAt, 'family_id' => $familyId, 'absolute_expires_at' => $absoluteExpiresAt]);
    }

    public function rotate(string $oldHash, string $newHash, string $newExpiresAt): ?array
    {
        $this->db->beginTransaction();
        try {
            // Lock the user first: concurrent refresh requests for one account are serialized.
            $stmt = $this->db->prepare("SELECT user_id FROM refresh_tokens WHERE token_hash = :token_hash");
            $stmt->execute(['token_hash' => $oldHash]);
            $userId = $stmt->fetchColumn();
            if ($userId === false) {
                $this->db->rollBack();
                return null;
            }
            $stmt = $this->db->prepare("SELECT id, is_banned, email_verified_at FROM users WHERE id = :id FOR UPDATE");
            $stmt->execute(['id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user || (int)$user['is_banned'] !== 0 || $user['email_verified_at'] === null) {
                $this->db->rollBack();
                return null;
            }
            $stmt = $this->db->prepare("SELECT * FROM refresh_tokens WHERE token_hash = :token_hash FOR UPDATE");
            $stmt->execute(['token_hash' => $oldHash]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$record) {
                $this->db->rollBack();
                return null;
            }
            if ($record['used_at'] !== null) {
                // Replay: invalidate every token in this login session.
                $stmt = $this->db->prepare("UPDATE refresh_tokens SET revoked_at = UTC_TIMESTAMP() WHERE family_id = :family_id AND revoked_at IS NULL");
                $stmt->execute(['family_id' => $record['family_id']]);
                $this->db->commit();
                return null;
            }
            if ($record['revoked_at'] !== null || strtotime($record['expires_at'] . ' UTC') <= time() || strtotime($record['absolute_expires_at'] . ' UTC') <= time()) {
                $this->db->rollBack();
                return null;
            }
            $stmt = $this->db->prepare("UPDATE refresh_tokens SET used_at = UTC_TIMESTAMP() WHERE id = :id");
            $stmt->execute(['id' => $record['id']]);
            $stmt = $this->db->prepare("INSERT INTO refresh_tokens (user_id, token_hash, expires_at, family_id, absolute_expires_at) VALUES (:user_id, :token_hash, :expires_at, :family_id, :absolute_expires_at)");
            $stmt->execute(['user_id' => $userId, 'token_hash' => $newHash, 'expires_at' => gmdate('Y-m-d H:i:s', min(strtotime($newExpiresAt . ' UTC'), strtotime($record['absolute_expires_at'] . ' UTC'))), 'family_id' => $record['family_id'], 'absolute_expires_at' => $record['absolute_expires_at']]);
            $this->db->commit();
            return ['user_id' => (int)$userId, 'family_id' => $record['family_id']];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function isSessionActive(int $userId, string $familyId): bool
    {
        $stmt = $this->db->prepare("SELECT id FROM refresh_tokens WHERE user_id = :user_id AND family_id = :family_id AND used_at IS NULL AND revoked_at IS NULL AND expires_at > UTC_TIMESTAMP() AND absolute_expires_at > UTC_TIMESTAMP() LIMIT 1");
        $stmt->execute(['user_id' => $userId, 'family_id' => $familyId]);
        return $stmt->fetchColumn() !== false;
    }

    public function revokeSession(int $userId, string $familyId, string $tokenHash): bool
    {
        $this->db->beginTransaction();
        try {
            // Use the same lock order as rotate(): user first, token second.
            $stmt = $this->db->prepare("SELECT id FROM users WHERE id = :user_id FOR UPDATE");
            $stmt->execute(['user_id' => $userId]);
            if ($stmt->fetchColumn() === false) {
                $this->db->rollBack();
                return false;
            }
            $stmt = $this->db->prepare("SELECT id FROM refresh_tokens WHERE user_id = :user_id AND family_id = :family_id AND token_hash = :token_hash AND used_at IS NULL AND revoked_at IS NULL FOR UPDATE");
            $stmt->execute(['user_id' => $userId, 'family_id' => $familyId, 'token_hash' => $tokenHash]);
            if ($stmt->fetchColumn() === false) {
                $this->db->rollBack();
                return false;
            }
            $stmt = $this->db->prepare("UPDATE refresh_tokens SET revoked_at = UTC_TIMESTAMP() WHERE user_id = :user_id AND family_id = :family_id AND revoked_at IS NULL");
            $stmt->execute(['user_id' => $userId, 'family_id' => $familyId]);
            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function revoke(int $userId, string $tokenHash): void
    {
        $stmt = $this->db->prepare("UPDATE refresh_tokens SET revoked_at = UTC_TIMESTAMP() WHERE user_id = :user_id AND token_hash = :token_hash AND used_at IS NULL AND revoked_at IS NULL");
        $stmt->execute(['user_id' => $userId, 'token_hash' => $tokenHash]);
    }

    public function revokeAll(int $userId): void
    {
        $stmt = $this->db->prepare("UPDATE refresh_tokens SET revoked_at = UTC_TIMESTAMP() WHERE user_id = :user_id AND revoked_at IS NULL");
        $stmt->execute(['user_id' => $userId]);
    }

    public function getStats(): array
    {
        $stmt = $this->db->query("
            SELECT
                COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN used_at IS NULL AND revoked_at IS NULL AND expires_at > UTC_TIMESTAMP() AND absolute_expires_at > UTC_TIMESTAMP() THEN 1 ELSE 0 END), 0) AS active,
                COALESCE(SUM(CASE WHEN used_at IS NOT NULL THEN 1 ELSE 0 END), 0) AS used,
                COALESCE(SUM(CASE WHEN revoked_at IS NOT NULL THEN 1 ELSE 0 END), 0) AS revoked,
                COALESCE(SUM(CASE WHEN expires_at <= UTC_TIMESTAMP() OR absolute_expires_at <= UTC_TIMESTAMP() THEN 1 ELSE 0 END), 0) AS expired,
                COALESCE(SUM(CASE WHEN absolute_expires_at <= UTC_TIMESTAMP() THEN 1 ELSE 0 END), 0) AS ready_for_cleanup
            FROM refresh_tokens
        ");
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        return array_map('intval', $stats);
    }

    public function deleteExpired(int $limit = 1000): int
    {
        $limit = max(1, min($limit, 10000));
        $stmt = $this->db->prepare("DELETE FROM refresh_tokens WHERE absolute_expires_at <= UTC_TIMESTAMP() LIMIT {$limit}");
        $stmt->execute();
        return $stmt->rowCount();
    }
}
