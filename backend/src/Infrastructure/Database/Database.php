<?php
declare(strict_types=1);

namespace DefMan\Infrastructure\Database;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Strict-typed PHP 8.3 PDO Connection Singleton
 * Refactored from: config.php & dbconnect.php
 */
final class Database
{
    private static ?PDO $instance = null;

    private function __construct() {}

    private function __clone() {}

    public function __wakeup(): never
    {
        throw new RuntimeException('Cannot unserialize singleton Database instance.');
    }

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $host    = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
            $port    = (int) ($_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: 3306);
            $dbName  = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'opr0413';
            $user    = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'opr0413';
            $pass    = $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: 'd3fman13';
            $charset = 'utf8mb4';

            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $dbName, $charset);

            try {
                self::$instance = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_STRINGIFY_FETCHES  => false,
                ]);
            } catch (PDOException $e) {
                throw new RuntimeException('Database connection failed: ' . $e->getMessage(), (int) $e->getCode(), $e);
            }
        }

        return self::$instance;
    }
}
