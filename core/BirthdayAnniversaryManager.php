<?php
/**
 * Birthday/Anniversary Tracking & Auto-Message Logic
 * This file contains functions to handle birthday and anniversary tracking
 */

class BirthdayAnniversaryManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Add birthday fields to customer create/edit form
     */
    public function addBirthdayFieldsToCustomerForm() {
        return <<<HTML
        <div class="col-12">
            <div style="display:flex;align-items:center;gap:.5rem;margin:.3rem 0 .8rem;">
              <div style="width:4px;height:16px;background:linear-gradient(180deg,#c9a84c,#e8c96a);border-radius:2px;"></div>
              <span style="font-size:.72rem;font-weight:800;color:#6b7280;text-transform:uppercase;letter-spacing:.08em;">Birthday & Anniversary</span>
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label">Birthday (Day & Month)</label>
            <input type="text" name="birthday" id="birthday_input" class="form-control" placeholder="MM-DD (e.g., 05-15)" value="<?= htmlspecialchars($_POST['birthday'] ?? '') ?>">
            <small class="text-muted">Format: MM-DD (Month-Day only)</small>
        </div>
        <div class="col-md-4">
            <label class="form-label">Anniversary Date</label>
            <input type="text" name="anniversary" id="anniversary_input" class="form-control" placeholder="YYYY-MM-DD" value="<?= htmlspecialchars($_POST['anniversary'] ?? '') ?>">
            <small class="text-muted">Full anniversary date</small>
        </div>
        <div class="col-md-4">
            <label class="form-label">Send Birthday Messages?</label>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="send_birthday_msg" id="send_birthday_msg" <?= ($_POST['send_birthday_msg'] ?? false) ? 'checked' : '' ?>>
                <label class="form-check-label" for="send_birthday_msg">
                    Enable auto birthday message
                </label>
            </div>
        </div>
HTML;
    }
    
    /**
     * Add birthday fields to booking form
     */
    public function addBirthdayFieldsToBookingForm() {
        return <<<HTML
        <!-- Birthday/Anniversary Section -->
        <div class="evt-names-panel" id="birthday-names-panel" style="display:none;">
          <div class="evt-names-panel-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 7v5l3 3"/></svg>
            Birthday & Anniversary Dates <span style="font-weight:500;color:#b08030;">(optional)</span>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">🎂 Birthday Person's Birthday</label>
              <input type="text" name="birthday_person_dob" class="form-control" placeholder="MM-DD (e.g., 05-15)" value="<?= htmlspecialchars($_POST['birthday_person_dob'] ?? '') ?>">
              <small class="text-muted">Day and month only for annual reminders</small>
            </div>
            <div class="col-md-6">
              <label class="form-label">💍 Wedding Anniversary</label>
              <input type="text" name="wedding_anniversary" class="form-control" placeholder="YYYY-MM-DD" value="<?= htmlspecialchars($_POST['wedding_anniversary'] ?? '') ?>">
              <small class="text-muted">Full date for anniversary tracking</small>
            </div>
          </div>
        </div>
HTML;
    }
    
    /**
     * Validate birthday format (MM-DD)
     */
    public function validateBirthdayFormat($birthday) {
        if (empty($birthday)) return true; // Optional field
        if (!preg_match('/^\d{2}-\d{2}$/', $birthday)) {
            return false;
        }
        list($month, $day) = explode('-', $birthday);
        $month = (int)$month;
        $day = (int)$day;
        return $month >= 1 && $month <= 12 && $day >= 1 && $day <= 31;
    }
    
    /**
     * Get upcoming birthdays (next 30 days)
     */
    public function getUpcomingBirthdays($branch_id = null) {
        $query = "SELECT id, name, mobile, email, birthday FROM customers WHERE birthday IS NOT NULL AND birthday != ''";
        $params = [];
        
        if ($branch_id) {
            $query .= " AND branch_id = ?";
            $params[] = $branch_id;
        }
        
        $customers = $this->db->fetchAll($query, $params);
        $upcoming = [];
        
        $today = date('m-d');
        foreach ($customers as $cust) {
            $custBday = $cust['birthday']; // MM-DD format
            // Check if birthday is within next 30 days
            if ($this->isUpcomingDate($custBday, 30)) {
                $upcoming[] = $cust;
            }
        }
        
        return $upcoming;
    }
    
    /**
     * Get upcoming anniversaries (next 30 days)
     */
    public function getUpcomingAnniversaries($branch_id = null) {
        $query = "SELECT b.id, c.name, c.mobile, c.email, b.bride_name, b.groom_name, b.wedding_anniversary 
                  FROM bookings b 
                  JOIN customers c ON c.id = b.customer_id 
                  WHERE b.wedding_anniversary IS NOT NULL AND b.wedding_anniversary != '' AND b.status = 'confirmed'";
        $params = [];
        
        if ($branch_id) {
            $query .= " AND b.branch_id = ?";
            $params[] = $branch_id;
        }
        
        $bookings = $this->db->fetchAll($query, $params);
        $upcoming = [];
        
        foreach ($bookings as $booking) {
            // Check if anniversary is within next 30 days (this year)
            if ($this->isUpcomingAnniversary($booking['wedding_anniversary'], 30)) {
                $upcoming[] = $booking;
            }
        }
        
        return $upcoming;
    }
    
    /**
     * Check if a date (MM-DD format) is upcoming within X days
     */
    private function isUpcomingDate($mmdd, $daysAhead = 30) {
        if (!preg_match('/^\d{2}-\d{2}$/', $mmdd)) return false;
        
        list($month, $day) = explode('-', $mmdd);
        $today = new DateTime();
        $upcoming = new DateTime($today->format('Y') . '-' . $month . '-' . $day);
        
        // If date has passed this year, check next year
        if ($upcoming < $today) {
            $upcoming = new DateTime(($today->format('Y') + 1) . '-' . $month . '-' . $day);
        }
        
        $diff = $today->diff($upcoming)->days;
        return $diff >= 0 && $diff <= $daysAhead;
    }
    
    /**
     * Check if an anniversary (YYYY-MM-DD format) is upcoming within X days
     */
    private function isUpcomingAnniversary($fullDate, $daysAhead = 30) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fullDate)) return false;
        
        list($year, $month, $day) = explode('-', $fullDate);
        $today = new DateTime();
        $anniversary = new DateTime($today->format('Y') . '-' . $month . '-' . $day);
        
        // If anniversary has passed this year, check next year
        if ($anniversary < $today) {
            $anniversary = new DateTime(($today->format('Y') + 1) . '-' . $month . '-' . $day);
        }
        
        $diff = $today->diff($anniversary)->days;
        return $diff >= 0 && $diff <= $daysAhead;
    }
    
    /**
     * Send birthday message
     */
    public function sendBirthdayMessage($customer_id, $customer_name, $mobile, $email) {
        // This would integrate with your messaging system (SMS, Email, etc.)
        $message = "Happy Birthday {$customer_name}! 🎂\n\nWishing you a wonderful day filled with joy and celebrations!";
        
        // Log the message for tracking
        $this->db->insert(
            "INSERT INTO birthday_messages (customer_id, message_type, recipient_mobile, recipient_email, message_text, sent_date) VALUES (?, ?, ?, ?, ?, NOW())",
            [$customer_id, 'birthday', $mobile, $email, $message]
        );
        
        // TODO: Implement actual SMS/Email sending here
        return true;
    }
    
    /**
     * Send anniversary message
     */
    public function sendAnniversaryMessage($booking_id, $couple_names, $mobile, $email) {
        $message = "Happy Anniversary {$couple_names}! 💍\n\nWishing you continued love and happiness!";
        
        // Log the message for tracking
        $this->db->insert(
            "INSERT INTO birthday_messages (booking_id, message_type, recipient_mobile, recipient_email, message_text, sent_date) VALUES (?, ?, ?, ?, ?, NOW())",
            [$booking_id, 'anniversary', $mobile, $email, $message]
        );
        
        // TODO: Implement actual SMS/Email sending here
        return true;
    }
}

?>
