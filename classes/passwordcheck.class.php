<?php
/* Version:     1.0
    Date:       23/10/16
    Name:       passwordcheck.class.php
    Purpose:    Password validation class.
    Notes:      - 
    To do:      -
    
    @author     Simon Wilson <simon@simonandkate.net>
    @copyright  2016 Simon Wilson
    
 *  1.0
                Initial version
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;

class PasswordCheck {

    public $passwordvalidate;

    public function PWValidate($email, $password, $Blowfish_Pre, $Blowfish_End) {
        /**
         * Returns: 
         * 0 for incorrect call
         * 1 for invalid email address
         * 2 for incorrect password
         * 10 for valid email / password combination
         */
        global $logfile;
        if (!isset($email) || !isset($password) || !isset($Blowfish_Pre) || !isset($Blowfish_End)):
            $msg = new Message;
            $msg->MessageTxt("[DEBUG]", "Class " .__METHOD__ . " ".__LINE__," called without correct parameters",$logfile);
            return $this->passwordvalidate = 0;
        else:
            global $db;
            $email = "'".($db->escape($email))."'";
            if($row = $db->select('salt, password','users',"WHERE email=$email LIMIT 1")):
                if ($row->num_rows === 0):
                    $msg = new Message;
                    $msg->MessageTxt("[DEBUG]", "Class " .__METHOD__ . " ".__LINE__,"Invalid email address",$logfile);
                    $this->passwordvalidate = 1;
                elseif ($row->num_rows === 1):
                    $row = $row->fetch_assoc();
                    $hashed_pass = crypt($password, $Blowfish_Pre . $row['salt'] . $Blowfish_End);
                    //if(PHP_MAJOR_VERSION < 7):
                    //    $msg = new Message;$msg->MessageTxt("[DEBUG]", "Class " .__METHOD__ . " ".__LINE__,"PHP < 7, falling back for password comparison",$logfile);
                    //    if ($hashed_pass != $row['password']) :
                    //        $msg = new Message;
                    //        $msg->MessageTxt("[NOTICE]", "Class " .__METHOD__ . " ".__LINE__,"Invalid password",$logfile);
                    //        $this->passwordvalidate = 2;
                    //    else:
                    //        $msg = new Message;
                    //        $msg->MessageTxt("[DEBUG]", "Class " .__METHOD__ . " ".__LINE__,"Email and password validated for $email",$logfile);
                    //        $this->passwordvalidate = 10;
                    //    endif;
                    //else:
                        if(hash_equals($row['password'],$hashed_pass) != true):
                            $msg = new Message;
                            $msg->MessageTxt("[NOTICE]", "Class " .__METHOD__ . " ".__LINE__,"Invalid password",$logfile);
                            $this->passwordvalidate = 2;
                        else:
                            $msg = new Message;
                            $msg->MessageTxt("[DEBUG]", "Class " .__METHOD__ . " ".__LINE__,"Email and password validated for $email",$logfile);
                            $this->passwordvalidate = 10;
                        endif;
                    //endif;    
                else:
                    trigger_error("[ERROR] Class Passwords: PasswordValidate - Other failure: Error: " . $db->error, E_USER_ERROR);
                endif;
            else:
                $this->passwordvalidate = 0;
                trigger_error("[ERROR] Class Passwords: PasswordValidate - SQL failure: Error: " . $db->error, E_USER_ERROR);
            endif;
        endif;
        return $this->passwordvalidate;
    }

    public function __toString() {
        $msg = new Message;
        $msg->MessageTxt("[ERROR]", "Class " . __CLASS__, "Called as string");
        return "Called as a string";
    }

}
