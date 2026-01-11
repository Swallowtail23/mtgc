<?php

/*
Version:     1.10
Date:        11/01/26
Name:        SessionManager.php
Purpose:     Check login class, get user details or force session destroy and return to login.php.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

namespace MTG\Auth;

use MTG\Core\AppConfig;
use MTG\Core\Message;

class SessionManager
{
    /**
    * @var mysqli
    */
    private $db;
    private $adminip;
    private $session;
    private $fxAPI;
    private $fxLocal;
    private $sessionArray = [];
    private $message;
    private $appConfig;

    private const ADMIN_OK = 1;
    private const ADMIN_WRONG_LOCATION = 2;
    private const ADMIN_NONE = 3;

    public function __construct($db, $session, AppConfig $appConfig)
    {
        $this->db = $db;
        $this->session = $session;
        $this->appConfig = $appConfig;
        $this->adminip = $this->appConfig->security('adminIp', '');
        $this->fxAPI = $this->appConfig->fx('api', '');
        $this->fxLocal = $this->appConfig->fx('local', '');
        $this->message = new Message($this->appConfig);
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

    private function addToSessionArray($data)
    {
        $this->sessionArray = array_merge($this->sessionArray, $data);
    }

    public function getUserInfo()
    {
        // Get user status and info for logged-in user, and currency fx rate if set
        $userNumber = $this->session['user'];
        $query = "SELECT status, username, admin, grpinout, groupid, collection_view, currency
            FROM users WHERE usernumber = ?";
        $stmt = $this->db->prepare($query);
        if ($stmt === false) :
            $this->message->logMessage('[ERROR]', "Prepare failed: " . $this->db->error);
            return false;
        endif;
        $stmt->bind_param("s", $userNumber);
        if (!$stmt->execute()) :
            $this->message->logMessage('[ERROR]', "Execute failed: " . $this->db->error);
            return false;
        endif;
        $stmt->store_result();
        if ($stmt->num_rows === 0) :
            $this->message->logMessage('[DEBUG]', "No records found for usernumber: $userNumber");
            return false;
        endif;
        /** @var string $status */
        $status = '';
        /** @var string $userName */
        $userName = '';
        /** @var int $adminDb */
        $adminDb = 0;
        /** @var string|null $grpinout */
        $grpinout = null;
        /** @var int|null $groupid */
        $groupid = null;
        /** @var string|null $collection_view */
        $collection_view = null;
        /** @var string|null $currency */
        $currency = null;
        $stmt->bind_result($status, $userName, $adminDb, $grpinout, $groupid, $collection_view, $currency);
        if ($stmt->fetch()) :
            $this->message->logMessage(
                '[DEBUG]',
                "User status: $status, $userName, $adminDb, $grpinout, $groupid, $collection_view, $currency"
            );
        else :
            $this->message->logMessage('[DEBUG]', "Fetch failed");
        endif;

        if (
            $stmt->error
            or $stmt->num_rows === 0
            or $status === ''
            or $status === 'disabled'
            or $status === 'locked'
        ) :
            $stmt->close();
            session_destroy();
            header("Location: /login.php");
            exit();
        else :
            if ($status === 'chgpwd') :
                if ($_SERVER['REQUEST_URI'] == '/profile.php' && isset($_SESSION['just_logged_in'])) :
                    // First visit to profile.php will clear the flag
                    unset($_SESSION['just_logged_in']);
                elseif ($_SERVER['REQUEST_URI'] == '/profile.php') :
                    // Subsequent vists to profile.php with chgpwd set OK - allows password form submit to complete
                else :
                    // any other page, destroy the session and logout
                    session_destroy();
                    header("Location: /login.php");
                    exit();
                endif;
            else :
                unset($_SESSION['just_logged_in']); // Clear the flag
            endif;
            $stmt->fetch();
            $stmt->close();
            if ($adminDb) :                                       //Boolean true in db
                $adminArray = $this->checkAdmin($adminDb);
            else :                                               //Boolean false in dB
                $adminArray = self::ADMIN_NONE;
            endif;
            $mytable = $userNumber . "collection";

            if (isset($this->fxAPI) and $this->fxAPI !== null and $this->fxAPI !== "" and $this->fxAPI !== "disabled") :
                $fx = true;
                $defaultLocalCurrency = $this->fxLocal;
                $userLocalCurrency = $currency;
                if (
                    isset($userLocalCurrency)
                    and $userLocalCurrency !== null
                    and $userLocalCurrency !== ""
                ) : //Does user have a currency set?
                    $this->message->logMessage('[DEBUG]', "User has currency set: $userLocalCurrency");
                    $currencies = "usd_" . $userLocalCurrency;
                elseif (
                    isset($defaultLocalCurrency)
                    and $defaultLocalCurrency !== null
                    and $defaultLocalCurrency !== ""
                ) : //...else use default
                    $this->message->logMessage('[DEBUG]', "No user currency set, using default: $defaultLocalCurrency");
                    $currencies = "usd_" . $defaultLocalCurrency;
                else : // else disable fx
                    $this->message->logMessage('[DEBUG]', "FX conversion disabled, no local currency required");
                    $fx = false;
                endif;
                list($baseCurrency, $targetCurrency) = array_map('strtoupper', explode('_', $currencies));
                if ($baseCurrency === $targetCurrency) :
                    $this->message->logMessage('[DEBUG]', "Base currency same as target, disabling conversion");
                    $fx = false;
                else :
                    $this->message->logMessage('[DEBUG]', "Currency conversion from $baseCurrency to $targetCurrency");
                endif;
            else :
                $fx = false;
                $this->message->logMessage('[DEBUG]', "FX conversion disabled (1)");
                $targetCurrency = "usd";
                $rate = false;
            endif;
            if (isset($fx) and $fx === true) :
                $rate = $this->getRateForCurrencyPair($currencies);
                if ($rate === null) :
                    $fx = false;
                    $this->message->logMessage('[DEBUG]', "FX conversion disabled (2)");
                else :
                    $this->message->logMessage('[DEBUG]', "Conversion rate for $currencies is $rate");
                endif;
            else :
                $this->message->logMessage('[DEBUG]', "FX conversion disabled (3)");
                $rate = false;
            endif;
            $this->addToSessionArray([
                'usernumber' => $userNumber,
                'username' => $userName,
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
        if ($adminDb) :
            if (($this->adminip === 1) or ($this->adminip === $_SERVER['REMOTE_ADDR'])) :
                //Admin and secure location, or Admin and admin IP set to ''
                return self::ADMIN_OK;
            else :
                //Admin but not a secure location
                return self::ADMIN_WRONG_LOCATION;
            endif;
        endif;
        return self::ADMIN_NONE;
    }

    public function getRateForCurrencyPair($currencies)
    {
        $this->message->logMessage('[DEBUG]', "Called for $currencies");
        // Ensure $currencies is safe to use in the query (sanitize if necessary)
        $query = "SELECT rate, updatetime FROM fx WHERE currencies = ?";

        $stmt = $this->db->prepare($query);
        $stmt->bind_param("s", $currencies);
        $stmt->execute();
        $stmt->store_result();

        $rate = null; // Default rate value

        if ($stmt->num_rows > 0) :
            /** @var float|string|null $existingRate */
            $existingRate = null;
            /** @var int|null $lastUpdateTime */
            $lastUpdateTime = null;
            $stmt->bind_result($existingRate, $lastUpdateTime);
            $stmt->fetch();
            // If the timestamp is more than an hour old, proceed with the update
            $age = time() - $lastUpdateTime;
            $this->message->logMessage('[DEBUG]', "Existing rate age is $age");
            if ($lastUpdateTime === null or $age > 3600) :
                $rate = $this->updateFxRate($currencies, $existingRate);
                if ($rate === null) :
                    $this->message->logMessage(
                        '[ERROR]',
                        "API has not provided a rate and no cached rate available for $currencies"
                    );
                    return $rate;
                else :
                    $this->message->logMessage('[DEBUG]', "Updating... new rate is $rate");
                endif;
            else :
                $rate = $existingRate; // Keep the existing rate from the database
                $this->message->logMessage('[DEBUG]', "Not updating... rate is $rate");
            endif;
        elseif ($stmt->num_rows === 0) :
            $rate = $this->updateFxRate($currencies);
            if ($rate === null) :
                $this->message->logMessage('[ERROR]', "API has not provided a rate");
                return $rate;
            else :
                $this->message->logMessage('[DEBUG]', "New currency pair... rate is $rate");
            endif;
        endif;

        $stmt->close();

        return $rate;
    }

    private function updateFxRate($currencies, $existingRate = null)
    {
        $freecurrencyapi = new \FreeCurrencyApi\FreeCurrencyApi\FreeCurrencyApiClient($this->fxAPI);
        list($baseCurrency, $targetCurrency) = array_map('strtoupper', explode('_', $currencies));
        try {
            $this->message->logMessage('[DEBUG]', "Requesting FX rate for $baseCurrency to $targetCurrency");
            $freecurrencyData = $freecurrencyapi->latest(
                ['base_currency' => "$baseCurrency", 'currencies' => "$targetCurrency",]
            );
        } catch (\Throwable $e) {
            $this->message->logMessage(
                '[ERROR]',
                "FreecurrencyAPI call failed for $currencies, using cached rate if available: " . $e->getMessage()
            );
            return $existingRate;
        }
        if (isset($freecurrencyData["data"][$targetCurrency])) :
            $fxResult = $freecurrencyData["data"]["$targetCurrency"];
            $this->message->logMessage(
                '[NOTICE]',
                "FreecurrencyAPI call, $baseCurrency to $targetCurrency is $fxResult"
            );
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
                $this->message->logMessage('[NOTICE]', "FreecurrencyAPI call, database updated");
            else :
                $this->message->logMessage('[ERROR]', "FreecurrencyAPI call, database update failed: " . $stmt->error);
            endif;
            // Closing the statement
            $stmt->close();
            return $fxResult;
        else :
            $this->message->logMessage('[ERROR]', "FreecurrencyAPI call failed for $targetCurrency");
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

    public function __toString()
    {
        $this->message->logMessage("[ERROR]", "Called as string");
        return "Called as a string";
    }

    /**
    * Optionally inject redirect/terminate handlers to allow testing without exiting the process.
    */
    public static function forcePasswordChange(
        AppConfig $appConfig,
        $redirectHandler = null,
        $terminateHandler = null
    ) {
        if ((isset($_SESSION["chgpwd"])) and ($_SESSION["chgpwd"] == true)) :
            $msg = new Message($appConfig);
            $msg->logMessage('[DEBUG]', 'forcePasswordChange: redirecting to profile.php');
            $target = '/profile.php';
            if (is_callable($redirectHandler)) :
                $redirectHandler($target);
            else :
                header("Location: $target");
            endif;

            if (is_callable($terminateHandler)) :
                $terminateHandler();
            else :
                exit();
            endif;
        endif;
    }

    public static function generateCsrfToken()
    {
        if (!isset($_SESSION['csrf_token'])) :
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        endif;

        return $_SESSION['csrf_token'];
    }

    public static function validateCsrfToken($submittedToken)
    {
        if (!isset($_SESSION['csrf_token']) || !is_string($submittedToken)) :
            return false;
        endif;

        return hash_equals($_SESSION['csrf_token'], $submittedToken);
    }

    public static function validateAjaxRequest(
        $expectedReferringPages,
        AppConfig $appConfig,
        $context = '',
        $requireCsrf = true
    ) {
        $msg = new Message($appConfig);
        $contextLabel = $context !== '' ? $context . ': ' : '';
        $msg->logMessage('[DEBUG]', "{$contextLabel}Ajax validation started");

        $referringPage = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        $msg->logMessage('[DEBUG]', "{$contextLabel}Referring page is: $referringPage");
        $normalizedReferringPage = str_replace('www.', '', $referringPage);

        $isValidReferrer = false;
        foreach ($expectedReferringPages as $page) :
            $normalizedPage = str_replace('www.', '', $page);
            if (strpos($normalizedReferringPage, $normalizedPage) !== false) :
                $isValidReferrer = true;
                break;
            endif;
        endforeach;

        if ($isValidReferrer === false) :
            $msg->logMessage('[DEBUG]', "{$contextLabel}Referrer validation failed");
            return [
                'valid' => false,
                'reason' => 'referrer'
            ];
        endif;

        if ($requireCsrf) :
            $submittedToken = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
            if (!is_string($submittedToken)) :
                $submittedToken = '';
            endif;

            if (!self::validateCsrfToken($submittedToken)) :
                $msg->logMessage('[DEBUG]', "{$contextLabel}CSRF validation failed");
                return [
                    'valid' => false,
                    'reason' => 'csrf'
                ];
            endif;
        endif;

        $msg->logMessage('[DEBUG]', "{$contextLabel}Ajax validation passed");
        return [
            'valid' => true,
            'reason' => ''
        ];
    }
}
