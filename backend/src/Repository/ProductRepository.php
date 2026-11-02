<?php
declare(strict_types=1);

namespace DefMan\Repository;

use PDO;
use RuntimeException;

/**
 * Product Repository (`products` table — 3NF)
 * Refactored from: form_productdetails.php
 */
final readonly class ProductRepository
{
    public function __construct(private PDO $pdo) {}

    public function findAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT p.`id`, p.`name`, COUNT(dr.`id`) AS `report_count`
             FROM `products` p
             LEFT JOIN `defect_reports` dr ON dr.`product_id` = p.`id`
             GROUP BY p.`id`, p.`name`
             ORDER BY p.`name` ASC'
        );
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT `id`, `name` FROM `products` WHERE `id` = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByName(string $name): ?array
    {
        $stmt = $this->pdo->prepare('SELECT `id`, `name` FROM `products` WHERE `name` = :name LIMIT 1');
        $stmt->execute([':name' => $name]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(string $name): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO `products` (`name`) VALUES (:name)');
        $stmt->execute([':name' => trim(strip_tags($name))]);
        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $id): ?string
    {
        $product = $this->findById($id);
        if ($product === null) {
            return null;
        }

        $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM `defect_reports` WHERE `product_id` = :id');
        $countStmt->execute([':id' => $id]);
        $reportCount = (int) $countStmt->fetchColumn();
        if ($reportCount > 0) {
            throw new RuntimeException(
                "Cannot delete product '{$product['name']}' because {$reportCount} defect report(s) reference it."
            );
        }

        $stmt = $this->pdo->prepare('DELETE FROM `products` WHERE `id` = :id');
        $stmt->execute([':id' => $id]);
        return (string) $product['name'];
    }
}
