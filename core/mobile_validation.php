<?php
/**
 * Mobile Number Validation Helper
 * Validates Sri Lankan mobile numbers (10 digits, starts with 07)
 */

class MobileValidator {
    
    /**
     * Validate mobile number format
     * @param string $mobile Mobile number to validate
     * @return bool True if valid, false otherwise
     */
    public static function validate($mobile) {
        if (empty($mobile)) {
            return false;
        }
        
        // Remove any non-digit characters
        $cleaned = preg_replace('/\D/', '', $mobile);
        
        // Check if it's exactly 10 digits and starts with 07
        return preg_match('/^07\d{8}$/', $cleaned) === 1;
    }
    
    /**
     * Format mobile number to standard format
     * @param string $mobile Mobile number to format
     * @return string Formatted mobile number or original if invalid
     */
    public static function format($mobile) {
        $cleaned = preg_replace('/\D/', '', $mobile);
        
        if (self::validate($cleaned)) {
            return $cleaned;
        }
        
        return $mobile;
    }
    
    /**
     * Get validation error message
     * @return string Error message
     */
    public static function getErrorMessage() {
        return 'Mobile number must be 10 digits and start with 07 (e.g., 0712345678)';
    }
    
    /**
     * Validate and return error if invalid
     * @param string $mobile Mobile number to validate
     * @return array ['valid' => bool, 'message' => string]
     */
    public static function validateWithMessage($mobile) {
        if (empty($mobile)) {
            return [
                'valid' => false,
                'message' => 'Mobile number is required'
            ];
        }
        
        if (!self::validate($mobile)) {
            return [
                'valid' => false,
                'message' => self::getErrorMessage()
            ];
        }
        
        return [
            'valid' => true,
            'message' => 'Mobile number is valid'
        ];
    }
}

// Usage example:
// $validation = MobileValidator::validateWithMessage($_POST['mobile']);
// if (!$validation['valid']) {
//     echo $validation['message'];
// }
?>
