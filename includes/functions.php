<?php

/*
Version:     28.22
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

function symbolReplace($str)
{
    if ($str === null) :
        return null;
    endif;

    static $symbols = [
        '{E}'      => '<img src="images/e.png" alt="{E}" class="manaimg">',
        '{T}'      => '<img src="images/t.png" alt="{T}" class="manaimg">',
        '{Q}'      => '<img src="images/q.png" alt="{Q}" class="manaimg">',
        '{P}'      => '<img src="images/paw.png" alt="{Q}" class="manaimg" title="pawprint">',
        '{H}'      => 'Phyrexian mana ',
        '{W}'      => '<img src="images/w.png" alt="{W}" class="manaimg">',
        '{U}'      => '<img src="images/u.png" alt="{U}" class="manaimg">',
        '{B}'      => '<img src="images/b.png" alt="{B}" class="manaimg">',
        '{R}'      => '<img src="images/r.png" alt="{R}" class="manaimg">',
        '{G}'      => '<img src="images/g.png" alt="{G}" class="manaimg">',
        '{S}'      => '<img src="images/s.png" alt="{S}" class="manaimg">',
        '{C}'      => '<img src="images/colourless_mana.png" alt="{C}" class="manaimg">',
        '{HR}'     => '<img src="images/hr.png" alt="{HR}" class="manaimg">',
        '{+oo}'    => '<img src="images/inf.png" alt="{+oo}" class="manaimg">',
        '{100}'    => '<img src="images/100.png" alt="{100}" class="manaimg">',
        '{1000000}' => '<img src="images/1m.png" alt="{1000000}" class="manaimg">',
        '{WU}'     => '<img src="images/wu.png" alt="{WU}" class="manaimg">',
        '{W/U}'    => '<img src="images/wu.png" alt="{WU}" class="manaimg">',
        '{WB}'     => '<img src="images/wb.png" alt="{WB}" class="manaimg">',
        '{W/B}'    => '<img src="images/wb.png" alt="{WB}" class="manaimg">',
        '{UB}'     => '<img src="images/ub.png" alt="{UB}" class="manaimg">',
        '{U/B}'    => '<img src="images/ub.png" alt="{UB}" class="manaimg">',
        '{UR}'     => '<img src="images/ur.png" alt="{UR}" class="manaimg">',
        '{U/R}'    => '<img src="images/ur.png" alt="{UR}" class="manaimg">',
        '{BR}'     => '<img src="images/br.png" alt="{BR}" class="manaimg">',
        '{B/R}'    => '<img src="images/br.png" alt="{BR}" class="manaimg">',
        '{BG}'     => '<img src="images/bg.png" alt="{BG}" class="manaimg">',
        '{B/G}'    => '<img src="images/bg.png" alt="{BG}" class="manaimg">',
        '{RW}'     => '<img src="images/rw.png" alt="{RW}" class="manaimg">',
        '{R/W}'    => '<img src="images/rw.png" alt="{RW}" class="manaimg">',
        '{RG}'     => '<img src="images/rg.png" alt="{RG}" class="manaimg">',
        '{R/G}'    => '<img src="images/rg.png" alt="{RG}" class="manaimg">',
        '{GW}'     => '<img src="images/gw.png" alt="{GW}" class="manaimg">',
        '{G/W}'    => '<img src="images/gw.png" alt="{GW}" class="manaimg">',
        '{GU}'     => '<img src="images/gu.png" alt="{GU}" class="manaimg">',
        '{G/U}'    => '<img src="images/gu.png" alt="{GU}" class="manaimg">',
        '{C/W}'    => '<img src="images/cw.png" alt="{C/W}" class="manaimg">',
        '{C/U}'    => '<img src="images/cu.png" alt="{C/U}" class="manaimg">',
        '{C/B}'    => '<img src="images/cb.png" alt="{C/B}" class="manaimg">',
        '{C/R}'    => '<img src="images/cr.png" alt="{C/R}" class="manaimg">',
        '{C/G}'    => '<img src="images/cg.png" alt="{C/G}" class="manaimg">',
        '{2W}'     => '<img src="images/2w.png" alt="{2W}" class="manaimg">',
        '{2U}'     => '<img src="images/2u.png" alt="{2U}" class="manaimg">',
        '{2B}'     => '<img src="images/2b.png" alt="{2B}" class="manaimg">',
        '{2R}'     => '<img src="images/2r.png" alt="{2R}" class="manaimg">',
        '{2G}'     => '<img src="images/2g.png" alt="{2G}" class="manaimg">',
        '{2/W}'    => '<img src="images/2w.png" alt="{2/W}" class="manaimg">',
        '{2/B}'    => '<img src="images/2b.png" alt="{2/B}" class="manaimg">',
        '{2/G}'    => '<img src="images/2g.png" alt="{2/G}" class="manaimg">',
        '{2/U}'    => '<img src="images/2u.png" alt="{2/U}" class="manaimg">',
        '{2/R}'    => '<img src="images/2r.png" alt="{2/R}" class="manaimg">',
        '{X}'      => '<img src="images/x.png" alt="{X}" class="manaimg">',
        '{Y}'      => '<img src="images/y.png" alt="{Y}" class="manaimg">',
        '{Z}'      => '<img src="images/z.png" alt="{Z}" class="manaimg">',
        '{1/2}'    => '<img src="images/half.png" alt="{1/2}" class="manaimg">',
        '{0}'      => '<img src="images/0.png" alt="{0}" class="manaimg">',
        '{1}'      => '<img src="images/1.png" alt="{1}" class="manaimg">',
        '{2}'      => '<img src="images/2.png" alt="{2}" class="manaimg">',
        '{3}'      => '<img src="images/3.png" alt="{3}" class="manaimg">',
        '{4}'      => '<img src="images/4.png" alt="{4}" class="manaimg">',
        '{5}'      => '<img src="images/5.png" alt="{5}" class="manaimg">',
        '{6}'      => '<img src="images/6.png" alt="{6}" class="manaimg">',
        '{7}'      => '<img src="images/7.png" alt="{7}" class="manaimg">',
        '{8}'      => '<img src="images/8.png" alt="{8}" class="manaimg">',
        '{9}'      => '<img src="images/9.png" alt="{9}" class="manaimg">',
        '{10}'     => '<img src="images/10.png" alt="{10}" class="manaimg">',
        '{11}'     => '<img src="images/11.png" alt="{11}" class="manaimg">',
        '{12}'     => '<img src="images/12.png" alt="{12}" class="manaimg">',
        '{13}'     => '<img src="images/13.png" alt="{13}" class="manaimg">',
        '{14}'     => '<img src="images/14.png" alt="{14}" class="manaimg">',
        '{15}'     => '<img src="images/15.png" alt="{15}" class="manaimg">',
        '{16}'     => '<img src="images/16.png" alt="{16}" class="manaimg">',
        '{17}'     => '<img src="images/17.png" alt="{17}" class="manaimg">',
        '{18}'     => '<img src="images/18.png" alt="{18}" class="manaimg">',
        '{19}'     => '<img src="images/19.png" alt="{19}" class="manaimg">',
        '{20}'     => '<img src="images/20.png" alt="{20}" class="manaimg">',
        '{PW}'     => '<img src="images/pw.png" alt="{PW}" class="manaimg">',
        '{W/P}'    => '<img src="images/pw.png" alt="{W/P}" class="manaimg">',
        '{PU}'     => '<img src="images/pu.png" alt="{PU}" class="manaimg">',
        '{U/P}'    => '<img src="images/pu.png" alt="{U/P}" class="manaimg">',
        '{PB}'     => '<img src="images/pb.png" alt="{PB}" class="manaimg">',
        '{B/P}'    => '<img src="images/pb.png" alt="{B/P}" class="manaimg">',
        '{PR}'     => '<img src="images/pr.png" alt="{PR}" class="manaimg">',
        '{R/P}'    => '<img src="images/pr.png" alt="{R/P}" class="manaimg">',
        '{PG}'     => '<img src="images/pg.png" alt="{PG}" class="manaimg">',
        '{G/P}'    => '<img src="images/pg.png" alt="{G/P}" class="manaimg">',
        '{CHAOS}'  => '<img src="images/chaos.png" alt="{PG}" class="manaimg">',
        '{G/U/P}'  => '<img src="images/gup.png" alt="{G/U/P}" class="manaimg">',
        '{G/W/P}'  => '<img src="images/gwp.png" alt="{G/W/P}" class="manaimg">',
        '{R/G/P}'  => '<img src="images/rgp.png" alt="{R/G/P}" class="manaimg">',
        '{R/W/P}'  => '<img src="images/rwp.png" alt="{R/W/P}" class="manaimg">',
        '{PWk}'    => 'Planeswalk',
        '{Ch}'     => 'Chaos',
        "\n"       => '<br>',
        '?'        => '-',
        '£'        => '<br>',
        '#'        => '',
    ];

    return strtr($str, $symbols);
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

function downloadBulk($url, $dest, $msg, $context = 'downloadBulk', $debug = false)
{
    // Downloads URL to $dest atomically via $dest.tmp, returns true/false.
    // Logs errors via $msg.

    $tmp = $dest . '.tmp';
    $fp = fopen($tmp, 'wb');
    if ($fp === false) :
        $msg->logMessage('[ERROR]', "$context: failed to open temp file for write: $tmp");
        return false;
    endif;

    $userAgent = \MTG\Core\UserAgent::build('/opt/mtg/mtg_new.ini', null, $GLOBALS['logfile'] ?? null);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_FAILONERROR, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: application/json;q=0.9,*/*;q=0.8"));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    curl_setopt($ch, CURLOPT_ENCODING, '');

    // Optional debug to logfile (off by default)
    $logfp = null;
    if ($debug === true) :
        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        $logfp = fopen($GLOBALS['logfile'], 'ab');
        if ($logfp !== false) :
            curl_setopt($ch, CURLOPT_STDERR, $logfp);
        endif;
    endif;

    $ok = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = ($ok === false) ? curl_error($ch) : '';

    curl_close($ch);
    fclose($fp);
    if (is_resource($logfp)) :
        fclose($logfp);
    endif;

    if ($ok === false) :
        @unlink($tmp);
        $msg->logMessage('[ERROR]', "$context: curl download failed (HTTP $httpCode): $err");
        return false;
    endif;

    // Basic sanity: must be non-zero
    if (!is_file($tmp) || filesize($tmp) === 0) :
        @unlink($tmp);
        $msg->logMessage('[ERROR]', "$context: download produced empty file: $tmp");
        return false;
    endif;

    // Atomic replace
    if (!rename($tmp, $dest)) :
        @unlink($tmp);
        $msg->logMessage('[ERROR]', "$context: failed to move temp file into place: $tmp -> $dest");
        return false;
    endif;

    return true;
}

function fetchJson($url, $msg, $context)
{
    $userAgent = \MTG\Core\UserAgent::build('/opt/mtg/mtg_new.ini', null, $GLOBALS['logfile'] ?? null);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: application/json;q=0.9,*/*;q=0.8"));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    curl_setopt($ch, CURLOPT_FAILONERROR, 1);

    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

    if ($body === false) :
        $msg->logMessage(
            '[ERROR]',
            "$context: curl_exec failed (HTTP $httpCode): " . curl_error($ch)
        );
        curl_close($ch);
        return false;
    endif;

    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) :
        $msg->logMessage('[ERROR]', "$context: HTTP $httpCode from $url");
        return false;
    endif;

    $data = json_decode($body, true);
    if (!is_array($data)) :
        $msg->logMessage(
            '[ERROR]',
            "$context: JSON decode failed: " . json_last_error_msg()
        );
        return false;
    endif;

    return $data;
}

function validateTrueDecimal($v)
{
    global $logfile;
    $result = floor($v);
    $msg = new \MTG\Core\Message($logfile);

    $msg->logMessage('[DEBUG]', "Checking $v for true decimal, result is $result");
    return(floor($v) != $v);
}

function cardTypes($finishes)
{
    global $db, $logfile;
    $cardtypes = 'none';
    $card_normal = 0;
    $card_foil = 0;
    $card_etched = 0;
    foreach ($finishes as $key => $value) :
        if ($value == 'nonfoil') :
            $card_normal = 1;
        elseif ($value == 'foil') :
            $card_foil = 1;
        elseif ($value == 'etched') :
            $card_etched = 1;
        endif;
    endforeach;
    if ($card_normal == 1 and $card_foil == 1 and $card_etched == 1) :
        $cardtypes = 'normalfoiletched';
    elseif ($card_normal == 1 and $card_foil == 1 and $card_etched == 0) :
        $cardtypes = 'normalfoil';
    elseif ($card_normal == 1 and $card_foil == 0 and $card_etched == 1) :
        $cardtypes = 'normaletched';
    elseif ($card_normal == 0 and $card_foil == 1 and $card_etched == 1) :
        $cardtypes = 'foiletched';
    elseif ($card_normal == 0 and $card_foil == 0 and $card_etched == 1) :
        $cardtypes = 'etchedonly';
    elseif ($card_normal == 0 and $card_foil == 1 and $card_etched == 0) :
        $cardtypes = 'foilonly';
    elseif ($card_normal == 1 and $card_foil == 0 and $card_etched == 0) :
        $cardtypes = 'normalonly';
    endif;
    return $cardtypes;
}

function promoLookup($promo_type)
{
    global $promos_to_show, $logfile;
    $msg = new \MTG\Core\Message($logfile);

    $msg->logMessage('[DEBUG]', "Looking up promo description for '$promo_type'");
    $index = array_search($promo_type, array_column($promos_to_show, 'promotype'));
    if ($index !== false) :
        $promo_description = $promos_to_show[$index]['display'];
    else :
        $promo_description = 'skip';
    endif;
    $msg->logMessage('[DEBUG]', "Promo description for '$promo_type' is '$promo_description'");
    return $promo_description;
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
