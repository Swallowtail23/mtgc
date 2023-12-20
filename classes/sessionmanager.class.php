<?php
/* Version:     1.1
    Date:       27/11/23
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
        $this->message = new Message();
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
        global $myURL;
        
        // Get user status and info for logged-in user, and currency fx rate if set
        $userNumber = $this->session['user'];
        $query = "SELECT status, username, admin, grpinout, groupid, collection_view, currency FROM users WHERE usernumber = ?";
        $stmt = $this->db->prepare($query);
        if ($stmt === false):
            $this->message->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Prepare failed: " . $this->db->error,$this->logfile);
            return false;
        endif;
        $stmt->bind_param("s", $userNumber);
        if (!$stmt->execute()):
            $this->message->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Execute failed: " . $this->db->error,$this->logfile);
            return false;
        endif;
        $stmt->store_result();
        if ($stmt->num_rows === 0):
            $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"No records found for usernumber: $userNumber",$this->logfile);
            return false;
        endif;
        $stmt->bind_result($status, $username, $adminDb, $grpinout, $groupid, $collection_view, $currency);
        if ($stmt->fetch()):
            $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"User status: $status, $username, $adminDb, $grpinout, $groupid, $collection_view, $currency",$this->logfile);
        else:
            $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Fetch failed",$this->logfile);
        endif;
        
        if ($stmt->error OR $stmt->num_rows === 0 OR $status === '' OR $status === 'disabled' OR $status === 'locked'):
            $stmt->close();
            session_destroy();
            header("Location: /login.php");
            exit();
        else:
            if ($status === 'chgpwd'):
                if ($_SERVER['REQUEST_URI'] == '/profile.php' && isset($_SESSION['just_logged_in'])):
                    unset($_SESSION['just_logged_in']); // Clear the flag
                else:
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

            if(isset($this->fxAPI) AND $this->fxAPI !== NULL AND $this->fxAPI !== ""): // fx API key is globally present
                $fx = TRUE;
                $defaultLocalCurrency = $this->fxLocal;
                $userLocalCurrency = $currency;
                if(isset($userLocalCurrency) AND $userLocalCurrency !== NULL AND $userLocalCurrency !== ""): //Does user have a currency set?
                    $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"User has currency set: $userLocalCurrency",$this->logfile);
                    $currencies = "usd_".$userLocalCurrency;
                elseif(isset($defaultLocalCurrency) AND $defaultLocalCurrency !== NULL AND $defaultLocalCurrency !== ""): //...else use default
                    $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"No user currency set, using default: $defaultLocalCurrency",$this->logfile);
                    $currencies = "usd_".$defaultLocalCurrency;
                else:                                                                                       ///... else disable fx 
                    $fx = FALSE;
                endif;
                list($baseCurrency, $targetCurrency) = array_map('strtoupper', explode('_', $currencies));
                if($baseCurrency === $targetCurrency):
                    $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Base currency same as target, disabling conversion",$this->logfile);
                    $fx = FALSE;
                else:
                    $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Currency conversion from $baseCurrency to $targetCurrency",$this->logfile);
                endif;
            else:
                $fx = FALSE;
                $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"FX conversion disabled (1)",$this->logfile);
            endif;
            if(isset($fx) AND $fx === TRUE):
                $rate = $this->getRateForCurrencyPair($currencies);
                if($rate === NULL):
                    $fx = FALSE;
                    $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"FX conversion disabled (2)",$this->logfile);
                else:
                    $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Conversion rate for $currencies is $rate",$this->logfile);
                endif;
            else:
                $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"FX conversion disabled (3)",$this->logfile);
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
        $this->message->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": Called for $currencies", $this->logfile);
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
            $this->message->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": Existing rate age is $age", $this->logfile);
            if ($lastUpdateTime === null OR $age > 3600) :
                $rate = $this->updateFxRate($currencies);
                if($rate === NULL):
                    $this->message->MessageTxt('[ERROR]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": API has not provided a rate", $this->logfile);
                    return $rate;
                else:
                    $this->message->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": Updating... new rate is $rate", $this->logfile);
                endif;
            else :
                $rate = $existingRate; // Keep the existing rate from the database
                $this->message->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": Not updating... rate is $rate", $this->logfile);
            endif;
        elseif ($stmt->num_rows === 0) :
            $rate = $this->updateFxRate($currencies);
            if($rate === NULL):
                $this->message->MessageTxt('[ERROR]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": API has not provided a rate", $this->logfile);
                return $rate;
            else:
                $this->message->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": New currency pair... rate is $rate", $this->logfile);
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
            $this->message->MessageTxt('[NOTICE]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": FreecurrencyAPI call, $baseCurrency to $targetCurrency is $fxResult", $this->logfile);
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
                $obj = new Message;
                $obj->MessageTxt('[NOTICE]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": FreecurrencyAPI call, database updated", $this->logfile);
            else :
                $obj = new Message;
                $obj->MessageTxt('[ERROR]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": FreecurrencyAPI call, database update failed: " . $stmt->error, $this->logfile);
            endif;
            // Closing the statement
            $stmt->close();
            return $fxResult;
        else:
            $this->message->MessageTxt('[ERROR]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": FreecurrencyAPI call failed for $targetCurrency", $this->logfile);
            return null;
        endif;
    }

    private function updateFxRateIfNeeded($currencies)
    {
        // Check the timestamp in the 'fx' table
        $lastUpdateTime = $this->getLastUpdateTime($currencies);

        // If the timestamp is more than an hour old, proceed with the update
        if ($lastUpdateTime === null || (time() - $lastUpdateTime) > 3600) {
            return $this->updateFxRate($currencies);
        }

        return null; // Return null if no update needed
    }

    private function getLastUpdateTime($currencies)
    {
        $query = "SELECT updatetime FROM fx WHERE currencies = ? ORDER BY updatetime DESC LIMIT 1";

        $stmt = $this->db->prepare($query);
        $stmt->bind_param("s", $currencies);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 0) {
            $stmt->close();
            return null; // Return null if no records found
        }

        $stmt->bind_result($lastUpdateTime);
        $stmt->fetch();
        $stmt->close();

        return strtotime($lastUpdateTime);
    }

    public function __toString() {
        $this->message->MessageTxt("[ERROR]", "Class " . __CLASS__, "Called as string");
        return "Called as a string";
    }
}

