<?php

/*
Version:     1.6
Date:        11/01/26
Name:        UserStatus.php
Purpose:     Get user status, bad login counts, and lock users on threshold.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

namespace MTG\Auth;

use MTG\Core\AppConfig;
use MTG\Core\Message;

class UserStatus
{
    /**
    * @var \mysqli|object
    */
    private $db;
    private $appConfig;
    private $message;
    private $email;
    public $status;
    public $badlogincount;

    public function __construct($db, AppConfig $appConfig, $email)
    {
        $this->db = $db;
        $this->appConfig = $appConfig;
        $this->email = $email;
        $this->message = new Message($this->appConfig);
    }

    public function getUserStatus()
    {
        /**
         * Returns:
         * 0 for error
         * 1 for password change required
         * 2 for locked
         * 3 for disabled
         * 10 for active
         */
        if (!isset($this->email)) :
            $this->message->logMessage("[ERROR]", "Called without correct parameters");
            return $this->status['code'] = 0;
        else :
            $query = "SELECT status,usernumber,admin FROM users WHERE email = ? LIMIT 1";
            if ($row = $this->db->execute_query($query, [$this->email])) :
                if ($row->num_rows === 0) :
                    $this->message->logMessage(
                        "[ERROR]",
                        "Called with invalid email address $this->email"
                    );
                    $this->status['code'] = 0;
                elseif ($row->num_rows === 1) :
                    $row = $row->fetch_assoc();
                    $status = $row['status'];
                    $userNumber = $row['usernumber'];
                    $this->status['number'] = $userNumber;
                    $adminrights = $row['admin'];
                    $this->status['admin'] = $adminrights;
                    if ($status == 'active') :
                        $this->message->logMessage("[DEBUG]", "User $this->email is active");
                        $this->status['code'] = 10;
                    elseif ($status == 'disabled') :
                        $this->message->logMessage("[DEBUG]", "User $this->email is disabled");
                        $this->status['code'] = 3;
                    elseif ($status == 'locked') :
                        $this->message->logMessage("[DEBUG]", "User $this->email is locked");
                        $this->status['code'] = 2;
                    elseif ($status == 'chgpwd') :
                        $this->message->logMessage("[DEBUG]", "User $this->email needs to change password");
                        $this->status['code'] = 1;
                    else :
                        $this->message->logMessage("[DEBUG]", "User $this->email unknown status");
                        $this->status['code'] = 0;
                    endif;
                else :
                    throw new \Exception(
                        "[ERROR] Class " . __METHOD__ . " " . __LINE__,
                        " - Other failure: Error: " . $this->db->error
                    );
                endif;
            else :
                $this->status = 0;
                throw new \Exception(
                    "[ERROR] Class " . __METHOD__ . " " . __LINE__,
                    " - SQL failure: Error: " . $this->db->error
                );
            endif;
        endif;
        return $this->status;
    }

    public function getBadLogin()
    {
        if (!isset($this->email)) :
            $this->message->logMessage("[ERROR]", "Called without correct parameters");
            return $this->badlogincount['code'] = 0;
        else :
            $query = "SELECT badlogins FROM users WHERE email = ? LIMIT 1";
            if ($row = $this->db->execute_query($query, [$this->email])) :
                if ($row->num_rows === 0) :
                    $this->message->logMessage("[ERROR]", "Called with invalid email address $this->email");
                    $this->badlogincount['code'] = 0;
                    $this->badlogincount['count'] = null;
                elseif ($row->num_rows === 1) :
                    $row = $row->fetch_assoc();
                    $this->badlogincount['code'] = 1;
                    if (is_null($row['badlogins'])) :
                        $row['badlogins'] = 0;
                    endif;
                    $this->badlogincount['count'] = $row['badlogins'];
                    $this->message->logMessage(
                        "[DEBUG]",
                        "Called: $this->email has {$row['badlogins']} bad logins"
                    );
                else :
                    throw new \Exception(
                        "[ERROR] Class " . __METHOD__ . " " . __LINE__,
                        "- Other failure: Error: " . $this->db->error
                    );
                endif;
            else :
                $this->status = 0;
                throw new \Exception(
                    "[ERROR] Class " . __METHOD__ . " " . __LINE__,
                    "- SQL failure: Error: " . $this->db->error
                );
            endif;
        endif;
        $this->message->logMessage("[DEBUG]", "Returning bad login code: {$this->badlogincount['code']}");
        return $this->badlogincount;
    }

    public function incrementBadLogin()
    {
        $this->message->logMessage('[ERROR]', "Incrementing bad login count for $this->email...");
        $query = "UPDATE users 
                    SET  badlogins = CASE WHEN badlogins IS NULL
                                               THEN 1
                                               ELSE badlogins+1
                                               END
                    WHERE email=?";
        if ($this->db->execute_query($query, [$this->email]) !== true) :
            throw new \Exception(
                "[ERROR] Class " . __METHOD__ . " " . __LINE__,
                " - SQL failure: Error: " . $this->db->error
            );
        else :
            $this->message->logMessage('[DEBUG]', "...sql result: {$this->db->info}");
        endif;
    }

    public function zeroBadLogin()
    {
        $query = "UPDATE users SET  badlogins = 0 WHERE email=?";
        if ($this->db->execute_query($query, [$this->email]) !== true) :
            $this->message->logMessage('[ERROR]', "Resetting bad login count failed");
        else :
            $this->message->logMessage('[DEBUG]', "Reset bad login count to 0: {$this->db->info}");
        endif;
    }

    public function triggerLocked()
    {
        $status = 'locked';
        $query = "UPDATE users SET status=? WHERE email=?";
        if ($this->db->execute_query($query, [$status, $this->email]) !== true) :
            $this->message->logMessage('[ERROR]', "Locking account $this->email failed");
        else :
            $this->message->logMessage('[DEBUG]', "Locking account $this->email: {$this->db->info}");
        endif;
    }
}
