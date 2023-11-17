<?php
/* Version:     1.0
    Date:       16/11/23
    Name:       sessionManager.class.php
    Purpose:    Simple check login class, gets user details 
                or forces session destroy and return to login.php
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
    private $adminip;
    private $session;
    private $sessionArray = [];
    
    const ADMIN_OK = 1;
    const ADMIN_WRONG_LOCATION = 2;
    const ADMIN_NONE = 3;
    
    public function __construct($db,$adminip,$session) {
        $this->db = $db;
        $this->adminip = $adminip;
        $this->session = $session;
        $this->sessionArray = [
            'usernumber' => '',
            'username' => '',
            'admin' => self::ADMIN_NONE,
            'grpinout' => '',
            'groupid' => '',
            'collection_view' => '',
            'table' => ''
        ];
    }

    private function addToSessionArray($data) {
        $this->sessionArray = array_merge($this->sessionArray, $data);
    }
    
    public function checkLogged()
    {
        // Get user status and info
        $userNumber = $this->session['user'];
        $query = "SELECT status, username, admin, grpinout, groupid, collection_view FROM users WHERE usernumber = ?";

        $stmt = $this->db->prepare($query);
        $stmt->bind_param("s", $userNumber);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($status, $username, $adminDb, $grpinout, $groupid, $collection_view);
            
        if ($stmt->error OR $stmt->num_rows === 0 OR $status === '' OR $status === 'disabled' OR $status === 'locked'):
            $stmt->close();
            session_destroy();
            header("Location: /login.php");
            exit();
        else:
            $stmt->fetch();
            $stmt->close();
            if($adminDb):                                       //Boolean true in db
                $adminArray = $this->checkAdmin($adminDb); 
            else:                                               //Boolean false in dB
                $adminArray = self::ADMIN_NONE;
            endif;
            $mytable = $userNumber . "collection";

            $this->addToSessionArray([
                'usernumber' => $userNumber,
                'username' => $username,
                'admin' => $adminArray,
                'grpinout' => $grpinout,
                'groupid' => $groupid,
                'collection_view' => $collection_view,
                'table' => $mytable
            ]);
        endif;

        return $this->sessionArray;
    }
    
    private function checkAdmin($adminDb) 
    { 
        // Check for Session variable for admin access. Every page load rechecks this
        if ($adminDb):
            if (($this->adminip === 1) OR ($this->adminip === $_SERVER['REMOTE_ADDR'])):
                //Admin and secure location, or Admin and admin IP set to ''
                return self::ADMIN_OK;
            else:
                //Admin but not a secure location
                return self::ADMIN_WRONG_LOCATION;
            endif;
        endif;
        return self::ADMIN_NONE;
    }
}

