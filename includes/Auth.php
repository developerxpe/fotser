<?php

class Auth {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function login(string $username, string $password): bool {
        $user = $this->db->fetch(
            "SELECT * FROM users WHERE username = ? OR email = ?",
            [$username, $username]
        );

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['logged_in'] = true;
            return true;
        }

        return false;
    }

    public function logout(): void {
        session_unset();
        session_destroy();
    }

    public function isLoggedIn(): bool {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    public function requireLogin(): void {
        if (!$this->isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }

    public function requireAdmin(): void {
        $this->requireLogin();
        if (!$this->isAdmin()) {
            header('Location: index.php');
            exit;
        }
    }

    public function isAdmin(): bool {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }

    public function getCurrentUser(): ?array {
        if (!$this->isLoggedIn()) {
            return null;
        }

        return $this->db->fetch(
            "SELECT id, username, email, role, created_at FROM users WHERE id = ?",
            [$_SESSION['user_id']]
        );
    }

    public function getCurrentUserId(): ?int {
        return $this->isLoggedIn() ? (int)$_SESSION['user_id'] : null;
    }

    public function getUserById(int $id): ?array {
        return $this->db->fetch(
            "SELECT id, username, email, role, created_at FROM users WHERE id = ?",
            [$id]
        );
    }

    public function updatePassword(int $userId, string $newPassword): bool {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $stmt = $this->db->execute(
            "UPDATE users SET password = ? WHERE id = ?",
            [$hashedPassword, $userId]
        );

        return $stmt->rowCount() > 0;
    }
} 