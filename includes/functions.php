<?php

/*
Version:     28.2
Date:        27/12/25
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

function forcePasswordChange()
{
    global $_SESSION;
    if ((isset($_SESSION["chgpwd"])) and ($_SESSION["chgpwd"] == true)) :
        header("Location: /profile.php");
    endif;
}

function mtgCardCopyLimit($card_type, $ability, $f1_ability = null, $f2_ability = null, $decktype = null)
{
    global $any_quantity;

    if ($decktype === 'Wishlist') :
        return null;
    endif;

    if ($card_type !== null && str_contains($card_type, 'Basic Land')) :
        return null;
    endif;

    $ability_candidates = array_filter(
        [
            $ability,
            $f1_ability,
            $f2_ability
        ]
    );

    foreach ($ability_candidates as $ability_text) :
        foreach ($any_quantity as $rule) :
            if (str_contains($ability_text, $rule)) :
                return null;
            endif;
        endforeach;

        $pattern = '/A deck can have up to ([a-z0-9-]+) cards named/i';
        if (preg_match($pattern, $ability_text, $matches)) :
            $limit_text = strtolower(str_replace('-', ' ', $matches[1]));
            if (ctype_digit($limit_text)) :
                return (int) $limit_text;
            endif;
            $word_map = [
                'one' => 1,
                'two' => 2,
                'three' => 3,
                'four' => 4,
                'five' => 5,
                'six' => 6,
                'seven' => 7,
                'eight' => 8,
                'nine' => 9,
                'ten' => 10,
                'eleven' => 11,
                'twelve' => 12,
                'thirteen' => 13,
                'fourteen' => 14,
                'fifteen' => 15,
                'sixteen' => 16,
                'seventeen' => 17,
                'eighteen' => 18,
                'nineteen' => 19,
                'twenty' => 20
            ];
            if (isset($word_map[$limit_text])) :
                return $word_map[$limit_text];
            endif;
        endif;
    endforeach;

    return 4;
}

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

function validPass($candidate)
{
    if (!preg_match_all('$\S*(?=\S{8,})(?=\S*[a-z])(?=\S*[A-Z])(?=\S*[\d])\S*$', $candidate, $hole)) :
        return false;
    else :
        return true;
    endif;
    $hole = '';
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

function loginStamp($userEmail)
{
    global $db, $logfile;
    $msg = new \MTG\Core\Message($logfile);

    $msg->logMessage('[NOTICE]', "Writing user login");
    $logindate = date("Y-m-d");
    $query = "UPDATE users SET lastlogin_date = ? WHERE email = ?";
    if ($db->execute_query($query, [$logindate,$userEmail]) === true) :
        $msg->logMessage('[DEBUG]', "Writing user login successful");
        return 1;
    else :
        $msg->logMessage('[ERROR]', "Writing user login failed");
        return 0;
    endif;
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

function getBulkInfo($type)
{
    // Function to return the URI for the Scryfall bulk data file, and the file location where it needs to go
    global $logfile, $defaultCardsUrl, $allCardsUrl, $imgLocation;
    $msg = new \MTG\Core\Message($logfile);
    $bulkInfo = false;

    $url = $urlDefault = $urlAll = $fileLocation = $fileLocationDefault = $fileLocationAll = '';
    $scryfallBulk = $scryfallBulkDefault = $scryfallBulkAll = null;
    $msg->logMessage('[NOTICE]', "scryfall bulk API: called with '$type'");

    if ($type === "all") :
        $url = $allCardsUrl;
        $fileLocation = $imgLocation . 'json/bulk_all.json';
    elseif ($type === "default") :  // At the moment, elseif and else do the same, i.e. a "primary" load only
        $url = $defaultCardsUrl;
        $fileLocation = $imgLocation . 'json/bulk.json';
    elseif ($type === "refresh") :
        $urlDefault = $defaultCardsUrl;
        $urlAll = $allCardsUrl;
        $fileLocationDefault = $imgLocation . 'json/bulk.json';
        $fileLocationAll = $imgLocation . 'json/bulk_all.json';
    else :  // At the moment, else does a "default" load only - catches anything else
        $type = "default";
        $url = $defaultCardsUrl;
        $fileLocation = $imgLocation . 'json/bulk.json';
    endif;

    if (!empty($url) && !empty($fileLocation)) :
        $msg->logMessage('[NOTICE]', "Scryfall bulk API: fetching current URL $url");
        $scryfallBulk = fetchJson($url, $msg, 'Scryfall bulk API');
        if ($scryfallBulk === false) :
            return false;
        endif;
    elseif (
        !empty($urlDefault)
        && !empty($urlAll)
        && !empty($fileLocationDefault)
        && !empty($fileLocationAll)
    ) :
        // Run twice, once for each file and location
        $msg->logMessage('[NOTICE]', "Scryfall bulk API: fetching current URL $urlDefault");
        $scryfallBulkDefault = fetchJson($urlDefault, $msg, 'Scryfall bulk API');
        if ($scryfallBulkDefault === false) :
            return false;
        endif;
        $msg->logMessage('[NOTICE]', "Scryfall bulk API: fetching current URL $urlAll");
        $scryfallBulkAll = fetchJson($urlAll, $msg, 'Scryfall bulk API');
        if ($scryfallBulkAll === false) :
            return false;
        endif;
    else :
        $msg->logMessage('[ERROR]', "Scryfall bulk API: failed");
        return false;
    endif;
    if (
        isset($scryfallBulk['type'])
        && in_array($scryfallBulk['type'], ['default_cards', 'all_cards'], true)
    ) :
        if ($type === 'all' && $scryfallBulk['type'] !== 'all_cards') :
            $msg->logMessage('[ERROR]', "Scryfall bulk API: expected all_cards, got {$scryfallBulk['type']}");
            return false;
        endif;
        if ($type === 'default' && $scryfallBulk['type'] !== 'default_cards') :
            $msg->logMessage('[ERROR]', "Scryfall bulk API: expected default_cards, got {$scryfallBulk['type']}");
            return false;
        endif;
        if (isset($scryfallBulk["download_uri"])) :
            $bulk_uri = $scryfallBulk["download_uri"];
            $msg->logMessage('[NOTICE]', "Scryfall bulk API: Download URI: $bulk_uri");
            $bulkInfo = [
                'bulkUrl' => $bulk_uri,
                'fileLocation' => $fileLocation
            ];
        else :
            $msg->logMessage('[ERROR]', "Scryfall bulk API info not available");
            return false;
        endif;
    elseif (
        isset($scryfallBulkDefault['type'], $scryfallBulkAll['type'])
        && $scryfallBulkDefault['type'] === 'default_cards'
        && $scryfallBulkAll['type'] === 'all_cards'
    ) :
        if (isset($scryfallBulkDefault["download_uri"])) :
            $bulk_uri_default = $scryfallBulkDefault["download_uri"];
            $msg->logMessage('[NOTICE]', "Scryfall bulk API: Download URI: $bulk_uri_default");
        else :
            $msg->logMessage('[ERROR]', "Scryfall bulk API: Error");
            return false;
        endif;
        if (isset($scryfallBulkAll["download_uri"])) :
            $bulk_uri_all = $scryfallBulkAll["download_uri"];
            $msg->logMessage('[NOTICE]', "Scryfall bulk API: Download URI: $bulk_uri_all");
        else :
            $msg->logMessage('[ERROR]', "Scryfall bulk API: Error");
            return false;
        endif;
        $bulkInfo = [
            'bulkUrlDefault' => $bulk_uri_default,
            'fileLocationDefault' => $fileLocationDefault,
            'bulkUrlAll' => $bulk_uri_all,
            'fileLocationAll' => $fileLocationAll,
        ];
    else :
        $msg->logMessage('[ERROR]', "Scryfall bulk API info not available");
        return false;
    endif;

    return $bulkInfo;
}

function getBulkJson($uri, $file_location, $max_fileage)
{
    // Function to download and save bulk Scryfall data files
    global $logfile;
    $msg = new \MTG\Core\Message($logfile);

    $shouldDownload = true;
    $reason = '';

    if (is_file($file_location)) :
        $size = filesize($file_location);
        if ($size > 0) :
            $mtime = filemtime($file_location);
            $fileDate = date('d-m-Y H:i', $mtime);

            if ((time() - $mtime) > $max_fileage) :
                $shouldDownload = true;
                $reason = "File old ($fileDate), downloading: $uri";
            else :
                $shouldDownload = false;
                $msg->logMessage(
                    '[NOTICE]',
                    "Scryfall bulk API: File fresh ($file_location, $fileDate, $size), skipping download"
                );
            endif;
        else :
            $shouldDownload = true;
            $reason = "0-byte file at ($file_location), downloading: $uri";
        endif;
    else :
        $shouldDownload = true;
        $reason = "No file at ($file_location), downloading: $uri";
    endif;

    if ($shouldDownload === false) :
        $msg->logMessage('[NOTICE]', "Scryfall bulk API: Existing file not too old, skipping");
        return 'Skipped';
    endif;

    $msg->logMessage('[NOTICE]', "Scryfall bulk API: $reason");

    $ok = downloadBulk($uri, $file_location, $msg, 'Scryfall bulk API download', false);
    if ($ok === true) :
        $size = filesize($file_location);
        $msg->logMessage(
            '[NOTICE]',
            "Scryfall bulk API: Download OK, file at ($file_location), size ($size), proceeding"
        );
        return 'Success';
    endif;

    // Retry once, briefly
    $msg->logMessage('[ERROR]', "Scryfall bulk API: Download failed, retrying in 20 seconds");
    sleep(20);

    $ok = downloadBulk($uri, $file_location, $msg, 'Scryfall bulk API download', false);
    if ($ok === true) :
        $size = filesize($file_location);
        $msg->logMessage(
            '[NOTICE]',
            "Scryfall bulk API: Download OK after retry, file at ($file_location), size ($size), proceeding"
        );
        return 'Success';
    endif;

    $msg->logMessage('[ERROR]', "Scryfall bulk API: Download failed after retry, exiting");
    return false;
}

function scryfallImport($file_location, $type)
{
    // Function to process and import lines within Scryfall bulk data files
    global
        $db,
        $logfile,
        $games_to_include,
        $langs_to_skip,
        $langs_to_skip_all,
        $layouts_to_skip,
        $serverEmail,
        $adminEmail,
        $imgLocation,
        $twoCardDetailSections;
    $msg = new \MTG\Core\Message($logfile);

    $msg->logMessage('[DEBUG]', 'Checking for cards_scry content_hash and price_hash columns');
    $contentHashResult = $db->query("SHOW COLUMNS FROM `cards_scry` LIKE 'content_hash'");
    if ($contentHashResult === false) :
        throw new Exception(
            '[ERROR] scryfall_bulk.php: Checking cards_scry content_hash column: ' . $db->error
        );
    elseif ($contentHashResult->num_rows === 0) :
        throw new Exception(
            '[ERROR] scryfall_bulk.php: cards_scry content_hash column missing (manual schema update required)'
        );
    else :
        $msg->logMessage('[DEBUG]', 'cards_scry content_hash column present');
    endif;
    if ($contentHashResult !== false) :
        $contentHashResult->free();
    endif;

    $priceHashResult = $db->query("SHOW COLUMNS FROM `cards_scry` LIKE 'price_hash'");
    if ($priceHashResult === false) :
        throw new Exception(
            '[ERROR] scryfall_bulk.php: Checking cards_scry price_hash column: ' . $db->error
        );
    elseif ($priceHashResult->num_rows === 0) :
        throw new Exception(
            '[ERROR] scryfall_bulk.php: cards_scry price_hash column missing (manual schema update required)'
        );
    else :
        $msg->logMessage('[DEBUG]', 'cards_scry price_hash column present');
    endif;
    if ($priceHashResult !== false) :
        $priceHashResult->free();
    endif;

    // Initiate counters at zero
    $count_inc = $count_skip = $total_count = $count_add = $count_update = $count_other = 0;
    $count_update_content = $count_update_price = $count_update_both = 0;
    $diag_change_count = 0;
    $diag_no_change_count = 0;
    $diag_limit = 50;
    $bulkDiagnosticEnabled = method_exists($msg, 'isBulkDiagnosticEnabled')
        && $msg->isBulkDiagnosticEnabled();
    if ($bulkDiagnosticEnabled) :
        $msg->logMessage(
            '[NOTICE]',
            'Bulk diagnostic mode enabled for scryfall bulk import (first 50 change + no change rows).'
        );
    endif;

    $data = JsonMachine\Items::fromFile(
        $file_location,
        ['decoder' => new JsonMachine\JsonDecoder\ExtJsonDecoder(true)]
    );

    $date = date('Y-m-d');
    $timeslice_start = microtime(true);
    $batch_size = 5000;
    $log_interval = 2500;

    if ($type === 'default') :
        $primary = 1;

        // By default, set to TRUE. This will download all images for cards in the Default Cards file when run
        // with an empty database (about 90,000 images, i.e. potentially about 20GB)
        $imageDownloads = true;
    elseif ($type === 'all') :
        $primary = 0;

        // Don't by default download all images for all cards.
        // Images will be obtained on first card detail load or search result inclusion
        $imageDownloads = false;
    endif;

    $imageManager = null;
    if ($imageDownloads === true) :
        $imageManager = new \MTG\Cards\ImageManager($db, $logfile, $serverEmail, $adminEmail);
    endif;

    $stmt = $db->prepare("INSERT INTO
                            `cards_scry`
                            (id, oracle_id, tcgplayer_id, multiverse, multiverse2,
                            name, printed_name, flavor_name, lang, release_date,
                            api_uri, scryfall_uri, layout, image_uri, manacost,
                            cmc, type, ability, power, toughness,
                            loyalty, color, color_identity, keywords, generatedmana,
                            legalitystandard, legalitypioneer, legalitymodern, legalitylegacy, legalitypauper,
                            legalityvintage, legalitycommander, legalityalchemy, legalityhistoric, reserved,
                            foil, nonfoil, oversized, promo, set_id,
                            game_types, finishes, promo_types, setcode, set_name,
                            number, number_import, rarity, flavor, backid,
                            artist, price, price_foil, price_etched, gatherer_uri,
                            updatetime, f1_name, f1_manacost, f1_power, f1_toughness,
                            f1_loyalty, f1_type, f1_ability, f1_colour, f1_artist,
                            f1_flavor, f1_image_uri, f1_cmc, f1_printed_name, f1_flavor_name,
                            f2_name, f2_manacost, f2_power, f2_toughness, f2_loyalty,
                            f2_type, f2_ability, f2_colour, f2_artist, f2_flavor,
                            f2_image_uri, f2_cmc, f2_printed_name, f2_flavor_name, p1_id,
                            p1_component, p1_name, p1_type_line, p1_uri, p2_id,
                            p2_component, p2_name, p2_type_line, p2_uri, p3_id,
                            p3_component, p3_name, p3_type_line, p3_uri, p4_id,
                            p4_component, p4_name, p4_type_line, p4_uri, p5_id,
                            p5_component, p5_name, p5_type_line, p5_uri, p6_id,
                            p6_component, p6_name, p6_type_line, p6_uri, p7_id,
                            p7_component, p7_name, p7_type_line, p7_uri, maxpower,
                            minpower, maxtoughness, mintoughness, maxloyalty, minloyalty,
                            price_sort, content_hash, price_hash, date_added, primary_card
                            )
                        VALUES(
                            ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,
                            ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,
                            ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,
                            ?,?,?,?,?,?,?
                        )
                        ON DUPLICATE KEY UPDATE
                            id = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(id), id),
                            oracle_id = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(oracle_id), oracle_id),
                            tcgplayer_id = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(tcgplayer_id),
                                tcgplayer_id
                            ),
                            multiverse = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(multiverse),
                                multiverse
                            ),
                            multiverse2 = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(multiverse2),
                                multiverse2
                            ),
                            name = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(name), name),
                            printed_name = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(printed_name),
                                printed_name
                            ),
                            flavor_name = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(flavor_name),
                                flavor_name
                            ),
                            lang = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(lang), lang),
                            release_date = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(release_date),
                                release_date
                            ),
                            api_uri = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(api_uri), api_uri),
                            scryfall_uri = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(scryfall_uri),
                                scryfall_uri
                            ),
                            layout = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(layout), layout),
                            image_uri = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(image_uri), image_uri),
                            manacost = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(manacost), manacost),
                            cmc = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(cmc), cmc),
                            type = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(type), type),
                            ability = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(ability), ability),
                            power = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(power), power),
                            toughness = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(toughness), toughness),
                            loyalty = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(loyalty), loyalty),
                            color = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(color), color),
                            color_identity = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(color_identity),
                                color_identity
                            ),
                            keywords = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(keywords), keywords),
                            generatedmana = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(generatedmana),
                                generatedmana
                            ),
                            legalitystandard = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(legalitystandard),
                                legalitystandard
                            ),
                            legalitypioneer = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(legalitypioneer),
                                legalitypioneer
                            ),
                            legalitymodern = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(legalitymodern),
                                legalitymodern
                            ),
                            legalitylegacy = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(legalitylegacy),
                                legalitylegacy
                            ),
                            legalitypauper = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(legalitypauper),
                                legalitypauper
                            ),
                            legalityvintage = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(legalityvintage),
                                legalityvintage
                            ),
                            legalitycommander = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(legalitycommander),
                                legalitycommander
                            ),
                            legalityalchemy = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(legalityalchemy),
                                legalityalchemy
                            ),
                            legalityhistoric = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(legalityhistoric),
                                legalityhistoric
                            ),
                            reserved = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(reserved), reserved),
                            foil = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(foil), foil),
                            nonfoil = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(nonfoil), nonfoil),
                            oversized = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(oversized), oversized),
                            promo = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(promo), promo),
                            set_id = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(set_id), set_id),
                            game_types = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(game_types),
                                game_types
                            ),
                            finishes = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(finishes), finishes),
                            promo_types = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(promo_types),
                                promo_types
                            ),
                            setcode = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(setcode), setcode),
                            set_name = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(set_name), set_name),
                            number = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(number), number),
                            number_import = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(number_import),
                                number_import
                            ),
                            rarity = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(rarity), rarity),
                            flavor = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(flavor), flavor),
                            backid = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(backid), backid),
                            artist = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(artist), artist),
                            price = IF(NOT (price_hash <=> VALUES(price_hash)), VALUES(price), price),
                            price_foil = IF(NOT (price_hash <=> VALUES(price_hash)), VALUES(price_foil), price_foil),
                            price_etched = IF(
                                NOT (price_hash <=> VALUES(price_hash)),
                                VALUES(price_etched),
                                price_etched
                            ),
                            price_sort = IF(NOT (price_hash <=> VALUES(price_hash)), VALUES(price_sort), price_sort),
                            gatherer_uri = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(gatherer_uri),
                                gatherer_uri
                            ),
                            updatetime = IF(
                                NOT (content_hash <=> VALUES(content_hash)) OR NOT (price_hash <=> VALUES(price_hash)),
                                VALUES(updatetime),
                                updatetime
                            ),
                            f1_name = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(f1_name), f1_name),
                            f1_manacost = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(f1_manacost),
                                f1_manacost
                            ),
                            f1_power = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(f1_power), f1_power),
                            f1_toughness = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(f1_toughness),
                                f1_toughness
                            ),
                            f1_loyalty = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(f1_loyalty),
                                f1_loyalty
                            ),
                            f1_type = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(f1_type), f1_type),
                            f1_ability = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(f1_ability),
                                f1_ability
                            ),
                            f1_colour = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(f1_colour), f1_colour),
                            f1_artist = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(f1_artist), f1_artist),
                            f1_flavor = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(f1_flavor), f1_flavor),
                            f1_image_uri = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(f1_image_uri),
                                f1_image_uri
                            ),
                            f1_cmc = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(f1_cmc), f1_cmc),
                            f1_printed_name = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(f1_printed_name),
                                f1_printed_name
                            ),
                            f1_flavor_name = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(f1_flavor_name),
                                f1_flavor_name
                            ),
                            f2_name = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(f2_name), f2_name),
                            f2_manacost = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(f2_manacost),
                                f2_manacost
                            ),
                            f2_power = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(f2_power), f2_power),
                            f2_toughness = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(f2_toughness),
                                f2_toughness
                            ),
                            f2_loyalty = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(f2_loyalty),
                                f2_loyalty
                            ),
                            f2_type = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(f2_type), f2_type),
                            f2_ability = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(f2_ability),
                                f2_ability
                            ),
                            f2_colour = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(f2_colour), f2_colour),
                            f2_artist = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(f2_artist), f2_artist),
                            f2_flavor = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(f2_flavor), f2_flavor),
                            f2_image_uri = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(f2_image_uri),
                                f2_image_uri
                            ),
                            f2_cmc = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(f2_cmc), f2_cmc),
                            f2_printed_name = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(f2_printed_name),
                                f2_printed_name
                            ),
                            f2_flavor_name = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(f2_flavor_name),
                                f2_flavor_name
                            ),
                            p1_id = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p1_id), p1_id),
                            p1_component = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(p1_component),
                                p1_component
                            ),
                            p1_name = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p1_name), p1_name),
                            p1_type_line = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(p1_type_line),
                                p1_type_line
                            ),
                            p1_uri = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p1_uri), p1_uri),
                            p2_id = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p2_id), p2_id),
                            p2_component = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(p2_component),
                                p2_component
                            ),
                            p2_name = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p2_name), p2_name),
                            p2_type_line = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(p2_type_line),
                                p2_type_line
                            ),
                            p2_uri = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p2_uri), p2_uri),
                            p3_id = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p3_id), p3_id),
                            p3_component = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(p3_component),
                                p3_component
                            ),
                            p3_name = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p3_name), p3_name),
                            p3_type_line = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(p3_type_line),
                                p3_type_line
                            ),
                            p3_uri = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p3_uri), p3_uri),
                            p4_id = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p4_id), p4_id),
                            p4_component = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(p4_component),
                                p4_component
                            ),
                            p4_name = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p4_name), p4_name),
                            p4_type_line = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(p4_type_line),
                                p4_type_line
                            ),
                            p4_uri = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p4_uri), p4_uri),
                            p5_id = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p5_id), p5_id),
                            p5_component = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(p5_component),
                                p5_component
                            ),
                            p5_name = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p5_name), p5_name),
                            p5_type_line = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(p5_type_line),
                                p5_type_line
                            ),
                            p5_uri = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p5_uri), p5_uri),
                            p6_id = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p6_id), p6_id),
                            p6_component = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(p6_component),
                                p6_component
                            ),
                            p6_name = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p6_name), p6_name),
                            p6_type_line = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(p6_type_line),
                                p6_type_line
                            ),
                            p6_uri = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p6_uri), p6_uri),
                            p7_id = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p7_id), p7_id),
                            p7_component = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(p7_component),
                                p7_component
                            ),
                            p7_name = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p7_name), p7_name),
                            p7_type_line = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(p7_type_line),
                                p7_type_line
                            ),
                            p7_uri = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(p7_uri), p7_uri),
                            maxpower = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(maxpower), maxpower),
                            minpower = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(minpower), minpower),
                            maxtoughness = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(maxtoughness),
                                maxtoughness
                            ),
                            mintoughness = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(mintoughness),
                                mintoughness
                            ),
                            maxloyalty = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(maxloyalty),
                                maxloyalty
                            ),
                            minloyalty = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(minloyalty),
                                minloyalty
                            ),
                            content_hash = IF(
                                NOT (content_hash <=> VALUES(content_hash)),
                                VALUES(content_hash),
                                content_hash
                            ),
                            price_hash = IF(
                                NOT (price_hash <=> VALUES(price_hash)),
                                VALUES(price_hash),
                                price_hash
                            ),
                            primary_card = IF(?, 1, primary_card)
                        ");
    if ($stmt === false) :
        throw new Exception('[ERROR] cards.php: Preparing SQL: ' . $db->error);
    endif;
    $hashStmt = $db->prepare("SELECT content_hash, price_hash FROM `cards_scry` WHERE id = ? LIMIT 1");
    if ($hashStmt === false) :
        throw new Exception('[ERROR] scryfall_bulk.php: Preparing hash lookup SQL: ' . $db->error);
    endif;

    // Initialise all variables for binding
    $id = null;
    $oracle_id = null;
    $tcgplayer_id = null;
    $multi_1 = null;
    $multi_2 = null;
    $name = null;
    $printed_name = null;
    $flavor_name = null;
    $lang = null;
    $released_at = null;
    $uri = null;
    $scryfall_uri = null;
    $layout = null;
    $image_uri = null;
    $mana_cost = null;
    $cmc = null;
    $type_line = null;
    $oracle_text = null;
    $power = null;
    $toughness = null;
    $loyalty = null;
    $colors = null;
    $color_identity = null;
    $keywords = null;
    $produced_mana = null;

    $legality_standard = null;
    $legality_pioneer = null;
    $legality_modern = null;
    $legality_legacy = null;
    $legality_pauper = null;
    $legality_vintage = null;
    $legality_commander = null;
    $legality_alchemy = null;
    $legality_historic = null;

    $reserved = null;
    $foil = null;
    $nonfoil = null;
    $oversized = null;
    $promo = null;
    $set_id = null;

    $game_types = null;
    $finishes = null;
    $promo_types = null;

    $set_code = null;
    $set_name = null;
    $number_int = null;
    $collector_number = null;
    $rarity = null;
    $flavor_text = null;
    $card_back_id = null;
    $artist = null;

    $price_usd = null;
    $price_usd_foil = null;
    $price_usd_etched = null;
    $gatherer_uri = null;

    $time = null;

    /* Face 1 */
    $name_1 = null;
    $manacost_1 = null;
    $power_1 = null;
    $toughness_1 = null;
    $loyalty_1 = null;
    $type_1 = null;
    $ability_1 = null;
    $colour_1 = null;
    $artist_1 = null;
    $flavor_1 = null;
    $image_1 = null;
    $cmc_1 = null;
    $printed_name_1 = null;
    $flavor_name_1 = null;

    /* Face 2 */
    $name_2 = null;
    $manacost_2 = null;
    $power_2 = null;
    $toughness_2 = null;
    $loyalty_2 = null;
    $type_2 = null;
    $ability_2 = null;
    $colour_2 = null;
    $artist_2 = null;
    $flavor_2 = null;
    $image_2 = null;
    $cmc_2 = null;
    $printed_name_2 = null;
    $flavor_name_2 = null;

    /* Parts */
    $id_p1 = $component_p1 = $name_p1 = $type_line_p1 = $uri_p1 = null;
    $id_p2 = $component_p2 = $name_p2 = $type_line_p2 = $uri_p2 = null;
    $id_p3 = $component_p3 = $name_p3 = $type_line_p3 = $uri_p3 = null;
    $id_p4 = $component_p4 = $name_p4 = $type_line_p4 = $uri_p4 = null;
    $id_p5 = $component_p5 = $name_p5 = $type_line_p5 = $uri_p5 = null;
    $id_p6 = $component_p6 = $name_p6 = $type_line_p6 = $uri_p6 = null;
    $id_p7 = $component_p7 = $name_p7 = $type_line_p7 = $uri_p7 = null;

    /* Stats */
    $maxpower = null;
    $minpower = null;
    $maxtoughness = null;
    $mintoughness = null;
    $maxloyalty = null;
    $minloyalty = null;

    $price_sort = null;
    $content_hash = null;
    $price_hash = null;
    $primary = (int) $primary;

    $hash_id = null;
    $existing_content_hash = null;
    $existing_price_hash = null;

    $bind = $stmt->bind_param(
        "sssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssss"
        . "ssssssssssssssssssssssssssssssssssssssssssssssssii",
        $id,
        $oracle_id,
        $tcgplayer_id,
        $multi_1,
        $multi_2,
        $name,
        $printed_name,
        $flavor_name,
        $lang,
        $released_at,
        $uri,
        $scryfall_uri,
        $layout,
        $image_uri,
        $mana_cost,
        $cmc,
        $type_line,
        $oracle_text,
        $power,
        $toughness,
        $loyalty,
        $colors,
        $color_identity,
        $keywords,
        $produced_mana,
        $legality_standard,
        $legality_pioneer,
        $legality_modern,
        $legality_legacy,
        $legality_pauper,
        $legality_vintage,
        $legality_commander,
        $legality_alchemy,
        $legality_historic,
        $reserved,
        $foil,
        $nonfoil,
        $oversized,
        $promo,
        $set_id,
        $game_types,
        $finishes,
        $promo_types,
        $set_code,
        $set_name,
        $number_int,
        $collector_number,
        $rarity,
        $flavor_text,
        $card_back_id,
        $artist,
        $price_usd,
        $price_usd_foil,
        $price_usd_etched,
        $gatherer_uri,
        $time,
        $name_1,
        $manacost_1,
        $power_1,
        $toughness_1,
        $loyalty_1,
        $type_1,
        $ability_1,
        $colour_1,
        $artist_1,
        $flavor_1,
        $image_1,
        $cmc_1,
        $printed_name_1,
        $flavor_name_1,
        $name_2,
        $manacost_2,
        $power_2,
        $toughness_2,
        $loyalty_2,
        $type_2,
        $ability_2,
        $colour_2,
        $artist_2,
        $flavor_2,
        $image_2,
        $cmc_2,
        $printed_name_2,
        $flavor_name_2,
        $id_p1,
        $component_p1,
        $name_p1,
        $type_line_p1,
        $uri_p1,
        $id_p2,
        $component_p2,
        $name_p2,
        $type_line_p2,
        $uri_p2,
        $id_p3,
        $component_p3,
        $name_p3,
        $type_line_p3,
        $uri_p3,
        $id_p4,
        $component_p4,
        $name_p4,
        $type_line_p4,
        $uri_p4,
        $id_p5,
        $component_p5,
        $name_p5,
        $type_line_p5,
        $uri_p5,
        $id_p6,
        $component_p6,
        $name_p6,
        $type_line_p6,
        $uri_p6,
        $id_p7,
        $component_p7,
        $name_p7,
        $type_line_p7,
        $uri_p7,
        $maxpower,
        $minpower,
        $maxtoughness,
        $mintoughness,
        $maxloyalty,
        $minloyalty,
        $price_sort,
        $content_hash,
        $price_hash,
        $date,
        $primary,
        $primary
    );

    if ($bind === false) :
        mtgError(E_USER_ERROR, '[ERROR] scryfall_bulk.php: Binding parameters: ' . $db->error, __FILE__, __LINE__);
    endif;
    $hashBind = $hashStmt->bind_param("s", $hash_id);
    if ($hashBind === false) :
        mtgError(E_USER_ERROR, '[ERROR] scryfall_bulk.php: Binding hash id: ' . $db->error, __FILE__, __LINE__);
    endif;
    $hashBindResult = $hashStmt->bind_result($existing_content_hash, $existing_price_hash);
    if ($hashBindResult === false) :
        mtgError(
            E_USER_ERROR,
            '[ERROR] scryfall_bulk.php: Binding hash results: ' . $db->error,
            __FILE__,
            __LINE__
        );
    endif;
    $lastGoodId = null;
    $lastGoodCount = 0;

    $msg->logMessage('[DEBUG]', 'Starting bulk import transaction batch');
    $batchStart = $db->begin_transaction();
    if ($batchStart === false) :
        mtgError(
            E_USER_ERROR,
            '[ERROR] scryfall_bulk.php: Starting transaction batch: ' . $db->error,
            __FILE__,
            __LINE__
        );
    endif;

    try {
        foreach ($data as $key => $value) :
            $total_count = $total_count + 1;
            $commit_due = ($total_count % $batch_size === 0);
            $log_due = ($total_count % $log_interval === 0);

            // Bind vars that always exist
            $id = $value["id"] ?? null;
            if ($id === null) :
                $count_skip = $count_skip + 1;
                $msg->logMessage('[WARNING]', "Skipping record {$total_count}: missing id");
                if ($commit_due) :
                    $commitResult = $db->commit();
                    if ($commitResult === false) :
                        mtgError(
                            E_USER_ERROR,
                            '[ERROR] scryfall_bulk.php: Committing transaction batch: ' . $db->error,
                            __FILE__,
                            __LINE__
                        );
                    endif;
                    $msg->logMessage('[DEBUG]', "Committed transaction batch at record $total_count");
                    $batchStart = $db->begin_transaction();
                    if ($batchStart === false) :
                        mtgError(
                            E_USER_ERROR,
                            '[ERROR] scryfall_bulk.php: Starting transaction batch: ' . $db->error,
                            __FILE__,
                            __LINE__
                        );
                    endif;
                endif;
                if ($log_due) :
                    $timeslice = microtime(true) - $timeslice_start;
                    $commit_note = $commit_due ? '; batch committed' : '';
                    $msg->logMessage(
                        '[NOTICE]',
                        "Scryfall bulk API ($type) progress: $total_count records processed; timeslice: "
                        . sprintf('%.2f', $timeslice) . "s{$commit_note}"
                    );
                    $timeslice_start = microtime(true);
                endif;
                continue;
            endif;

            $msg->logMessage('[DEBUG]', "Scryfall bulk API ($type), Record $id: $total_count");

            // Re-null per record
            $multi_1 = $multi_2 = null;
            $number_int = null;

            /* Face 1 + Face 2 bind vars */
            $name_1 = $name_2 = null;
            $printed_name_1 = $printed_name_2 = null;
            $flavor_name_1 = $flavor_name_2 = null;
            $manacost_1 = $manacost_2 = null;
            $power_1 = $power_2 = null;
            $toughness_1 = $toughness_2 = null;
            $loyalty_1 = $loyalty_2 = null;
            $type_1 = $type_2 = null;
            $ability_1 = $ability_2 = null;
            $colour_1 = $colour_2 = null;
            $artist_1 = $artist_2 = null;
            $flavor_1 = $flavor_2 = null;
            $image_1 = $image_2 = null;
            $cmc_1 = $cmc_2 = null;

            /* Parts */
            $id_p1 = $component_p1 = $name_p1 = $type_line_p1 = $uri_p1 = null;
            $id_p2 = $component_p2 = $name_p2 = $type_line_p2 = $uri_p2 = null;
            $id_p3 = $component_p3 = $name_p3 = $type_line_p3 = $uri_p3 = null;
            $id_p4 = $component_p4 = $name_p4 = $type_line_p4 = $uri_p4 = null;
            $id_p5 = $component_p5 = $name_p5 = $type_line_p5 = $uri_p5 = null;
            $id_p6 = $component_p6 = $name_p6 = $type_line_p6 = $uri_p6 = null;
            $id_p7 = $component_p7 = $name_p7 = $type_line_p7 = $uri_p7 = null;

            /* JSON-ish bind vars */
            $colors = $game_types = $promo_types = $color_identity = $keywords = $produced_mana = null;
            $finishes = null;

            /* Derived stats */
            $maxpower = $minpower = $maxtoughness = $mintoughness = null;
            $maxloyalty = $minloyalty = null;
            $price_sort = null;
            $content_hash = null;
            $price_hash = null;

            /* New bind vars that replace direct $value[...] usage */
            $oracle_id = $value["oracle_id"] ?? null;
            $tcgplayer_id = $value["tcgplayer_id"] ?? null;

            $name = $value["name"] ?? null;
            $printed_name = $value["printed_name"] ?? null;
            $flavor_name = $value["flavor_name"] ?? null;

            $lang = $value["lang"] ?? null;
            $released_at = $value["released_at"] ?? null;

            $uri = $value["uri"] ?? null;
            $scryfall_uri = $value["scryfall_uri"] ?? null;
            $layout = $value["layout"] ?? null;

            $image_uri = $value["image_uris"]["normal"] ?? null;
            $mana_cost = $value["mana_cost"] ?? null;
            $cmc = $value["cmc"] ?? null;
            $type_line = $value["type_line"] ?? null;
            $oracle_text = $value["oracle_text"] ?? null;

            $power = $value["power"] ?? null;
            $toughness = $value["toughness"] ?? null;
            $loyalty = $value["loyalty"] ?? null;

            $reserved = $value["reserved"] ?? null;
            $foil = $value["foil"] ?? null;
            $nonfoil = $value["nonfoil"] ?? null;
            $oversized = $value["oversized"] ?? null;
            $promo = $value["promo"] ?? null;
            $set_id = $value["set_id"] ?? null;

            $set_code = $value["set"] ?? null;
            $set_name = $value["set_name"] ?? null;
            $collector_number = $value["collector_number"] ?? null;
            $rarity = $value["rarity"] ?? null;
            $flavor_text = $value["flavor_text"] ?? null;
            $card_back_id = $value["card_back_id"] ?? null;
            $artist = $value["artist"] ?? null;

            $gatherer_uri = $value["related_uris"]["gatherer"] ?? null;

            // Legalities (bind vars)
            $legality_standard = $value["legalities"]["standard"] ?? null;
            $legality_pioneer = $value["legalities"]["pioneer"] ?? null;
            $legality_modern = $value["legalities"]["modern"] ?? null;
            $legality_legacy = $value["legalities"]["legacy"] ?? null;
            $legality_pauper = $value["legalities"]["pauper"] ?? null;
            $legality_vintage = $value["legalities"]["vintage"] ?? null;
            $legality_commander = $value["legalities"]["commander"] ?? null;
            $legality_alchemy = $value["legalities"]["alchemy"] ?? null;
            $legality_historic = $value["legalities"]["historic"] ?? null;

            // Skip logic (leave unchanged)
            $skip = 1; // skip by default

            // Check if game type is to be included
            $games = $value['games'] ?? array();
            foreach ($games as $game_type) :
                if (in_array($game_type, $games_to_include, true)) :
                    $skip = 0;
                    break;
                endif;
            endforeach;

            // Check langs to include
            if (
                (in_array($lang, $langs_to_skip, true) and $type === 'default')
                or
                (in_array($lang, $langs_to_skip_all, true) and $type === 'all')
                or
                (in_array($layout, $layouts_to_skip, true))
            ) :
                $skip = 1;
            endif;

            // Only proceed if not to be skipped
            if ($skip === 1) :
                $count_skip = $count_skip + 1;
            elseif ($skip === 0) :
                $time = time();
                $count_inc = $count_inc + 1;

                // Card faces / parts / multiverse loops (keep logic, no structural changes)
                $cardFaces = $value['card_faces'] ?? array();
                if (!empty($cardFaces)) :
                    $face_loop = 1;
                    foreach ($cardFaces as $value3) :
                        if (isset($value3["name"])) :
                            ${'name_' . $face_loop} = $value3["name"];
                        endif;
                        if (isset($value3["printed_name"])) :
                            ${'printed_name_' . $face_loop} = $value3["printed_name"];
                        endif;
                        if (isset($value3["flavor_name"])) :
                            ${'flavor_name_' . $face_loop} = $value3["flavor_name"];
                        endif;
                        if (isset($value3["mana_cost"])) :
                            ${'manacost_' . $face_loop} = $value3["mana_cost"];
                        endif;
                        if (isset($value3["power"])) :
                            ${'power_' . $face_loop} = $value3["power"];
                        endif;
                        if (isset($value3["toughness"])) :
                            ${'toughness_' . $face_loop} = $value3["toughness"];
                        elseif (isset($value3["defense"])) :
                            ${'toughness_' . $face_loop} = $value3["defense"];
                        endif;
                        if (isset($value3["loyalty"])) :
                            ${'loyalty_' . $face_loop} = $value3["loyalty"];
                        endif;
                        if (isset($value3["type_line"])) :
                            ${'type_' . $face_loop} = $value3["type_line"];
                        endif;
                        if (isset($value3["oracle_text"])) :
                            ${'ability_' . $face_loop} = $value3["oracle_text"];
                        endif;
                        if (isset($value3["colors"])) :
                            ${'colour_' . $face_loop} = json_encode($value3["colors"]);
                        endif;
                        if (isset($value3["artist"])) :
                            ${'artist_' . $face_loop} = $value3["artist"];
                        endif;
                        if (isset($value3["flavor_text"])) :
                            ${'flavor_' . $face_loop} = $value3["flavor_text"];
                        endif;
                        if (isset($value3["image_uris"]["normal"])) :
                            ${'image_' . $face_loop} = $value3["image_uris"]["normal"];
                        endif;
                        if (isset($value3["cmc"])) :
                            ${'cmc_' . $face_loop} = $value3["cmc"];
                        endif;
                        $face_loop = $face_loop + 1;
                        if ($face_loop > 2) :
                            break;
                        endif;
                    endforeach;
                    $msg->logMessage(
                        '[DEBUG]',
                        "Scryfall bulk API ($type), Record $id: $total_count - finished face loops"
                    );
                endif;

                $allParts = $value['all_parts'] ?? array();
                if (!empty($allParts)) :
                    $all_parts_loop = 1;
                    foreach ($allParts as $value4) :
                        if (isset($value4["component"]) and $value4["component"] != "combo_piece") :
                            if (isset($value4["id"])) :
                                ${'id_p' . $all_parts_loop} = $value4["id"];
                            endif;
                            if (isset($value4["component"])) :
                                ${'component_p' . $all_parts_loop} = $value4["component"];
                            endif;
                            if (isset($value4["name"])) :
                                ${'name_p' . $all_parts_loop} = $value4["name"];
                            endif;
                            if (isset($value4["type_line"])) :
                                ${'type_line_p' . $all_parts_loop} = $value4["type_line"];
                            endif;
                            if (isset($value4["uri"])) :
                                ${'uri_p' . $all_parts_loop} = $value4["uri"];
                            endif;
                            $all_parts_loop = $all_parts_loop + 1;
                            if ($all_parts_loop > 7) :
                                break;
                            endif;
                        endif;
                    endforeach;
                endif;

                $multiverseIds = $value['multiverse_ids'] ?? array();
                $multiverse_loop = 1;
                foreach ($multiverseIds as $m_id) :
                    ${'multi_' . $multiverse_loop} = $m_id;
                    $multiverse_loop = $multiverse_loop + 1;
                    if ($multiverse_loop > 2) :
                        break;
                    endif;
                endforeach;

                // Derived power/toughness/loyalty (unchanged)
                $powerarray = array();
                $toughnessarray = array();
                $loyaltyarray = array();

                if (isset($value['power'])) :
                    array_push($powerarray, (int)$value['power']);
                endif;
                if (isset($power_1)) :
                    array_push($powerarray, (int)$power_1);
                endif;
                if (isset($power_2)) :
                    array_push($powerarray, (int)$power_2);
                endif;
                if (!empty($powerarray)) :
                    $maxpower = max($powerarray);
                    $minpower = min($powerarray);
                endif;

                if (isset($value['toughness'])) :
                    array_push($toughnessarray, (int)$value['toughness']);
                endif;
                if (isset($toughness_1)) :
                    array_push($toughnessarray, (int)$toughness_1);
                endif;
                if (isset($toughness_2)) :
                    array_push($toughnessarray, (int)$toughness_2);
                endif;
                if (!empty($toughnessarray)) :
                    $maxtoughness = max($toughnessarray);
                    $mintoughness = min($toughnessarray);
                endif;

                if (isset($value['loyalty'])) :
                    array_push($loyaltyarray, (int)$value['loyalty']);
                endif;
                if (isset($loyalty_1)) :
                    array_push($loyaltyarray, (int)$loyalty_1);
                endif;
                if (isset($loyalty_2)) :
                    array_push($loyaltyarray, (int)$loyalty_2);
                endif;
                if (!empty($loyaltyarray)) :
                    $maxloyalty = max($loyaltyarray);
                    $minloyalty = min($loyaltyarray);
                endif;

                // JSON-ish extras to bind vars (same names as your bind list)
                $colors = isset($value["colors"]) ? json_encode($value["colors"]) : null;
                $game_types = isset($value["games"]) ? json_encode($value["games"]) : null;
                $promo_types = isset($value["promo_types"]) ? json_encode($value["promo_types"]) : null;
                $finishes = isset($value["finishes"]) ? json_encode($value["finishes"]) : null;
                $color_identity = isset($value["color_identity"]) ? json_encode($value["color_identity"]) : null;
                $keywords = isset($value["keywords"]) ? json_encode($value["keywords"]) : null;
                $produced_mana = isset($value["produced_mana"]) ? json_encode($value["produced_mana"]) : null;

                // Prices -> new bind vars
                $price_usd = $value["prices"]['usd'] ?? null;
                $price_usd_foil = $value["prices"]['usd_foil'] ?? null;
                $price_usd_etched = $value["prices"]['usd_etched'] ?? null;

                // Keep your price_sort logic but run it using the new vars
                if ($price_usd_foil === null and $price_usd === null and $price_usd_etched === null) :
                    $price_sort = null;
                elseif ($price_usd_foil === null and $price_usd_etched === null) :
                    $price_sort = $price_usd;
                elseif ($price_usd === null and $price_usd_etched === null) :
                    $price_sort = $price_usd_foil;
                elseif ($price_usd_foil === null and $price_usd === null) :
                    $price_sort = $price_usd_etched;
                elseif ($price_usd === null) :
                    $price_sort = min($price_usd_etched, $price_usd_foil);
                elseif ($price_usd_foil === null) :
                    $price_sort = min($price_usd_etched, $price_usd);
                elseif ($price_usd_etched === null) :
                    $price_sort = min($price_usd, $price_usd_foil);
                else :
                    $price_sort = min($price_usd, $price_usd_foil, $price_usd_etched);
                endif;

                // Collector number -> number_int (keep existing normalisation)
                if (isset($value["collector_number"])) :
                    $coll_no = $value["collector_number"];

                    if (isset($value["layout"]) and $value["layout"] === 'meld') :
                        $coll_no = str_replace('a', '', $coll_no);
                        $coll_no = str_replace('b', '', $coll_no);
                    endif;

                    $coll_no = str_replace('-', '', $coll_no);
                    $coll_no = str_replace('a', '1', $coll_no);
                    $coll_no = str_replace('b', '2', $coll_no);
                    $coll_no = str_replace('c', '3', $coll_no);
                    $coll_no = str_replace('d', '4', $coll_no);
                    $coll_no = str_replace('e', '5', $coll_no);
                    $coll_no = str_replace('f', '6', $coll_no);
                    $coll_no = str_replace('g', '7', $coll_no);
                    $coll_no = str_replace('h', '8', $coll_no);
                    $coll_no = str_replace('E', '', $coll_no);
                    $coll_no = str_replace('★', '', $coll_no);
                    $coll_no = str_replace('*', '', $coll_no);
                    $coll_no = str_replace('†', '', $coll_no);
                    $coll_no = str_replace('U', '', $coll_no);

                    // For cards with collector number "XXXs", turn into "5XXX"
                    // so they go to the end of the series
                    if (substr($coll_no, strlen($coll_no) - 1) === 's') :
                        $coll_no = str_replace('s', '', $coll_no);
                        if (ctype_digit($coll_no)) :
                            $coll_no = (int) $coll_no + 5000;
                        endif;
                    endif;

                    if (substr($coll_no, strlen($coll_no) - 1) === 'p') :
                        $coll_no = str_replace('p', '', $coll_no);
                    endif;

                    $number_int = (int) $coll_no;
                endif;

                $contentHashData = array(
                    $id,
                    $oracle_id,
                    $tcgplayer_id,
                    $multi_1,
                    $multi_2,
                    $name,
                    $printed_name,
                    $flavor_name,
                    $lang,
                    $released_at,
                    $uri,
                    $scryfall_uri,
                    $layout,
                    $image_uri,
                    $mana_cost,
                    $cmc,
                    $type_line,
                    $oracle_text,
                    $power,
                    $toughness,
                    $loyalty,
                    $colors,
                    $color_identity,
                    $keywords,
                    $produced_mana,
                    $legality_standard,
                    $legality_pioneer,
                    $legality_modern,
                    $legality_legacy,
                    $legality_pauper,
                    $legality_vintage,
                    $legality_commander,
                    $legality_alchemy,
                    $legality_historic,
                    $reserved,
                    $foil,
                    $nonfoil,
                    $oversized,
                    $promo,
                    $set_id,
                    $game_types,
                    $finishes,
                    $promo_types,
                    $set_code,
                    $set_name,
                    $number_int,
                    $collector_number,
                    $rarity,
                    $flavor_text,
                    $card_back_id,
                    $artist,
                    $gatherer_uri,
                    $name_1,
                    $manacost_1,
                    $power_1,
                    $toughness_1,
                    $loyalty_1,
                    $type_1,
                    $ability_1,
                    $colour_1,
                    $artist_1,
                    $flavor_1,
                    $image_1,
                    $cmc_1,
                    $printed_name_1,
                    $flavor_name_1,
                    $name_2,
                    $manacost_2,
                    $power_2,
                    $toughness_2,
                    $loyalty_2,
                    $type_2,
                    $ability_2,
                    $colour_2,
                    $artist_2,
                    $flavor_2,
                    $image_2,
                    $cmc_2,
                    $printed_name_2,
                    $flavor_name_2,
                    $id_p1,
                    $component_p1,
                    $name_p1,
                    $type_line_p1,
                    $uri_p1,
                    $id_p2,
                    $component_p2,
                    $name_p2,
                    $type_line_p2,
                    $uri_p2,
                    $id_p3,
                    $component_p3,
                    $name_p3,
                    $type_line_p3,
                    $uri_p3,
                    $id_p4,
                    $component_p4,
                    $name_p4,
                    $type_line_p4,
                    $uri_p4,
                    $id_p5,
                    $component_p5,
                    $name_p5,
                    $type_line_p5,
                    $uri_p5,
                    $id_p6,
                    $component_p6,
                    $name_p6,
                    $type_line_p6,
                    $uri_p6,
                    $id_p7,
                    $component_p7,
                    $name_p7,
                    $type_line_p7,
                    $uri_p7,
                    $maxpower,
                    $minpower,
                    $maxtoughness,
                    $mintoughness,
                    $maxloyalty,
                    $minloyalty
                );
                $contentPayload = json_encode($contentHashData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if ($contentPayload === false) :
                    $msg->logMessage('[WARNING]', "Failed to JSON encode content_hash data for $id");
                    $contentPayload = '';
                endif;
                $content_hash = sha1($contentPayload);

                $priceHashData = array(
                    $price_usd,
                    $price_usd_foil,
                    $price_usd_etched,
                    $price_sort
                );
                $pricePayload = json_encode($priceHashData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if ($pricePayload === false) :
                    $msg->logMessage('[WARNING]', "Failed to JSON encode price_hash data for $id");
                    $pricePayload = '';
                endif;
                $price_hash = sha1($pricePayload);

                $content_changed = false;
                $price_changed = false;
                $existing_content_hash = null;
                $existing_price_hash = null;

                $hash_id = $id;
                $hashExec = $hashStmt->execute();
                if ($hashExec === false) :
                    mtgError(
                        E_USER_ERROR,
                        '[ERROR] scryfall_bulk.php: Checking existing hashes: ' . $db->error,
                        __FILE__,
                        __LINE__
                    );
                endif;
                $hashStore = $hashStmt->store_result();
                if ($hashStore === false) :
                    mtgError(
                        E_USER_ERROR,
                        '[ERROR] scryfall_bulk.php: Storing hash results: ' . $db->error,
                        __FILE__,
                        __LINE__
                    );
                endif;
                if ($hashStmt->num_rows > 0) :
                    $hashStmt->fetch();
                    $content_changed = ($existing_content_hash !== $content_hash);
                    $price_changed = ($existing_price_hash !== $price_hash);
                endif;
                $hashStmt->free_result();

                // Execute using already-bound params
                $exec = $stmt->execute();

                if ($exec === false) :
                    mtgError(
                        E_USER_ERROR,
                        "[ERROR] scryfall_bulk.php: Writing new card details: " . $db->error,
                        __FILE__,
                        __LINE__
                    );
                else :
                    $lastGoodId = $id;
                    $lastGoodCount = $total_count;
                    $status = $stmt->affected_rows; // 1 = add, 2 = change, 0 = no change

                    if ($status === 1) :
                        $count_add = $count_add + 1;
                        $msg->logMessage('[DEBUG]', "Added card - no error returned; return code: $status");

                        if ($imageDownloads === true) :
                            $imageManager->getImage(
                                $set_code,
                                $id,
                                $imgLocation,
                                $layout,
                                $twoCardDetailSections
                            );
                        endif;
                    elseif ($status === 2) :
                        $count_update = $count_update + 1;
                        if ($content_changed === true and $price_changed === true) :
                            $count_update_both = $count_update_both + 1;
                            $count_update_content = $count_update_content + 1;
                            $count_update_price = $count_update_price + 1;
                            $msg->logMessage(
                                '[DEBUG]',
                                "Updated card - content and price hash change; return code: $status"
                            );
                        elseif ($content_changed === true) :
                            $count_update_content = $count_update_content + 1;
                            $msg->logMessage(
                                '[DEBUG]',
                                "Updated card - content hash change; return code: $status"
                            );
                        elseif ($price_changed === true) :
                            $count_update_price = $count_update_price + 1;
                            $msg->logMessage(
                                '[DEBUG]',
                                "Updated card - price hash change; return code: $status"
                            );
                        else :
                            $msg->logMessage(
                                '[WARNING]',
                                "Updated card - hash change not detected; return code: $status"
                            );
                        endif;
                    else :
                        $count_other = $count_other + 1;
                        $msg->logMessage('[DEBUG]', "No change - no error returned; return code: $status");
                    endif;

                    if ($bulkDiagnosticEnabled) :
                        $is_change = ($status !== 0);
                        $log_change = $is_change && $diag_change_count < $diag_limit;
                        $log_no_change = (!$is_change) && $diag_no_change_count < $diag_limit;
                        if ($log_change || $log_no_change) :
                            $diag_bucket = $is_change ? 'change' : 'no_change';
                            if ($log_change) :
                                $diag_change_count = $diag_change_count + 1;
                            else :
                                $diag_no_change_count = $diag_no_change_count + 1;
                            endif;
                            $diag_payload = [
                                'bucket' => $diag_bucket,
                                'status' => $status,
                                'id' => $id,
                                'name' => $name,
                                'set_code' => $set_code,
                                'collector_number' => $collector_number,
                                'layout' => $layout,
                                'lang' => $lang,
                                'content_changed' => $content_changed,
                                'price_changed' => $price_changed,
                                'existing_content_hash' => $existing_content_hash,
                                'existing_price_hash' => $existing_price_hash,
                                'content_hash' => $content_hash,
                                'price_hash' => $price_hash,
                                'content_hash_data' => $contentHashData,
                                'price_hash_data' => $priceHashData,
                                'prices' => [
                                    'usd' => $price_usd,
                                    'usd_foil' => $price_usd_foil,
                                    'usd_etched' => $price_usd_etched,
                                    'price_sort' => $price_sort
                                ],
                                'scryfall_record' => $value
                            ];
                            $diag_json = json_encode(
                                $diag_payload,
                                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                            );
                            if ($diag_json === false) :
                                $diag_json = 'Bulk diagnostic JSON encoding failed for ' . ($id ?? 'unknown id');
                            endif;
                            $msg->logBulkDiagnostic($diag_json);
                        endif;
                    endif;
                endif;
            endif;
            if ($commit_due) :
                $commitResult = $db->commit();
                if ($commitResult === false) :
                    mtgError(
                        E_USER_ERROR,
                        '[ERROR] scryfall_bulk.php: Committing transaction batch: ' . $db->error,
                        __FILE__,
                        __LINE__
                    );
                endif;
                $msg->logMessage('[DEBUG]', "Committed transaction batch at record $total_count");
                $batchStart = $db->begin_transaction();
                if ($batchStart === false) :
                    mtgError(
                        E_USER_ERROR,
                        '[ERROR] scryfall_bulk.php: Starting transaction batch: ' . $db->error,
                        __FILE__,
                        __LINE__
                    );
                endif;
            endif;
            if ($log_due) :
                $timeslice = microtime(true) - $timeslice_start;
                $commit_note = $commit_due ? '; batch committed' : '';
                $msg->logMessage(
                    '[NOTICE]',
                    "Scryfall bulk API ($type) progress: $total_count records processed; timeslice: "
                    . sprintf('%.2f', $timeslice) . "s{$commit_note}"
                );
                $timeslice_start = microtime(true);
            endif;
        endforeach;
    } catch (Throwable $e) {
        $msg->logMessage(
            '[ERROR]',
            "Bulk import aborted (likely truncated JSON). Last good: {$lastGoodId} at {$lastGoodCount}. "
            . "File: {$file_location}. Error: " . $e->getMessage()
        );
        $db->rollback();

        $badPath = $file_location . '.bad-' . date('Ymd-His');
        $renamed = @rename($file_location, $badPath);
        $msg->logMessage(
            $renamed ? '[NOTICE]' : '[WARNING]',
            $renamed
                ? "Quarantined bad JSON to {$badPath}"
                : "Failed to quarantine JSON from {$file_location} to {$badPath}"
        );

        return "FAILED: aborted at {$lastGoodCount} (id {$lastGoodId}). Quarantined to {$badPath}";
    }
    $commitResult = $db->commit();
    if ($commitResult === false) :
        throw new Exception('[ERROR] scryfall_bulk.php: Final commit failed: ' . $db->error);
    endif;
    $stmt->close();
    $hashStmt->close();

    $msg->logMessage(
        '[NOTICE]',
        "Bulk update completed: Total $total_count, added: $count_add, skipped $count_skip, "
        . "included $count_inc, updated: $count_update (content: $count_update_content, "
        . "price: $count_update_price, both: $count_update_both), other: $count_other"
    );
    $message = "Total: $total_count; total added: $count_add; total skipped: $count_skip; "
        . "total included: $count_inc; total updated: $count_update (content: $count_update_content; "
        . "price: $count_update_price; both: $count_update_both)";
    return $message;
    // return $message to then use in parent to send email using MyPHPMailer
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

function cardLegalDBField($decktype)
{
    global $db, $deck_legality_map, $logfile;
    $msg = new \MTG\Core\Message($logfile);

    $msg->logMessage('[DEBUG]', "Looking up db_field for legality for deck type '$decktype'");
    $index = array_search("$decktype", array_column($deck_legality_map, 'decktype'));
    if ($index !== false) :
        $db_field = $deck_legality_map[$index]['db_field'];
    endif;
    $msg->logMessage('[DEBUG]', "Deck type '$decktype' has legality in '$db_field'");
    return $db_field;
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

function deckLegalList($deckNumber, $deck_type, $db_field)
{
    global $db, $logfile;
    $msg = new \MTG\Core\Message($logfile);

    $msg->logMessage(
        '[DEBUG]',
        "Getting deck legality list for $deck_type deck '$deckNumber' (using db_field '$db_field')"
    );
    $sql = "SELECT cardnumber FROM deckcards WHERE decknumber = ?";
    $msg->logMessage('[DEBUG]', "Looking up SQL: $sql");
    $sqlresult = $db->execute_query($sql, [$deckNumber]);
    if ($sqlresult === false) :
        throw new Exception(
            '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                . ": SQL failure: " . $db->error
        );
    else :
        $i = 0;
        $record = array();
        while ($row = $sqlresult->fetch_assoc()) :
            $record[$i] = $row['cardnumber'];
            $i = $i + 1;
        endwhile;
    endif;
    $list = array();
    $p = 0;
    foreach ($record as $value) :
        $sql2 = "SELECT $db_field FROM cards_scry WHERE id = ? LIMIT 1";
        $sqlresult2 = $db->execute_query($sql2, [$value]);
        if ($sqlresult2 === false) :
            throw new Exception(
                '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                    . ": SQL failure: " . $db->error
            );
        else :
            $row2 = $sqlresult2->fetch_array(MYSQLI_ASSOC);
            $legal = $row2["$db_field"];
        endif;
        $list[$p]['id'] = $value;
        $list[$p]['legality'] = $legal;
        $p = $p + 1;
    endforeach;
    return $list;
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

/*
 * Generate (or return existing) CSRF token for the user's session.
 */

function generateCsrfToken()
{
    if (!isset($_SESSION['csrf_token'])) :
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    endif;

    return $_SESSION['csrf_token'];
}

/*
 * Validate the submitted CSRF token against the session token.
 */

function validateCsrfToken($submittedToken)
{
    if (!isset($_SESSION['csrf_token']) || !is_string($submittedToken)) :
        return false;
    endif;

    return hash_equals($_SESSION['csrf_token'], $submittedToken);
}

function inputInterpreter($input_string)
{
    // Called by quickAdd in deckmanager class, index.php search inputs and profile.php collection imports
    // This function takes an input string, either from deck quick add or search strings,
    // and strips it into components:
    // - UUID
    // - qty (not applicable for searches)
    // - cardname
    // - set
    // - collector number
    global $db, $logfile, $bracketsInNames, $importLinestoIgnore;
    $msg = new \MTG\Core\Message($logfile);

    $msg->logMessage('[DEBUG]', "Input interpreter called with '$input_string'");
    $raw_string = $input_string;
    $sanitised_string = htmlspecialchars($input_string, ENT_NOQUOTES, 'UTF-8');

    // Define is_csv as a closure
    $is_csv = function ($string) use ($logfile) {
        $msg = new \MTG\Core\Message($logfile);
        // Check if the string contains at least 4 commas
        $comma_count = substr_count($string, ',');
        if ($comma_count < 4) :
            $msg->logMessage('[DEBUG]', "Input is not CSV");
            return false;
        endif;

        // Check if the string can be parsed into fields
        $fields = str_getcsv($string, ',', '"', '\\');

        // If str_getcsv returns an array with more than one element, it's likely a CSV
        $fieldcount = count($fields);
        $msg->logMessage('[DEBUG]', "Input is CSV, returning field count $fieldcount");
        return $fieldcount > 1;
    };

    // Define extract_and_process_csv as a closure
    $extract_and_process_csv = function ($line) use ($logfile) {
        $msg = new \MTG\Core\Message($logfile);

        // Parse the CSV row, with basic sanity checking on where things should be and what they should look like
        $fields = str_getcsv($line, ',', '"', '\\');
        $qtyFields = count($fields);

        if ($qtyFields === 6 || $qtyFields === 8) : // Only check if it has 6 or 8 fields, otherwise don't bother
            // Header check
            $headerKeywords  = ['set', 'number', 'name'];
            $isHeader = true;
            foreach ($headerKeywords as $keyword) :
                $found = false;
                foreach ($fields as $field) :
                    if (stripos($field, $keyword) !== false) :
                        $found = true;
                        break;
                    endif;
                endforeach;
                if (!$found) :
                    $isHeader = false;
                    break;
                endif;
            endforeach;
            if ($isHeader) :
                return 'header';
            endif;

            // Validate and determine CSV format
            if ($qtyFields === 6) :
                if (
                    !isValidSetcode($fields[0]) ||
                    !isValidCardName($fields[2]) ||
                    !(is_numeric($fields[3]) || empty($fields[3])) ||
                    !(is_numeric($fields[4]) || empty($fields[4])) ||
                    !validUUID($fields[5])
                ) :
                    $csvFormat = 'invalid';
                else :
                    $csvFormat = 'delver';
                endif;
            elseif ($qtyFields === 8) :
                if (
                    !isValidSetcode($fields[0]) ||
                    !isValidCardName($fields[2]) ||
                    !isValidLanguageCode($fields[3]) ||
                    !(is_numeric($fields[4]) || empty($fields[4])) ||
                    !(is_numeric($fields[5]) || empty($fields[5])) ||
                    !(is_numeric($fields[6]) || empty($fields[6])) ||
                    !(validUUID($fields[7]) || empty($fields[7]))
                ) :
                    $csvFormat = 'invalid';
                else :
                    $csvFormat = 'mtgc';
                endif;
            else :
                $csvFormat = 'invalid';
            endif;
            $msg->logMessage('[DEBUG]', "CSV input has $qtyFields fields, format is '$csvFormat'");

            if ($csvFormat === 'invalid') :
                return false;
            endif;

            // Extracting common fields
            $set    = $fields[0];
            $number = $fields[1];
            $name   = $fields[2];

            // Extracting other fields based on format
            if ($csvFormat === 'mtgc') :
                $lang   = $fields[3];
                $param5 = isset($fields[4]) ? (int)$fields[4] : 0;
                $param6 = isset($fields[5]) ? (int)$fields[5] : 0;
                $param7 = isset($fields[6]) ? (int)$fields[6] : 0;
                $uuid   = isset($fields[7]) ? $fields[7] : '';
            elseif ($csvFormat === 'delver') : // No etched in Delver Lens files
                $lang   = 'unspecified';
                $param5 = isset($fields[3]) ? (int)$fields[3] : 0;
                $param6 = isset($fields[4]) ? (int)$fields[4] : 0;
                $param7 = 0;
                $uuid   = isset($fields[5]) ? $fields[5] : '';
            else :
                return false;
            endif;

            // Sum the values of parameters 5, 6, and 7 for merged quantity input (used in decks)
            $qty = $param5 + $param6 + $param7;

            return [
                'set' => $set,
                'number' => $number,
                'name' => $name,
                'lang' => $lang,
                'qty' => $qty,
                'uuid' => $uuid,
                'normal' => $param5,
                'foil' => $param6,
                'etched' => $param7
            ];
        else :
            $msg->logMessage('[ERROR]', "Invalid CSV format: $line");
            return false;
        endif;
    };

    // MAIN PROCESSING //

    // Is the line CSV with at least 4 fields?
    if ($is_csv($raw_string)) :
        // The line is in CSV format
        $result = $extract_and_process_csv($raw_string);

        if ($result === 'header') :
            return 'header';
        elseif ($result !== false) :
            if (($result['normal'] + $result['foil'] + $result['etched'] === 0) && $result['qty'] > 0) :
                $result['normal'] = $result['qty'];
            endif;
            $msg->logMessage('[DEBUG]', "Input interpreter result (CSV): Qty: "
                    . "[{$result['qty']} (N: {$result['normal']},"
                    . " F: {$result['foil']}, E: {$result['etched']})] x Card: [{$result['name']}] "
                    . "Set: [{$result['set']}] Collector number: [{$result['number']}] "
                    . "UUID: [{$result['uuid']}]");
            return [
                'set' => $result['set'],
                'number' => $result['number'],
                'name' => $result['name'],
                'lang' => $result['lang'],
                'qty' => $result['qty'],
                'uuid' => $result['uuid'],
                'normal' => $result['normal'],
                'foil' => $result['foil'],
                'etched' => $result['etched']
            ];
        else :
            return false;
        endif;
    elseif (
        trim($sanitised_string) === '' || inArrayCaseInsensitive(trim($sanitised_string), $importLinestoIgnore)
    ) :
        return 'empty line';
    else :
        // Not a CSV
        // Need to interpret a text line
        // as either a moxfield decklist line or a MTGC quick add text line
        // (MTGC has no info on normal/foil/etched)

        // If the string starts with a number < 1000, assume it's a quantity and
        // strip it from the string into a variable,
        // leaving the rest of the string to be assessed for name / set / number.
        // The only card names that start with numbers are Year cards, e.g.
        // 2001 World Championships Ad etc.

        $patternNumber = '/^(\d{1,3})\s+(.*)/'; // Match numbers up to 3 digits, and remove into $qty
        $matches = [];
        if (preg_match($patternNumber, trim($sanitised_string), $matches)) :
            $qty = $matches[1];
            $sanitised_string = trim($matches[2]);
        else :
            $qty = '';
            $sanitised_string = trim($sanitised_string);
        endif;

        // If string contains an opening ( or [ but no closing ) or ], then terminate the string with %] and submit
        if (
            strpos($sanitised_string, '(') !== false
            &&
            strpos($sanitised_string, ']') === false
            &&
            strpos($sanitised_string, ')') === false
        ) :
            $sanitised_string = $sanitised_string . "%)";
        elseif (
            strpos($sanitised_string, '[') !== false
            &&
            strpos($sanitised_string, ']') === false
            &&
            strpos($sanitised_string, ')') === false
        ) :
            $sanitised_string = $sanitised_string . "%]";
        endif;

        // Shortcut matches
        $pattern_shortcut1 = '/^[[(]([^)\]]+)[\])]\s+(\d+\S*?)$/';         // e.g. (mh3) 304 or [mh3] 304
        $pattern_shortcut2 = '/^[[(]([^)\]]+)\s+(\d+\S*?)[)\]]$/';         // e.g. (mh3 304) or [mh3 304]

        // Full matches
        $pattern_full_1    = '/^(.+?)\s+[(\[]([^)\]]+)[)\]]\s+(\d+\S*?)(\s\*F\*)?$/';
           // Plains (mh3) 304 or Plains [mh3] 304   Note - quantity already removed
        $pattern_full_2    = '/^(.+?)\s+[(\[]([^)\]]+)\s+(\d+\S*?)[)\]](\s\*F\*)?$/';
           // Plains (mh3 304) or Plains [mh3 304]   Note - quantity already removed

        // Legacy match - catches remaining non-specific cases, e.g. "Plains"
        $pattern_mtgc      = "/^([^()\[\]]+)?(?:[\[\(]\s*([^)\]\s]+)(?:\s*([^)\]\s]+(?:\s+[^)\]\s]+)*)?)?\s*[\)\]])?/";

        // Shortcut matches (qty irrelevant)
        if (
            preg_match($pattern_shortcut1, $sanitised_string, $matches)
            ||
            preg_match($pattern_shortcut2, $sanitised_string, $matches)
        ) :
            $msg->logMessage('[DEBUG]', "Input interpreter result: String '$sanitised_string' is shortcut");
            $format = 'shortcut';
            // Set
            if (isset($matches[1])) :
                $set = strtoupper($matches[1]);
            else :
                $set = '';
            endif;
            // Collector number
            if (isset($matches[2])) :
                $number = $matches[2];
            else :
                $number = '';
            endif;
            $msg->logMessage(
                '[DEBUG]',
                "Input interpreter result (Shortcut): Set: [$set] Collector number: [$number]"
            );
            $output = [
                'set' => $set,
                'number' => $number,
                'name' => '',
                'lang' => '',
                'qty' => $qty,
                'uuid' => '',
                'normal' => 0,
                'foil' => 0,
                'etched' => 0
            ];

        // Full matches
        elseif (
            preg_match($pattern_full_1, $sanitised_string, $matches)
            ||
            preg_match($pattern_full_2, $sanitised_string, $matches)
        ) :
            $msg->logMessage('[DEBUG]', "Input interpreter result: String '$sanitised_string' is full string");
            $format = 'full';
            if ($qty === '') :
                $qty = 1;
            endif;
            $isFoil = isset($matches[4]) ? true : false;
            if ($isFoil) :
                $normal = 0;
                $foil = $qty;
            else :
                 $normal = $qty;
                 $foil = 0;
            endif;
            // Name
            if (isset($matches[1])) :
                $name = trim($matches[1]);
            else :
                $name = '';
            endif;
            // Set
            if (isset($matches[2])) :
                $set = strtoupper($matches[2]);
            else :
                $set = '';
            endif;
            // Collector number
            if (isset($matches[3])) :
                $number = $matches[3];
            else :
                $number = '';
            endif;
            $name = htmlspecialchars_decode($name, ENT_QUOTES);
            $msg->logMessage(
                '[DEBUG]',
                "Input interpreter result (full): Qty: [$qty (N:$normal / F:$foil)] x Card: [$name] "
                    . "Set: [$set] Collector number: [$number]"
            );
            $output = [
                'set' => $set,
                'number' => $number,
                'name' => $name,
                'lang' => '',
                'qty' => $qty,
                'uuid' => '',
                'normal' => $normal,
                'foil' => $foil,
                'etched' => 0
                ];
        elseif (preg_match($pattern_mtgc, trim($sanitised_string), $matches)) :
            $msg->logMessage('[DEBUG]', "Input interpreter result: String '$sanitised_string' is mtgc");
            $format = 'mtgc';
            if ($qty === '') :
                $qty = 1;
            endif;

            // Name
            /// Catch fringe cases where name contains brackets ///
            if (isset($matches[1]) && isset($matches[2])) :
                if (isset($matches[3])) :
                    $teststring = trim($matches[2]) . " " . trim($matches[3]);
                else :
                    $teststring = trim($matches[2]);
                endif;
            endif;
            if (isset($teststring) && inArrayCaseInsensitive($teststring, $bracketsInNames)) :
                $msg->logMessage(
                    '[DEBUG]',
                    "Bracket contents match a card with brackets in name, resetting name, set to match"
                );
                $matches[1] = $matches[1] . "(" . $teststring . ")";
                $matches[2] = $matches[3] = '';
            endif;

            if (isset($matches[1])) :
                $name = trim($matches[1]);
            else :
                $name = '';
            endif;
            // Set
            if (isset($matches[2])) :
                $set = strtoupper($matches[2]);
            else :
                $set = '';
            endif;
            // Collector number
            if (isset($matches[3])) :
                $number = $matches[3];
            else :
                $number = '';
            endif;
            $name = htmlspecialchars_decode($name, ENT_QUOTES);
            $msg->logMessage(
                '[DEBUG]',
                "Input interpreter result (MTGC Quick add): Qty: [$qty] x Card: [$name] Set: [$set] "
                    . "Collector number: [$number]"
            );
            $output = [
                'set' => $set,
                'number' => $number,
                'name' => $name,
                'lang' => '',
                'qty' => $qty,
                'uuid' => '',
                'normal' => $qty,
                'foil' => 0,
                'etched' => 0
            ];
        else :
            return false;
        endif;
        return $output;
    endif;
}
