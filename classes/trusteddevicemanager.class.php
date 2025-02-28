<?php
/* Version:     1.0
    Date:       28/02/25
    Name:       trusteddevicemanager.class.php
    Purpose:    Manage trusted device tokens for extended session handling
    Notes:      - 
    To do:      -
    
    @author     Claude with Simon Wilson <simon@simonandkate.net>
    @copyright  2025 Simon Wilson
    
 *  1.0
                Initial version
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
    die('Direct access prohibited');
endif;

class TrustedDeviceManager {
    private $db;
    private $logfile;
    private $message;
    private $token_length = 64; // Length of random token
    private $cookie_name = 'mtgc_trusted_device';
    private $cookie_lifetime = 604800; // Default 7 days in seconds
    
    public function __construct($db, $logfile) {
        $this->db = $db;
        $this->logfile = $logfile;
        
        // Check if Message class exists and include it if needed
        if (!class_exists('Message')) {
            require_once(__DIR__ . '/../classes/message.class.php');
        }
        
        // Handle case where Message class still can't be loaded by using direct file logging
        try {
            $this->message = new Message($this->logfile);
        } catch (Error $e) {
            // Fall back to direct file logging
            $this->directLog('[NOTICE]', 'Falling back to direct logging in TrustedDeviceManager');
        }
    }
    
    /**
     * Direct file logging when Message class is unavailable
     */
    private function directLog($level, $text) {
        if (($fd = fopen($this->logfile, "a")) !== false) {
            $timestamp = date("[d/m/Y:H:i:s]");
            $str = "$timestamp $level TrustedDeviceManager: $text";
            fwrite($fd, $str . "\n");
            fclose($fd);
        }
    }
    
    /**
     * Generate a secure random token
     * 
     * @return string The generated token
     */
    private function generateToken() {
        // Generate cryptographically secure pseudo-random bytes
        $randomBytes = random_bytes($this->token_length);
        // Convert to hex for storage in cookie
        return bin2hex($randomBytes);
    }
    
    /**
     * Create a new trusted device token for a user
     * 
     * @param int $user_id The user's ID
     * @param int $days_valid How many days the token should be valid (default 7)
     * @return bool Success of operation
     */
    public function createTrustedDevice($user_id, $days_valid = 7) {
        // Generate a new random token
        $token = $this->generateToken();
        
        // Hash the token for storage in the database
        $token_hash = password_hash($token, PASSWORD_DEFAULT);
        
        // Calculate expiration date
        $expires = new DateTime();
        $expires->modify("+$days_valid days");
        $expires_formatted = $expires->format('Y-m-d H:i:s');
        
        // Get device information
        $device_name = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : 'Unknown';
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown';
        
        // Store token in database
        $query = "INSERT INTO trusted_devices 
                 (user_id, token_hash, device_name, ip_address, user_agent, created, expires) 
                 VALUES (?, ?, ?, ?, ?, NOW(), ?)";
        
        $stmt = $this->db->prepare($query);
        
        if ($stmt === false) {
            // Use direct logging if message object isn't available
            if (isset($this->message)) {
                $this->message->logMessage('[ERROR]', "Failed to prepare statement: " . $this->db->error);
            } else {
                $this->directLog('[ERROR]', "Failed to prepare statement: " . $this->db->error);
            }
            return false;
        }
        
        $stmt->bind_param("isssss", $user_id, $token_hash, $device_name, $ip_address, $user_agent, $expires_formatted);
        
        if (!$stmt->execute()) {
            // Use direct logging if message object isn't available
            if (isset($this->message)) {
                $this->message->logMessage('[ERROR]', "Failed to store trusted device: " . $stmt->error);
            } else {
                $this->directLog('[ERROR]', "Failed to store trusted device: " . $stmt->error);
            }
            $stmt->close();
            return false;
        }
        
        $stmt->close();
        
        // Set the cookie with the token
        $cookie_lifetime = time() + ($days_valid * 86400); // days to seconds
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        $path = '/';
        
        setcookie(
            $this->cookie_name,
            $token,
            [
                'expires' => $cookie_lifetime,
                'path' => $path,
                'domain' => '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Strict'
            ]
        );
        
        // Use direct logging if message object isn't available
        if (isset($this->message)) {
            $this->message->logMessage('[NOTICE]', "Created trusted device for user $user_id");
        } else {
            $this->directLog('[NOTICE]', "Created trusted device for user $user_id");
        }
        return true;
    }
    
    /**
     * Validate a trusted device token
     * 
     * @return int|false User ID if valid, false if invalid
     */
    public function validateTrustedDevice() {
        // Check if trusted device cookie exists
        if (!isset($_COOKIE[$this->cookie_name])) {
            return false;
        }
        
        $token = $_COOKIE[$this->cookie_name];
        
        // Look up all non-expired tokens
        $query = "SELECT id, user_id, token_hash FROM trusted_devices WHERE expires > NOW()";
        $result = $this->db->query($query);
        
        if ($result === false) {
            // Use direct logging if message object isn't available
            if (isset($this->message)) {
                $this->message->logMessage('[ERROR]', "Failed to query trusted devices: " . $this->db->error);
            } else {
                $this->directLog('[ERROR]', "Failed to query trusted devices: " . $this->db->error);
            }
            return false;
        }
        
        // Check each token until we find a match
        while ($row = $result->fetch_assoc()) {
            if (password_verify($token, $row['token_hash'])) {
                // Token is valid, update last_used
                $update = "UPDATE trusted_devices SET last_used = NOW() WHERE id = ?";
                $stmt = $this->db->prepare($update);
                
                if ($stmt !== false) {
                    $stmt->bind_param("i", $row['id']);
                    $stmt->execute();
                    $stmt->close();
                }
                
                // Use direct logging if message object isn't available
                if (isset($this->message)) {
                    $this->message->logMessage('[NOTICE]', "Valid trusted device found for user " . $row['user_id']);
                } else {
                    $this->directLog('[NOTICE]', "Valid trusted device found for user " . $row['user_id']);
                }
                return $row['user_id'];
            }
        }
        
        // No valid token found
        return false;
    }
    
    /**
     * Remove a trusted device token (logout from trusted device)
     * 
     * @return bool Success of operation
     */
    public function removeTrustedDevice() {
        // Check if trusted device cookie exists
        if (!isset($_COOKIE[$this->cookie_name])) {
            return false;
        }
        
        $token = $_COOKIE[$this->cookie_name];
        
        // Look up all tokens
        $query = "SELECT id, token_hash FROM trusted_devices";
        $result = $this->db->query($query);
        
        if ($result === false) {
            $this->message->logMessage('[ERROR]', "Failed to query trusted devices: " . $this->db->error);
            return false;
        }
        
        // Check each token to find a match
        while ($row = $result->fetch_assoc()) {
            if (password_verify($token, $row['token_hash'])) {
                // Token found, delete it
                $delete = "DELETE FROM trusted_devices WHERE id = ?";
                $stmt = $this->db->prepare($delete);
                
                if ($stmt === false) {
                    $this->message->logMessage('[ERROR]', "Failed to prepare delete statement: " . $this->db->error);
                    return false;
                }
                
                $stmt->bind_param("i", $row['id']);
                $success = $stmt->execute();
                $stmt->close();
                
                // Expire the cookie
                setcookie($this->cookie_name, '', time() - 3600, '/');
                
                if ($success) {
                    $this->message->logMessage('[NOTICE]', "Removed trusted device");
                    return true;
                } else {
                    $this->message->logMessage('[ERROR]', "Failed to remove trusted device");
                    return false;
                }
            }
        }
        
        // No matching token found, still expire the cookie
        setcookie($this->cookie_name, '', time() - 3600, '/');
        return false;
    }
    
    /**
     * Remove all trusted devices for a specific user
     * 
     * @param int $user_id The user's ID
     * @return bool Success of operation
     */
    public function removeAllUserDevices($user_id) {
        $query = "DELETE FROM trusted_devices WHERE user_id = ?";
        $stmt = $this->db->prepare($query);
        
        if ($stmt === false) {
            $this->message->logMessage('[ERROR]', "Failed to prepare statement: " . $this->db->error);
            return false;
        }
        
        $stmt->bind_param("i", $user_id);
        $success = $stmt->execute();
        $stmt->close();
        
        if ($success) {
            $this->message->logMessage('[NOTICE]', "Removed all trusted devices for user $user_id");
            return true;
        } else {
            $this->message->logMessage('[ERROR]', "Failed to remove trusted devices for user $user_id");
            return false;
        }
    }
    
    /**
     * Clean up expired trusted device tokens
     * 
     * @return int Number of expired tokens removed
     */
    public function cleanupExpiredTokens() {
        $query = "DELETE FROM trusted_devices WHERE expires < NOW()";
        $result = $this->db->query($query);
        
        if ($result === false) {
            $this->message->logMessage('[ERROR]', "Failed to clean up expired tokens: " . $this->db->error);
            return 0;
        }
        
        $affected = $this->db->affected_rows;
        $this->message->logMessage('[NOTICE]', "Cleaned up $affected expired trusted device tokens");
        return $affected;
    }
    
    /**
     * Get all trusted devices for a user
     * 
     * @param int $user_id The user's ID
     * @return array Array of device information
     */
    public function getUserDevices($user_id) {
        $query = "SELECT id, device_name, ip_address, user_agent, last_used, created, expires 
                 FROM trusted_devices 
                 WHERE user_id = ? 
                 ORDER BY last_used DESC, created DESC";
        
        $stmt = $this->db->prepare($query);
        
        if ($stmt === false) {
            $this->message->logMessage('[ERROR]', "Failed to prepare statement: " . $this->db->error);
            return [];
        }
        
        $stmt->bind_param("i", $user_id);
        
        if (!$stmt->execute()) {
            $this->message->logMessage('[ERROR]', "Failed to execute query: " . $stmt->error);
            $stmt->close();
            return [];
        }
        
        $result = $stmt->get_result();
        $devices = [];
        
        while ($row = $result->fetch_assoc()) {
            $devices[] = $row;
        }
        
        $stmt->close();
        return $devices;
    }
    
    /**
     * Remove a specific device by ID
     * 
     * @param int $device_id The device ID to remove
     * @param int $user_id The user ID (for security verification)
     * @return bool Success of operation
     */
    public function removeDeviceById($device_id, $user_id) {
        $query = "DELETE FROM trusted_devices WHERE id = ? AND user_id = ?";
        $stmt = $this->db->prepare($query);
        
        if ($stmt === false) {
            $this->message->logMessage('[ERROR]', "Failed to prepare statement: " . $this->db->error);
            return false;
        }
        
        $stmt->bind_param("ii", $device_id, $user_id);
        $success = $stmt->execute();
        
        if (!$success) {
            $this->message->logMessage('[ERROR]', "Failed to remove device: " . $stmt->error);
            $stmt->close();
            return false;
        }
        
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        if ($affected > 0) {
            $this->message->logMessage('[NOTICE]', "Removed device ID $device_id for user $user_id");
            return true;
        } else {
            $this->message->logMessage('[NOTICE]', "No device found with ID $device_id for user $user_id");
            return false;
        }
    }
    
    public function __toString() {
        $this->message->logMessage("[ERROR]","Called as string");
        return "Called as a string";
    }
}