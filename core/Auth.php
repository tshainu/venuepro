<?php
class Auth {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function login($email, $password) {
        $user = $this->db->fetchOne(
            "SELECT u.*, r.slug as role_slug, r.name as role_name, b.name as branch_name 
             FROM users u 
             LEFT JOIN roles r ON u.role_id = r.id 
             LEFT JOIN branches b ON u.branch_id = b.id 
             WHERE u.email = ? AND u.is_active = 1",
            [$email]
        );
        if ($user && password_verify($password, $user['password'])) {
            $this->db->execute("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
            $this->setSession($user);
            return true;
        }
        return false;
    }

    public function loginWithUserId($user_id, $username, $password) {
        $user = $this->db->fetchOne(
            "SELECT u.*, r.slug as role_slug, r.name as role_name, b.name as branch_name 
             FROM users u 
             LEFT JOIN roles r ON u.role_id = r.id 
             LEFT JOIN branches b ON u.branch_id = b.id 
             WHERE u.user_id = ? AND u.username = ? AND u.is_active = 1",
            [$user_id, $username]
        );
        if ($user && password_verify($password, $user['password'])) {
            $this->db->execute("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
            $this->setSession($user);
            return true;
        }
        return false;
    }

    private function setSession($user) {
        $_SESSION['user_id']     = $user['id'];
        $_SESSION['user_name']   = $user['name'];
        $_SESSION['user_email']  = $user['email'];
        $_SESSION['user_role']   = $user['role_slug'];
        $_SESSION['user_role_id']= $user['role_id'];
        $_SESSION['branch_id']   = $user['branch_id'];
        $_SESSION['branch_name'] = $user['branch_name'];
        $_SESSION['language']    = $user['language'] ?? 'en';
        $_SESSION['user_uid']      = $user['user_id'] ?? '';
        $_SESSION['user_username'] = $user['username'] ?? '';
    }

    public function logout() {
        session_destroy();
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }

    public static function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public static function check() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . '/login.php');
            exit;
        }
    }

    public static function isSuperAdmin() {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'super_admin';
    }

    public static function hasRole($roles) {
        if (!is_array($roles)) $roles = [$roles];
        return isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], $roles);
    }

    public static function currentUser() {
        return [
            'id'          => $_SESSION['user_id'] ?? null,
            'name'        => $_SESSION['user_name'] ?? '',
            'email'       => $_SESSION['user_email'] ?? '',
            'role'        => $_SESSION['user_role'] ?? '',
            'role_id'     => $_SESSION['user_role_id'] ?? null,
            'branch_id'   => $_SESSION['branch_id'] ?? null,
            'branch_name' => $_SESSION['branch_name'] ?? '',
            'language'    => $_SESSION['language'] ?? 'en',
        ];
    }

    public static function branchId() {
        return $_SESSION['branch_id'] ?? null;
    }
}
