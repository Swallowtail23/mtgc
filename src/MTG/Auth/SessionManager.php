<?php

/*
Version:     1.16
Date:        29/04/26
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
    * @var \mysqli|object
    */
    private $db;
    /** @var string|int|null */
    private $adminip;
    /** @var array<string, mixed> */
    private array $session;
    /** @var string|null */
    private $fxAPI;
    /** @var string|null */
    private $fxLocal;
    private bool $fxPending = false;
    private bool $fxMissing = false;
    /** @var array<string, mixed> */
    private array $sessionArray = [];
    private Message $message;
    private AppConfig $appConfig;

    private const ADMIN_OK = 1;
    private const ADMIN_WRONG_LOCATION = 2;
    private const ADMIN_NONE = 3;

    /**
    * @param \mysqli|object $db
    * @param array<string, mixed> $session
    */
    public function __construct($db, array $session, AppConfig $appConfig)
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

    /**
    * @param array<string, mixed> $data
    */
    private function addToSessionArray(array $data): void
    {
        $this->sessionArray = array_merge($this->sessionArray, $data);
    }

    /**
    * @return array<string, mixed>|false
    */
    public function getUserInfo(): array|false
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
            $currencies = '';
            $targetCurrency = "usd";
            $rate = false;

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
                if ($currencies !== '') :
                    list($baseCurrency, $targetCurrency) = array_map('strtoupper', explode('_', $currencies));
                    if ($baseCurrency === $targetCurrency) :
                        $this->message->logMessage('[DEBUG]', "Base currency same as target, disabling conversion");
                        $fx = false;
                    else :
                        $this->message->logMessage(
                            '[DEBUG]',
                            "Currency conversion from $baseCurrency to $targetCurrency"
                        );
                    endif;
                endif;
            else :
                $fx = false;
                $this->message->logMessage('[DEBUG]', "FX conversion disabled (1)");
            endif;
            if (isset($fx) and $fx === true) :
                $rate = $this->getRateForCurrencyPair($currencies);
                if ($rate === null) :
                    $fx = false;
                    $this->message->logMessage('[DEBUG]', "FX conversion disabled (rate is null)");
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
                'rate' => $rate,
                'fx_pending' => $this->fxPending,
                'fx_missing' => $this->fxMissing
            ]);
        endif;
        return $this->sessionArray;
    }

    private function checkAdmin(int $adminDb): int
    {
        // Check for Session variable for admin access. Every page load rechecks this
        if ($adminDb) :
            $adminIp = (string) $this->adminip;
            $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
            if ($adminIp === '' || $adminIp === '1' || ($remoteAddr !== '' && $adminIp === $remoteAddr)) :
                //Admin and secure location, or Admin and admin IP set to ''
                return self::ADMIN_OK;
            else :
                //Admin but not a secure location
                return self::ADMIN_WRONG_LOCATION;
            endif;
        endif;
        return self::ADMIN_NONE;
    }

    public function getRateForCurrencyPair(string $currencies): float|string|null
    {
        $this->message->logMessage('[DEBUG]', "Called for $currencies");
        // Ensure $currencies is safe to use in the query (sanitize if necessary)
        $query = "SELECT rate, updatetime FROM fx WHERE currencies = ?";

        $stmt = $this->db->prepare($query);
        $stmt->bind_param("s", $currencies);
        $stmt->execute();
        $stmt->store_result();

        $rate = null; // Default rate value
        $this->fxPending = false;
        $this->fxMissing = false;

        if ($stmt->num_rows > 0) :
            /** @var float|string|null $existingRate */
            $existingRate = null;
            /** @var int|null $lastUpdateTime */
            $lastUpdateTime = null;
            $stmt->bind_result($existingRate, $lastUpdateTime);
            $stmt->fetch();
            // If the timestamp is more than an hour old, proceed with the update
            $age = $lastUpdateTime === null ? null : time() - $lastUpdateTime;
            $this->message->logMessage('[DEBUG]', "Existing rate age is $age");
            if ($lastUpdateTime === null or $age > 3600) :
                $this->fxPending = true;
                if ($existingRate === null || $existingRate === '') :
                    $this->fxMissing = true;
                    $rate = null;
                    $this->message->logMessage(
                        '[NOTICE]',
                        "No cached rate available for $currencies"
                    );
                else :
                    $rate = $existingRate;
                    $this->message->logMessage('[DEBUG]', "Using stale cached rate $rate");
                endif;
            else :
                $rate = $existingRate; // Keep the existing rate from the database
                $this->message->logMessage('[DEBUG]', "Not updating... rate is $rate");
            endif;
        elseif ($stmt->num_rows === 0) :
            $this->fxPending = true;
            $this->fxMissing = true;
            $rate = null;
            $this->message->logMessage('[NOTICE]', "No cached rate available for $currencies");
        endif;

        $stmt->close();

        return $rate;
    }

    public function refreshFxRate(string $currencies): float|string|null
    {
        if ($this->fxAPI === null || $this->fxAPI === '' || $this->fxAPI === 'disabled') :
            $this->message->logMessage('[ERROR]', 'FX refresh requested without API key');
            return null;
        endif;

        return $this->updateFxRate($currencies);
    }

    private function updateFxRate(string $currencies, float|string|null $existingRate = null): float|string|null
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
    * Optionally inject redirect/terminate handlers to allow testing without exiting the process.
    */
    public static function forcePasswordChange(
        AppConfig $appConfig,
        ?callable $redirectHandler = null,
        ?callable $terminateHandler = null
    ): void {
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

    public static function generateCsrfToken(): string
    {
        if (!isset($_SESSION['csrf_token'])) :
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        endif;

        return $_SESSION['csrf_token'];
    }

    public static function validateCsrfToken(mixed $submittedToken): bool
    {
        if (!isset($_SESSION['csrf_token']) || !is_string($submittedToken)) :
            return false;
        endif;

        return hash_equals($_SESSION['csrf_token'], $submittedToken);
    }

    public static function validateAjaxRequest(
        array $expectedReferringPages,
        AppConfig $appConfig,
        string $context = '',
        bool $requireCsrf = true
    ): array {
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
