<?php

/*
Version:     28.24
Date:        10/01/26
Name:        functions.php
Purpose:     Functions for all pages
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
    die('Direct access prohibited');
endif;


function cssVersionCheck()
{
    global $db, $logfile;
    $msg = new \MTG\Core\Message($logfile);
    if (!is_object($db) or !method_exists($db, 'execute_query')) :
        $msg->logMessage(
            '[WARNING]',
            "CSS version check skipped, defaulting to minified CSS: database unavailable"
        );
        return "-min";
    endif;
    $sql = "SELECT usemin FROM admin LIMIT 1";
    $result = $db->execute_query($sql);
    if ($result === false or !is_object($result)) :
        $msg->logMessage(
            '[ERROR]',
            "CSS version check failed, defaulting to minified CSS: " . ($db->error ?? 'unknown error')
        );
        return "-min";
    else :
        $row = $result->fetch_assoc();
        if (method_exists($result, 'free')) :
            $result->free();
        endif;
        if (!empty($row) and (int) $row['usemin'] === 1) :
            return "-min";
        else :
            return "";
        endif;
    endif;
}

function normalizeRedirectUrl($url)
{
    if (!is_string($url) || $url === '') :
        return null;
    endif;

    $parsed = parse_url($url);
    if ($parsed === false) :
        return null;
    endif;

    if (isset($parsed['scheme']) || isset($parsed['host'])) :
        return null;
    endif;

    $path = $parsed['path'] ?? '';
    if ($path === '') :
        return null;
    endif;

    if ($path[0] !== '/') :
        $path = '/' . ltrim($path, '/');
    endif;

    $query = $parsed['query'] ?? '';
    $fragment = $parsed['fragment'] ?? '';
    $final = $path;
    if ($query !== '') :
        $final .= '?' . $query;
    endif;
    if ($fragment !== '') :
        $final .= '#' . $fragment;
    endif;

    return $final;
}

function setMtceMode($toggle): bool
{
    global $db, $logfile;

    $msg = new \MTG\Core\Message($logfile);

    $toggle = strtolower(trim((string) $toggle));

    if ($toggle === 'off') :
        $msg->logMessage('[NOTICE]', "Setting maintenance mode off");
        $mtcequery = 0;
    elseif ($toggle === 'on') :
        $msg->logMessage('[NOTICE]', "Setting maintenance mode on");
        $mtcequery = 1;
    else :
        $msg->logMessage('[ERROR]', "Invalid maintenance mode toggle: '{$toggle}'");
        return false;
    endif;

    $query = 'UPDATE admin SET mtce=?';

    $stmt = $db->prepare($query);
    if ($stmt === false) :
        throw new Exception(
            '[ERROR]' . basename(__FILE__) . " " . __LINE__ . " Function " . __FUNCTION__
                . ": Prepare SQL failed: " . $db->error
        );
    endif;

    $bound = $stmt->bind_param('i', $mtcequery);
    if ($bound === false) :
        $stmt->close();
        throw new Exception(
            '[ERROR]' . basename(__FILE__) . " " . __LINE__ . " Function " . __FUNCTION__
                . ": Bind SQL failed: " . $stmt->error
        );
    endif;

    $exec = $stmt->execute();
    if ($exec === false) :
        $msg->logMessage('[ERROR]', "Setting mtce mode to {$mtcequery} failed: " . $stmt->error);
        $stmt->close();
        return false;
    else :
        $msg->logMessage('[NOTICE]', "Set mtce mode to {$mtcequery}");
        $stmt->close();
        return true;
    endif;
}

function mtceModeCheck($user)
{
    global $db,$logfile;
    $msg = new \MTG\Core\Message($logfile);

    $msg->logMessage('[DEBUG]', "Checking maintenance mode, user $user");
    $sql1 = "SELECT mtce FROM admin LIMIT 1";
    $result1 = $db->execute_query($sql1);
    if ($result1 === false) :
        throw new Exception(
            '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                . ": SQL failure: " . $db->error
        );
    else :
        $row1 = $result1->fetch_assoc();
        if (!empty($row1) and $row1['mtce'] == 1) :
            $msg->logMessage('[DEBUG]', "Maintenance mode on, running admin check");
            $sql2 = "SELECT admin FROM users WHERE usernumber = ?";
            $result2 = $db->execute_query($sql2, [$user]);
            if ($result2 === false) :
                throw new Exception(
                    '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                        . ": SQL failure: " . $db->error
                );
            else :
                $row2 = $result2->fetch_assoc();
                if (!empty($row2)) :
                    if ($row2['admin'] == 1) :
                        $msg->logMessage('[DEBUG]', "Maintenance mode on, user is admin, ignoring (return 2)");
                        return 2;
                    else :
                        $msg->logMessage(
                            '[DEBUG]',
                            "Maintenance mode on, user is not admin (return 1, destroy session)"
                        );
                        return 1;
                    endif;
                else :
                    throw new Exception(
                        '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                            . ": SQL failure: " . $db->error
                    );
                endif;
            endif;
        else :
            $msg->logMessage('[DEBUG]', "Maintenance mode not set");
            return 0; // maintenance mode not set
        endif;
    endif;
}

function langReplace($str)
{
    global $search_langs;

    foreach ($search_langs as $lang) :
        if ($lang['code'] == $str) :
            return $lang['pretty'];
        endif;
    endforeach;

    return $str; // Return the original string if no match is found
}

function checkRemoteFile($url)
{
    global $logfile;
    $msg = new \MTG\Core\Message($logfile);

    if (stripos($url, 'file://') === 0) :
        $path = substr($url, 7);
        if (is_file($path) && filesize($path) > 0) :
            $msg->logMessage('[NOTICE]', "$url exists locally");
            return true;
        endif;
        $msg->logMessage('[ERROR]', "$url does not exist locally or is empty");
        return false;
    endif;

    $ch = curl_init();
    $userAgent = \MTG\Core\UserAgent::build('/opt/mtg/mtg_new.ini', null, $logfile);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_NOBODY, 1);
    curl_setopt($ch, CURLOPT_VERBOSE, 1);
    curl_setopt($ch, CURLOPT_STDERR, fopen('php://stderr', 'w'));
    curl_setopt($ch, CURLOPT_FAILONERROR, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: application/json;q=0.9,*/*;q=0.8"));
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    $curlresult = curl_exec($ch);
    $curldetail = curl_getinfo($ch);
    curl_close($ch);
    if ($curlresult === false or $curldetail['http_code'] != 200 or $curldetail['download_content_length'] < 1) :
        $msg->logMessage(
            '[ERROR]',
            "{$curldetail['url']} DOES NOT exist, HTTP code is: {$curldetail['http_code']}, "
            . "file size is: {$curldetail['download_content_length']} bytes"
        );
        return false;
    else :
        $msg->logMessage(
            '[NOTICE]',
            "{$curldetail['url']} exists, HTTP code is: {$curldetail['http_code']}, "
            . "file size is: {$curldetail['download_content_length']} bytes"
        );
        return true;
    endif;
}

function getStringParameters($input, $ignore1, $ignore2 = '')
{
    $params = array();

    foreach ($input as $key => $value) :
        if ($key === $ignore1 || $key === $ignore2) :
            continue;
        endif;

        if ($key === 'layout') :
            $validlayouts = array('grid', 'list', 'bulk', '');
            if (!in_array($value, $validlayouts, true)) :
                $value = 'grid';
            endif;
            $params[$key] = (string) $value;
        elseif ($key === 'set' && is_array($value)) :
            // keep set[] as array
            $params['set'] = array_values($value);
        else :
            $params[$key] = (string) $value;
        endif;
    endforeach;

    if (empty($params)) :
        return '';
    endif;

    return '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

function autoLink($str, $attributes = array())
{
    $attrs = '';
    foreach ($attributes as $attribute => $value) :
        $attrs .= " {$attribute}=\"{$value}\"";
    endforeach;
    $str = ' ' . $str;
    $str = preg_replace(
        '`([^"=\'>])((http|https|ftp)://[^\s<]+[^\s<\.)])`i',
        '$1<a href="$2"' . $attrs . '>$2</a>',
        $str
    );
    $str = substr($str, 1);
    return $str;
}

function getFullURL()
{
    // Get HTTP/HTTPS (the possible values for this vary from server to server)
    $myUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']
        && !in_array(strtolower($_SERVER['HTTPS']), array('off','no')))
        ? 'https'
        : 'http';
    // Get domain portion
    $myUrl .= '://' . $_SERVER['HTTP_HOST'];
    // Get path to script
    $myUrl .= $_SERVER['REQUEST_URI'];
    // Add path info, if any
    if (!empty($_SERVER['PATH_INFO'])) $myUrl .= $_SERVER['PATH_INFO'];

    return $myUrl;
}

function ensureDirectoryExists($path)
{
    global $logfile;

    if (is_dir($path)) :
        return;
    endif;

    if (@mkdir($path, 0755, true)) :
        $msg = new \MTG\Core\Message($logfile);
        $msg->logMessage('[NOTICE]', "Created directory $path");
        return;
    endif;

    $error = error_get_last();
    $msg = new \MTG\Core\Message($logfile);
    $msg->logMessage('[ERROR]', "Failed to create directory $path: " . ($error['message'] ?? 'unknown error'));
    throw new Exception("[ERROR] Unable to create directory {$path}");
}

function validateTrueDecimal($v)
{
    global $logfile;
    $result = floor($v);
    $msg = new \MTG\Core\Message($logfile);

    $msg->logMessage('[DEBUG]', "Checking $v for true decimal, result is $result");
    return(floor($v) != $v);
}

function validUUID($uuid)
{
    global $logfile;
    $msg = new \MTG\Core\Message($logfile);

    $msg->logMessage('[DEBUG]', "Checking for valid UUID ($uuid)");
    if (
        preg_match(
            '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/',
            $uuid
        )
    ) :
        $msg->logMessage('[DEBUG]', "Valid UUID ($uuid)");
        return $uuid;
    else :
        $msg->logMessage('[ERROR]', "Invalid UUID ($uuid), returning 'false'");
        return false;
    endif;
}

function validTableName($input)
{
    global $db, $logfile;
    $msg = new \MTG\Core\Message($logfile);

    $msg->logMessage('[DEBUG]', "Checking for valid table name ($input)");
    $pattern = '/^\d+collection$/';
    if (preg_match($pattern, $input)) :
        $msg->logMessage('[DEBUG]', "Valid table name");
        return $input;
    else :
        $msg->logMessage('[ERROR]', "Invalid table name");
        return false;
    endif;
}

function isValidSetcode($setcode)
{
    return preg_match('/^[a-zA-Z0-9]{3,6}$/', $setcode) || empty($setcode);
}

function isValidCardName($name)
{
    // Cannot be only numbers
    return preg_match('/\D/', $name) || empty($name);
}

function isValidLanguageCode($lang)
{
    // Alpha only
    return preg_match('/^[a-zA-Z]*$/', $lang) || empty($lang);
}

function inArrayCaseInsensitive($needle, $haystack)
{
    if (!is_array($haystack)) :
        return false;
    endif;

    foreach ($haystack as $item) :
        if (strtolower($needle) == strtolower($item)) :
            return true;
        endif;
    endforeach;
    return false;
}

function ajaxRespondJson($payload, $statusCode = 200)
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit();
}

function ajaxRespondText($text, $statusCode = 200)
{
    http_response_code($statusCode);
    echo $text;
    exit();
}

function escapeCardNotesForTextarea($notes)
{
    return htmlspecialchars((string) $notes, ENT_QUOTES, 'UTF-8');
}
