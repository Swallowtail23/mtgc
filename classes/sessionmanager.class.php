<?php
/* Version:     1.0
    Date:       16/11/23
    Name:       sessionManager.class.php
    Purpose:    Simple check login class
    Notes:      - 
    To do:      -
    
    @author     Simon Wilson <simon@simonandkate.net>
    @copyright  2023 Simon Wilson
    
 *  1.0
                Initial version
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;

class SessionManager {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }

    public function checkLogged() {
        if (session_status() == PHP_SESSION_NONE):
            session_start();
        endif;
        
        if (isset($_SESSION['user'])):
            $user = $_SESSION['user'];
        else:
            $user = '';
            header("Location: /login.php");
            exit();
        endif;

        // Check user status
        $row = $this->db->select_one('status', 'users', "WHERE usernumber='$user'");
        
        if ($row === false):
            header("Location: /login.php");
        else:
            if (!$_SESSION["logged"] == true):
                header("Location: /login.php");
                exit();
            elseif (isset($row['status']) AND (($row['status'] === 'disabled') OR ($row['status'] === 'locked'))):
                session_destroy();
                header("Location: /login.php");
                exit();
            else:
                // Need a catch here?
            endif;
        endif;

        return $user;
    }
}

