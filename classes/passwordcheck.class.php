<?php
/* Version:     2.1
    Date:       20/01/24
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
 *  
    2.1         20/01/24
 *              Move to logMessage
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
        $this->message = new Message($this->logfile);
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
            $this->message->logMessage("[DEBUG]","Called without correct parameters");
            return $this->passwordvalidate = 0;
        else:
            if($row = $this->db->execute_query("SELECT password FROM users WHERE email = ? LIMIT 1",[$email])):
                if ($row->num_rows === 0):
                    $this->message->logMessage("[DEBUG]","Invalid email address, returning 1");
                    $this->passwordvalidate = 1;
                elseif ($row->num_rows === 1):
                    $row = $row->fetch_assoc();
                        $db_password = $row['password'];
                        if (password_verify($password, $db_password)):
                            $this->message->logMessage("[DEBUG]","Email and password validated for $email, returning 10");
                            $this->passwordvalidate = 10;
                        else:
                            $this->message->logMessage("[NOTICE]","Valid email, invalid password, returning 2");
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

    public function passwordReset($email,$admin, $dbname) 
    {
        global $serveremail, $adminemail;
        if (!isset($email)):
            $this->message->logMessage("[DEBUG]","Called without target account");
            return 0;
            exit;
        elseif($admin !== 1):
            $this->message->logMessage("[DEBUG]","Called by non-admin user");
            return 0;      
            exit;
        else:
            if($row = $this->db->execute_query("SELECT username, email FROM users WHERE email = ? LIMIT 1",[$email])):
                if ($row->num_rows === 0):
                    $this->message->logMessage("[DEBUG]","Invalid email address");
                    return 0;
                    exit;
                elseif ($row->num_rows === 1): // $email matches a user
                    $row = $row->fetch_assoc();
                    $username = $row['username'];
                    $randompassword = $this->generateRandomPassword(12);
                    $this->message->logMessage("[DEBUG]","New password generated for $email, $username");
                    $reset = $this->newUser($username, $email, $randompassword, $dbname);
                    $this->message->logMessage("[DEBUG]","Newuser result: $reset");
                    if($reset === 1):
                        $from = "From: $serveremail\r\nReturn-path: $serveremail"; 
                        $subject = "Password reset"; 
                        $message = "$randompassword";
                        mail($email, $subject, $message, $from); 
                    elseif($reset === 0):
                        $from = "From: $serveremail\r\nReturn-path: $serveremail"; 
                        $subject = "Password reset failed";
                        $message = "Password reset failed for $username / $email";
                        mail($adminemail, $subject, $message, $from); 
                    endif;
                else:
                    trigger_error("[ERROR] Class Passwords: passwordReset - Other failure: Error: " . $this->db->error, E_USER_ERROR);
                    return 0;
                    exit;
                endif;
            else:
                trigger_error("[ERROR] Class Passwords: passwordReset - SQL failure: Error: " . $this->db->error, E_USER_ERROR);
                return 0;
                exit;
            endif;
        endif;
        return 1;
    }
    
    private function generateRandomPassword($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ&$@^*-_';
        $charactersLength = strlen($characters);
        $randomPassword = '';
        for ($i = 0; $i < $length; $i++) {
            $randomPassword .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomPassword;
    }
    
    public function newUser($username, $postemail, $password = '', $dbname = '') 
    {
        global $serveremail, $adminemail;
        $msg = new Message($this->logfile);
        $mysql_date = date( 'Y-m-d' );
        if($password === ''):
            $noSuppliedPW = TRUE;
            $password = $this->generateRandomPassword();
        else:
            $noSuppliedPW = FALSE;
        endif;
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $query = "INSERT INTO users (username, reg_date, email, password, status, groupid, grpinout) 
                    VALUES (?, ?, ?, ?, 'chgpwd', 1, 0) 
                    ON DUPLICATE KEY UPDATE password=?, status='chgpwd', badlogins=0 ";
        $msg->logMessage('[NOTICE]',"New user query/password update for $username / $postemail from {$_SERVER['REMOTE_ADDR']}");
        $stmt = $this->db->prepare($query);
        if ($stmt):
            $stmt->bind_param("sssss", $username, $mysql_date, $postemail, $hashed_password, $hashed_password);
            if ($stmt->execute()):
                $affected_rows = $stmt->affected_rows;
                $msg->logMessage('[NOTICE]',"New user query from ".$_SERVER['REMOTE_ADDR']." affected $affected_rows rows");
            else:
                trigger_error("[ERROR] Class Passwords: newUser: New user query failed " . $stmt->error, E_USER_ERROR);
            endif;
            $stmt->close();
        else:
            trigger_error("[ERROR] Class Passwords: newUser: New user query failed to prepare statement " . $this->db->error, E_USER_ERROR);
        endif;

        // Retrieve the new user to confirm that it has written OK
        $query_select = "SELECT password, username, usernumber FROM users WHERE email=?";
        $stmt_select = $this->db->prepare($query_select);
        $stmt_select->bind_param("s", $postemail);

        if ($stmt_select->execute()):
            $stmt_select->store_result();
            $stmt_select->bind_result($db_password, $db_username, $db_usernumber);

            if ($stmt_select->fetch()):
                if (password_verify($password, $db_password)):
                    // User has been created OK
                    $msg->logMessage('[NOTICE]',"User creation successful, password matched");
                    $usersuccess = 1;

                    // Create the user's database table
                    $mytable = "{$db_usernumber}collection";

                    // Does it already exist
                    $queryexists = "SHOW TABLES FROM $dbname LIKE '$mytable'";
                    $stmt_exists = $this->db->prepare($queryexists);

                    if ($stmt_exists->execute()):
                        $stmt_exists->store_result();
                        $collection_exists = $stmt_exists->num_rows; // $collection_exists now includes the quantity of tables with the collection name
                        $stmt_exists->close();

                        $msg->logMessage('[DEBUG]',"Collection table check returned $collection_exists rows");

                        if ($collection_exists === 0): // No existing collection table
                            $msg->logMessage('[DEBUG]',"No Collection table, creating...");
                            $query_create = "CREATE TABLE `$mytable` LIKE collectionTemplate";
                            $stmt_create = $this->db->prepare($query_create);

                            if ($stmt_create->execute()):
                                $msg->logMessage('[NOTICE]',"Collection table copy ok");
                                $tablesuccess = 1;
                            else:
                                $msg->logMessage('[ERROR]',"Collection table copy failed");
                                $tablesuccess = 5;
                            endif;
                            $stmt_create->close();
                        elseif ($collection_exists == -1):
                            $tablesuccess = 5;
                        else: // There is already a table with this name
                            $tablesuccess = 0;
                        endif;
                    else:
                        $msg->logMessage('[ERROR]',"Collection table check failed");
                    endif;
                else:
                    $msg->logMessage('[ERROR]',"User creation unsuccessful, password check failed, aborting");
                    $usersuccess = 0;
                endif;
            else:
                $msg->logMessage('[ERROR]',"User creation unsuccessful");
                $usersuccess = 0;
            endif;
            $stmt_select->close();
        else:
            $msg->logMessage('[ERROR]',"User creation unsuccessful");
            $usersuccess = 0;
        endif;
        
        if($usersuccess === 1 && $noSuppliedPW === TRUE):
            $from = "From: $serveremail\r\nReturn-path: $serveremail"; 
            $subject = "New account at MtG Collection"; 
            $message = "Your new password is $password";
            mail($postemail, $subject, $message, $from); 
        endif;
        
        if (($usersuccess === 1) && ($tablesuccess === 1)):
            return 2;
        elseif (($usersuccess === 1) && ($tablesuccess === 0)):
            return 1;
        elseif (($usersuccess === 1) && ($tablesuccess === 5)):
            return 5;
        else:
            return 0;
        endif;
    }
    
    public function __toString() {
        $this->message->logMessage("[ERROR]","Called as string");
        return "Called as a string";
    }
}
