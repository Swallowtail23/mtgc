<?php
/* Version:     2.0
    Date:       18/03/23
    Name:       passwordcheck.class.php
    Purpose:    Password validation class.
    Notes:      - 
    To do:      -
    
    @author     Simon Wilson <simon@simonandkate.net>
    @copyright  2016 Simon Wilson
    
 *  1.0
                Initial version
 *  2.0
 *              Moved from crypt() to password_verify()
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;

class PasswordCheck 
{
    private $db;
    private $logfile;
    private $message;

    public $passwordvalidate;

    public function __construct($db, $logfile)
    {
        $this->db = $db;
        $this->logfile = $logfile;
        $this->message = new Message();
    }

    public function PWValidate($email, $password) 
    {
        /**
         * Returns: 
         * 0 for incorrect call
         * 1 for invalid email address
         * 2 for incorrect password
         * 10 for valid email / password combination
         */
        if (!isset($email) || !isset($password)):
            $this->message->MessageTxt("[DEBUG]", "Class " .__METHOD__ . " ".__LINE__," called without correct parameters",$this->logfile);
            return $this->passwordvalidate = 0;
        else:
            if($row = $this->db->execute_query("SELECT password FROM users WHERE email = ? LIMIT 1",[$email])):
                if ($row->num_rows === 0):
                    $this->message->MessageTxt("[DEBUG]", "Class " .__METHOD__ . " ".__LINE__,"Invalid email address, returning 1",$this->logfile);
                    $this->passwordvalidate = 1;
                elseif ($row->num_rows === 1):
                    $row = $row->fetch_assoc();
                        $db_password = $row['password'];
                        if (password_verify($password, $db_password)):
                            $this->message->MessageTxt("[DEBUG]", "Class " .__METHOD__ . " ".__LINE__,"Email and password validated for $email, returning 10",$this->logfile);
                            $this->passwordvalidate = 10;
                        else:
                            $this->message->MessageTxt("[NOTICE]", "Class " .__METHOD__ . " ".__LINE__,"Valid email, invalid password, returning 2",$this->logfile);
                            $this->passwordvalidate = 2;
                        endif;
                    //endif;    
                else:
                    trigger_error("[ERROR] Class Passwords: PasswordValidate - Other failure: Error: " . $this->db->error, E_USER_ERROR);
                endif;
            else:
                $this->passwordvalidate = 0;
                trigger_error("[ERROR] Class Passwords: PasswordValidate - SQL failure: Error: " . $this->db->error, E_USER_ERROR);
            endif;
        endif;
        return $this->passwordvalidate;
    }

    public function __toString() {
        $this->message->MessageTxt("[ERROR]", "Class " . __CLASS__, "Called as string");
        return "Called as a string";
    }
}
