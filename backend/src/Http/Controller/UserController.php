<?php
declare(strict_types=1);

namespace DefMan\Http\Controller;

use DefMan\Domain\Enum\UserLevel;
use DefMan\Http\Middleware\RbacMiddleware;
use DefMan\Repository\UserRepository;

/**
 * User & Admin Account Management Controller
 * Refactored from: form_usermanagement.php, form_newuser.php, form_userdetails.php, db.php
 */
final readonly class UserController
{
    public function __construct(
        private UserRepository $userRepository,
        private RbacMiddleware $rbac
    ) {}

    /**
     * GET /api/admin/users (Refactored from form_usermanagement.php)
     */
    public function listUsers(): array
    {
        $this->rbac->requireLevel(UserLevel::ADMIN);
        $lists = $this->userRepository->listAllByStatus();
        $lists['generated_password_suggestion'] = AuthController::generateSecurePassword(10);
        return $lists;
    }

    /**
     * POST /api/admin/users (Refactored from form_newuser.php)
     */
    public function createUser(array $body): array
    {
        $this->rbac->requireLevel(UserLevel::ADMIN);

        $username = trim((string) ($body['username'] ?? ''));
        $email    = trim((string) ($body['email'] ?? ''));
        $password = (string) ($body['password'] ?? AuthController::generateSecurePassword(10));
        $levelInt = (int) ($body['userlevel'] ?? UserLevel::CUSTOMER->value);
        $level    = UserLevel::tryFrom($levelInt) ?? UserLevel::CUSTOMER;

        if ($email === '') {
            http_response_code(422);
            return ['error' => 'Empty E-mail!'];
        }
        if ($username === '' || preg_match('/[^A-Za-z0-9]/', $username)) {
            http_response_code(422);
            return ['error' => 'Illegal characters in username or too short username'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(422);
            return ['error' => 'Bad e-mail address.'];
        }
        if ($this->userRepository->existsByEmail($email)) {
            http_response_code(409);
            return ['error' => 'There is user with that e-mail already.'];
        }
        if ($this->userRepository->existsByLogin($username)) {
            http_response_code(409);
            return ['error' => 'There is user with that username already.'];
        }

        $id = $this->userRepository->create(
            login: $username,
            plainPassword: $password,
            email: $email,
            userLevel: $level,
            activate: true
        );

        http_response_code(201);
        return [
            'id'      => $id,
            'message' => 'User added. Mail sent.',
        ];
    }

    /**
     * PATCH /api/admin/users/{id} (Refactored from db.php?function=accept & role assignment)
     */
    public function updateUserStatus(int $id, array $body): array
    {
        $this->rbac->requireLevel(UserLevel::ADMIN);

        $action   = (string) ($body['action'] ?? 'approve');
        $levelInt = (int) ($body['userlevel'] ?? UserLevel::CUSTOMER->value);
        $level    = UserLevel::tryFrom($levelInt) ?? UserLevel::CUSTOMER;

        if ($action === 'approve') {
            $this->userRepository->approveApplicant($id, $level);
            return ['message' => "User #{$id} approved with role {$level->label()}."];
        }

        if ($action === 'role') {
            $this->userRepository->updateRole($id, $level);
            return ['message' => "User #{$id} role updated to {$level->label()}."];
        }

        http_response_code(400);
        return ['error' => 'Unsupported action.'];
    }

    /**
     * DELETE /api/admin/users/{id} (Refactored from db.php?function=delete)
     */
    public function deleteUser(int $id): array
    {
        $this->rbac->requireLevel(UserLevel::ADMIN);
        $deleted = $this->userRepository->delete($id);

        if (!$deleted) {
            http_response_code(400);
            return ['error' => 'Cannot delete Admin user or non-existent user.'];
        }

        return ['message' => "User #{$id} deleted."];
    }

    /**
     * GET /api/users/me (Refactored from form_userdetails.php)
     */
    public function getMe(): array
    {
        $user = $this->rbac->requireLevel(UserLevel::CUSTOMER);
        return [
            'id'        => (int) $user['id'],
            'login'     => ucfirst((string) $user['login']),
            'email'     => $user['email'],
            'name'      => $user['name'] ?? '',
            'company'   => $user['company'] ?? '',
            'phone'     => $user['phone'] ?? '',
            'userlevel' => (int) $user['userlevel'],
        ];
    }

    /**
     * PUT /api/users/me (Refactored from form_userdetails.php: updatepassword, updateemail, updatepersonal)
     */
    public function updateMe(array $body): array
    {
        $user   = $this->rbac->requireLevel(UserLevel::CUSTOMER);
        $userId = (int) $user['id'];
        $section = (string) ($body['section'] ?? 'personal');

        if ($section === 'password') {
            $newPass    = (string) ($body['change_password'] ?? '');
            $verifyPass = (string) ($body['verify_password'] ?? '');

            if ($newPass === '') {
                http_response_code(422);
                return ['error' => 'Empty New Password'];
            }
            if ($verifyPass === '') {
                http_response_code(422);
                return ['error' => 'Empty Verify Password'];
            }
            if ($newPass !== $verifyPass) {
                http_response_code(422);
                return ['error' => 'Passwords do not match'];
            }

            $this->userRepository->updatePassword($userId, $newPass);
            return ['message' => 'Settings changed'];
        }

        if ($section === 'email') {
            $email = trim(strip_tags((string) ($body['email'] ?? '')));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(422);
                return ['error' => 'E-mail not valid!'];
            }
            if ($this->userRepository->existsByEmail($email, $userId)) {
                http_response_code(409);
                return ['error' => 'E-mail already in use!'];
            }

            $this->userRepository->updateEmail($userId, $email);
            return ['message' => 'E-mail changed!'];
        }

        $name    = trim(strip_tags((string) ($body['name'] ?? '')));
        $company = trim(strip_tags((string) ($body['company'] ?? '')));
        $phone   = trim(strip_tags((string) ($body['phone'] ?? '')));

        $this->userRepository->updatePersonalDetails($userId, $name, $company, $phone);
        return ['message' => 'Personal information updated!'];
    }
}
