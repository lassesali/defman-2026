<?php
declare(strict_types=1);

/**
 * CLI Database Seeder Runner
 * Usage:
 *   php bin/seed.php [--if-empty]
 */

use DefMan\Database\DatabaseSeeder;
use DefMan\Infrastructure\Database\Database;

require_once __DIR__ . '/../src/Domain/Enum/DomainEnums.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'DefMan\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/../src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

$ifEmptyOnly = in_array('--if-empty', $argv ?? [], true);

try {
    $pdo = Database::getConnection();
    $seeder = new DatabaseSeeder($pdo);
    $seeder->ensureSchema();

    if ($ifEmptyOnly) {
        $userCount    = (int) $pdo->query('SELECT COUNT(*) FROM `users` WHERE `company_id` IS NOT NULL')->fetchColumn();
        $productCount = (int) $pdo->query('SELECT COUNT(*) FROM `products`')->fetchColumn();
        $reportCount  = (int) $pdo->query('SELECT COUNT(*) FROM `defect_reports`')->fetchColumn();
        if ($userCount > 0 && $productCount > 0 && $reportCount > 0) {
            fwrite(STDOUT, "[DefMan Seeder] Database already seeded ({$userCount} users, {$productCount} products, {$reportCount} reports found). Skipping.\n");
            exit(0);
        }
    }

    $seeder->run();
    fwrite(STDOUT, "[DefMan Seeder] Database seeded successfully.\n");
} catch (\Throwable $e) {
    fwrite(STDERR, "[DefMan Seeder] Error: " . $e->getMessage() . "\n");
    exit(1);
}
