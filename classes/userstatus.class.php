<?php
/* Version:     3.1
    Date:       20/01/24
    Name:       userstatus.class.php
    Purpose:    User status class - gets user status, gets and increments 
                bad login count. Also triggers 'locked' status when ini file 
                badlogin limit is reached.
    Notes:      - 
    To do:      -
    
    @author     Simon Wilson <simon@simonandkate.net>
    @copyright  2016 Simon Wilson
    
 *  1.0
                Initial version
 *  2.0
 *              Added ZeroBadLogin public function, reset bad login count to 
 *                  zero after a good login
 *  3.0
 *              Corrected empty array key for invalid email address
 * 
    3.1         20/01/24
 *              Move to logMessage
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;

class UserStatus 
{
    private $db;
    private $logfile;
    private $message;
    private $email;
    public $status;
    public $badlogincount;

    public function __construct($db, $logfile, $email)
    {
        $this->db = $db;
        $this->logfile = $logfile;
        $this->email = $email;
        $this->message = new Message($this->logfile);
    }
    
    public function GetUserStatus() {
        /**
         * Returns: 
         * 0 for error
         * 1 for password change required
         * 2 for locked
         * 3 for disabled
         * 10 for active
         */
        if (!isset($this->email)):
            $this->message->logMessage("[ERROR]","Called without correct parameters");
            return $this->status['code'] = 0;
        else:
            if($row = $this->db->execute_query("SELECT status,usernumber,admin FROM users WHERE email = ? LIMIT 1",[$this->email])):
                if ($row->num_rows === 0):
                    $this->message->logMessage("[ERROR]","Called with invalid email address $this->email");
                    $this->status['code'] = 0;
                elseif ($row->num_rows === 1):
                    $row = $row->fetch_assoc();
                    $status = $row['status'];
                    $usernumber = $row['usernumber'];
                    $this->status['number'] = $usernumber;
                    $adminrights = $row['admin'];
                    $this->status['admin'] = $adminrights;
                    if ($status == 'active') :
                        $this->message->logMessage("[DEBUG]","User $this->email is active");
                        $this->status['code'] = 10;
                    elseif ($status == 'disabled') :
                        $this->message->logMessage("[DEBUG]","User $this->email is disabled");
                        $this->status['code'] = 3;
                    elseif ($status == 'locked') :
                        $this->message->logMessage("[DEBUG]","User $this->email is locked");
                        $this->status['code'] = 2;
                    elseif ($status == 'chgpwd') :
                        $this->message->logMessage("[DEBUG]","User $this->email needs to change password");
                        $this->status['code'] = 1;
                    else:
                        $this->message->logMessage("[DEBUG]","User $this->email unknown status");
                        $this->status['code'] = 0;
                    endif;
                else:
                    trigger_error("[ERROR] Class " .__METHOD__ . " ".__LINE__," - Other failure: Error: " . $this->db->error, E_USER_ERROR);
                endif;
            else:
                $this->status = 0;
                trigger_error("[ERROR] Class " .__METHOD__ . " ".__LINE__," - SQL failure: Error: " . $this->db->error, E_USER_ERROR);
            endif;
        endif;
        return $this->status;
    }
    
    public function GetBadLogin() {
        if (!isset($this->email)):
            $this->message->logMessage("[ERROR]","Called without correct parameters");
            return $this->badlogincount['code'] = 0;
        else:
            if($row = $this->db->execute_query("SELECT badlogins FROM users WHERE email = ? LIMIT 1",[$this->email])):
                if ($row->num_rows === 0):
                    $this->message->logMessage("[ERROR]","Called with invalid email address $this->email");
                    $this->badlogincount['code'] = 0;
                    $this->badlogincount['count'] = null;
                elseif ($row->num_rows === 1):
                    $row = $row->fetch_assoc();
                    $this->badlogincount['code'] = 1;    
                    if(is_null($row['badlogins'])):
                        $row['badlogins'] = 0;
                    endif;
                    $this->badlogincount['count'] = $row['badlogins'];
                    $this->message->logMessage("[DEBUG]","Called: $this->email has {$row['badlogins']} bad logins");
                else:
                    trigger_error("[ERROR] Class " .__METHOD__ . " ".__LINE__,"- Other failure: Error: " . $this->db->error, E_USER_ERROR);
                endif;
            else:
                $this->status = 0;
                trigger_error("[ERROR] Class " .__METHOD__ . " ".__LINE__,"- SQL failure: Error: " . $this->db->error, E_USER_ERROR);
            endif;
        endif;
        $this->message->logMessage("[DEBUG]","Returning {$this->badlogincount['code']}");
        return $this->badlogincount;
    }
    
    public function IncrementBadLogin() {
        $this->message->logMessage('[ERROR]',"Incrementing bad login count for $this->email...");
        $query =   "UPDATE users 
                    SET  badlogins = CASE WHEN badlogins IS NULL
                                               THEN 1
                                               ELSE badlogins+1
                                               END
                    WHERE email=?";
        if ($this->db->execute_query($query, [$this->email]) !== TRUE):
            trigger_error("[ERROR] Class " .__METHOD__ . " ".__LINE__," - SQL failure: Error: " . $this->db->error, E_USER_ERROR);
        else:
            $this->message->logMessage('[DEBUG]',"...sql result: {$this->db->info}");
        endif;
    }    
    
    public function ZeroBadLogin() {
        $query = "UPDATE users SET  badlogins = 0 WHERE email=?";
        if ($this->db->execute_query($query, [$this->email]) !== TRUE):
            $this->message->logMessage('[ERROR]',"Resetting bad login count failed");
        else:
            $this->message->logMessage('[DEBUG]',"Reset bad login count to 0: {$this->db->info}");
        endif;
    }
    
    public function TriggerLocked() {
        $status = 'locked';
        $query = "UPDATE users SET status=? WHERE email=?";
        if ($this->db->execute_query($query, [$status,$this->email]) !== TRUE):
            $this->message->logMessage('[ERROR]',"Locking account $this->email failed");
        else:
            $this->message->logMessage('[DEBUG]',"Locking account $this->email: {$this->db->info}");
        endif;
    }    
    
    public function __toString() {
        $this->message->logMessage("[ERROR]","Called as string");
        return "Called as a string";
    }
}
