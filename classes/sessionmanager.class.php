<?php
/* Version:     1.2
    Date:       20/01/24
    Name:       sessionManager.class.php
    Purpose:    Check login class, get user details 
                or force session destroy and return to login.php
    Notes:      - 
    To do:      -
    
    @author     Simon Wilson <simon@simonandkate.net>
    @copyright  2023 Simon Wilson
    
 *  1.0
                Initial version
 *  1.1
 *              27/11/23
 *              Brought in fx logic to userinfo method, and renamed from checkLogged to getUserInfo
 * 
    1.2         20/01/24
 *              Move to logMessage
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;

class SessionManager {
    private $db;
    private $adminip;
    private $session;
    private $fxAPI;
    private $fxLocal;
    private $sessionArray = [];
    private $logfile;
    private $message;
    
    const ADMIN_OK = 1;
    const ADMIN_WRONG_LOCATION = 2;
    const ADMIN_NONE = 3;
    
    public function __construct($db,$adminip,$session, $fxAPI, $fxLocal, $logfile) {
        $this->db = $db;
        $this->adminip = $adminip;
        $this->session = $session;
        $this->fxAPI = $fxAPI;
        $this->fxLocal = $fxLocal;
        $this->logfile = $logfile;
        $this->message = new Message($this->logfile);
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
    
    public function getUserInfo()
    {
        // Get user status and info for logged-in user, and currency fx rate if set
        $userNumber = $this->session['user'];
        $query = "SELECT status, username, admin, grpinout, groupid, collection_view, currency FROM users WHERE usernumber = ?";
        $stmt = $this->db->prepare($query);
        if ($stmt === false):
            $this->message->logMessage('[ERROR]',"Prepare failed: ".$this->db->error);
            return false;
        endif;
        $stmt->bind_param("s", $userNumber);
        if (!$stmt->execute()):
            $this->message->logMessage('[ERROR]',"Execute failed: ".$this->db->error);
            return false;
        endif;
        $stmt->store_result();
        if ($stmt->num_rows === 0):
            $this->message->logMessage('[DEBUG]',"No records found for usernumber: $userNumber");
            return false;
        endif;
        $stmt->bind_result($status, $username, $adminDb, $grpinout, $groupid, $collection_view, $currency);
        if ($stmt->fetch()):
            $this->message->logMessage('[DEBUG]',"User status: $status, $username, $adminDb, $grpinout, $groupid, $collection_view, $currency");
        else:
            $this->message->logMessage('[DEBUG]',"Fetch failed");
        endif;
        
        if ($stmt->error OR $stmt->num_rows === 0 OR $status === '' OR $status === 'disabled' OR $status === 'locked'):
            $stmt->close();
            session_destroy();
            header("Location: /login.php");
            exit();
        else:
            if ($status === 'chgpwd'):
                if ($_SERVER['REQUEST_URI'] == '/profile.php' && isset($_SESSION['just_logged_in'])):
                    // First visit to profile.php will clear the flag
                    unset($_SESSION['just_logged_in']);
                elseif ($_SERVER['REQUEST_URI'] == '/profile.php'):
                    // Subsequent vists to profile.php with chgpwd set OK - allows password form submit to complete
                else:
                    // any other page, destroy the session and logout
                    session_destroy();
                    header("Location: /login.php");
                    exit();
                endif;
            else:
                unset($_SESSION['just_logged_in']); // Clear the flag
            endif;
            $stmt->fetch();
            $stmt->close();
            if($adminDb):                                       //Boolean true in db
                $adminArray = $this->checkAdmin($adminDb); 
            else:                                               //Boolean false in dB
                $adminArray = self::ADMIN_NONE;
            endif;
            $mytable = $userNumber . "collection";

            if(isset($this->fxAPI) AND $this->fxAPI !== NULL AND $this->fxAPI !== "" AND $this->fxAPI !== "disabled"): // fx API key is globally present
                $fx = TRUE;
                $defaultLocalCurrency = $this->fxLocal;
                $userLocalCurrency = $currency;
                if(isset($userLocalCurrency) AND $userLocalCurrency !== NULL AND $userLocalCurrency !== ""): //Does user have a currency set?
                    $this->message->logMessage('[DEBUG]',"User has currency set: $userLocalCurrency");
                    $currencies = "usd_".$userLocalCurrency;
                elseif(isset($defaultLocalCurrency) AND $defaultLocalCurrency !== NULL AND $defaultLocalCurrency !== ""): //...else use default
                    $this->message->logMessage('[DEBUG]',"No user currency set, using default: $defaultLocalCurrency");
                    $currencies = "usd_".$defaultLocalCurrency;
                else:                                                                                       ///... else disable fx 
                    $fx = FALSE;
                endif;
                list($baseCurrency, $targetCurrency) = array_map('strtoupper', explode('_', $currencies));
                if($baseCurrency === $targetCurrency):
                    $this->message->logMessage('[DEBUG]',"Base currency same as target, disabling conversion");
                    $fx = FALSE;
                else:
                    $this->message->logMessage('[DEBUG]',"Currency conversion from $baseCurrency to $targetCurrency");
                endif;
            else:
                $fx = FALSE;
                $this->message->logMessage('[DEBUG]',"FX conversion disabled (1)");
            endif;
            if(isset($fx) AND $fx === TRUE):
                $rate = $this->getRateForCurrencyPair($currencies);
                if($rate === NULL):
                    $fx = FALSE;
                    $this->message->logMessage('[DEBUG]',"FX conversion disabled (2)");
                else:
                    $this->message->logMessage('[DEBUG]',"Conversion rate for $currencies is $rate");
                endif;
            else:
                $this->message->logMessage('[DEBUG]',"FX conversion disabled (3)");
                $rate = FALSE;
            endif;
            $this->addToSessionArray([
                'usernumber' => $userNumber,
                'username' => $username,
                'admin' => $adminArray,
                'grpinout' => $grpinout,
                'groupid' => $groupid,
                'collection_view' => $collection_view,
                'table' => $mytable,
                'fx' => $fx,
                'currency' => $targetCurrency,
                'rate' => $rate
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

    public function getRateForCurrencyPair($currencies)
    {
        $this->message->logMessage('[DEBUG]',"Called for $currencies");
        // Ensure $currencies is safe to use in the query (sanitize if necessary)
        $query = "SELECT rate, updatetime FROM fx WHERE currencies = ?";

        $stmt = $this->db->prepare($query);
        $stmt->bind_param("s", $currencies);
        $stmt->execute();
        $stmt->store_result();

        $rate = null; // Default rate value

        if ($stmt->num_rows > 0) :
            $stmt->bind_result($existingRate, $lastUpdateTime);
            $stmt->fetch();
            // If the timestamp is more than an hour old, proceed with the update
            $age = time() - $lastUpdateTime;
            $this->message->logMessage('[DEBUG]',"Existing rate age is $age");
            if ($lastUpdateTime === null OR $age > 3600) :
                $rate = $this->updateFxRate($currencies);
                if($rate === NULL):
                    $this->message->logMessage('[ERROR]',"API has not provided a rate");
                    return $rate;
                else:
                    $this->message->logMessage('[DEBUG]',"Updating... new rate is $rate");
                endif;
            else :
                $rate = $existingRate; // Keep the existing rate from the database
                $this->message->logMessage('[DEBUG]',"Not updating... rate is $rate");
            endif;
        elseif ($stmt->num_rows === 0) :
            $rate = $this->updateFxRate($currencies);
            if($rate === NULL):
                $this->message->logMessage('[ERROR]',"API has not provided a rate");
                return $rate;
            else:
                $this->message->logMessage('[DEBUG]',"New currency pair... rate is $rate");
            endif;
        endif;

        $stmt->close();

        return $rate;
    }

    private function updateFxRate($currencies)
    {
        $freecurrencyapi = new \FreeCurrencyApi\FreeCurrencyApi\FreeCurrencyApiClient($this->fxAPI);
        list($baseCurrency, $targetCurrency) = array_map('strtoupper', explode('_', $currencies));
        $freecurrencyData = $freecurrencyapi->latest(['base_currency' => "$baseCurrency",'currencies' => "$targetCurrency",]);
        if (isset($freecurrencyData["data"][$targetCurrency])):
            $fxResult = $freecurrencyData["data"]["$targetCurrency"];
            $this->message->logMessage('[NOTICE]',"FreecurrencyAPI call, $baseCurrency to $targetCurrency is $fxResult");
            $time = time();
            $stmt = $this->db->prepare("
                INSERT INTO fx (updatetime, rate, currencies)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE
                updatetime = ?,
                rate = ?
            ");
            // Binding parameters
            $stmt->bind_param("sssss", $time, $fxResult, $currencies, $time, $fxResult);
            if ($stmt->execute()) :
                $this->message->logMessage('[NOTICE]',"FreecurrencyAPI call, database updated");
            else :
                $this->message->logMessage('[ERROR]',"FreecurrencyAPI call, database update failed: ".$stmt->error);
            endif;
            // Closing the statement
            $stmt->close();
            return $fxResult;
        else:
            $this->message->logMessage('[ERROR]',"FreecurrencyAPI call failed for $targetCurrency");
            return null;
        endif;
    }
    
    /**
     * Regenerate session ID to help prevent session fixation attacks
     * 
     * This method should be called after authentication events such as:
     * - Successful login
     * - Password changes
     * - Email changes
     * - Privilege/role changes
     * 
     * @param bool $deleteOldSession Whether to delete data from old session
     * @return bool Success of operation
     */

    public function __toString() {
        $this->message->logMessage("[ERROR]","Called as string");
        return "Called as a string";
    }
}

