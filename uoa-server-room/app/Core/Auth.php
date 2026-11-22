<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

final class Auth
{
    public static function attempt(string $email, string $password): bool
    {
        $stmt = Database::connection()->prepare(
            "SELECT id, name, email, password_hash, status
             FROM users WHERE email = :email AND deleted_at IS NULL LIMIT 1"
        );
        $stmt->execute(['email' => mb_strtolower(trim($email))]);
        $user = $stmt->fetch();

        if (!$user || $user['status'] !== 'ACTIVE' || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
        ];

        Database::connection()->prepare('UPDATE users SET last_login_at = UTC_TIMESTAMP() WHERE id = :id')
            ->execute(['id' => $user['id']]);

        return true;
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function logout(): void
    {
        unset($_SESSION['user']);
        session_regenerate_id(true);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: ?page=login');
            exit;
        }
    }
}
