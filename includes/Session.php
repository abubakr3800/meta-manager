<?php
require_once __DIR__ . '/../config/database.php';

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
            session_start();
        }
    }

    public static function requireLogin(): int
    {
        self::start();
        if (empty($_SESSION['admin_user_id'])) {
            app_redirect('login.php');
        }
        return (int)$_SESSION['admin_user_id'];
    }

    public static function login(int $adminUserId): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['admin_user_id'] = $adminUserId;
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        session_destroy();
    }

    public static function currentUserId(): ?int
    {
        self::start();
        return isset($_SESSION['admin_user_id']) ? (int)$_SESSION['admin_user_id'] : null;
    }

    /** Returns the identity (Meta login) currently active for this admin, if any. */
    public static function currentIdentityId(): ?int
    {
        self::start();
        $adminId = self::currentUserId();
        if (!$adminId) {
            return null;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT id FROM meta_identities WHERE admin_user_id = :a ORDER BY updated_at DESC LIMIT 1'
        );
        $stmt->execute(['a' => $adminId]);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
    }
}
