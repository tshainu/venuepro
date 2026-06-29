<?php
class Helper {

    public static function formatCurrency($amount) {
        return 'Rs. ' . number_format((float)$amount, 2);
    }

    public static function formatDate($date) {
        if (!$date || $date === '0000-00-00') return '-';
        return date('d M Y', strtotime($date));
    }

    public static function formatDateTime($dt) {
        if (!$dt) return '-';
        return date('d M Y, h:i A', strtotime($dt));
    }

    public static function generateRef($prefix, $table, $col, $branch_id = null) {
        $db = Database::getInstance();
        $year = date('Y');
        $like = $prefix . '-' . $year . '-%';
        $row = $db->fetchOne("SELECT MAX($col) as last FROM $table WHERE $col LIKE ?", [$like]);
        $last = $row['last'] ?? null;
        $num = 1;
        if ($last) {
            $parts = explode('-', $last);
            $num = (int)end($parts) + 1;
        }
        return $prefix . '-' . $year . '-' . str_pad($num, 5, '0', STR_PAD_LEFT);
    }

    public static function statusBadge($status) {
        $map = [
            'inquiry'    => 'secondary',
            'tentative'  => 'warning',
            'booked'     => 'warning',
            'confirmed'  => 'success',
            'completed'  => 'primary',
            'cancelled'  => 'danger',
            'draft'      => 'secondary',
            'sent'       => 'info',
            'accepted'   => 'success',
            'rejected'   => 'danger',
            'expired'    => 'dark',
            'paid'       => 'success',
            'partial'    => 'warning',
            'overdue'    => 'danger',
            'advance'    => 'info',
            'interim'    => 'warning',
            'final'      => 'success',
            'available'  => 'success',
            'reserved'   => 'warning',
            'occupied'   => 'primary',
            'maintenance'=> 'danger',
        ];
        $color = $map[$status] ?? 'secondary';
        // Use custom pill style for all statuses
        $customClass = 'badge-' . $status;
        $customStatuses = ['inquiry','tentative','booked','confirmed','completed','cancelled',
                           'draft','sent','partial','overdue','paid',
                           'advance','interim','final',
                           'available','reserved','occupied','maintenance'];
        if (in_array($status, $customStatuses)) {
            return '<span class="badge ' . $customClass . '" style="font-size:.7rem;padding:.3rem .65rem;border-radius:20px;font-weight:600;">' . ucfirst(str_replace('_', ' ', $status)) . '</span>';
        }
        return '<span class="badge bg-' . $color . '">' . ucfirst(str_replace('_', ' ', $status)) . '</span>';
    }

    public static function flash($type, $message) {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    public static function getFlash() {
        if (isset($_SESSION['flash'])) {
            $f = $_SESSION['flash'];
            unset($_SESSION['flash']);
            return $f;
        }
        return null;
    }

    public static function sanitize($str) {
        return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
    }

    public static function redirect($url) {
        header('Location: ' . $url);
        exit;
    }

    public static function paginate($total, $page, $per_page = PER_PAGE) {
        $pages = ceil($total / $per_page);
        return [
            'total'    => $total,
            'page'     => $page,
            'per_page' => $per_page,
            'pages'    => $pages,
            'offset'   => ($page - 1) * $per_page,
        ];
    }

    public static function getSetting($key, $branch_id = null) {
        $db = Database::getInstance();
        $bid = $branch_id ?? Auth::branchId() ?? 1;
        $row = $db->fetchOne(
            "SELECT setting_value FROM settings WHERE setting_key = ? AND (branch_id = ? OR branch_id IS NULL) ORDER BY branch_id DESC LIMIT 1",
            [$key, $bid]
        );
        return $row ? $row['setting_value'] : null;
    }

    public static function isAjax() {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    public static function jsonResponse($data, $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
