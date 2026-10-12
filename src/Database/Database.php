<?php

namespace App\Database;

class Database
{
    public \PDO $pdo;
    protected static ?self $instance = null;

    private function __construct()
    {
        $dsn = 'mysql:host=' . $_ENV['DB_HOST'] .
            ';port=' . $_ENV['DB_PORT'] .
            ';dbname=' . $_ENV['DB_NAME'] .
            ';charset=' . 'utf8mb4';

        $this->pdo = new \PDO(
            $dsn,
            $_ENV['DB_USER'],
            $_ENV['DB_PASS'],
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                // RECOMMENDED: Disable emulated prepared statements for improved security
                \PDO::ATTR_EMULATE_PREPARES => false,
                // RECOMMENDED: Return associative arrays by default (unless a class is specified)
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]
        );
    }

    public function getConnection(): \PDO
    {
        return $this->pdo;
    }

    private function __clone() {}
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize a singleton.");
    }

    public function query(string $sql, array $params = [], string $className = \stdClass::class): array
    {
        $sth = $this->pdo->prepare($sql);
        $sth->execute($params);

        // When requesting the default stdClass, FETCH_CLASS may not behave as expected without additional flags.
        // Prefer checking whether a class is required: return an instance of that class, otherwise an array or object.
        if ($className === \stdClass::class) {
            return $sth->fetchAll(\PDO::FETCH_OBJ); // Returns an array of standard objects
        }

        return $sth->fetchAll(\PDO::FETCH_CLASS, $className);
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
