<?php
declare(strict_types=1);

/**
 * DefMan REST API Front Controller
 * Replaces monolithic 2004 index.php + menubar.php + db.php
 */

use DefMan\Http\Controller\AuthController;
use DefMan\Http\Controller\DefectController;
use DefMan\Http\Controller\ProductController;
use DefMan\Http\Controller\UserController;
use DefMan\Http\Middleware\RbacMiddleware;
use DefMan\Infrastructure\Database\Database;
use DefMan\Repository\AnalysisRepository;
use DefMan\Repository\DefectRepository;
use DefMan\Repository\ProductRepository;
use DefMan\Repository\UserRepository;

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

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $pdo            = Database::getConnection();
    $userRepo       = new UserRepository($pdo);
    $productRepo    = new ProductRepository($pdo);
    $defectRepo     = new DefectRepository($pdo);
    $analysisRepo   = new AnalysisRepository($pdo);
    $rbac           = new RbacMiddleware($userRepo);

    $authCtrl       = new AuthController($userRepo, $rbac);
    $userCtrl       = new UserController($userRepo, $rbac);
    $defectCtrl     = new DefectController($defectRepo, $analysisRepo, $rbac);
    $productCtrl    = new ProductController($productRepo, $rbac);

    $method = $_SERVER['REQUEST_METHOD'];
    $uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
    $rawBody = file_get_contents('php://input');
    $body   = $rawBody ? (json_decode($rawBody, true) ?? []) : [];

    $response = match (true) {
        // Auth & Registration
        $method === 'POST' && $uri === '/api/auth/login'         => $authCtrl->login($body),
        $method === 'POST' && $uri === '/api/auth/logout'        => $authCtrl->logout(),
        $method === 'POST' && $uri === '/api/auth/apply'         => $authCtrl->apply($body),
        $method === 'POST' && $uri === '/api/auth/lost-password' => $authCtrl->lostPassword($body),

        // User Profile (Level 2+)
        $method === 'GET'  && $uri === '/api/users/me'           => $userCtrl->getMe(),
        $method === 'PUT'  && $uri === '/api/users/me'           => $userCtrl->updateMe($body),

        // Admin User Approval & Management (Level 4)
        $method === 'GET'    && $uri === '/api/admin/users'      => $userCtrl->listUsers(),
        $method === 'POST'   && $uri === '/api/admin/users'      => $userCtrl->createUser($body),
        $method === 'PATCH'  && preg_match('#^/api/admin/users/(\d+)$#', $uri, $m) === 1 => $userCtrl->updateUserStatus((int) $m[1], $body),
        $method === 'DELETE' && preg_match('#^/api/admin/users/(\d+)$#', $uri, $m) === 1 => $userCtrl->deleteUser((int) $m[1]),

        // Products (Public GET, Level 4 Admin POST/DELETE)
        $method === 'GET'    && ($uri === '/api/products' || $uri === '/api/admin/products') => $productCtrl->listProducts(),
        $method === 'POST'   && $uri === '/api/admin/products'   => $productCtrl->createProduct($body),
        $method === 'DELETE' && preg_match('#^/api/admin/products/(\d+)$#', $uri, $m) === 1 => $productCtrl->deleteProduct((int) $m[1]),

        // Public & Customer Defect Reporting
        $method === 'POST'   && $uri === '/api/defects/public'   => $defectCtrl->createPublicReport($body),
        $method === 'POST'   && $uri === '/api/defects'          => $defectCtrl->createCustomerReport($body),
        $method === 'GET'    && $uri === '/api/defects'          => $defectCtrl->listDefects($_GET),
        $method === 'GET'    && preg_match('#^/api/defects/(\d+)$#', $uri, $m) === 1 => $defectCtrl->getDefect((int) $m[1]),
        $method === 'PUT'    && preg_match('#^/api/defects/(\d+)$#', $uri, $m) === 1 => $defectCtrl->updateDefectReport((int) $m[1], $body),
        $method === 'DELETE' && preg_match('#^/api/defects/(\d+)$#', $uri, $m) === 1 => $defectCtrl->deleteDefect((int) $m[1]),

        // Analyzer Triage, IEEE 1992 Classification & Cost Resolution (Level 3+)
        $method === 'GET'    && $uri === '/api/analyzer/queue'   => $defectCtrl->getAnalyzerQueue($_GET),
        $method === 'PUT'    && preg_match('#^/api/defects/(\d+)/analyze$#', $uri, $m) === 1 => $defectCtrl->analyzeDefect((int) $m[1], $body),
        $method === 'PUT'    && preg_match('#^/api/defects/(\d+)/resolve$#', $uri, $m) === 1 => $defectCtrl->resolveDefect((int) $m[1], $body),

        default => (static function (): array {
            http_response_code(404);
            return ['error' => 'Endpoint not found.'];
        })(),
    };

    echo json_encode($response, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error'   => 'Internal Server Error',
        'details' => $e->getMessage(),
    ]);
}
