<?php
declare(strict_types=1);

namespace DefMan\Repository;

use PDO;

/**
 * Product Repository
 * Refactored from: form_productdetails.php
 */
final readonly class ProductRepository
{
    public function __construct(private PDO $pdo) {}

    public function findAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT p.`id`, p.`name`, COUNT(r.`ID`) AS `report_count`
             FROM `product` p
             LEFT JOIN `raport` r ON r.`Product` = p.`name` OR r.`product_id` = p.`id`
             GROUP BY p.`id`, p.`name`
             ORDER BY p.`name` ASC'
        );
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT `id`, `name` FROM `product` WHERE `id` = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByName(string $name): ?array
    {
        $stmt = $this->pdo->prepare('SELECT `id`, `name` FROM `product` WHERE `name` = :name LIMIT 1');
        $stmt->execute([':name' => $name]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(string $name): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO `product` (`name`) VALUES (:name)');
        $stmt->execute([':name' => trim(strip_tags($name))]);
        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $id): ?string
    {
        $product = $this->findById($id);
        if ($product === null) {
            return null;
        }
        $stmt = $this->pdo->prepare('DELETE FROM `product` WHERE `id` = :id');
        $stmt->execute([':id' => $id]);
        return (string) $product['name'];
    }
}
