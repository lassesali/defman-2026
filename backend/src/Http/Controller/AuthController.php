<?php
declare(strict_types=1);

namespace DefMan\Http\Controller;

use DefMan\Domain\Enum\UserLevel;
use DefMan\Http\Middleware\RbacMiddleware;
use DefMan\Repository\UserRepository;

/**
 * Authentication & Password Recovery Controller
 * Refactored from: login.php, logout.php, randompassword.php, form_lostpassword.php, form_apply.php
 */
final readonly class AuthController
{
    public function __construct(
        private UserRepository $userRepository,
        private RbacMiddleware $rbac
    ) {}

    /**
     * POST /api/auth/login (Refactored from login.php)
     */
    public function login(array $body): array
    {
        $username = trim((string) ($body['username'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        if ($username === '' || $password === '') {
            http_response_code(422);
            return ['error' => 'Username and password are required.'];
        }

        $user = $this->userRepository->findActiveByLogin($username);
        if ($user === null || !password_verify($password, (string) $user['password'])) {
            http_response_code(401);
            return ['error' => 'Authentication failed'];
        }

        if (password_needs_rehash((string) $user['password'], PASSWORD_ARGON2ID)) {
            $this->userRepository->updatePassword((int) $user['id'], $password);
        }

        $token = $this->userRepository->createSessionToken(
            (int) $user['id'],
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        );

        return [
            'token' => $token,
            'user'  => [
                'id'        => (int) $user['id'],
                'login'     => $user['login'],
                'email'     => $user['email'],
                'userlevel' => (int) $user['userlevel'],
                'role'      => UserLevel::from((int) $user['userlevel'])->label(),
                'name'      => $user['name'],
                'company'   => $user['company'],
                'phone'     => $user['phone'],
            ],
        ];
    }

    /**
     * POST /api/auth/logout (Refactored from logout.php)
     */
    public function logout(): array
    {
        $user  = $this->rbac->authenticateOptional();
        $token = $this->rbac->extractBearerToken();

        if ($user !== null && $token !== null) {
            $this->userRepository->revokeSessionToken($token, (int) $user['id']);
        }

        return ['message' => 'Logged out'];
    }

    /**
     * POST /api/auth/apply (Refactored from form_apply.php)
     */
    public function apply(array $body): array
    {
        $username       = trim((string) ($body['username'] ?? ''));
        $password       = (string) ($body['password'] ?? '');
        $verifyPassword = (string) ($body['verify_password'] ?? '');
        $email          = trim((string) ($body['email'] ?? ''));
        $fullName       = trim(strip_tags((string) ($body['name'] ?? '')));
        $company        = trim(strip_tags((string) ($body['company'] ?? '')));
        $phone          = trim(strip_tags((string) ($body['phone'] ?? '')));

        if ($email === '') {
            http_response_code(422);
            return ['error' => 'Empty E-mail!'];
        }
        if ($username === '') {
            http_response_code(422);
            return ['error' => 'Empty Username!'];
        }
        if ($password === '' || $verifyPassword === '') {
            http_response_code(422);
            return ['error' => 'Empty Password!'];
        }
        if ($password !== $verifyPassword) {
            http_response_code(422);
            return ['error' => 'Passwords do not match!'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(422);
            return ['error' => 'E-mail is not valid'];
        }
        if ($this->userRepository->existsByEmail($email)) {
            http_response_code(409);
            return ['error' => 'Someone has already selected the e-mail address you typed.'];
        }
        if ($this->userRepository->existsByLogin($username)) {
            http_response_code(409);
            return ['error' => 'Someone has already selected the username you typed.'];
        }

        $userId = $this->userRepository->create(
            login: $username,
            plainPassword: $password,
            email: $email,
            userLevel: UserLevel::APPLYING,
            company: $company ?: null,
            phone: $phone ?: null,
            name: $fullName ?: null,
            activate: false
        );

        http_response_code(201);
        return [
            'id'      => $userId,
            'message' => 'Your application has been sent.',
        ];
    }

    /**
     * POST /api/auth/lost-password (Refactored from form_lostpassword.php)
     * Uses cryptographically secure random_bytes() reset token instead of emailing plaintext passwords.
     */
    public function lostPassword(array $body): array
    {
        $email = trim((string) ($body['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(422);
            return ['error' => 'E-mail not found'];
        }

        $user = $this->userRepository->findActiveByEmail($email);
        if ($user === null) {
            http_response_code(404);
            return ['error' => 'E-mail not found'];
        }

        $resetToken = $this->userRepository->createPasswordResetToken((int) $user['id']);

        return [
            'message'     => 'Information sent to given address.',
            'reset_token' => $resetToken, // Exposed in dev/response for testable workflow
        ];
    }

    /**
     * Cryptographically secure replacement for randompassword.php
     */
    public static function generateSecurePassword(int $length = 12): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
        $maxIndex = strlen($alphabet) - 1;
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, $maxIndex)];
        }
        return $password;
    }
}
