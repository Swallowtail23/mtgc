<?php

/*
Version:     1.8
Date:        11/01/26
Name:        PasswordCheck.php
Purpose:     Password validation class.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

namespace MTG\Auth;

use MTG\Core\AppConfig;
use MTG\Core\Message;
use MTG\Core\MyPHPMailer;

class PasswordCheck
{
    /**
    * @var mysqli
    */
    private $db;
    private $message;
    private $siteTitle;
    private $serverEmail;
    private $adminEmail;
    private $emailEnabled;
    private $baseUrl;
    private $appConfig;

    public $passwordvalidate;

    public function __construct($db, AppConfig $appConfig)
    {
        $this->db = $db;
        $this->appConfig = $appConfig;
        $this->message = new Message($this->appConfig);
        $this->siteTitle = (string) $this->appConfig->general('title', '');
        $this->serverEmail = (string) $this->appConfig->email('serverEmail', '');
        $this->adminEmail = (string) $this->appConfig->email('adminEmail', '');
        $this->emailEnabled = (bool) $this->appConfig->email('enabled', false);
        $this->baseUrl = (string) $this->appConfig->general('url', '');
    }

    /**
     * Request a password reset link (token sent via email if enabled).
     */
    public function requestResetToken($email, $forceChange = false)
    {
        if (!$this->emailEnabled) :
            $this->message->logMessage('[NOTICE]', 'Password reset request blocked; email disabled');
            return false;
        endif;

        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) :
            return true; // do not disclose validity
        endif;

        $user = $this->findUserByEmail($email);
        if ($user === null) :
            return true; // do not disclose validity
        endif;

        if ($forceChange) :
            $this->updateUserStatus($email, 'chgpwd');
        endif;

        $this->ensureResetTable();
        $this->clearExpiredResetTokens();
        $token = bin2hex(random_bytes(16));
        $tokenHash = password_hash($token, PASSWORD_DEFAULT);
        $expires = date('Y-m-d H:i:s', time() + 600);

        if (!$this->persistResetToken($email, $tokenHash, $expires)) :
            $this->message->logMessage('[ERROR]', "Failed to persist reset token for $email");
            return false;
        endif;

        $linkBase = ($this->baseUrl !== '') ? rtrim($this->baseUrl, '/') : '';
        $link = $linkBase . "/reset.php?email=" . urlencode($email) . "&token=" . urlencode($token);
        if ($linkBase === '') :
            $link = "/reset.php?email=" . urlencode($email) . "&token=" . urlencode($token);
        endif;
        return $this->sendResetEmail($email, $link);
    }

    /**
     * Complete password reset using token.
     */
    public function completeReset($email, $token, $newPassword)
    {
        if (!$this->emailEnabled) :
            $this->message->logMessage('[NOTICE]', 'Complete reset blocked; email disabled');
            return false;
        endif;

        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || empty($token) || empty($newPassword)) :
            return false;
        endif;

        $record = $this->fetchResetRecord($email);
        if ($record === null) :
            $this->message->logMessage('[ERROR]', "No reset token found for $email");
            return false;
        endif;

        $expiresAt = $record['expires_at'] ?? null;
        if ($expiresAt === null || strtotime($expiresAt) < time()) :
            $this->message->logMessage('[ERROR]', "Reset token expired for $email");
            $this->clearResetRecord($email);
            return false;
        endif;

        if (!password_verify($token, $record['token_hash'])) :
            $this->message->logMessage('[ERROR]', "Reset token verification failed for $email");
            return false;
        endif;

        if (!self::validPass($newPassword)) :
            $this->message->logMessage('[ERROR]', "Reset password does not meet complexity requirements for $email");
            return false;
        endif;

        $currentPasswordHash = $this->getCurrentPasswordHash($email);
        if (!empty($currentPasswordHash) && password_verify($newPassword, $currentPasswordHash)) :
            $this->message->logMessage('[ERROR]', "Reset password matches existing password for $email");
            return false;
        endif;

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        if (!$this->updateUserPassword($email, $hashedPassword, true)) :
            $this->message->logMessage('[ERROR]', "Failed to update password for $email");
            return false;
        endif;
        $this->sendPasswordChangeNotification($email);

        $this->clearResetRecord($email);
        $this->clearExpiredResetTokens();
        return true;
    }

    public function validatePassword($email, $password)
    {
        /**
         * Returns:
         * 0 for incorrect call
         * 1 for invalid email address
         * 2 for incorrect password
         * 10 for valid email / password combination
         */
        if (!isset($email) || !isset($password)) :
            $this->message->logMessage("[DEBUG]", "Called without correct parameters");
            return $this->passwordvalidate = 0;
        else :
            if ($row = $this->db->execute_query("SELECT password FROM users WHERE email = ? LIMIT 1", [$email])) :
                if ($row->num_rows === 0) :
                    $this->message->logMessage("[DEBUG]", "Invalid email address, returning 1");
                    $this->passwordvalidate = 1;
                elseif ($row->num_rows === 1) :
                    $row = $row->fetch_assoc();
                        $db_password = $row['password'];
                    if (password_verify($password, $db_password)) :
                        $this->message->logMessage("[DEBUG]", "Email and password validated for $email, returning 10");
                        $this->passwordvalidate = 10;
                    else :
                            $this->message->logMessage("[NOTICE]", "Valid email, invalid password, returning 2");
                            $this->passwordvalidate = 2;
                    endif;
                    //endif;
                else :
                    throw new \Exception(
                        "[ERROR] Class Passwords: PasswordValidate - Other failure: Error: " . $this->db->error
                    );
                endif;
            else :
                $this->passwordvalidate = 0;
                throw new \Exception(
                    "[ERROR] Class Passwords: PasswordValidate - SQL failure: Error: " . $this->db->error
                );
            endif;
        endif;
        return $this->passwordvalidate;
    }

    public static function validPass($candidate)
    {
        if (!preg_match_all('$\S*(?=\S{8,})(?=\S*[a-z])(?=\S*[A-Z])(?=\S*[\d])\S*$', $candidate, $hole)) :
            return false;
        else :
            return true;
        endif;
        $hole = '';
    }

    public function passwordReset($email, $admin, $dbname)
    {
        if (!isset($email)) :
            $this->message->logMessage("[DEBUG]", "Called without target account");
            return 0;
            exit;
        elseif ($admin !== 1) :
            $this->message->logMessage("[DEBUG]", "Called by non-admin user");
            return 0;
            exit;
        elseif (!$this->emailEnabled) :
            $this->message->logMessage("[NOTICE]", "Password reset requested but email disabled");
            return 0;
        else :
            if (
                $row = $this->db->execute_query(
                    "SELECT username, email FROM users WHERE email = ? LIMIT 1",
                    [$email]
                )
            ) :
                if ($row->num_rows === 0) :
                    $this->message->logMessage("[DEBUG]", "Invalid email address");
                    return 0;
                    exit;
                elseif ($row->num_rows === 1) : // $email matches a user
                    $row = $row->fetch_assoc();
                    $userName = $row['username'];
                    $randompassword = $this->generateRandomPassword(12);
                    $this->message->logMessage("[DEBUG]", "New password generated for $email, $userName");
                    $reset = $this->newUser($userName, $email, $randompassword, $dbname);
                    $this->message->logMessage("[DEBUG]", "Newuser result: $reset");
                    if ($reset === 1) :
                        $from = "From: $this->serverEmail\r\nReturn-path: $this->serverEmail";
                        $subject = "Password reset";
                        $message = "A new password was requested for your email at $this->siteTitle "
                            . "({$this->baseUrl})\n\n"
                            . "Please login with this temporary password: $randompassword\n"
                            . "You will need to then choose a new password.\n\n"
                            . "If you did not request a new password at $this->siteTitle, you can ignore this email.";
                        if ($this->emailEnabled) :
                            mail($email, $subject, $message, $from);
                        else :
                            $this->message->logMessage(
                                '[NOTICE]',
                                "Email disabled; password reset not emailed to $email"
                            );
                        endif;
                    elseif ($reset === 0) :
                        $from = "From: $this->serverEmail\r\nReturn-path: $this->serverEmail";
                        $subject = "Password reset failed";
                        $message = "Password reset failed for $userName / $email";
                        if ($this->emailEnabled) :
                            mail($this->adminEmail, $subject, $message, $from);
                        else :
                            $this->message->logMessage(
                                '[NOTICE]',
                                "Email disabled; admin reset failure email not sent for $email"
                            );
                        endif;
                    endif;
                else :
                    throw new \Exception(
                        "[ERROR] Class Passwords: passwordReset - Other failure: Error: " . $this->db->error
                    );
                    return 0;
                    exit;
                endif;
            else :
                throw new \Exception(
                    "[ERROR] Class Passwords: passwordReset - SQL failure: Error: " . $this->db->error
                );
                return 0;
                exit;
            endif;
        endif;
        return 1;
    }

    private function generateRandomPassword($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ&$@^*-_';
        $charactersLength = strlen($characters);
        $randomPassword = '';
        for ($i = 0; $i < $length; $i++) {
            $randomPassword .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomPassword;
    }

    /**
     * Look up a user by email, returning an array or null.
     */
    protected function findUserByEmail($email)
    {
        $query = "SELECT usernumber, email FROM users WHERE email = ? LIMIT 1";
        $stmt = $this->db->prepare($query);
        if ($stmt === false) :
            return null;
        endif;
        $stmt->bind_param("s", $email);
        if (!$stmt->execute()) :
            $stmt->close();
            return null;
        endif;
        $stmt->store_result();
        if ($stmt->num_rows !== 1) :
            $stmt->close();
            return null;
        endif;
        /** @var int $usernumber */
        $usernumber = 0;
        /** @var string $dbemail */
        $dbemail = '';
        $stmt->bind_result($usernumber, $dbemail);
        $stmt->fetch();
        $stmt->close();
        return ['usernumber' => $usernumber, 'email' => $dbemail];
    }

    /**
     * Ensure password_resets table exists.
     */
    protected function ensureResetTable()
    {
        $create = "CREATE TABLE IF NOT EXISTS password_resets (
            email VARCHAR(255) PRIMARY KEY,
            token_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL
        )";
        $this->db->query($create);
    }

    /**
     * Store/reset token.
     */
    protected function persistResetToken($email, $tokenHash, $expires)
    {
        $query = "INSERT INTO password_resets (email, token_hash, expires_at, created_at)
                  VALUES (?, ?, ?, NOW())
                  ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash), expires_at=VALUES(expires_at),
                  created_at=VALUES(created_at)";
        $stmt = $this->db->prepare($query);
        if ($stmt === false) :
            return false;
        endif;
        $stmt->bind_param("sss", $email, $tokenHash, $expires);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    /**
     * Notify user their password has changed (best-effort).
     */
    public function sendPasswordChangeNotification($email)
    {
        if (!$this->emailEnabled) :
            $this->message->logMessage(
                '[NOTICE]',
                "Password change notification suppressed; email disabled for $email"
            );
            return false;
        endif;
        if (!class_exists(MyPHPMailer::class)) :
            $this->message->logMessage('[ERROR]', "MyPHPMailer class not available for password change notice");
            return false;
        endif;

        $siteTitleEsc = htmlspecialchars($this->siteTitle, ENT_QUOTES, 'UTF-8');
        $subject = "$this->siteTitle password changed";
        $plain = "Your password on $this->siteTitle was changed. "
                  . "If this was not you, please reset your password immediately.";
        $html = "<p>Your password on $siteTitleEsc was changed.</p>"
              . "<p>If this was not you, please reset your password immediately.</p>";

        $mailer = new MyPHPMailer(true, $this->appConfig);
        if ($mailer->sendEmail($email, true, $subject, $html, $plain)) :
            $this->message->logMessage('[NOTICE]', "Password change notification sent to $email");
            return true;
        endif;
        $this->message->logMessage('[ERROR]', "Password change notification failed to send to $email");
        return false;
    }

    /**
     * Fetch current password hash for a user.
     */
    protected function getCurrentPasswordHash($email)
    {
        $query = "SELECT password FROM users WHERE email = ? LIMIT 1";
        $stmt = $this->db->prepare($query);
        if ($stmt === false) :
            return null;
        endif;
        $stmt->bind_param("s", $email);
        if (!$stmt->execute()) :
            $stmt->close();
            return null;
        endif;
        $stmt->store_result();
        if ($stmt->num_rows !== 1) :
            $stmt->close();
            return null;
        endif;
        /** @var string $hash */
        $hash = '';
        $stmt->bind_result($hash);
        $stmt->fetch();
        $stmt->close();
        return $hash;
    }

    /**
     * Fetch stored reset token.
     */
    public function fetchResetRecord($email)
    {
        $query = "SELECT token_hash, expires_at FROM password_resets WHERE email = ? LIMIT 1";
        $stmt = $this->db->prepare($query);
        if ($stmt === false) :
            return null;
        endif;
        $stmt->bind_param("s", $email);
        if (!$stmt->execute()) :
            $stmt->close();
            return null;
        endif;
        $stmt->store_result();
        if ($stmt->num_rows !== 1) :
            $stmt->close();
            return null;
        endif;
        /** @var string $tokenHash */
        $tokenHash = '';
        /** @var string $expires */
        $expires = '';
        $stmt->bind_result($tokenHash, $expires);
        $stmt->fetch();
        $stmt->close();
        return ['token_hash' => $tokenHash, 'expires_at' => $expires];
    }

    /**
     * Clear reset token.
     */
    protected function clearResetRecord($email)
    {
        $query = "DELETE FROM password_resets WHERE email = ?";
        $stmt = $this->db->prepare($query);
        if ($stmt === false) :
            return;
        endif;
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Clear expired reset tokens.
     */
    protected function clearExpiredResetTokens()
    {
        $query = "DELETE FROM password_resets WHERE expires_at < NOW()";
        $this->db->query($query);
    }

    /**
     * Allow callers to clear reset token for an email.
     */
    public function clearResetForEmail($email)
    {
        $this->clearResetRecord($email);
    }

    /**
     * Update user status helper.
     */
    protected function updateUserStatus($email, $status)
    {
        $query = "UPDATE users SET status = ?, badlogins = 0 WHERE email = ?";
        $stmt = $this->db->prepare($query);
        if ($stmt === false) :
            return false;
        endif;
        $stmt->bind_param("ss", $status, $email);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    /**
     * Update user password.
     */
    protected function updateUserPassword($email, $hashedPassword, $setActive = false)
    {
        if ($setActive) :
            $query = "UPDATE users SET password = ?, badlogins = 0, status = 'active' WHERE email = ?";
        else :
            $query = "UPDATE users SET password = ?, badlogins = 0 WHERE email = ?";
        endif;
        $stmt = $this->db->prepare($query);
        if ($stmt === false) :
            return false;
        endif;
        $stmt->bind_param("ss", $hashedPassword, $email);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    /**
     * Send reset email via PHPMailer wrapper.
     */
    protected function sendResetEmail($email, $link)
    {
        if (!class_exists(MyPHPMailer::class)) :
            $this->message->logMessage('[ERROR]', "MyPHPMailer class not available");
            return false;
        endif;

        $mail = new MyPHPMailer(true, $this->appConfig);
        $subject = "{$this->siteTitle} password reset";
        $safeLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
        $bodyText = "A password reset was requested for your account. Click the link below to set a new password:\n\n"
            . "$link\n\nIf you did not request this, you can ignore this email.";
        $bodyHtml = "<p>A password reset was requested for your account.</p>"
            . "<p><a href=\"{$safeLink}\">Click here to set a new password</a></p>"
            . "<p>If you did not request this, you can ignore this email.</p>";

        return $mail->sendEmail($email, true, $subject, $bodyHtml, $bodyText);
    }

    public function newUser($userName, $postemail, $password = '', $dbname = '')
    {
        $msg = new Message($this->appConfig);
        $postemail = trim($postemail);
        if (!filter_var($postemail, FILTER_VALIDATE_EMAIL)) :
            $msg->logMessage('[NOTICE]', "Email validation failed in newUser for input '$postemail'");
            return 6;
        endif;
        $mysql_date = date('Y-m-d');
        if ($password === '') :
            $noSuppliedPW = true;
            $password = $this->generateRandomPassword();
        else :
            $noSuppliedPW = false;
        endif;
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $query = "INSERT INTO users (username, reg_date, email, password, status, groupid, grpinout)
                    VALUES (?, ?, ?, ?, 'chgpwd', 1, 0)
                    ON DUPLICATE KEY UPDATE password=?, status='chgpwd', badlogins=0 ";
        $msg->logMessage(
            '[NOTICE]',
            "New user query/password update for $userName / $postemail from {$_SERVER['REMOTE_ADDR']}"
        );
        $stmt = $this->db->prepare($query);
        if ($stmt) :
            $stmt->bind_param("sssss", $userName, $mysql_date, $postemail, $hashed_password, $hashed_password);
            if ($stmt->execute()) :
                $affected_rows = $stmt->affected_rows;
                $msg->logMessage(
                    '[NOTICE]',
                    "New user query from " . $_SERVER['REMOTE_ADDR'] . " affected $affected_rows rows"
                );
            else :
                throw new \Exception("[ERROR] Class Passwords: newUser: New user query failed " . $stmt->error);
            endif;
            $stmt->close();
        else :
                throw new \Exception(
                    "[ERROR] Class Passwords: newUser: New user query failed to prepare statement "
                        . $this->db->error
                );
        endif;

        // Retrieve the new user to confirm that it has written OK
        $query_select = "SELECT password, username, usernumber FROM users WHERE email=?";
        $stmt_select = $this->db->prepare($query_select);
        $stmt_select->bind_param("s", $postemail);

        $db_password   = '';
        $db_username   = '';
        $db_usernumber = '';
        if ($stmt_select->execute()) :
            $stmt_select->store_result();
            $stmt_select->bind_result($db_password, $db_username, $db_usernumber);

            if ($stmt_select->fetch()) :
                if (hash_equals($hashed_password, $db_password)) :
                    // User has been created OK
                    $msg->logMessage('[NOTICE]', "User creation successful, password matched");
                    $usersuccess = 1;

                    // Create the user's database table
                    $mytable = "{$db_usernumber}collection";

                    // Does it already exist
                    $queryexists = "SHOW TABLES FROM $dbname LIKE '$mytable'";
                    $stmt_exists = $this->db->prepare($queryexists);

                    if ($stmt_exists->execute()) :
                        $stmt_exists->store_result();
                        // Count tables matching collection name
                        $collection_exists = $stmt_exists->num_rows;
                        $stmt_exists->close();

                        $msg->logMessage('[DEBUG]', "Collection table check returned $collection_exists rows");

                        if ($collection_exists === 0) : // No existing collection table
                            $msg->logMessage('[DEBUG]', "No Collection table, creating...");
                            $query_create = "CREATE TABLE `$mytable` LIKE collectionTemplate";
                            $stmt_create = $this->db->prepare($query_create);

                            if ($stmt_create->execute()) :
                                $msg->logMessage('[NOTICE]', "Collection table copy ok");
                                $tablesuccess = 1;
                            else :
                                $msg->logMessage('[ERROR]', "Collection table copy failed");
                                $tablesuccess = 5;
                            endif;
                            $stmt_create->close();
                        elseif ($collection_exists == -1) :
                            $tablesuccess = 5;
                        else : // There is already a table with this name
                            $tablesuccess = 0;
                        endif;
                    else :
                        $msg->logMessage('[ERROR]', "Collection table check failed");
                    endif;
                else :
                    $msg->logMessage('[ERROR]', "User creation unsuccessful, password check failed, aborting");
                    $usersuccess = 0;
                endif;
            else :
                $msg->logMessage('[ERROR]', "User creation unsuccessful");
                $usersuccess = 0;
            endif;
            $stmt_select->close();
        else :
            $msg->logMessage('[ERROR]', "User creation unsuccessful");
            $usersuccess = 0;
        endif;

        if ($usersuccess === 1 && $noSuppliedPW === true) :
            if ($this->emailEnabled) :
                $this->message->logMessage('[NOTICE]', "Triggering reset token for new user $postemail");
                $this->requestResetToken($postemail, true);
            else :
                $msg->logMessage(
                    '[NOTICE]',
                    "Email disabled; reset link not sent to $postemail"
                );
            endif;
        endif;

        if (($usersuccess === 1) && ($tablesuccess === 1)) :
            return 2;
        elseif (($usersuccess === 1) && ($tablesuccess === 0)) :
            return 1;
        elseif (($usersuccess === 1) && ($tablesuccess === 5)) :
            return 5;
        else :
            return 0;
        endif;
    }

    public function __toString()
    {
        $this->message->logMessage("[ERROR]", "Called as string");
        return "Called as a string";
    }
}
