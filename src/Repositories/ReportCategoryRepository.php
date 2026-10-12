<?php

namespace App\Repositories;

use App\Database\Database;
use App\Models\ReportCategory;
use PDO;

class ReportCategoryRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->pdo;
    }

    public function findById(int $id): ?ReportCategory
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM report_categories
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();

        return $row
            ? ReportCategory::fromArray($row)
            : null;
    }

    public function findAllActive(): array
    {
        $stmt = $this->db->query("
            SELECT *
            FROM report_categories
            WHERE is_active = 1
            ORDER BY id ASC
        ");

        $rows = $stmt->fetchAll();

        return array_map(
            fn(array $row) => ReportCategory::fromArray($row),
            $rows
        );
    }
}
