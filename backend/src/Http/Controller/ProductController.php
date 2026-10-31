<?php
declare(strict_types=1);

namespace DefMan\Http\Controller;

use DefMan\Domain\Enum\UserLevel;
use DefMan\Http\Middleware\RbacMiddleware;
use DefMan\Repository\ProductRepository;

/**
 * Product Management Controller
 * Refactored from: form_productdetails.php
 */
final readonly class ProductController
{
    public function __construct(
        private ProductRepository $productRepository,
        private RbacMiddleware $rbac
    ) {}

    /**
     * GET /api/products (Available to all levels so Public/Customer forms can populate dropdowns)
     */
    public function listProducts(): array
    {
        return ['products' => $this->productRepository->findAll()];
    }

    /**
     * POST /api/admin/products (Refactored from form_productdetails.php?function=newproduct)
     */
    public function createProduct(array $body): array
    {
        $this->rbac->requireLevel(UserLevel::ADMIN);

        $name = trim(strip_tags((string) ($body['name'] ?? '')));
        if ($name === '') {
            http_response_code(422);
            return ['error' => 'Empty Product name!'];
        }

        if ($this->productRepository->findByName($name) !== null) {
            http_response_code(409);
            return ['error' => "Product '{$name}' already exists."];
        }

        $id = $this->productRepository->create($name);
        http_response_code(201);
        return [
            'id'      => $id,
            'name'    => $name,
            'message' => "You added new product: {$name} in database.",
        ];
    }

    /**
     * DELETE /api/admin/products/{id} (Refactored from form_productdetails.php?function=deleteproduct)
     */
    public function deleteProduct(int $id): array
    {
        $this->rbac->requireLevel(UserLevel::ADMIN);

        $deletedName = $this->productRepository->delete($id);
        if ($deletedName === null) {
            http_response_code(404);
            return ['error' => 'Product not found.'];
        }

        return ['message' => "You deleted product: {$deletedName} from the database."];
    }
}
