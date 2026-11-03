<?php
declare(strict_types=1);

namespace DefMan\Http\Middleware;

use DefMan\Domain\Enum\UserLevel;
use DefMan\Repository\UserRepository;

/**
 * RBAC Authentication & Authorization Middleware
 * Enforces the 4 DefMan user levels:
 *   - Level 1 (Public):   Unauthenticated / public defect submission
 *   - Level 2 (Customer): Authenticated user tracking their own submitted defects
 *   - Level 3 (Analyzer): Developer/QA triage, IEEE fault classification, fix & cost tracking
 *   - Level 4 (Admin):    User registration approval queue, role assignment, product management
 */
final readonly class RbacMiddleware
{
    public function __construct(private UserRepository $userRepository) {}

    public function extractBearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+([a-f0-9]{64})$/i', $header, $matches)) {
            return $matches[1];
        }
        return $_COOKIE['defman_token'] ?? null;
    }

    public function authenticateOptional(): ?array
    {
        $token = $this->extractBearerToken();
        if ($token === null) {
            return null;
        }
        return $this->userRepository->findUserBySessionToken($token);
    }

    public function requireLevel(UserLevel $minimumLevel): array
    {
        if ($minimumLevel === UserLevel::PUBLIC) {
            return $this->authenticateOptional() ?? [
                'id'        => null,
                'login'     => 'public',
                'userlevel' => UserLevel::PUBLIC->value,
            ];
        }

        $user = $this->authenticateOptional();
        if ($user === null) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Authentication required.']);
            exit;
        }

        $userLevel = (int) $user['userlevel'];
        if ($userLevel < $minimumLevel->value) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'error'          => 'Insufficient permissions.',
                'required_level' => $minimumLevel->label(),
                'current_level'  => UserLevel::tryFrom($userLevel)?->label() ?? 'Unknown',
            ]);
            exit;
        }

        return $user;
    }
}
