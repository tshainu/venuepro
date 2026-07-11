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
            // Log after setSession so $_SESSION is populated
            Logger::log('login', 'users', $user['id'], $user['username'], null, null, "User {$user['name']} logged in");
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
            Logger::log('login', 'users', $user['id'], $user['username'], null, null, "User {$user['name']} logged in");
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
        $_SESSION['language']    = $user['language'] ?? 'en';
        $_SESSION['user_uid']      = $user['user_id'] ?? '';
        $_SESSION['user_username'] = $user['username'] ?? '';

        // Handle multiple accessible branches
        $accessible_branches = [];
        $primary_branch_id = $user['branch_id'];
        $primary_branch_name = $user['branch_name'];

        if ($user['id']) {
            $branches = $this->db->fetchAll(
                "SELECT b.id, b.name FROM user_accessible_branches uab JOIN branches b ON uab.branch_id = b.id WHERE uab.user_id = ?",
                [$user['id']]
            );
            if (!empty($branches)) {
                $accessible_branches = $branches;
                if (!$primary_branch_id) {
                    $primary_branch_id = $branches[0]['id'];
                    $primary_branch_name = $branches[0]['name'];
                }
            } else {
                // For owner role: auto-load all branches belonging to their business
                if (!empty($user['user_id'])) {
                    $biz = $this->db->fetchOne(
                        "SELECT id FROM sa_businesses WHERE admin_user_id = ? LIMIT 1",
                        [$user['user_id']]
                    );
                    if ($biz) {
                        $biz_branches = $this->db->fetchAll(
                            "SELECT id, name FROM branches WHERE business_id = ? AND is_active = 1",
                            [$biz['id']]
                        );
                        if (!empty($biz_branches)) {
                            $accessible_branches = $biz_branches;
                            if (!$primary_branch_id) {
                                $primary_branch_id = $biz_branches[0]['id'];
                                $primary_branch_name = $biz_branches[0]['name'];
                            }
                        }
                    }
                }
            }
        }

        $_SESSION['branch_id']   = $primary_branch_id;
        $_SESSION['branch_name'] = $primary_branch_name;
        $_SESSION['accessible_branches'] = $accessible_branches;

        // Load business name for business admin users (no branch assigned)
        $business_name = '';
        if (empty($user['branch_id']) && !empty($user['user_id'])) {
            $biz = $this->db->fetchOne(
                "SELECT business_name FROM sa_businesses WHERE admin_user_id = ? LIMIT 1",
                [$user['user_id']]
            );
            if ($biz) $business_name = $biz['business_name'];
        }
        $_SESSION['business_name'] = $business_name;
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
        // Re-hydrate session if role or branch_id is stale/missing
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] === '' || !isset($_SESSION['branch_id'])) {
            $db = Database::getInstance();
            $user = $db->fetchOne(
                "SELECT u.*, r.slug as role_slug, r.name as role_name, b.name as branch_name 
                 FROM users u 
                 LEFT JOIN roles r ON u.role_id = r.id 
                 LEFT JOIN branches b ON u.branch_id = b.id 
                 WHERE u.id = ? AND u.is_active = 1",
                [$_SESSION['user_id']]
            );
            if (!$user) {
                session_destroy();
                header('Location: ' . BASE_URL . '/login.php');
                exit;
            }
            $_SESSION['user_role']    = $user['role_slug'];
            $_SESSION['user_role_id'] = $user['role_id'];
            $_SESSION['user_uid']     = $user['user_id'] ?? '';
            $_SESSION['user_username']= $user['username'] ?? '';
            $_SESSION['user_name']    = $user['name'];
            $_SESSION['user_email']   = $user['email'];
            $_SESSION['language']     = $user['language'] ?? 'en';

            $accessible_branches = [];
            $primary_branch_id = $user['branch_id'];
            $primary_branch_name = $user['branch_name'];

            $branches = $db->fetchAll(
                "SELECT b.id, b.name FROM user_accessible_branches uab JOIN branches b ON uab.branch_id = b.id WHERE uab.user_id = ?",
                [$user['id']]
            );
            if (!empty($branches)) {
                $accessible_branches = $branches;
                if (!$primary_branch_id) {
                    $primary_branch_id = $branches[0]['id'];
                    $primary_branch_name = $branches[0]['name'];
                }
            } else {
                // For owner role: auto-load all branches belonging to their business
                if (!empty($user['user_id'])) {
                    $biz = $db->fetchOne(
                        "SELECT id FROM sa_businesses WHERE admin_user_id = ? LIMIT 1",
                        [$user['user_id']]
                    );
                    if ($biz) {
                        $biz_branches = $db->fetchAll(
                            "SELECT id, name FROM branches WHERE business_id = ? AND is_active = 1",
                            [$biz['id']]
                        );
                        if (!empty($biz_branches)) {
                            $accessible_branches = $biz_branches;
                            if (!$primary_branch_id) {
                                $primary_branch_id = $biz_branches[0]['id'];
                                $primary_branch_name = $biz_branches[0]['name'];
                            }
                        }
                    }
                }
            }

            $_SESSION['branch_id']   = $primary_branch_id;
            $_SESSION['branch_name'] = $primary_branch_name;
            $_SESSION['accessible_branches'] = $accessible_branches;
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
            'id'            => $_SESSION['user_id'] ?? null,
            'name'          => $_SESSION['user_name'] ?? '',
            'email'         => $_SESSION['user_email'] ?? '',
            'role'          => $_SESSION['user_role'] ?? '',
            'role_id'       => $_SESSION['user_role_id'] ?? null,
            'branch_id'     => $_SESSION['branch_id'] ?? null,
            'branch_name'   => $_SESSION['branch_name'] ?? '',
            'accessible_branches' => $_SESSION['accessible_branches'] ?? [],
            'business_name' => $_SESSION['business_name'] ?? '',
            'language'      => $_SESSION['language'] ?? 'en',
        ];
    }

    public static function branchId() {
        return $_SESSION['branch_id'] ?? null;
    }

    public static function getAccessibleBranches() {
        return $_SESSION['accessible_branches'] ?? [];
    }
}
