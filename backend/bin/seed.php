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

    if ($ifEmptyOnly) {
        $userCount = (int) $pdo->query('SELECT COUNT(*) FROM `users`')->fetchColumn();
        if ($userCount > 0) {
            fwrite(STDOUT, "[DefMan Seeder] Database already seeded ({$userCount} users found). Skipping.\n");
            exit(0);
        }
    }

    $seeder = new DatabaseSeeder($pdo);
    $seeder->run();
    fwrite(STDOUT, "[DefMan Seeder] Database seeded successfully.\n");
} catch (\Throwable $e) {
    fwrite(STDERR, "[DefMan Seeder] Error: " . $e->getMessage() . "\n");
    exit(1);
}
