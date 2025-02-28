<?php
/**
 * twofactormanager.class.php
 * 
 * Version: 1.0
 * Date: 28/02/2025
 * 
 * Two-factor authentication manager class
 * Handles 2FA setup, verification, and management
 * 
 * @author Claude
 * @copyright MTG Collection 2025
 */

// Prevent direct access
if (__FILE__ == $_SERVER['PHP_SELF']) :
    die('Direct access prohibited');
endif;

class TwoFactorManager {
    private $db;
    private $logfile;
    private $log;
    private $code_length = 6;
    private $code_expiry = 600; // 10 minutes in seconds
    private $max_attempts = 3;
    private $smtp_parameters;
    private $serveremail;
    
    /**
     * Constructor
     * 
     * @param object $db Database connection
     * @param string $logfile Log file path
     */
    public function __construct($db, $smtpParameters, $serveremail, $logfile = "") {
        $this->db = $db;
        $this->logfile = $logfile;
        $this->smtp_parameters = $smtpParameters;
        $this->serveremail = $serveremail;
        
        // Try to use Message class for logging if available
        if(class_exists('Message')) {
            $this->log = new Message($this->logfile);
        }
    }
    
    /**
     * Log message to file
     * 
     * @param string $message Message to log
     * @param int $level Log level
     * @return void
     */
    private function logIt($message, $level = 3) {
        if($this->log) {
            $this->log->logMessage($message, $level);
        } else if($this->logfile != "") {
            error_log(date("Y-m-d H:i:s")." - ".$message."\n", 3, $this->logfile);
        }
    }
    
    /**
     * Generate a random verification code
     * 
     * @return string Verification code
     */
    private function generateCode() {
        $min = pow(10, $this->code_length - 1);
        $max = pow(10, $this->code_length) - 1;
        return (string)random_int($min, $max);
    }
    
    /**
     * Check if 2FA is enabled for a user
     * 
     * @param int $user_id User ID
     * @return bool True if 2FA is enabled
     */
    public function isEnabled($user_id) {
        if(!$user_id) {
            $this->logIt("TwoFactorManager::isEnabled - Invalid user ID", 2);
            return false;
        }
        
        $query = "SELECT tfa_enabled FROM users WHERE usernumber = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return (bool)$row['tfa_enabled'];
        }
        
        return false;
    }
    
    /**
     * Enable 2FA for a user
     * 
     * @param int $user_id User ID
     * @param string $method 2FA method (email, app)
     * @return bool True on success
     */
    public function enable($user_id, $method = 'email') {
        if(!$user_id) {
            $this->logIt("TwoFactorManager::enable - Invalid user ID", 2);
            return false;
        }
        
        if(!in_array($method, ['email', 'app'])) {
            $this->logIt("TwoFactorManager::enable - Invalid 2FA method: $method", 2);
            return false;
        }
        
        // Generate backup codes
        $backup_codes = $this->generateBackupCodes();
        $backup_json = json_encode($backup_codes);
        
        // Set 2FA as enabled
        $query = "UPDATE users SET tfa_enabled = 1, tfa_method = ?, tfa_backup_codes = ? WHERE usernumber = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ssi", $method, $backup_json, $user_id);
        $success = $stmt->execute();
        
        if($success) {
            $this->logIt("TwoFactorManager::enable - 2FA enabled for user ID: $user_id using method: $method", 3);
            return true;
        }
        
        $this->logIt("TwoFactorManager::enable - Failed to enable 2FA for user ID: $user_id", 2);
        return false;
    }
    
    /**
     * Disable 2FA for a user
     * 
     * @param int $user_id User ID
     * @return bool True on success
     */
    public function disable($user_id) {
        if(!$user_id) {
            $this->logIt("TwoFactorManager::disable - Invalid user ID", 2);
            return false;
        }
        
        $query = "UPDATE users SET tfa_enabled = 0, tfa_method = NULL, tfa_backup_codes = NULL WHERE usernumber = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $user_id);
        $success = $stmt->execute();
        
        if($success) {
            $this->logIt("TwoFactorManager::disable - 2FA disabled for user ID: $user_id", 3);
            return true;
        }
        
        $this->logIt("TwoFactorManager::disable - Failed to disable 2FA for user ID: $user_id", 2);
        return false;
    }
    
    /**
     * Start 2FA verification process
     * 
     * @param int $user_id User ID
     * @param string $email User email
     * @return bool True on success
     */
    public function startVerification($user_id, $email) {
        if(!$user_id || !$email) {
            $this->logIt("TwoFactorManager::startVerification - Invalid user ID or email", 2);
            return false;
        }
        
        // Check if user has 2FA enabled
        if(!$this->isEnabled($user_id)) {
            $this->logIt("TwoFactorManager::startVerification - 2FA not enabled for user ID: $user_id", 3);
            return false;
        }
        
        // Get 2FA method
        $method = $this->getMethod($user_id);
        
        // Generate verification code
        $code = $this->generateCode();
        $expiry = time() + $this->code_expiry;
        
        // Store code in database
        $query = "INSERT INTO tfa_codes (user_id, code, expiry, attempts) VALUES (?, ?, ?, 0)
                 ON DUPLICATE KEY UPDATE code = ?, expiry = ?, attempts = 0";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("isisi", $user_id, $code, $expiry, $code, $expiry);
        $success = $stmt->execute();
        
        if(!$success) {
            $this->logIt("TwoFactorManager::startVerification - Failed to save verification code for user ID: $user_id", 2);
            return false;
        }
        
        // If method is email, send the code
        if($method == 'email') {
            return $this->sendVerificationEmail($email, $code);
        }
        
        // For app-based 2FA, just return success
        return true;
    }
    
    /**
     * Verify 2FA code
     * 
     * @param int $user_id User ID
     * @param string $code Verification code
     * @return bool True if verified
     */
    public function verify($user_id, $code) {
        if(!$user_id || !$code) {
            $this->logIt("TwoFactorManager::verify - Invalid user ID or code", 2);
            return false;
        }
        
        // Check if this is a backup code
        if($this->verifyBackupCode($user_id, $code)) {
            $this->logIt("TwoFactorManager::verify - Backup code used for user ID: $user_id", 3);
            return true;
        }
        
        // Check verification code
        $query = "SELECT id, code, expiry, attempts FROM tfa_codes WHERE user_id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if(!$result || $result->num_rows == 0) {
            $this->logIt("TwoFactorManager::verify - No verification code found for user ID: $user_id", 2);
            return false;
        }
        
        $row = $result->fetch_assoc();
        
        // Check attempts
        if($row['attempts'] >= $this->max_attempts) {
            $this->logIt("TwoFactorManager::verify - Max attempts reached for user ID: $user_id", 2);
            return false;
        }
        
        // Update attempts
        $new_attempts = $row['attempts'] + 1;
        $query = "UPDATE tfa_codes SET attempts = ? WHERE user_id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ii", $new_attempts, $row['id']);
        $stmt->execute();
        
        // Check expiry
        if(time() > $row['expiry']) {
            $this->logIt("TwoFactorManager::verify - Code expired for user ID: $user_id", 3);
            return false;
        }
        
        // Verify code
        if($code === $row['code']) {
            // Delete used code
            $query = "DELETE FROM tfa_codes WHERE user_id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("i", $row['id']);
            $stmt->execute();
            
            $this->logIt("TwoFactorManager::verify - Code verified for user ID: $user_id", 3);
            return true;
        }
        
        $this->logIt("TwoFactorManager::verify - Invalid code for user ID: $user_id", 3);
        return false;
    }
    
    /**
     * Get 2FA method for a user
     * 
     * @param int $user_id User ID
     * @return string 2FA method
     */
    public function getMethod($user_id) {
        if(!$user_id) {
            $this->logIt("TwoFactorManager::getMethod - Invalid user ID", 2);
            return '';
        }
        
        $query = "SELECT tfa_method FROM users WHERE user_id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['tfa_method'] ?? 'email';
        }
        
        return 'email';
    }
    
    /**
     * Send verification email
     * 
     * @param string $email User email
     * @param string $code Verification code
     * @return bool True on success
     */
    private function sendVerificationEmail($email, $code) {
        if(!$email || !$code) {
            $this->logIt("TwoFactorManager::sendVerificationEmail - Invalid email or code", 2);
            return false;
        }
        
        // Use existing mailer class
        if(!class_exists('MyPHPMailer')) {
            $this->logIt("TwoFactorManager::sendVerificationEmail - MyPHPMailer class not available", 2);
            return false;
        }
        
        try {
            $mail = new myPHPMailer(true, $this->smtp_parameters, $this->serveremail, $this->logfile);
            $subject = "Your verification code";
            $emailbody = "Your verification code is: $code\n\nThis code will expire in 10 minutes.\n\nIf you did not request this code, please ignore this email.";
            
            if($mail->sendEmail($email, TRUE, $subject, $emailbody)) {
                $this->logIt("TwoFactorManager::sendVerificationEmail - Verification email sent to: $email", 3);
                return true;
            }
        } catch(Exception $e) {
            $this->logIt("TwoFactorManager::sendVerificationEmail - Failed to send email: " . $e->getMessage(), 2);
        }
        
        return false;
    }
    
    /**
     * Generate backup codes
     * 
     * @param int $count Number of backup codes to generate
     * @return array Array of backup codes
     */
    private function generateBackupCodes($count = 8) {
        $codes = [];
        for($i = 0; $i < $count; $i++) {
            $codes[] = [
                'code' => bin2hex(random_bytes(4)),
                'used' => false
            ];
        }
        return $codes;
    }
    
    /**
     * Verify backup code
     * 
     * @param int $user_id User ID
     * @param string $code Backup code
     * @return bool True if verified
     */
    private function verifyBackupCode($user_id, $code) {
        if(!$user_id || !$code) {
            return false;
        }
        
        $query = "SELECT tfa_backup_codes FROM users WHERE user_id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if(!$result || $result->num_rows == 0) {
            return false;
        }
        
        $row = $result->fetch_assoc();
        $backup_codes = json_decode($row['tfa_backup_codes'], true);
        
        if(!is_array($backup_codes)) {
            return false;
        }
        
        foreach($backup_codes as $key => $backup) {
            if($backup['code'] === $code && $backup['used'] === false) {
                // Mark code as used
                $backup_codes[$key]['used'] = true;
                
                // Update database
                $backup_json = json_encode($backup_codes);
                $query = "UPDATE users SET tfa_backup_codes = ? WHERE user_id = ?";
                $stmt = $this->db->prepare($query);
                $stmt->bind_param("si", $backup_json, $user_id);
                $stmt->execute();
                
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get remaining backup codes for a user
     * 
     * @param int $user_id User ID
     * @return array Array of unused backup codes
     */
    public function getBackupCodes($user_id) {
        if(!$user_id) {
            return [];
        }
        
        $query = "SELECT tfa_backup_codes FROM users WHERE usernumber = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if(!$result || $result->num_rows == 0) {
            return [];
        }
        
        $row = $result->fetch_assoc();
        $backup_codes = json_decode($row['tfa_backup_codes'], true);
        
        if(!is_array($backup_codes)) {
            return [];
        }
        
        // Return only unused codes
        $unused_codes = [];
        foreach($backup_codes as $backup) {
            if($backup['used'] === false) {
                $unused_codes[] = $backup['code'];
            }
        }
        
        return $unused_codes;
    }
    
    /**
     * Generate new backup codes for a user
     * 
     * @param int $user_id User ID
     * @return array Array of new backup codes
     */
    public function regenerateBackupCodes($user_id) {
        if(!$user_id) {
            return [];
        }
        
        $backup_codes = $this->generateBackupCodes();
        $backup_json = json_encode($backup_codes);
        
        $query = "UPDATE users SET tfa_backup_codes = ? WHERE usernumber = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("si", $backup_json, $user_id);
        $success = $stmt->execute();
        
        if($success) {
            $this->logIt("TwoFactorManager::regenerateBackupCodes - Backup codes regenerated for user ID: $user_id", 3);
            return array_column($backup_codes, 'code');
        }
        
        return [];
    }
    
    /**
     * String representation
     * 
     * @return string Class name
     */
    public function __toString() {
        return "TwoFactorManager";
    }
}
