<?php
declare(strict_types=1);

namespace DefMan\Repository;

use DefMan\Domain\Enum\UserLevel;
use PDO;

/**
 * User & Session Repository
 * Refactored from: db.php, login.php, logout.php, form_apply.php, form_newuser.php,
 *                  form_lostpassword.php, form_userdetails.php, form_usermanagement.php
 */
final readonly class UserRepository
{
    public function __construct(private PDO $pdo) {}

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT `id`, `login`, `password`, `email`, `userlevel`, `company`, `phone`, `name`, `activate`, `logged`
             FROM `users` WHERE `id` = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findActiveByLogin(string $login): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT `id`, `login`, `password`, `email`, `userlevel`, `company`, `phone`, `name`, `activate`, `logged`
             FROM `users` WHERE `login` = :login AND `activate` = 1 LIMIT 1'
        );
        $stmt->execute([':login' => $login]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findActiveByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT `id`, `login`, `password`, `email`, `userlevel`, `company`, `phone`, `name`, `activate`, `logged`
             FROM `users` WHERE `email` = :email AND `activate` = 1 AND `userlevel` > 1 LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function existsByEmail(string $email, ?int $excludeUserId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM `users` WHERE `email` = :email';
        $params = [':email' => $email];
        if ($excludeUserId !== null) {
            $sql .= ' AND `id` != :exclude_id';
            $params[':exclude_id'] = $excludeUserId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function existsByLogin(string $login): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM `users` WHERE `login` = :login');
        $stmt->execute([':login' => $login]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Lists approved users (userlevel > 0) and pending applicants (userlevel = 0)
     * Refactored from: form_usermanagement.php
     */
    public function listAllByStatus(): array
    {
        $activeStmt = $this->pdo->query(
            'SELECT `id`, `login`, `email`, `userlevel`, `company`, `phone`, `name`, `activate`, `logged`
             FROM `users` WHERE `userlevel` > 0 ORDER BY `userlevel` ASC, `login` ASC'
        );
        $applyingStmt = $this->pdo->query(
            'SELECT `id`, `login`, `email`, `userlevel`, `company`, `phone`, `name`, `activate`, `logged`
             FROM `users` WHERE `userlevel` = 0 ORDER BY `id` ASC'
        );

        return [
            'users'      => $activeStmt->fetchAll(),
            'applicants' => $applyingStmt->fetchAll(),
        ];
    }

    /**
     * Creates a new user or applicant
     * Refactored from: form_apply.php (userlevel=0, activate=0) & form_newuser.php (activate=1)
     */
    public function create(
        string $login,
        string $plainPassword,
        string $email,
        UserLevel $userLevel,
        ?string $company = null,
        ?string $phone = null,
        ?string $name = null,
        bool $activate = false
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO `users` (`login`, `password`, `email`, `userlevel`, `company`, `phone`, `name`, `activate`, `logged`)
             VALUES (:login, :password, :email, :userlevel, :company, :phone, :name, :activate, 0)'
        );
        $stmt->execute([
            ':login'     => $login,
            ':password'  => password_hash($plainPassword, PASSWORD_ARGON2ID),
            ':email'     => $email,
            ':userlevel' => $userLevel->value,
            ':company'   => $company,
            ':phone'     => $phone,
            ':name'      => $name,
            ':activate'  => $activate ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Admin approval of pending user application (db.php?function=accept)
     */
    public function approveApplicant(int $id, UserLevel $assignedLevel = UserLevel::CUSTOMER): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE `users` SET `userlevel` = :level, `activate` = 1 WHERE `id` = :id'
        );
        $stmt->execute([
            ':level' => $assignedLevel->value,
            ':id'    => $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function updateRole(int $id, UserLevel $level): bool
    {
        $stmt = $this->pdo->prepare('UPDATE `users` SET `userlevel` = :level WHERE `id` = :id');
        $stmt->execute([':level' => $level->value, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Deletes a non-admin user or declines an applicant (db.php?function=delete)
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM `users` WHERE `id` = :id AND `userlevel` != :admin');
        $stmt->execute([
            ':id'    => $id,
            ':admin' => UserLevel::ADMIN->value,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function updatePassword(int $userId, string $newPlainPassword): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE `users` SET `password` = :password, `reset_token_hash` = NULL, `reset_token_expires_at` = NULL
             WHERE `id` = :id'
        );
        $stmt->execute([
            ':password' => password_hash($newPlainPassword, PASSWORD_ARGON2ID),
            ':id'       => $userId,
        ]);
    }

    public function updateEmail(int $userId, string $email): void
    {
        $stmt = $this->pdo->prepare('UPDATE `users` SET `email` = :email WHERE `id` = :id');
        $stmt->execute([':email' => $email, ':id' => $userId]);
    }

    public function updatePersonalDetails(int $userId, string $name, string $company, string $phone): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE `users` SET `name` = :name, `company` = :company, `phone` = :phone WHERE `id` = :id'
        );
        $stmt->execute([
            ':name'    => $name,
            ':company' => $company,
            ':phone'   => $phone,
            ':id'      => $userId,
        ]);
    }

    public function setLoggedStatus(int $userId, bool $logged): void
    {
        $stmt = $this->pdo->prepare('UPDATE `users` SET `logged` = :logged WHERE `id` = :id');
        $stmt->execute([':logged' => $logged ? 1 : 0, ':id' => $userId]);
    }

    public function createSessionToken(int $userId, ?string $ipAddress, ?string $userAgent): string
    {
        $rawToken  = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = (new \DateTimeImmutable('+12 hours'))->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            'INSERT INTO `user_sessions` (`user_id`, `token_hash`, `ip_address`, `user_agent`, `expires_at`)
             VALUES (:user_id, :token_hash, :ip_address, :user_agent, :expires_at)'
        );
        $stmt->execute([
            ':user_id'    => $userId,
            ':token_hash' => $tokenHash,
            ':ip_address' => $ipAddress,
            ':user_agent' => $userAgent ? substr($userAgent, 0, 255) : null,
            ':expires_at' => $expiresAt,
        ]);

        $this->setLoggedStatus($userId, true);
        return $rawToken;
    }

    public function findUserBySessionToken(string $rawToken): ?array
    {
        $tokenHash = hash('sha256', $rawToken);
        $stmt = $this->pdo->prepare(
            'SELECT u.`id`, u.`login`, u.`email`, u.`userlevel`, u.`company`, u.`phone`, u.`name`, u.`activate`, u.`logged`
             FROM `user_sessions` s
             INNER JOIN `users` u ON u.`id` = s.`user_id`
             WHERE s.`token_hash` = :token_hash
               AND s.`expires_at` > NOW()
               AND u.`activate` = 1
             LIMIT 1'
        );
        $stmt->execute([':token_hash' => $tokenHash]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function revokeSessionToken(string $rawToken, int $userId): void
    {
        $tokenHash = hash('sha256', $rawToken);
        $stmt = $this->pdo->prepare('DELETE FROM `user_sessions` WHERE `token_hash` = :token_hash');
        $stmt->execute([':token_hash' => $tokenHash]);
        $this->setLoggedStatus($userId, false);
    }

    public function createPasswordResetToken(int $userId): string
    {
        $rawToken  = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = (new \DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            'UPDATE `users` SET `reset_token_hash` = :hash, `reset_token_expires_at` = :expires WHERE `id` = :id'
        );
        $stmt->execute([
            ':hash'    => $tokenHash,
            ':expires' => $expiresAt,
            ':id'      => $userId,
        ]);

        return $rawToken;
    }
}
