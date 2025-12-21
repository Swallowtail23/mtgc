<?php

/*
Version:     1.4
Date:        01/12/25
Name:        trusteddevicemanager.class.php
Purpose:     Manage trusted device tokens for extended session handling.
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
    die('Direct access prohibited');
endif;

class TrustedDeviceManager
{
    /**
    * @var mysqli
    */
    private $db;
    private $logfile;
    private $msg;
    private $tokenLength = 64;
    private $cookieName = 'mtgc_trusted_device';
    private $hmacSecret;

    public function __construct($db, $logfile)
    {
        $this->db = $db;
        $this->logfile = $logfile;

        // Load HMAC secret from environment variable
        $this->hmacSecret = getenv('HMAC_SECRET');

        if (!class_exists('Message')) :
            require_once(__DIR__ . '/../classes/message.class.php');
        endif;

        try {
            $this->msg = new Message($this->logfile);
        } catch (Error $e) {
            $this->msg = null; // Ensure it's null if instantiation fails
            $this->log('[NOTICE]', 'Falling back to direct logging in TrustedDeviceManager');
        }
    }

    private function log($level, $text)
    {
        if ($this->msg !== null) :
            $this->msg->logMessage($level, $text);
            return;
        endif;

        // Fallback to direct file logging
        if (($fd = fopen($this->logfile, "a")) !== false) :
            if (flock($fd, LOCK_EX)) :
                $timestamp = date("[d/m/Y:H:i:s]");
                fwrite($fd, "$timestamp $level TrustedDeviceManager: $text\n");
                flock($fd, LOCK_UN);
            endif;
            fclose($fd);
        endif;
    }

    public function getCookieName()
    {
        return $this->cookieName;
    }

    private function generateToken()
    {
        return bin2hex(random_bytes($this->tokenLength));
    }

    private function hashToken($token)
    {
        return hash_hmac('sha256', $token, $this->hmacSecret);
    }

    public function getTokenHash($token)
    {
        return $this->hashToken($token);
    }

    private function getClientIP()
    {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) :
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) :
            return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        else :
            return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        endif;
    }

    public function createTrustedDevice($userId, $daysValid = 7)
    {
        $token = $this->generateToken();
        $tokenHash = $this->hashToken($token);

        $expiresTimestamp = time() + ($daysValid * 86400);
        $expiresFormatted = date('Y-m-d H:i:s', $expiresTimestamp);

        $deviceName = isset($_SERVER['HTTP_USER_AGENT'])
            ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255)
            : 'Unknown';
        $ipAddress = $this->getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        $query = "INSERT INTO trusted_devices (user_id, token_hash, device_name, ip_address,
                  user_agent, created, expires)
                  VALUES (?, ?, ?, ?, ?, NOW(), ?)";

        $stmt = $this->db->prepare($query);
        if ($stmt === false) :
            $this->log('[ERROR]', "Failed to prepare statement: " . $this->db->error);
            return false;
        endif;

        $stmt->bind_param(
            "isssss",
            $userId,
            $tokenHash,
            $deviceName,
            $ipAddress,
            $userAgent,
            $expiresFormatted
        );

        if (!$stmt->execute()) :
            $this->log('[ERROR]', "Failed to store trusted device: " . $stmt->error);
            $stmt->close();
            return false;
        endif;

        $stmt->close();

        setcookie(
            $this->cookieName,
            $token,
            [
                'expires' => $expiresTimestamp,
                'path' => '/',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'httponly' => true,
                'samesite' => 'Lax'
            ]
        );

        $this->log('[NOTICE]', "Created trusted device for user $userId");
        return true;
    }

    public function validateTrustedDevice()
    {
        if (!isset($_COOKIE[$this->cookieName])) :
            $this->log('[DEBUG]', "Cookie not set");
            return false;
        endif;

        $token = $_COOKIE[$this->cookieName];
        $hashedToken = $this->hashToken($token);

        $query = "SELECT id, user_id FROM trusted_devices WHERE token_hash = ? AND expires > NOW()";
        $stmt = $this->db->prepare($query);
        if ($stmt === false) :
            $this->log('[ERROR]', "Failed to prepare statement: " . $this->db->error);
            return false;
        endif;

        $stmt->bind_param("s", $hashedToken);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) :
            $stmt->bind_result($deviceId, $userId);
            $stmt->fetch();

            $update = "UPDATE trusted_devices SET last_used = NOW() WHERE id = ?";
            $updateStmt = $this->db->prepare($update);
            if ($updateStmt !== false) :
                $updateStmt->bind_param("i", $deviceId);
                $updateStmt->execute();
                $updateStmt->close();
            endif;

            $stmt->close();
            $this->log('[NOTICE]', "Valid trusted device found for user $userId");
            return $userId;
        else :
            $this->log('[DEBUG]', "No record found");
        endif;

        $stmt->close();
        $this->log('[DEBUG]', "Other issue");
        return false;
    }

    public function removeTrustedDevice()
    {
        if (!isset($_COOKIE[$this->cookieName])) :
            return false;
        endif;

        $token = $_COOKIE[$this->cookieName];
        $hashedToken = $this->hashToken($token);

        $query = "DELETE FROM trusted_devices WHERE token_hash = ?";
        $stmt = $this->db->prepare($query);
        if ($stmt === false) :
            $this->log('[ERROR]', "Failed to prepare statement: " . $this->db->error);
            return false;
        endif;

        $stmt->bind_param("s", $hashedToken);
        $stmt->execute();
        $stmt->close();

        setcookie($this->cookieName, '', time() - 3600, '/');
        $this->log('[NOTICE]', "Removed trusted device");
        return true;
    }

    public function removeAllUserDevices($userId)
    {
        $query = "DELETE FROM trusted_devices WHERE user_id = ?";
        $stmt = $this->db->prepare($query);

        if ($stmt === false) :
            $this->log('[ERROR]', "Failed to prepare statement: " . $this->db->error);
            return false;
        endif;

        $stmt->bind_param("i", $userId);
        $success = $stmt->execute();
        $stmt->close();

        if ($success) :
            $this->log('[NOTICE]', "Removed all trusted devices for user $userId");
            return true;
        else :
            $this->log('[ERROR]', "Failed to remove trusted devices for user $userId");
            return false;
        endif;
    }

    public function cleanupExpiredTokens()
    {
        $query = "DELETE FROM trusted_devices WHERE expires < NOW()";
        $result = $this->db->query($query);

        if ($result === false) :
            $this->log('[ERROR]', "Failed to clean up expired tokens: " . $this->db->error);
            return 0;
        endif;

        $affected = $this->db->affected_rows;
        $this->log('[NOTICE]', "Cleaned up $affected expired trusted device tokens");
        return $affected;
    }

    /**
     * Get all trusted devices for a user
     *
     * @param int $userId The user's ID
     * @return array Array of device information
     */
    public function getUserDevices($userId)
    {
        $query = "SELECT id, device_name, token_hash, ip_address, user_agent, last_used, created, expires 
                 FROM trusted_devices 
                 WHERE user_id = ? 
                 ORDER BY last_used DESC, created DESC";

        $stmt = $this->db->prepare($query);

        if ($stmt === false) :
            $this->log('[ERROR]', "Failed to prepare statement: " . $this->db->error);
            return [];
        endif;

        $stmt->bind_param("i", $userId);

        if (!$stmt->execute()) :
            $this->log('[ERROR]', "Failed to execute query: " . $stmt->error);
            $stmt->close();
            return [];
        endif;

        $result = $stmt->get_result();
        $devices = [];

        while ($row = $result->fetch_assoc()) :
            $devices[] = $row;
        endwhile;

        $stmt->close();
        return $devices;
    }

    /**
     * Remove a specific device by ID
     *
     * @param int $deviceId The device ID to remove
     * @param int $userId The user ID (for security verification)
     * @return bool Success of operation
     */
    public function removeDeviceById($deviceId, $userId)
    {
        $query = "DELETE FROM trusted_devices WHERE id = ? AND user_id = ?";
        $stmt = $this->db->prepare($query);

        if ($stmt === false) :
            $this->log('[ERROR]', "Failed to prepare statement: " . $this->db->error);
            return false;
        endif;

        $stmt->bind_param("ii", $deviceId, $userId);
        $success = $stmt->execute();

        if (!$success) :
            $this->log('[ERROR]', "Failed to remove device: " . $stmt->error);
            $stmt->close();
            return false;
        endif;

        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected > 0) :
            $this->log('[NOTICE]', "Removed device ID $deviceId for user $userId");
            return true;
        else :
            $this->log('[NOTICE]', "No device found with ID $deviceId for user $userId");
            return false;
        endif;
    }

    public function __toString()
    {
        $this->log("[ERROR]", "Called as string");
        return "Called as a string";
    }
}
