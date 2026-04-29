<?php

/*
Version:     4.24
Date:        29/04/26
Name:        csv.php
Purpose:     Export collection and redirect from profile.php.
Notes:       Redirects to profile.php if not in SMTP debug, with flag on success/fail.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Auth\SessionManager;
use MTG\Cards\ImportExport;
use MTG\Core\Http\UrlHelper;
use MTG\Core\Validation;

// Bootstrap

$ctx                        = require __DIR__ . '/bootstrap_secure.php';

$appConfig                  = $ctx->config();
$db                         = $ctx->db();
$msg                        = $ctx->message();
$gameRules                  = $ctx->rules();
$sessionUser                = $ctx->sessionUser();

$userEmail                  = $sessionUser->email();
$mytable                    = $sessionUser->table();
$admin                      = $sessionUser->adminLevel();
$myURL                      = (string) $appConfig->general('url', '');
$smtpParameters             = $appConfig->getSmtpParameters();

// Content
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestInput = ($requestMethod === 'POST') ? INPUT_POST : INPUT_GET;
$requestType = filter_input($requestInput, 'type', FILTER_UNSAFE_RAW);
$requestType = is_string($requestType) ? trim($requestType) : '';

function requireCsvCsrfToken(int $requestInput): void
{
    $submittedToken = (string) filter_input($requestInput, 'csrf_token', FILTER_UNSAFE_RAW);
    if ($submittedToken === '' || !SessionManager::validateCsrfToken($submittedToken)) :
        http_response_code(403);
        die('CSRF check failed');
    endif;
}

if (
    !(
        ($requestMethod === 'GET' && $requestType === 'echo')
        || ($requestMethod === 'POST' && $requestType === 'email')
    )
) :
    $msg->logMessage('[DEBUG]', "csv.php called with invalid method/type '$requestMethod/$requestType'");
    throw new Exception("[ERROR] csv.php: Called with incorrect parameters");
endif;

requireCsvCsrfToken($requestInput);

$requestedTable = filter_input($requestInput, 'table', FILTER_UNSAFE_RAW);
if ($requestedTable !== null) :
    $requestedTable = trim($requestedTable);
endif;
if ($requestedTable !== null && $requestedTable !== '') :
    $msg->logMessage(
        '[DEBUG]',
        "csv.php requested table '$requestedTable' by user $userEmail, admin status $admin"
    );
    $validatedTable = Validation::validTableName($requestedTable, $appConfig);
    if ($validatedTable === false) :
        $msg->logMessage('[ERROR]', "csv.php invalid table '$requestedTable' requested by $userEmail");
        throw new Exception("[ERROR] csv.php: Invalid table requested");
    endif;
    if ($admin == 1) :
        $table = $validatedTable;
        $msg->logMessage('[DEBUG]', "csv.php admin export allowed for '$table'");
    else :
        if ($validatedTable !== $mytable) :
            $msg->logMessage(
                '[ERROR]',
                "csv.php blocked export for '$validatedTable' by $userEmail (user table '$mytable')"
            );
            throw new Exception("[ERROR] csv.php: Unauthorized table requested");
        endif;
        $table = $mytable;
        $msg->logMessage('[DEBUG]', "csv.php exporting own table '$table'");
    endif;

    $msg->logMessage('[NOTICE]', "csv.php running for '$table'");

    $obj = new ImportExport($db, $appConfig, $gameRules, $userEmail);

    // Can be called with type 'echo', 'email'
    // Difference is that 'echo' outputs to browser for download, 'email' triggers email output
    // In email mode, if SMTP is set to debug and site is in Debug log level, the SMTP output
    // will also be output to screen
    if ($requestType === 'echo') :
        $msg->logMessage('[DEBUG]', "csv.php running for '$table', output ('$requestType')");
        $obj->exportCollectionToCsv($table, $myURL, 'echo');
    elseif ($requestType === 'email') :
        $msg->logMessage('[DEBUG]', "csv.php running for '$table', output ('$requestType')");
        $mailexport = $obj->exportCollectionToCsv($table, $myURL, 'email');
        if ($smtpParameters['SMTPDebug'] !== 'SMTP::DEBUG_OFF' && $smtpParameters['globalDebug'] == 3) :
            $msg->logMessage('[DEBUG]', 'In debug, not redirecting');
        else :
            // If not in debug mode, redirect back to the calling page
            $msg->logMessage('[DEBUG]', 'Not in SMTP/site debug, redirecting back to referrer');
            $returnUrlRaw = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
            $returnUrl = '/profile.php';
            $expectedHost = parse_url($myURL, PHP_URL_HOST);
            if ($returnUrlRaw !== '') :
                $parsedReferrer = parse_url($returnUrlRaw);
                if ($parsedReferrer !== false) :
                    $referrerHost = $parsedReferrer['host'] ?? '';
                    if ($referrerHost === '' || $referrerHost === $expectedHost) :
                        $path = $parsedReferrer['path'] ?? '';
                        $query = $parsedReferrer['query'] ?? '';
                        $fragment = $parsedReferrer['fragment'] ?? '';
                        $pathWithQuery = $path;
                        if ($query !== '') :
                            $pathWithQuery .= '?' . $query;
                        endif;
                        if ($fragment !== '') :
                            $pathWithQuery .= '#' . $fragment;
                        endif;
                        $normalizedReturn = UrlHelper::normalizeRedirectUrl($pathWithQuery);
                        if ($normalizedReturn !== null) :
                            $returnUrl = $normalizedReturn;
                        else :
                            $msg->logMessage('[DEBUG]', 'csv.php referrer normalize failed, using profile.php');
                        endif;
                    else :
                        $msg->logMessage(
                            '[DEBUG]',
                            "csv.php referrer host mismatch ($referrerHost), using profile.php"
                        );
                    endif;
                else :
                    $msg->logMessage('[DEBUG]', 'csv.php referrer parse failed, using profile.php');
                endif;
            endif;
            $msg->logMessage('[DEBUG]', "csv.php redirecting back to $returnUrl");
            // If the mailexport was successful
            if ($mailexport === true) :
                $_SESSION['csv_status'] = 'true';
                header("Location: {$returnUrl}?csvsuccess=true");
            else :
                $_SESSION['csv_status'] = 'false';
                header("Location: {$returnUrl}?csvsuccess=false");
            endif;
            exit;
        endif;
    else :
        $msg->logMessage('[DEBUG]', "csv.php called for '$table', output type unclear ('$requestType')");
        throw new Exception("[ERROR] csv.php: Called with incorrect parameters");
    endif;
else :
    $msg->logMessage('[DEBUG]', 'csv.php running, failed');
    throw new Exception("[ERROR] csv.php: Called with no parameters");
endif;
