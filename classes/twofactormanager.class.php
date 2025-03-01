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
 * @author Simon Wilson
 * @copyright MTG Collection 2025
 * 
 * 1.0      28/02/25
 *          First release
 * 
 */

// Prevent direct access
if (__FILE__ == $_SERVER['PHP_SELF']) :
    die('Direct access prohibited');
endif;

use OTPHP\TOTP;

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
     */
    public function __construct($db, $smtpParameters, $serveremail, $logfile = "") {
        $this->db = $db;
        $this->logfile = $logfile;
        $this->smtp_parameters = $smtpParameters;
        $this->serveremail = $serveremail;
        
        if (!class_exists('Message')):
            require_once(__DIR__ . '/../classes/message.class.php');
        endif;
        $this->log = new Message($this->logfile);
    }
    
    private function directLog($level, $text) {
        if ($this->log !== null):
            $this->log->logMessage($level, $text);
            return;
        endif;
        if (($fd = fopen($this->logfile, "a")) !== false):
            if (flock($fd, LOCK_EX)):
                $timestamp = date("[d/m/Y:H:i:s]");
                fwrite($fd, "$timestamp $level TwoFactorManager: $text\n");
                flock($fd, LOCK_UN);
            endif;
            fclose($fd);
        endif;
    }
    
    private function generateCode() {
        $min = pow(10, $this->code_length - 1);
        $max = pow(10, $this->code_length) - 1;
        return (string) random_int($min, $max);
    }
    
    /**
     * Check if 2FA is enabled for a user.
     */
    public function isEnabled($user_id) {
        if (!$user_id):
            $this->directLog('[ERROR]', "Invalid user ID");
            return false;
        endif;
        
        $query = "SELECT tfa_enabled FROM users WHERE usernumber = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0):
            $row = $result->fetch_assoc();
            return (bool) $row['tfa_enabled'];
        endif;
        
        return false;
    }
    
    /**
     * Enable 2FA for a user. Accepts method 'email' or 'app'.
     */
    public function enable($user_id, $method = 'email')
    {
        if (!$user_id):
            $this->directLog('[ERROR]', "Invalid user ID");
            return false;
        endif;

        if (!in_array($method, ['email', 'app'])):
            $this->directLog('[ERROR]', "Invalid 2FA method: $method");
            return false;
        endif;

        // Generate backup codes (shared for both methods)
        $backup_codes = $this->generateBackupCodes(); // existing private method
        $backup_json = json_encode($backup_codes);

        if ($method === 'app'):
            // 1) Create a TOTP object and get the secret (base32-encoded)
            $totp = TOTP::create();          // defaults: 30-second step, 6-digit code
            $secret = $totp->getSecret();    // e.g. "JBSWY3DPEHPK3PXP"

            // 2) Set a label & issuer for the authenticator app
            $totp->setLabel($GLOBALS['siteTitle']);
            $totp->setIssuer("MySite");

            // 3) Generate a provisioning URI. You can then show this as a QR code
            //    for the user to scan with Google Authenticator, Authy, etc.
            $provisioningUri = $totp->getProvisioningUri();

            // 4) Update DB with everything: 
            //    - enable 2FA
            //    - set method = app
            //    - store backup codes
            //    - store the TOTP secret
            $query = "UPDATE users 
                      SET tfa_enabled = 1, 
                          tfa_method = ?, 
                          tfa_backup_codes = ?, 
                          tfa_app_secret = ? 
                      WHERE usernumber = ?";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("sssi", $method, $backup_json, $secret, $user_id);

            $success = $stmt->execute();
            if ($success):
                $this->directLog('[NOTICE]', "2FA (app) enabled for user $user_id");

                // 5) Show or return the provisioning URI so you can generate a QR code.
                // Store the URI in session for display on profile.php
                $_SESSION['tfa_provisioning_uri'] = $provisioningUri;
                $this->directLog('[NOTICE]', "2FA (app) provisioning URI stored in session ({$_SESSION['tfa_provisioning_uri']})");

                return true;
            else:
                $this->directLog('[ERROR]', "Failed to enable app-based 2FA for user $user_id");
                return false;
            endif;

        else:
            // 'email' method - your existing code
            $query = "UPDATE users 
                      SET tfa_enabled = 1, 
                          tfa_method = ?, 
                          tfa_backup_codes = ? 
                      WHERE usernumber = ?";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("ssi", $method, $backup_json, $user_id);

            $success = $stmt->execute();
            if ($success):
                $this->directLog('[NOTICE]', "2FA (email) enabled for user $user_id");
                return true;
            else:
                $this->directLog('[ERROR]', "Failed to enable email-based 2FA for user $user_id");
                return false;
            endif;
        endif;
    }
    
    /**
     * Disable 2FA for a user.
     */
    public function disable($user_id) {
        if (!$user_id):
            $this->directLog('[ERROR]', "Invalid user ID");
            return false;
        endif;
        
        $query = "UPDATE users SET tfa_enabled = 0, tfa_method = NULL, tfa_backup_codes = NULL, tfa_app_secret = NULL WHERE usernumber = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $user_id);
        $success = $stmt->execute();
        
        if ($success):
            $this->directLog('[NOTICE]', "2FA disabled for user ID: $user_id");
            return true;
        else:
            $this->directLog('[ERROR]', "Failed to disable 2FA for user ID: $user_id");
            return false;
        endif;
    }
    
    /**
     * Start 2FA verification process.
     */
    public function startVerification($user_id, $email) {
        if (!$user_id || !$email):
            $this->directLog('[ERROR]', "Invalid user ID or email");
            return false;
        endif;
        
        if (!$this->isEnabled($user_id)):
            $this->directLog('[NOTICE]', "2FA not enabled for user ID: $user_id");
            return false;
        endif;
        
        $method = $this->getMethod($user_id);
        
        if ($method === 'email'):
            $code = $this->generateCode();
            $expiry = time() + $this->code_expiry;
            $query = "INSERT INTO tfa_codes (user_id, code, expiry, attempts) VALUES (?, ?, ?, 0)
                      ON DUPLICATE KEY UPDATE code = ?, expiry = ?, attempts = 0";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("isisi", $user_id, $code, $expiry, $code, $expiry);
            $success = $stmt->execute();
            
            if (!$success):
                $this->directLog('[ERROR]', "Failed to save verification code for user ID: $user_id");
                return false;
            endif;
            
            return $this->sendVerificationEmail($email, $code);
        elseif ($method === 'app'):
            $this->directLog('[NOTICE]', "App-based 2FA selected for user ID: $user_id. Waiting for code verification.");
            // For app-based 2FA, the user will generate the TOTP code using their authenticator app.
            return true;
        else:
            $this->directLog('[ERROR]', "Unknown 2FA method for user ID: $user_id");
            return false;
        endif;
    }
    
    /**
     * Verify 2FA code.
     */
    public function verify($user_id, $code) {
        if (!$user_id || !$code):
            $this->directLog('[ERROR]', "Invalid user ID or code");
            return false;
        endif;
        
        $method = $this->getMethod($user_id);
        
        if ($method === 'app'):
            return $this->verifyAppCode($user_id, $code);
        endif;
        
        // Check if this is a backup code
        if ($this->verifyBackupCode($user_id, $code)):
            $this->directLog('[NOTICE]', "Backup code used for user ID: $user_id");
            return true;
        endif;
        
        $query = "SELECT id, code, expiry, attempts FROM tfa_codes WHERE user_id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$result || $result->num_rows == 0):
            $this->directLog('[ERROR]', "No verification code found for user ID: $user_id");
            return false;
        endif;
        
        $row = $result->fetch_assoc();
        
        if ($row['attempts'] >= $this->max_attempts):
            $this->directLog('[ERROR]', "Max attempts reached for user ID: $user_id");
            return false;
        endif;
        
        $new_attempts = $row['attempts'] + 1;
        $query = "UPDATE tfa_codes SET attempts = ? WHERE user_id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ii", $new_attempts, $row['id']);
        $stmt->execute();
        
        if (time() > $row['expiry']):
            $this->directLog('[NOTICE]', "Code expired for user ID: $user_id");
            return false;
        endif;
        
        if ($code === $row['code']):
            $query = "DELETE FROM tfa_codes WHERE user_id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("i", $row['id']);
            $stmt->execute();
            $this->directLog('[NOTICE]', "Code verified for user ID: $user_id");
            return true;
        else:
            $this->directLog('[ERROR]', "Invalid code for user ID: $user_id");
            return false;
        endif;
    }
    
    /**
     * Verify app-based (TOTP) code using OTPHP.
     */
    private function verifyAppCode($user_id, $code) {
        // Retrieve the stored secret from the DB.
        $query = "SELECT tfa_app_secret FROM users WHERE usernumber = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if (!$result || $result->num_rows == 0):
            $this->directLog('[ERROR]', "No TOTP secret found for user ID: $user_id");
            return false;
        endif;

        $row = $result->fetch_assoc();
        $secret = $row['tfa_app_secret'];

        if (!$secret):
            $this->directLog('[ERROR]', "TOTP secret is empty for user ID: $user_id");
            return false;
        endif;

        // Use OTPHP to create a TOTP object from the secret.
        $totp = \OTPHP\TOTP::create($secret);

        // Verify the user-provided code. OTPHP handles time-step drift if needed.
        $isValid = $totp->verify($code, null, 1); // 1 allows a ±1 time step drift.

        if ($isValid):
            $this->directLog('[NOTICE]', "App-based TOTP verified for user ID: $user_id");
            return true;
        else:
            $this->directLog('[ERROR]', "Invalid TOTP code for user ID: $user_id");
            return false;
        endif;
    }
    
    /**
     * A simple TOTP calculation.
     * For production use, consider a robust TOTP library.
     */
    private function calculateTOTP($secret, $timeCounter) {
        $key = pack("H*", $secret);
        $data = pack("N*", 0) . pack("N*", $timeCounter);
        $hash = hash_hmac("sha1", $data, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $truncatedHash = unpack("N", substr($hash, $offset, 4))[1] & 0x7FFFFFFF;
        $code = $truncatedHash % pow(10, $this->code_length);
        return str_pad($code, $this->code_length, "0", STR_PAD_LEFT);
    }
    
    /**
     * Get 2FA method for a user.
     */
    public function getMethod($user_id) {
        if (!$user_id):
            $this->directLog('[ERROR]', "Invalid user ID");
            return '';
        endif;
        
        $query = "SELECT tfa_method FROM users WHERE usernumber = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0):
            $row = $result->fetch_assoc();
            return isset($row['tfa_method']) ? $row['tfa_method'] : 'email';
        endif;
        
        return 'email';
    }
    
    /**
     * Send verification email.
     */
    private function sendVerificationEmail($email, $code) {
        if (!$email || !$code):
            $this->directLog('[ERROR]', "Invalid email or code");
            return false;
        endif;
        
        if (!class_exists('MyPHPMailer')):
            $this->directLog('[ERROR]', "MyPHPMailer class not available");
            return false;
        endif;
        
        try {
            $mail = new myPHPMailer(true, $this->smtp_parameters, $this->serveremail, $this->logfile);
            $subject = "Your verification code";
            $emailbody = "Your verification code is: $code\n\nThis code will expire in 10 minutes.\n\nIf you did not request this code, please ignore this email.";
            
            if ($mail->sendEmail($email, TRUE, $subject, $emailbody)):
                $this->directLog('[NOTICE]', "Verification email sent to: $email");
                return true;
            endif;
        } catch(Exception $e) {
            $this->directLog('[ERROR]', "Failed to send email: " . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Generate backup codes.
     */
    private function generateBackupCodes($count = 8) {
        $codes = [];
        for ($i = 0; $i < $count; $i++):
            $codes[] = [
                'code' => bin2hex(random_bytes(4)),
                'used' => false
            ];
        endfor;
        return $codes;
    }
    
    /**
     * Verify backup code.
     */
    private function verifyBackupCode($user_id, $code) {
        if (!$user_id || !$code):
            return false;
        endif;
        
        $query = "SELECT tfa_backup_codes FROM users WHERE usernumber = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$result || $result->num_rows == 0):
            return false;
        endif;
        
        $row = $result->fetch_assoc();
        $backup_codes = json_decode($row['tfa_backup_codes'], true);
        
        if (!is_array($backup_codes)):
            return false;
        endif;
        
        foreach ($backup_codes as $key => $backup):
            if ($backup['code'] === $code && $backup['used'] === false):
                $backup_codes[$key]['used'] = true;
                $backup_json = json_encode($backup_codes);
                $query = "UPDATE users SET tfa_backup_codes = ? WHERE usernumber = ?";
                $stmt = $this->db->prepare($query);
                $stmt->bind_param("si", $backup_json, $user_id);
                $stmt->execute();
                return true;
            endif;
        endforeach;
        
        return false;
    }
    
    /**
     * Get remaining backup codes for a user.
     */
    public function getBackupCodes($user_id) {
        if (!$user_id):
            return [];
        endif;
        
        $query = "SELECT tfa_backup_codes FROM users WHERE usernumber = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$result || $result->num_rows == 0):
            return [];
        endif;
        
        $row = $result->fetch_assoc();
        $backup_codes = json_decode($row['tfa_backup_codes'], true);
        
        if (!is_array($backup_codes)):
            return [];
        endif;
        
        $unused_codes = [];
        foreach ($backup_codes as $backup):
            if ($backup['used'] === false):
                $unused_codes[] = $backup['code'];
            endif;
        endforeach;
        
        return $unused_codes;
    }
    
    /**
     * Generate new backup codes for a user.
     */
    public function regenerateBackupCodes($user_id) {
        if (!$user_id):
            return [];
        endif;
        
        $backup_codes = $this->generateBackupCodes();
        $backup_json = json_encode($backup_codes);
        
        $query = "UPDATE users SET tfa_backup_codes = ? WHERE usernumber = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("si", $backup_json, $user_id);
        $success = $stmt->execute();
        
        if ($success):
            $this->directLog('[NOTICE]', "Backup codes regenerated for user ID: $user_id");
            return array_column($backup_codes, 'code');
        endif;
        
        return [];
    }
    
    public function __toString() {
        return "TwoFactorManager";
    }
}