<?php
declare(strict_types=1);

namespace DefMan\Repository;

use DefMan\Domain\Enum\UserLevel;
use PDO;

/**
 * User, Company, Session & Password Reset Repository (3NF Schema)
 * Refactored from: db.php, login.php, logout.php, form_apply.php, form_newuser.php,
 *                  form_lostpassword.php, form_userdetails.php, form_usermanagement.php
 */
final readonly class UserRepository
{
    public function __construct(private PDO $pdo) {}

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                u.`id`,
                u.`login`,
                u.`password`,
                u.`email`,
                u.`userlevel`,
                u.`company_id`,
                c.`name` AS `company`,
                u.`phone`,
                u.`name`,
                u.`activate`,
                CASE WHEN EXISTS (
                    SELECT 1 FROM `user_sessions` s
                    WHERE s.`user_id` = u.`id` AND s.`expires_at` > NOW()
                ) THEN 1 ELSE 0 END AS `logged`
             FROM `users` u
             LEFT JOIN `companies` c ON c.`id` = u.`company_id`
             WHERE u.`id` = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findActiveByLogin(string $login): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                u.`id`,
                u.`login`,
                u.`password`,
                u.`email`,
                u.`userlevel`,
                u.`company_id`,
                c.`name` AS `company`,
                u.`phone`,
                u.`name`,
                u.`activate`,
                CASE WHEN EXISTS (
                    SELECT 1 FROM `user_sessions` s
                    WHERE s.`user_id` = u.`id` AND s.`expires_at` > NOW()
                ) THEN 1 ELSE 0 END AS `logged`
             FROM `users` u
             LEFT JOIN `companies` c ON c.`id` = u.`company_id`
             WHERE u.`login` = :login AND u.`activate` = 1
             LIMIT 1'
        );
        $stmt->execute([':login' => $login]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findActiveByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                u.`id`,
                u.`login`,
                u.`password`,
                u.`email`,
                u.`userlevel`,
                u.`company_id`,
                c.`name` AS `company`,
                u.`phone`,
                u.`name`,
                u.`activate`,
                CASE WHEN EXISTS (
                    SELECT 1 FROM `user_sessions` s
                    WHERE s.`user_id` = u.`id` AND s.`expires_at` > NOW()
                ) THEN 1 ELSE 0 END AS `logged`
             FROM `users` u
             LEFT JOIN `companies` c ON c.`id` = u.`company_id`
             WHERE u.`email` = :email AND u.`activate` = 1 AND u.`userlevel` > 1
             LIMIT 1'
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
     * Online status (`logged`) is derived from active rows in `user_sessions`.
     */
    public function listAllByStatus(): array
    {
        $activeStmt = $this->pdo->query(
            'SELECT
                u.`id`,
                u.`login`,
                u.`email`,
                u.`userlevel`,
                u.`company_id`,
                c.`name` AS `company`,
                u.`phone`,
                u.`name`,
                u.`activate`,
                CASE WHEN EXISTS (
                    SELECT 1 FROM `user_sessions` s
                    WHERE s.`user_id` = u.`id` AND s.`expires_at` > NOW()
                ) THEN 1 ELSE 0 END AS `logged`
             FROM `users` u
             LEFT JOIN `companies` c ON c.`id` = u.`company_id`
             WHERE u.`userlevel` > 0
             ORDER BY u.`userlevel` ASC, u.`login` ASC'
        );
        $applyingStmt = $this->pdo->query(
            'SELECT
                u.`id`,
                u.`login`,
                u.`email`,
                u.`userlevel`,
                u.`company_id`,
                c.`name` AS `company`,
                u.`phone`,
                u.`name`,
                u.`activate`,
                0 AS `logged`
             FROM `users` u
             LEFT JOIN `companies` c ON c.`id` = u.`company_id`
             WHERE u.`userlevel` = 0
             ORDER BY u.`id` ASC'
        );

        return [
            'users'      => $activeStmt->fetchAll(),
            'applicants' => $applyingStmt->fetchAll(),
        ];
    }

    /**
     * Resolves a company name to `companies.id` (3NF lookup table), creating it if needed
     */
    public function resolveCompanyId(?string $company): ?int
    {
        $trimmed = trim((string) $company);
        if ($trimmed === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO `companies` (`name`) VALUES (:name)
             ON DUPLICATE KEY UPDATE `id` = LAST_INSERT_ID(`id`)'
        );
        $stmt->execute([':name' => $trimmed]);
        return (int) $this->pdo->lastInsertId();
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
        $companyId = $this->resolveCompanyId($company);

        $stmt = $this->pdo->prepare(
            'INSERT INTO `users` (`login`, `password`, `email`, `userlevel`, `company_id`, `phone`, `name`, `activate`)
             VALUES (:login, :password, :email, :userlevel, :company_id, :phone, :name, :activate)'
        );
        $stmt->execute([
            ':login'      => $login,
            ':password'   => password_hash($plainPassword, PASSWORD_ARGON2ID),
            ':email'      => $email,
            ':userlevel'  => $userLevel->value,
            ':company_id' => $companyId,
            ':phone'      => $phone !== null && trim($phone) !== '' ? trim($phone) : null,
            ':name'       => $name !== null && trim($name) !== '' ? trim($name) : null,
            ':activate'   => $activate ? 1 : 0,
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
     * Preserves reporter identity on `defect_reports` in `guest_name` and `guest_email`
     * prior to deletion so `chk_defect_reports_reporter_identity` is always satisfied.
     */
    public function delete(int $id): bool
    {
        $this->pdo->beginTransaction();
        try {
            $preserveStmt = $this->pdo->prepare(
                'UPDATE `defect_reports` dr
                 INNER JOIN `users` u ON u.`id` = dr.`reporter_user_id`
                 SET dr.`guest_name`       = COALESCE(dr.`guest_name`, u.`login`),
                     dr.`guest_email`      = COALESCE(dr.`guest_email`, u.`email`),
                     dr.`reporter_user_id` = NULL
                 WHERE dr.`reporter_user_id` = :id
                   AND u.`userlevel` != :admin'
            );
            $preserveStmt->execute([
                ':id'    => $id,
                ':admin' => UserLevel::ADMIN->value,
            ]);

            $stmt = $this->pdo->prepare('DELETE FROM `users` WHERE `id` = :id AND `userlevel` != :admin');
            $stmt->execute([
                ':id'    => $id,
                ':admin' => UserLevel::ADMIN->value,
            ]);
            $deleted = $stmt->rowCount() > 0;
            $this->pdo->commit();
            return $deleted;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function updatePassword(int $userId, string $newPlainPassword): void
    {
        $stmt = $this->pdo->prepare('UPDATE `users` SET `password` = :password WHERE `id` = :id');
        $stmt->execute([
            ':password' => password_hash($newPlainPassword, PASSWORD_ARGON2ID),
            ':id'       => $userId,
        ]);

        $clearResets = $this->pdo->prepare('DELETE FROM `password_resets` WHERE `user_id` = :user_id');
        $clearResets->execute([':user_id' => $userId]);
    }

    public function updateEmail(int $userId, string $email): void
    {
        $stmt = $this->pdo->prepare('UPDATE `users` SET `email` = :email WHERE `id` = :id');
        $stmt->execute([':email' => $email, ':id' => $userId]);
    }

    public function updatePersonalDetails(int $userId, string $name, string $company, string $phone): void
    {
        $companyId = $this->resolveCompanyId($company);

        $stmt = $this->pdo->prepare(
            'UPDATE `users` SET `name` = :name, `company_id` = :company_id, `phone` = :phone WHERE `id` = :id'
        );
        $stmt->execute([
            ':name'       => $name !== '' ? $name : null,
            ':company_id' => $companyId,
            ':phone'      => $phone !== '' ? $phone : null,
            ':id'         => $userId,
        ]);
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

        return $rawToken;
    }

    public function findUserBySessionToken(string $rawToken): ?array
    {
        $tokenHash = hash('sha256', $rawToken);
        $stmt = $this->pdo->prepare(
            'SELECT
                u.`id`,
                u.`login`,
                u.`email`,
                u.`userlevel`,
                u.`company_id`,
                c.`name` AS `company`,
                u.`phone`,
                u.`name`,
                u.`activate`,
                1 AS `logged`
             FROM `user_sessions` s
             INNER JOIN `users` u ON u.`id` = s.`user_id`
             LEFT JOIN `companies` c ON c.`id` = u.`company_id`
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
        $stmt = $this->pdo->prepare(
            'DELETE FROM `user_sessions` WHERE `token_hash` = :token_hash AND `user_id` = :user_id'
        );
        $stmt->execute([
            ':token_hash' => $tokenHash,
            ':user_id'    => $userId,
        ]);
    }

    public function createPasswordResetToken(int $userId): string
    {
        $rawToken  = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = (new \DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            'INSERT INTO `password_resets` (`user_id`, `token_hash`, `expires_at`)
             VALUES (:user_id, :token_hash, :expires_at)'
        );
        $stmt->execute([
            ':user_id'    => $userId,
            ':token_hash' => $tokenHash,
            ':expires_at' => $expiresAt,
        ]);

        return $rawToken;
    }
}
