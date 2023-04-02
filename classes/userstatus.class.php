<?php
/* Version:     3.0
    Date:       28/03/2023
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
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;

class UserStatus {

    public $status;
    public $badlogincount;
    
    public function GetUserStatus($email) {
        /**
         * Returns: 
         * 0 for error
         * 1 for password change required
         * 2 for locked
         * 3 for disabled
         * 10 for active
         */
        global $logfile;
        if (!isset($email)):
            $msg = new Message;
            $msg->MessageTxt("[ERROR]", "Class " .__METHOD__ . " ".__LINE__," called without correct parameters",$logfile);
            return $this->status['code'] = 0;
        else:
            global $db;
            $email = "'".($db->escape($email))."'";
            if($row = $db->select('status,usernumber,admin','users',"WHERE email=$email LIMIT 1")):
                if ($row->num_rows === 0):
                    $msg = new Message;
                    $msg->MessageTxt("[ERROR]", "Class " .__METHOD__ . " ".__LINE__," called with invalid email address $email",$logfile);
                    $this->status['code'] = 0;
                elseif ($row->num_rows === 1):
                    $row = $row->fetch_assoc();
                    $status = $row['status'];
                    $usernumber = $row['usernumber'];
                    $this->status['number'] = $usernumber;
                    $adminrights = $row['admin'];
                    $this->status['admin'] = $adminrights;
                    if ($status == 'active') :
                        $msg = new Message;
                        $msg->MessageTxt("[DEBUG]", "Class " .__METHOD__ . " ".__LINE__," - user $email is active",$logfile);
                        $this->status['code'] = 10;
                    elseif ($status == 'disabled') :
                        $msg = new Message;
                        $msg->MessageTxt("[DEBUG]", "Class " .__METHOD__ . " ".__LINE__," - user $email is disabled",$logfile);
                        $this->status['code'] = 3;
                    elseif ($status == 'locked') :
                        $msg = new Message;
                        $msg->MessageTxt("[DEBUG]", "Class " .__METHOD__ . " ".__LINE__," - user $email is locked",$logfile);
                        $this->status['code'] = 2;
                    elseif ($status == 'chgpwd') :
                        $msg = new Message;
                        $msg->MessageTxt("[DEBUG]", "Class " .__METHOD__ . " ".__LINE__," - user $email needs to change password",$logfile);
                        $this->status['code'] = 1;
                    else:
                        $msg = new Message;
                        $msg->MessageTxt("[DEBUG]", "Class " .__METHOD__ . " ".__LINE__," - user $email unknown status",$logfile);
                        $this->status['code'] = 0;
                    endif;
                else:
                    trigger_error("[ERROR] Class " .__METHOD__ . " ".__LINE__," - Other failure: Error: " . $db->error, E_USER_ERROR);
                endif;
            else:
                $this->status = 0;
                trigger_error("[ERROR] Class " .__METHOD__ . " ".__LINE__," - SQL failure: Error: " . $db->error, E_USER_ERROR);
            endif;
        endif;
        return $this->status;
    }
    
    public function GetBadLogin($email) {
        global $logfile;
        if (!isset($email)):
            $msg = new Message;
            $msg->MessageTxt("[ERROR]", "Class " .__METHOD__ . " ".__LINE__," called without correct parameters",$logfile);
            return $this->badlogincount['code'] = 0;
        else:
            global $db;
            $email = "'".($db->escape($email))."'";
            if($row = $db->select('badlogins','users',"WHERE email=$email LIMIT 1")):
                if ($row->num_rows === 0):
                    $msg = new Message;
                    $msg->MessageTxt("[ERROR]", "Class " .__METHOD__ . " ".__LINE__," called with invalid email address $email",$logfile);
                    $this->badlogincount['code'] = 0;
                    $this->badlogincount['count'] = null;
                elseif ($row->num_rows === 1):
                    $row = $row->fetch_assoc();
                    $this->badlogincount['code'] = 1;    
                    if(is_null($row['badlogins'])):
                        $row['badlogins'] = 0;
                    endif;
                    $this->badlogincount['count'] = $row['badlogins'];
                    $msg = new Message;
                    $msg->MessageTxt("[DEBUG]", "Class " .__METHOD__ . " ".__LINE__," called: $email has {$row['badlogins']} bad logins",$logfile);
                else:
                    trigger_error("[ERROR] Class " .__METHOD__ . " ".__LINE__," - Other failure: Error: " . $db->error, E_USER_ERROR);
                endif;
            else:
                $this->status = 0;
                trigger_error("[ERROR] Class " .__METHOD__ . " ".__LINE__," - SQL failure: Error: " . $db->error, E_USER_ERROR);
            endif;
        endif;
        $msg->MessageTxt("[DEBUG]", "Class " .__METHOD__ . " ".__LINE__," Returning {$this->badlogincount['code']}",$logfile);
        return $this->badlogincount;
    }
    
    public function IncrementBadLogin($email) {
        global $db,$logfile;
        $obj = new Message;$obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Incrementing bad login count for $email...",$logfile);
        $query =   "UPDATE users 
                    SET  badlogins = CASE WHEN badlogins IS NULL
                                               THEN 1
                                               ELSE badlogins+1
                                               END
                    WHERE email=?";
        if ($db->execute_query($query, [$email]) !== TRUE):
            trigger_error("[ERROR] Class " .__METHOD__ . " ".__LINE__," - SQL failure: Error: " . $db->error, E_USER_ERROR);
        else:
            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": ...sql result: {$db->info}",$logfile);
        endif;
    }    
    
    public function ZeroBadLogin($email) {
        global $db,$logfile;
        $query = "UPDATE users SET  badlogins = 0 WHERE email=?";
        if ($db->execute_query($query, [$email]) !== TRUE):
            $obj = new Message;$obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Resetting bad login count failed",$logfile);
        else:
            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Reset bad login count to 0: {$db->info}",$logfile);
        endif;
    }
    
    public function TriggerLocked($email) {
        global $db,$logfile;
        $status = 'locked';
        $query = "UPDATE users SET status=? WHERE email=?";
        if ($db->execute_query($query, [$status,$email]) !== TRUE):
            $obj = new Message;$obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Locking account $email failed",$logfile);
        else:
            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Locking account $email: {$db->info}",$logfile);
        endif;
    }    
    
    public function __toString() {
        $msg = new Message;
        $msg->MessageTxt("[ERROR]", "Class " . __CLASS__, "Called as string",$logfile);
        return "Called as a string";
    }

}
