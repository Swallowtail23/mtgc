<?php
/* Version:     24.3
    Date:       08/07/24
    Name:       functions.php
    Purpose:    Functions for all pages
    Notes:      
        
    1.0
                Initial version
    2.0         
                Added Custom error handling routine
 *  3.0
 *              Updated for classes and Mysqli_Manager
 *              including merging basicfunc and functions files
 *              Updated deckcard functions to use mysqli / $db
 * 4.0
 *              Added Function to see if an Admin user is running the page.
 * 5.0          
 *              Added functions in tidying up image routines, removing code from 
 *              carddetail page. Also added better flip image handling, and a 
 *              function to get the main image name.
 * 5.1      
 *              Added filter for # in symbolreplace function
 * 
 * 6.0
 *              Added login stamp to database to track last logged in date.
 * 7.0
 *              Added Scyfall JSON code
 * 7.1
 *              Added override for Scryfall JSON function (works with
 *              scrynameoverride column in cards table)
 * 7.2
 *              Corrected incorrect detection of null in scryfall data fetch
 * 8.0
 *              Moved from writelog to Message class
 * 9.0
 *              Marking tcgplayer function to be deprecated; improving scryfall 
 *              image function (+ get by set and number)
 * 10.0
 *              Refactoring for cards_scry
 * 11.0
 *              PHP 8.1 compatibility
 * 12.0
 *              Add flip image capability for battle cards
 * 13.0
 *              Deck card adding rewrite for Commander, etc.
 * 14.0
 *              Add function to get decks with a card
 * 15.0
 *              Functions to delete a deck and rename a deck
 * 16.0
 *              Added handling for etched cards
 * 17.0
 *              Review and improve Scryfall price routine
 *
 * 18.0         25/11/23
 *              Migrate more to prepared statements, and add collector number to Quick Add
 * 
 * 18.1         11/12/23
 *              Move deck, price, import/export, image functions to respective classes
 * 
 * 19.0         02/01/24
 *              Move bulk functions into this file
 * 
 * 20.0         13/01/24
 *              Move import/export function to just return message instead of emailing
 * 
 * 21.0         20/01/24
 *              Move to logMessage method
 * 
 * 22.0         06/06/24
 *              Move search interpreter to global function instead of individually 
 *              on each page and deck add. 
 *              Also aligns process with deck add interptation
 * 
 * 23.0         09/06/24
 *              Add CSV functions for deck/card add interpretation
 * 
 * 24.0         05/07/24
 *              Changes to input_interpreter to cater for import rewrite
 *              MTGC-100
 * 
 * 24.1         06/07/24
 *              Add moxfield decklist interpreter
 *              MTGC-100
 *              Improve shortcut searching
 * 
 * 24.2         07/07/24
 *              Improve input interpretation rigour and flexibility, including bracketed names
 * 
 * 24.3         08/07/24
 *              Ignore deck import lines with just titles MTGC105
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;

function forcechgpwd()
{ 
    global $_SESSION; 
    if ((isset($_SESSION["chgpwd"])) AND ($_SESSION["chgpwd"] == TRUE)):
        header("Location: /profile.php"); 
    endif;
}

function check_input($value)
{
    global $db;
    if (!is_numeric($value)):
        $value = "'" . $db->real_escape_string($value) . "'";
    endif;

    return $value;
}

function cssver()
{
    global $db;
    $sql = "SELECT usemin FROM admin LIMIT 1";
    $result = $db->execute_query($sql);
    if ($result === false):
        trigger_error('[ERROR]',basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ".$db->error,E_USER_ERROR);
    else:
        $row = $result->fetch_assoc();
        if (!empty($row) AND $row['usemin'] == 1):
            return "-min";
        else:
            return "";
        endif;
    endif;
}

function spamcheck($field)
{
    global $db, $logfile;
    $msg = new Message($logfile);
    
    // Sanitize e-mail address
    $msg->logMessage('[DEBUG]',"Checking email address <$field>");
    $field = filter_var($field, FILTER_SANITIZE_EMAIL);
    if (!filter_var($field, FILTER_VALIDATE_EMAIL)):
        $msg->logMessage('[ERROR]',"Invalid email address <$field> passed");
        return FALSE;
    else:
        $sql = "SELECT usernumber, username FROM users WHERE email = ? LIMIT 1";
        $result = $db->execute_query($sql, [$field]);
        if ($result === false):
            trigger_error('[ERROR]', basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__ . ": SQL failure: " . $db->error, E_USER_ERROR);
        else:
            $row = $result->fetch_assoc();
            if (empty($row)):
                return 'No match';
            elseif (filter_var($field, FILTER_VALIDATE_EMAIL)):
                $msg->logMessage('[NOTICE]',"Email address validated for reset request");
                return $field;
            else:
                return FALSE;
            endif;
        endif;
    endif;
}

function setmtcemode($toggle)
{
    global $db, $logfile;
    $msg = new Message($logfile);
    if ($toggle == 'off'):
        $msg->logMessage('[NOTICE]',"Setting maintenance mode off");
        $mtcequery = 0;
    elseif ($toggle == 'on'):
        $msg->logMessage('[NOTICE]',"Setting maintenance mode on");
        $mtcequery = 1;
    endif;
        $query = 'UPDATE admin SET mtce=?';
        $stmt = $db->prepare($query);
        $stmt->bind_param('i', $mtcequery);
        if ($stmt === false):
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": Binding SQL: ". $db->error, E_USER_ERROR);
        endif;
        $exec = $stmt->execute();
        if ($exec === false):
            $msg->logMessage('[NOTICE]',"Setting mtce mode to $mtcequery failed");
        else:
            $msg->logMessage('[NOTICE]',"Set mtce mode to $mtcequery");
        endif;
}

function mtcemode($user)
{
    global $db,$logfile;
    $msg = new Message($logfile);
    
    $msg->logMessage('[DEBUG]',"Checking maintenance mode, user $user");
    $sql1 = "SELECT mtce FROM admin LIMIT 1";
    $result1 = $db->execute_query($sql1);
    if ($result1 === false):
        trigger_error('[ERROR]', basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__ . ": SQL failure: " . $db->error, E_USER_ERROR);
    else:
        $row1 = $result1->fetch_assoc();
        if (!empty($row1) AND $row1['mtce'] == 1):
            $msg->logMessage('[DEBUG]',"Maintenance mode on, running admin check");
            $sql2 = "SELECT admin FROM users WHERE usernumber = ?";
            $result2 = $db->execute_query($sql2, [$user]);
            if ($result2 === false):
                trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ".$db->error, E_USER_ERROR);
            else:
                $row2 = $result2->fetch_assoc();
                if (!empty($row2)):
                    if ($row2['admin'] == 1):
                        $msg->logMessage('[DEBUG]',"Maintenance mode on, user is admin, ignoring (return 2)");
                        return 2;
                    else:
                        $msg->logMessage('[DEBUG]',"Maintenance mode on, user is not admin (return 1, destroy session)");
                        return 1;
                    endif;
                else:
                    trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
                endif;
            endif;
        else:
            $msg->logMessage('[DEBUG]',"Maintenance mode not set");
            return 0; // maintenance mode not set
        endif;
    endif;
}

function symbolreplace($str)
{
    $str = str_replace('{E}','<img src="images/e.png" alt="{E}" class="manaimg">',$str);
    $str = str_replace('{T}','<img src="images/t.png" alt="{T}" class="manaimg">',$str);
    $str = str_replace('{Q}','<img src="images/q.png" alt="{Q}" class="manaimg">',$str);
    $str = str_replace('{P}','<img src="images/paw.png" alt="{Q}" class="manaimg" title="pawprint">',$str);
    $str = str_replace('{H}','Phyrexian mana ',$str);
    
    $str = str_replace('{W}','<img src="images/w.png" alt="{W}" class="manaimg">',$str);
    $str = str_replace('{U}','<img src="images/u.png" alt="{U}" class="manaimg">',$str);
    $str = str_replace('{B}','<img src="images/b.png" alt="{B}" class="manaimg">',$str);
    $str = str_replace('{R}','<img src="images/r.png" alt="{R}" class="manaimg">',$str);
    $str = str_replace('{G}','<img src="images/g.png" alt="{G}" class="manaimg">',$str);
    $str = str_replace('{S}','<img src="images/s.png" alt="{S}" class="manaimg">',$str);
    $str = str_replace('{C}','<img src="images/colourless_mana.png" alt="{C}" class="manaimg">',$str);
    
    $str = str_replace('{HR}','<img src="images/hr.png" alt="{HR}" class="manaimg">',$str);
    $str = str_replace('{+oo}','<img src="images/inf.png" alt="{+oo}" class="manaimg">',$str);
    $str = str_replace('{100}','<img src="images/100.png" alt="{100}" class="manaimg">',$str);
    $str = str_replace('{1000000}','<img src="images/1m.png" alt="{1000000}" class="manaimg">',$str);
    
    $str = str_replace('{WU}','<img src="images/wu.png" alt="{WU}" class="manaimg">',$str);
    $str = str_replace('{W/U}','<img src="images/wu.png" alt="{WU}" class="manaimg">',$str);
    $str = str_replace('{WB}','<img src="images/wb.png" alt="{WB}" class="manaimg">',$str);
    $str = str_replace('{W/B}','<img src="images/wb.png" alt="{WB}" class="manaimg">',$str);
    $str = str_replace('{UB}','<img src="images/ub.png" alt="{UB}" class="manaimg">',$str);
    $str = str_replace('{U/B}','<img src="images/ub.png" alt="{UB}" class="manaimg">',$str);
    $str = str_replace('{UR}','<img src="images/ur.png" alt="{UR}" class="manaimg">',$str);
    $str = str_replace('{U/R}','<img src="images/ur.png" alt="{UR}" class="manaimg">',$str);
    $str = str_replace('{BR}','<img src="images/br.png" alt="{BR}" class="manaimg">',$str);
    $str = str_replace('{B/R}','<img src="images/br.png" alt="{BR}" class="manaimg">',$str);
    $str = str_replace('{BG}','<img src="images/bg.png" alt="{BG}" class="manaimg">',$str);
    $str = str_replace('{B/G}','<img src="images/bg.png" alt="{BG}" class="manaimg">',$str);
    $str = str_replace('{RW}','<img src="images/rw.png" alt="{RW}" class="manaimg">',$str);
    $str = str_replace('{R/W}','<img src="images/rw.png" alt="{RW}" class="manaimg">',$str);
    $str = str_replace('{RG}','<img src="images/rg.png" alt="{RG}" class="manaimg">',$str);
    $str = str_replace('{R/G}','<img src="images/rg.png" alt="{RG}" class="manaimg">',$str);
    $str = str_replace('{GW}','<img src="images/gw.png" alt="{GW}" class="manaimg">',$str);
    $str = str_replace('{G/W}','<img src="images/gw.png" alt="{GW}" class="manaimg">',$str);
    $str = str_replace('{GU}','<img src="images/gu.png" alt="{GU}" class="manaimg">',$str);
    $str = str_replace('{G/U}','<img src="images/gu.png" alt="{GU}" class="manaimg">',$str);
    
    $str = str_replace('{C/W}','<img src="images/cw.png" alt="{C/W}" class="manaimg">',$str);
    $str = str_replace('{C/U}','<img src="images/cu.png" alt="{C/U}" class="manaimg">',$str);
    $str = str_replace('{C/B}','<img src="images/cb.png" alt="{C/B}" class="manaimg">',$str);
    $str = str_replace('{C/R}','<img src="images/cr.png" alt="{C/R}" class="manaimg">',$str);
    $str = str_replace('{C/G}','<img src="images/cg.png" alt="{C/G}" class="manaimg">',$str);
        
    $str = str_replace('{2W}','<img src="images/2w.png" alt="{2W}" class="manaimg">',$str);
    $str = str_replace('{2U}','<img src="images/2u.png" alt="{2U}" class="manaimg">',$str);
    $str = str_replace('{2B}','<img src="images/2b.png" alt="{2B}" class="manaimg">',$str);
    $str = str_replace('{2R}','<img src="images/2r.png" alt="{2R}" class="manaimg">',$str);
    $str = str_replace('{2G}','<img src="images/2g.png" alt="{2G}" class="manaimg">',$str);
    $str = str_replace('{2/W}','<img src="images/2w.png" alt="{2/W}" class="manaimg">',$str);
    $str = str_replace('{2/B}','<img src="images/2b.png" alt="{2/B}" class="manaimg">',$str);
    $str = str_replace('{2/G}','<img src="images/2g.png" alt="{2/G}" class="manaimg">',$str);
    $str = str_replace('{2/U}','<img src="images/2u.png" alt="{2/U}" class="manaimg">',$str);
    $str = str_replace('{2/R}','<img src="images/2r.png" alt="{2/R}" class="manaimg">',$str);
    
    $str = str_replace('{X}','<img src="images/x.png" alt="{X}" class="manaimg">',$str);
    $str = str_replace('{Y}','<img src="images/y.png" alt="{Y}" class="manaimg">',$str);
    $str = str_replace('{Z}','<img src="images/z.png" alt="{Z}" class="manaimg">',$str);
    
    $str = str_replace('{1/2}','<img src="images/half.png" alt="{1/2}" class="manaimg">',$str);
    $str = str_replace('{0}','<img src="images/0.png" alt="{0}" class="manaimg">',$str);
    $str = str_replace('{1}','<img src="images/1.png" alt="{1}" class="manaimg">',$str);
    $str = str_replace('{2}','<img src="images/2.png" alt="{2}" class="manaimg">',$str);
    $str = str_replace('{3}','<img src="images/3.png" alt="{3}" class="manaimg">',$str);
    $str = str_replace('{4}','<img src="images/4.png" alt="{4}" class="manaimg">',$str);
    $str = str_replace('{5}','<img src="images/5.png" alt="{5}" class="manaimg">',$str);
    $str = str_replace('{6}','<img src="images/6.png" alt="{6}" class="manaimg">',$str);
    $str = str_replace('{7}','<img src="images/7.png" alt="{7}" class="manaimg">',$str);
    $str = str_replace('{8}','<img src="images/8.png" alt="{8}" class="manaimg">',$str);
    $str = str_replace('{9}','<img src="images/9.png" alt="{9}" class="manaimg">',$str);
    $str = str_replace('{10}','<img src="images/10.png" alt="{10}" class="manaimg">',$str);
    $str = str_replace('{11}','<img src="images/11.png" alt="{11}" class="manaimg">',$str);
    $str = str_replace('{12}','<img src="images/12.png" alt="{12}" class="manaimg">',$str);
    $str = str_replace('{13}','<img src="images/13.png" alt="{13}" class="manaimg">',$str);
    $str = str_replace('{14}','<img src="images/14.png" alt="{14}" class="manaimg">',$str);
    $str = str_replace('{15}','<img src="images/15.png" alt="{15}" class="manaimg">',$str);
    $str = str_replace('{16}','<img src="images/16.png" alt="{16}" class="manaimg">',$str);
    $str = str_replace('{17}','<img src="images/17.png" alt="{17}" class="manaimg">',$str);
    $str = str_replace('{18}','<img src="images/18.png" alt="{18}" class="manaimg">',$str);
    $str = str_replace('{19}','<img src="images/19.png" alt="{19}" class="manaimg">',$str);
    $str = str_replace('{20}','<img src="images/20.png" alt="{20}" class="manaimg">',$str);
    
    $str = str_replace('{PW}','<img src="images/pw.png" alt="{PW}" class="manaimg">',$str);
    $str = str_replace('{W/P}','<img src="images/pw.png" alt="{W/P}" class="manaimg">',$str);
    $str = str_replace('{PU}','<img src="images/pu.png" alt="{PU}" class="manaimg">',$str);
    $str = str_replace('{U/P}','<img src="images/pu.png" alt="{U/P}" class="manaimg">',$str);
    $str = str_replace('{PB}','<img src="images/pb.png" alt="{PB}" class="manaimg">',$str);
    $str = str_replace('{B/P}','<img src="images/pb.png" alt="{B/P}" class="manaimg">',$str);
    $str = str_replace('{PR}','<img src="images/pr.png" alt="{PR}" class="manaimg">',$str);
    $str = str_replace('{R/P}','<img src="images/pr.png" alt="{R/P}" class="manaimg">',$str);
    $str = str_replace('{PG}','<img src="images/pg.png" alt="{PG}" class="manaimg">',$str);
    $str = str_replace('{G/P}','<img src="images/pg.png" alt="{G/P}" class="manaimg">',$str);

    $str = str_replace('{CHAOS}','<img src="images/chaos.png" alt="{PG}" class="manaimg">',$str);
    $str = str_replace('{G/U/P}','<img src="images/gup.png" alt="{G/U/P}" class="manaimg">',$str);
    $str = str_replace('{G/W/P}','<img src="images/gwp.png" alt="{G/W/P}" class="manaimg">',$str);
    $str = str_replace('{R/G/P}','<img src="images/rgp.png" alt="{R/G/P}" class="manaimg">',$str);
    $str = str_replace('{R/W/P}','<img src="images/rwp.png" alt="{R/W/P}" class="manaimg">',$str);

    $str = str_replace('?','-',$str);
    $str = str_replace('£','<br>',$str);
    $str = str_replace('#','',$str);
    $str = str_replace('{PWk}','Planeswalk',$str);
    $str = str_replace('{Ch}','Chaos',$str);
    $str = str_replace("\n","<br>",$str);
    return $str;
}

function langreplace($str)
{
    global $search_langs;
    
    foreach ($search_langs as $lang):
        if ($lang['code'] == $str):
            return $lang['pretty'];
        endif;
    endforeach;
    
    return $str; // Return the original string if no match is found
}

function finddates($str)
{
    $matches = array();
    $pattern = '/'
               . '([0-9]{1,2})'     
               . '([\/])'       
               . '([0-9]{1,2})' 
               . '([\/])'       
               . '([0-9]{0,4})'
               . '/';
    if (preg_match_all($pattern, $str, $matches)):
        return $matches[0];
    endif;
}

function checkRemoteFile($url)
{
    global $logfile;
    $msg = new Message($logfile);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,$url);
    curl_setopt($ch, CURLOPT_NOBODY, 1);
    curl_setopt($ch, CURLOPT_VERBOSE, 1);
    curl_setopt($ch, CURLOPT_STDERR, fopen('php://stderr', 'w'));
    curl_setopt($ch, CURLOPT_FAILONERROR, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch,CURLOPT_USERAGENT,'MtGCollection/1.0');
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    $curlresult = curl_exec($ch);
    $curldetail = curl_getinfo($ch);
    curl_close($ch);
    if($curlresult === FALSE or $curldetail['http_code'] != 200 or $curldetail['download_content_length'] < 1):
        $msg->logMessage('[ERROR]',"{$curldetail['url']} DOES NOT exist, HTTP code is: {$curldetail['http_code']}, file size is: {$curldetail['download_content_length']} bytes");
        return false;
    else: 
        $msg->logMessage('[NOTICE]',"{$curldetail['url']} exists, HTTP code is: {$curldetail['http_code']}, file size is: {$curldetail['download_content_length']} bytes");
        return true;
    endif;
}

function getStringParameters($input,$ignore1,$ignore2='')
// This function takes a parsed GET string and passes it back with SET sub-arrays included, and a specified KEY excluded
{
    $output="";
    foreach($input as $key => $value):
        if ((isset($input['set'])) AND (is_array($input['set']))):
            $sets = filter_var_array($input['set'], FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);
        endif;
        if ($key === "set"):
            foreach($sets AS $keys=>$values):
                if (empty($output)):
                    $output .= '?set%5B%5D='.htmlspecialchars($values, ENT_COMPAT, 'UTF-8');
                else :
                    $output .= '&amp;set%5B%5D='.htmlspecialchars($values, ENT_COMPAT, 'UTF-8');
                endif;
            endforeach;
        elseif (($key === $ignore1) OR ($key === $ignore2)):
            // don't do anything!
        elseif ($key === 'name'):
            if (empty($output)) :
                $output .= '?'.htmlspecialchars($key, ENT_COMPAT, 'UTF-8').'='.htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            else :
                $output .= '&amp;'.htmlspecialchars($key, ENT_COMPAT, 'UTF-8').'='.htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            endif; 
        elseif ($key === 'layout'):
            $validlayouts = array('grid','list','bulk','');
            if (!in_array($value, $validlayouts)):
                $value = 'grid';
            endif;
            if (empty($output)) :
                $output .= '?'.htmlspecialchars($key, ENT_COMPAT, 'UTF-8').'='.htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            else :
                $output .= '&amp;'.htmlspecialchars($key, ENT_COMPAT, 'UTF-8').'='.htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            endif; 
        else:
            if (empty($output)) :
                $output .= '?'.htmlspecialchars($key, ENT_COMPAT, 'UTF-8').'='.htmlspecialchars($value, ENT_COMPAT, 'UTF-8');
            else :
                $output .= '&amp;'.htmlspecialchars($key, ENT_COMPAT, 'UTF-8').'='.htmlspecialchars($value, ENT_COMPAT, 'UTF-8');
            endif;    
        endif;
    endforeach;
       
    return $output;
}

function valid_pass($candidate) 
{
    if (!preg_match_all('$\S*(?=\S{8,})(?=\S*[a-z])(?=\S*[A-Z])(?=\S*[\d])\S*$', $candidate, $hole)):
        return FALSE;
    else:
        return TRUE;
    endif;
    $hole='';
}

function autolink($str, $attributes=array()) {
    $attrs = '';
    foreach ($attributes as $attribute => $value):
        $attrs .= " {$attribute}=\"{$value}\"";
    endforeach;
    $str = ' ' . $str;
    $str = preg_replace(
            '`([^"=\'>])((http|https|ftp)://[^\s<]+[^\s<\.)])`i',
            '$1<a href="$2"'.$attrs.'>$2</a>',
            $str
    );
    $str = substr($str, 1);
    return $str;
}

function get_full_url()
{
    // Get HTTP/HTTPS (the possible values for this vary from server to server)
    $myUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] && !in_array(strtolower($_SERVER['HTTPS']),array('off','no'))) ? 'https' : 'http';
    // Get domain portion
    $myUrl .= '://'.$_SERVER['HTTP_HOST'];
    // Get path to script
    $myUrl .= $_SERVER['REQUEST_URI'];
    // Add path info, if any
    if (!empty($_SERVER['PATH_INFO'])) $myUrl .= $_SERVER['PATH_INFO'];
    
    return $myUrl;
}

function loginstamp($useremail)
{
    global $db, $logfile;
    $msg = new Message($logfile);
    
    $msg->logMessage('[NOTICE]',"Writing user login");
    $logindate = date("Y-m-d");
    $query = "UPDATE users SET lastlogin_date = ? WHERE email = ?";
    if($db->execute_query($query,[$logindate,$useremail]) === TRUE):
        $msg->logMessage('[DEBUG]',"Writing user login successful");
        return 1;
    else:
        $msg->logMessage('[ERROR]',"Writing user login failed");
        return 0;
    endif;
}

function downloadbulk($url, $dest)
{
    global $db, $logfile;
    $options = array(
      CURLOPT_FILE => is_resource($dest) ? $dest : fopen($dest, 'w'),
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_URL => $url,
      CURLOPT_FAILONERROR => true, // HTTP code > 400 will throw curl error
      CURLOPT_USERAGENT => "MtGCollection/1.0",
      CURLOPT_HTTPHEADER => array("Accept: application/json;q=0.9,*/*;q=0.8"),
    );

    $ch = curl_init();
    curl_setopt_array($ch, $options);
    
    # DEBUG
    curl_setopt($ch, CURLOPT_VERBOSE, 1);
    $fp = fopen($logfile, 'a');
    curl_setopt($ch, CURLOPT_STDERR, $fp);
    # END DEBUG
    
    $return = curl_exec($ch);
    
    if ($return === false):
        return curl_error($ch);
    else:
        return true;
    endif;
}

function getBulkInfo($type)
// Function to return the URI for the Scryfall bulk data file, and the file location where it needs to go
{
    global $logfile, $default_cards_url, $all_cards_url, $ImgLocation;
    $msg = new Message($logfile);
    $date = date('Y-m-d');
    $bulk_info = FALSE;
    
    $url = $url_default = $url_all = $fileLocation = $fileLocation_default = $fileLocation_all = '';
    
    $msg->logMessage('[NOTICE]',"scryfall Bulk API: called with '$type'");
    
    if ($type === "all"):
        $url = $all_cards_url;
        $fileLocation = $ImgLocation.'json/bulk_all.json';
    elseif ($type === "standard"):  // At the moment, elseif and else do the same, i.e. a "primary" load only
        $url = $default_cards_url;
        $fileLocation = $ImgLocation.'json/bulk.json';
    elseif ($type === "refresh"):
        $url_default = $default_cards_url;
        $url_all = $all_cards_url;
        $fileLocation_default = $ImgLocation.'json/bulk.json';
        $fileLocation_all = $ImgLocation.'json/bulk_all.json';
    else:  // At the moment, else does a "standard" load only
        $url = $default_cards_url;
        $fileLocation = $ImgLocation.'json/bulk.json';
    endif;
    
    if (isset($url) && $url !== '' && isset($fileLocation)):
        $msg->logMessage('[NOTICE]',"Scryfall Bulk API: fetching current URL $url");
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, "MtGCollection/1.0");
        $curlresult = curl_exec($ch);
        curl_close($ch);
        $scryfall_bulk = json_decode($curlresult,true);
    elseif (isset($url_default) && $url_default !== '' && isset($url_all) && $url_all !== '' && isset($fileLocation_default) && $fileLocation_default !== '' && isset($fileLocation_all) && $fileLocation_all !== '' ):
        // Run twice, once for each file and location
        $msg->logMessage('[NOTICE]',"Scryfall Bulk API: fetching current URL $url_default");
        $ch = curl_init($url_default);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, "MtGCollection/1.0");
        $curlresult = curl_exec($ch);
        curl_close($ch);
        $scryfall_bulk_default = json_decode($curlresult,true);
        
        $msg->logMessage('[NOTICE]',"Scryfall Bulk API: fetching current URL $url_all");
        $ch = curl_init($url_all);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, "MtGCollection/1.0");
        $curlresult = curl_exec($ch);
        curl_close($ch);
        $scryfall_bulk_all = json_decode($curlresult,true);
    else:
        $msg->logMessage('[ERROR]',"Scryfall Bulk API: failed");
        $bulk_info = FALSE;
        return $bulk_info;
    endif;
    
    if (isset($scryfall_bulk["type"]) AND ($scryfall_bulk["type"] === "default_cards" || $scryfall_bulk["type"] === "all_cards")):
        if (isset($scryfall_bulk["download_uri"])):
            $bulk_uri = $scryfall_bulk["download_uri"];
            $msg->logMessage('[NOTICE]',"Scryfall Bulk API: Download URI: $bulk_uri");
            $bulk_info = [
                'bulkUrl' => $bulk_uri,
                'fileLocation' => $fileLocation
            ];
        else:
            $msg->logMessage('[ERROR]',"Scryfall Bulk API info not available");
            $bulk_info = FALSE;
        endif;
    elseif ((isset($scryfall_bulk_default["type"]) AND $scryfall_bulk_default["type"] === "default_cards") AND (isset($scryfall_bulk_all["type"]) AND $scryfall_bulk_all["type"] === "all_cards")):
        if (isset($scryfall_bulk_default["download_uri"])):
            $bulk_uri_default = $scryfall_bulk_default["download_uri"];
            $msg->logMessage('[NOTICE]',"Scryfall Bulk API: Download URI: $bulk_uri_default");
        else:
            $msg->logMessage('[ERROR]',"Scryfall Bulk API: Error");
            $bulk_info = FALSE;
            return $bulk_info;
        endif;
        if (isset($scryfall_bulk_all["download_uri"])):
            $bulk_uri_all = $scryfall_bulk_all["download_uri"];
            $msg->logMessage('[NOTICE]',"Scryfall Bulk API: Download URI: $bulk_uri_all");
        else:
            $msg->logMessage('[ERROR]',"Scryfall Bulk API: Error");
            $bulk_info = FALSE;
            return $bulk_info;
        endif;
        $bulk_info = [
            'bulkUrlDefault' => $bulk_uri_default,
            'fileLocationDefault' => $fileLocation_default,
            'bulkUrlAll' => $bulk_uri_all,
            'fileLocationAll' => $fileLocation_all,
        ];
    else:
        $msg->logMessage('[ERROR]',"Scryfall Bulk API info not available");
        $bulk_info = FALSE;
    endif;
    
    return $bulk_info;
}

function getBulkJson($uri, $file_location, $max_fileage)
// Function to download and save bulk Scryfall data files
{
    global $logfile;
    $msg = new Message($logfile);
    $download_bulk = FALSE;
    
    if (file_exists($file_location) AND filesize($file_location) > 0):
        $fileage = filemtime($file_location);
        $file_date = date('d-m-Y H:i',$fileage);
        $file_size = filesize($file_location);
        if (time()-$fileage > $max_fileage):
            $download = 2;
            $msg->logMessage('[NOTICE]',"Scryfall Bulk API: File old ($file_date), downloading: $uri");
        else:
            $download = 0;
            $msg->logMessage('[NOTICE]',"Scryfall Bulk API: File fresh ($file_location, $file_date, $file_size), skipping download");    
        endif;
    elseif (file_exists($file_location) AND filesize($file_location) == 0):
        $download = 1;
        $msg->logMessage('[NOTICE]',"Scryfall Bulk API: 0-byte file at ($file_location), downloading: $uri");
    else:
        $download = 1;
        $msg->logMessage('[NOTICE]',"Scryfall Bulk API: No file at ($file_location), downloading: $uri");
    endif;
    if($download > 0):
        $msg->logMessage('[NOTICE]',"Scryfall Bulk API: downloading: $uri");
        $bulkreturn = downloadbulk($uri,$file_location);
        if ($bulkreturn == true AND file_exists($file_location) AND filesize($file_location) > 0):
            $file_size = filesize($file_location);
            $msg->logMessage('[NOTICE]',"Scryfall Bulk API: Bulk function returned no error, file at ($file_location), size greater than 0 ($file_size), proceeding");
            $download_bulk = 'Success';
            return $download_bulk;
        else:
            $msg->logMessage('[ERROR]',"Scryfall Bulk API: File download error, waiting 5 minutes to try again");
            sleep(300);
            $bulkreturn = downloadbulk($bulk_uri,$file_location);
            if (!($bulkreturn == true AND file_exists($file_location) AND filesize($file_location) > 0)):
                $msg->logMessage('[ERROR]',"Scryfall Bulk API: File download error on retry, exiting.");
                $download_bulk = FALSE;
                return $download_bulk;
            else:
                $msg->logMessage('[NOTICE]',"Scryfall Bulk API: Bulk function returned no error, file at ($file_location), size greater than 0 ($file_size), proceeding");
                $download_bulk = 'Success';
                return $download_bulk;
            endif;
        endif;
    else:
        $msg->logMessage('[NOTICE]',"Scryfall Bulk API: Existing file not too old, skipping");
        $download_bulk = 'Skipped';
        return $download_bulk;
    endif;
    
    // Should never be here
    return $download_bulk;
}

function scryfallImport($file_location,$type)
// Function to process and import lines within Scryfall bulk data files
{
    global $db, $logfile, $games_to_include, $langs_to_skip, $langs_to_skip_all, $layouts_to_skip, $serveremail, $adminemail, $ImgLocation, $two_card_detail_sections;
    $msg = new Message($logfile);

    // Initiate counters at zero
    $count_inc = $count_skip = $total_count = $count_add = $count_update = $count_other = 0;

    $data = JsonMachine\Items::fromFile($file_location, ['decoder' => new JsonMachine\JsonDecoder\ExtJsonDecoder(true)]);

    $date = date('Y-m-d');
    
    if ($type === 'default'):
        $primary = 1;
        
        // By default, set to TRUE. This will download all images for cards in the Default Cards file when run with an empty database (about 90,000 images, i.e. potentially about 20GB)
        // Swap these next two lines if needed on a database re-population to prevent ALL images being downloaded
        // $imageDownloads = FALSE;
        $imageDownloads = TRUE;
    elseif ($type === 'all'):
        $primary = 0;
        
        // Don't by default download all images for all cards. Images will be obtained on first card detail load or search result inclusion
        $imageDownloads = FALSE;
    endif;
    
    foreach($data AS $key => $value):
        $total_count = $total_count + 1;
        $id = $value["id"];
        $msg->logMessage('[DEBUG]',"Scryfall bulk API ($type), Record $id: $total_count");
        $multi_1 = $multi_2 = $name_1 = $name_2 = null;
        $printed_name_1 = $printed_name_2 = $manacost_1 = $manacost_2 = null;
        $flavor_name_1 = $flavor_name_2 = $power_1 = $power_2 = null;
        $toughness_1 = $toughness_2 = $loyalty_1 = $loyalty_2 = $type_1 = $type_2 = $ability_1 = $cmc_1 = $cmc_2 = null;
        $ability_2 = $colour_1 = $colour_2 = $artist_1 = $artist_2 = $flavor_1 = $flavor_2 = $image_1 = $image_2 = null;
        $id_p1 = $component_p1 = $name_p1 = $type_line_p1 = $uri_p1 = null;
        $id_p2 = $component_p2 = $name_p2 = $type_line_p2 = $uri_p2 = null;
        $id_p3 = $component_p3 = $name_p3 = $type_line_p3 = $uri_p3 = null;
        $id_p4 = $component_p4 = $name_p4 = $type_line_p4 = $uri_p4 = null;
        $id_p5 = $component_p5 = $name_p5 = $type_line_p5 = $uri_p5 = null;
        $id_p6 = $component_p6 = $name_p6 = $type_line_p6 = $uri_p6 = null;
        $id_p7 = $component_p7 = $name_p7 = $type_line_p7 = $uri_p7 = null;
        $colors = $game_types = $promo_types = $color_identity = $keywords = $produced_mana = null;
        $maxpower = $minpower = $maxtoughness = $mintoughness = null;
        $maxloyalty = $minloyalty = null;
        $skip = 1; //skip by default
        //  Skips need to be specified in here
        /// Is it paper?
        foreach($value AS $key2 => $value2):
            if($key2 == 'games'):
                foreach($value2 as $game_type):
                    if(in_array($game_type,$games_to_include)):
                        $skip = 0;
                    endif;
                endforeach;
            endif;
        endforeach;
        if (   
                (in_array($value["lang"],$langs_to_skip) && $type === 'default')
                    OR 
                (in_array($value["lang"],$langs_to_skip_all) && $type === 'all')
                    OR 
                (in_array($value["layout"],$layouts_to_skip))
            ):
            $skip = 1;
        endif;
        // Actions on skip value
        if($skip === 1):
            $count_skip = $count_skip + 1;
        elseif($skip === 0):
            $time = time();
            $count_inc = $count_inc + 1;
            foreach($value AS $key2 => $value2):
                if($key2 == 'card_faces'):
                    $face_loop = 1;
                    foreach($value2 as $key3 => $value3):
                        if(isset($value3["name"])):
                            ${'name_'.$face_loop} = $value3["name"];
                        endif;
                        if(isset($value3["printed_name"])):
                            ${'printed_name_'.$face_loop} = $value3["printed_name"];
                        endif;
                        if(isset($value3["flavor_name"])):
                            ${'flavor_name_'.$face_loop} = $value3["flavor_name"];
                        endif;
                        if(isset($value3["mana_cost"])):
                            ${'manacost_'.$face_loop} = $value3["mana_cost"];
                        endif;
                        if(isset($value3["power"])):
                            ${'power_'.$face_loop} = $value3["power"];
                        endif;
                        if(isset($value3["toughness"])):
                            ${'toughness_'.$face_loop} = $value3["toughness"];
                        endif;
                        if(isset($value3["loyalty"])):
                            ${'loyalty_'.$face_loop} = $value3["loyalty"];
                        endif;
                        if(isset($value3["type_line"])):
                            ${'type_'.$face_loop} = $value3["type_line"];
                        endif;
                        if(isset($value3["oracle_text"])):
                            ${'ability_'.$face_loop} = $value3["oracle_text"];
                        endif;
                        if(isset($value3["colors"])):
                            ${'colour_'.$face_loop} = json_encode($value3["colors"]);
                        endif;
                        if(isset($value3["artist"])):
                            ${'artist_'.$face_loop} = $value3["artist"];
                        endif;
                        if(isset($value3["flavor_text"])):
                            ${'flavor_'.$face_loop} = $value3["flavor_text"];
                        endif;
                        if(isset($value3["image_uris"]["normal"])):
                            ${'image_'.$face_loop} = $value3["image_uris"]["normal"];
                        endif;
                        if(isset($value3["cmc"])):
                            ${'cmc_'.$face_loop} = $value3["cmc"];
                        endif;
                        $face_loop = $face_loop + 1;
                    endforeach;
                    $msg->logMessage('[DEBUG]',"Scryfall bulk API ($type), Record $id: $total_count - point 7, finished face loops");
                endif;
                if($key2 == 'all_parts'):
                    $all_parts_loop = 1;
                    foreach($value2 as $key4 => $value4):
                        if(isset($value4["component"]) AND $value4["component"] != "combo_piece"):
                            if(isset($value4["id"])):
                                ${'id_p'.$all_parts_loop} = $value4["id"];
                            endif;
                            if(isset($value4["component"])):
                                ${'component_p'.$all_parts_loop} = $value4["component"];
                            endif;
                            if(isset($value4["name"])):
                                ${'name_p'.$all_parts_loop} = $value4["name"];
                            endif;
                            if(isset($value4["type_line"])):
                                ${'type_line_p'.$all_parts_loop} = $value4["type_line"];
                            endif;
                            if(isset($value4["uri"])):
                                ${'uri_p'.$all_parts_loop} = $value4["uri"];
                            endif;
                            $all_parts_loop = $all_parts_loop + 1;
                        endif;
                    endforeach;
                endif;
                if($key2 == 'multiverse_ids'):
                    $multiverse_loop = 1;
                    foreach($value2 as $m_id):
                        ${'multi_'.$multiverse_loop} = $m_id;
                        $multiverse_loop = $multiverse_loop + 1;
                    endforeach;
                endif;
            endforeach;
            $powerarray = array();
            $toughnessarray = array();
            $loyaltyarray = array();
            if(isset($value['power'])):
                array_push($powerarray,(int)$value['power']);
            endif;
            if(isset($power_1)):
                array_push($powerarray,(int)$power_1);
            endif;
            if(isset($power_2)):
                array_push($powerarray,(int)$power_2);
            endif;
            if(!empty($powerarray)):
                $maxpower = max($powerarray);
                $minpower = min($powerarray);
            endif;
            if(isset($value['toughness'])):
                array_push($toughnessarray,(int)$value['toughness']);
            endif;
            if(isset($toughness_1)):
                array_push($toughnessarray,(int)$toughness_1);
            endif;
            if(isset($toughness_2)):
                array_push($toughnessarray,(int)$toughness_2);
            endif;
            if(!empty($toughnessarray)):
                $maxtoughness = max($toughnessarray);
                $mintoughness = min($toughnessarray);
            endif;
            if(isset($value['loyalty'])):
                array_push($loyaltyarray,(int)$value['loyalty']);
            endif;
            if(isset($loyalty_1)):
                array_push($loyaltyarray,(int)$loyalty_1);
            endif;
            if(isset($loyalty_2)):
                array_push($loyaltyarray,(int)$loyalty_2);
            endif;
            if(!empty($loyaltyarray)):
                $maxloyalty = max($loyaltyarray);
                $minloyalty = min($loyaltyarray);
            endif;
            if(isset($value["colors"])):
                $colors = json_encode($value["colors"]);
            endif;
            if(isset($value["games"])):
                $game_types = json_encode($value["games"]);
            endif;
            if(isset($value["promo_types"])):
                $promo_types = json_encode($value["promo_types"]);
            endif;
            if(isset($value["finishes"])):
                $finishes = json_encode($value["finishes"]);
            endif; 
            if(isset($value["color_identity"])):
                $color_identity = json_encode($value["color_identity"]);
            endif;
            if(isset($value["keywords"])):
                $keywords = json_encode($value["keywords"]);
            endif;
            if(isset($value["produced_mana"])):
                $produced_mana = json_encode($value["produced_mana"]);
            endif;
            if(isset($value["prices"]['usd'])):
                $normal_price = $value["prices"]['usd'];
            else:
                $normal_price = null;
            endif;
            if(isset($value["prices"]['usd_foil'])):
                $foil_price = $value["prices"]['usd_foil'];
            else:
                $foil_price = null;
            endif;
            if(isset($value["prices"]['usd_etched'])):
                $etched_price = $value["prices"]['usd_etched'];
            else:
                $etched_price = null;
            endif;
            if($foil_price === null AND $normal_price === null AND $etched_price === null):
                $price_sort = null;
            elseif($foil_price === null AND $etched_price === null):
                $price_sort = $normal_price;
            elseif($normal_price === null AND $etched_price === null):
                $price_sort = $foil_price;
            elseif($foil_price === null AND $normal_price === null):
                $price_sort = $etched_price;
            elseif($normal_price === null):
                $price_sort = min($etched_price,$foil_price);
            elseif($foil_price === null):
                $price_sort = min($etched_price,$normal_price);
            elseif($etched_price === null):
                $price_sort = min($normal_price,$foil_price);
            else:
                $price_sort = min($normal_price,$foil_price,$etched_price);
            endif;
            if(isset($value["collector_number"])):
                $coll_no = $value["collector_number"];
                if(isset($value["layout"]) AND $value["layout"] === 'meld'):
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
                if(substr($coll_no, strlen($coll_no)-1) === 's'):
                    $coll_no = str_replace('s', '', $coll_no);
                    if(is_int($coll_no)):
                        $coll_no = $coll_no + 2000;
                    endif;
                endif;
                if(substr($coll_no, strlen($coll_no)-1) === 'p'):
                    $coll_no = str_replace('p', '', $coll_no);
                endif;
                $msg->logMessage('[DEBUG]',"Scryfall bulk API ($type), Record $id: $total_count - $coll_no");
                $number_int = (int) $coll_no;
                $msg->logMessage('[DEBUG]',"Scryfall bulk API ($type), Record $id: $total_count - $number_int");
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
                                    price_sort, date_added, primary_card
                                    )
                                VALUES 
                                    (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                                ON DUPLICATE KEY UPDATE
                                    id = VALUES(id), oracle_id = VALUES(oracle_id), tcgplayer_id = VALUES(tcgplayer_id), 
                                    multiverse = VALUES(multiverse), multiverse2 = VALUES(multiverse2), name = VALUES(name), 
                                    printed_name = VALUES(printed_name), flavor_name = VALUES(flavor_name), 
                                    lang = VALUES(lang), release_date = VALUES(release_date), api_uri = VALUES(api_uri), 
                                    scryfall_uri = VALUES(scryfall_uri), layout = VALUES(layout), image_uri = VALUES(image_uri), 
                                    manacost = VALUES(manacost), cmc = VALUES(cmc), type = VALUES(type), ability = VALUES(ability), 
                                    power = VALUES(power), toughness = VALUES(toughness), loyalty = VALUES(loyalty), 
                                    color = VALUES(color), color_identity = VALUES(color_identity), keywords = VALUES(keywords), 
                                    generatedmana = VALUES(generatedmana), legalitystandard = VALUES(legalitystandard), 
                                    legalitypioneer = VALUES(legalitypioneer), legalitymodern = VALUES(legalitymodern), 
                                    legalitylegacy = VALUES(legalitylegacy), legalitypauper = VALUES(legalitypauper), 
                                    legalityvintage = VALUES(legalityvintage), legalitycommander = VALUES(legalitycommander), 
                                    legalityalchemy = VALUES(legalityalchemy), legalityhistoric = VALUES(legalityhistoric), 
                                    reserved = VALUES(reserved), foil = VALUES(foil), nonfoil = VALUES(nonfoil), 
                                    oversized = VALUES(oversized), promo = VALUES(promo), set_id = VALUES(set_id), 
                                    game_types = VALUES(game_types), finishes = VALUES(finishes), promo_types = VALUES(promo_types), setcode = VALUES(setcode), 
                                    set_name = VALUES(set_name), number = VALUES(number),
                                    number_import = VALUES(number_import), rarity = VALUES(rarity), flavor = VALUES(flavor), backid = VALUES(backid), 
                                    artist = VALUES(artist), price = VALUES(price), price_foil = VALUES(price_foil), price_etched = VALUES(price_etched), 
                                    gatherer_uri = VALUES(gatherer_uri), updatetime = VALUES(updatetime), 
                                    f1_name = VALUES(f1_name), f1_manacost = VALUES(f1_manacost), f1_power = VALUES(f1_power), f1_toughness = VALUES(f1_toughness),
                                    f1_loyalty = VALUES(f1_loyalty), f1_type = VALUES(f1_type), f1_ability = VALUES(f1_ability), 
                                    f1_colour = VALUES(f1_colour), f1_artist = VALUES(f1_artist), f1_flavor = VALUES(f1_flavor), 
                                    f1_image_uri = VALUES(f1_image_uri), f1_cmc = VALUES(f1_cmc), f1_printed_name = VALUES(f1_printed_name), f1_flavor_name = VALUES(f1_flavor_name),
                                    f2_name = VALUES(f2_name), f2_manacost = VALUES(f2_manacost), f2_power = VALUES(f2_power), f2_toughness = VALUES(f2_toughness),
                                    f2_loyalty = VALUES(f2_loyalty), f2_type = VALUES(f2_type), f2_ability = VALUES(f2_ability), 
                                    f2_colour = VALUES(f2_colour), f2_artist = VALUES(f2_artist), f2_flavor = VALUES(f2_flavor), 
                                    f2_image_uri = VALUES(f2_image_uri), f2_cmc = VALUES(f2_cmc),  f2_printed_name = VALUES(f2_printed_name), f2_flavor_name = VALUES(f2_flavor_name),
                                    p1_id = VALUES(p1_id), p1_component = VALUES(p1_component), p1_name = VALUES(p1_name), 
                                    p1_type_line = VALUES(p1_type_line), p1_uri = VALUES(p1_uri),
                                    p2_id = VALUES(p2_id), p2_component = VALUES(p2_component), p2_name = VALUES(p2_name), 
                                    p2_type_line = VALUES(p2_type_line), p2_uri = VALUES(p2_uri),
                                    p3_id = VALUES(p3_id), p3_component = VALUES(p3_component), p3_name = VALUES(p3_name), 
                                    p3_type_line = VALUES(p3_type_line), p3_uri = VALUES(p3_uri),
                                    p4_id = VALUES(p4_id), p4_component = VALUES(p4_component), p4_name = VALUES(p4_name), 
                                    p4_type_line = VALUES(p4_type_line), p4_uri = VALUES(p4_uri),
                                    p5_id = VALUES(p5_id), p5_component = VALUES(p5_component), p5_name = VALUES(p5_name), 
                                    p5_type_line = VALUES(p5_type_line), p5_uri = VALUES(p5_uri),
                                    p6_id = VALUES(p6_id), p6_component = VALUES(p6_component), p6_name = VALUES(p6_name), 
                                    p6_type_line = VALUES(p6_type_line), p6_uri = VALUES(p6_uri),
                                    p7_id = VALUES(p7_id), p7_component = VALUES(p7_component), p7_name = VALUES(p7_name), 
                                    p7_type_line = VALUES(p7_type_line), p7_uri = VALUES(p7_uri),
                                    maxpower = VALUES(maxpower), minpower = VALUES(minpower), maxtoughness = VALUES(maxtoughness), 
                                    mintoughness = VALUES(mintoughness), maxloyalty = VALUES(maxloyalty), minloyalty = VALUES(minloyalty), price_sort = VALUES(price_sort),
                                    primary_card = IF(?, 1, primary_card)
                                ");
            if ($stmt === false):
                trigger_error('[ERROR] cards.php: Preparing SQL: ' . $db->error, E_USER_ERROR);
            endif;
            $bind = $stmt->bind_param("sssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssii", 
                    $id, 
                    $value["oracle_id"],
                    $value["tcgplayer_id"],
                    $multi_1,
                    $multi_2,
                    $value["name"],
                    $value["printed_name"],
                    $value["flavor_name"],
                    $value["lang"],
                    $value["released_at"],
                    $value["uri"],
                    $value["scryfall_uri"],
                    $value["layout"],
                    $value["image_uris"]["normal"],
                    $value["mana_cost"],
                    $value["cmc"],
                    $value["type_line"],
                    $value["oracle_text"],
                    $value["power"],
                    $value["toughness"],
                    $value["loyalty"],
                    $colors,
                    $color_identity,
                    $keywords,
                    $produced_mana,
                    $value["legalities"]["standard"],
                    $value["legalities"]["pioneer"],
                    $value["legalities"]["modern"],
                    $value["legalities"]["legacy"],
                    $value["legalities"]["pauper"],
                    $value["legalities"]["vintage"],
                    $value["legalities"]["commander"],
                    $value["legalities"]["alchemy"],
                    $value["legalities"]["historic"],
                    $value["reserved"],
                    $value["foil"],
                    $value["nonfoil"],
                    $value["oversized"],
                    $value["promo"],
                    $value["set_id"],
                    $game_types,
                    $finishes,
                    $promo_types,
                    $value["set"],
                    $value["set_name"],
                    $number_int,
                    $value["collector_number"],
                    $value["rarity"],
                    $value["flavor_text"],
                    $value["card_back_id"],
                    $value["artist"],
                    $value["prices"]["usd"],
                    $value["prices"]["usd_foil"],
                    $value["prices"]["usd_etched"],
                    $value["related_uris"]["gatherer"],
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
                    $date,
                    $primary,
                    $primary
                    );
            if ($bind === false):
                trigger_error('[ERROR] scryfall_bulk.php: Binding parameters: ' . $db->error, E_USER_ERROR);
            endif;
            $exec = $stmt->execute();
            if ($exec === false):
                trigger_error("[ERROR] scryfall_bulk.php: Writing new card details: " . $db->error, E_USER_ERROR);
            else:
                $status = mysqli_affected_rows($db); // 1 = add, 2 = change, 0 = no change
                if($status === 1):
                    $count_add = $count_add + 1;
                    $msg->logMessage('[DEBUG]',"Added card - no error returned; return code: $status");
                    //Fetching image
                    if($imageDownloads === TRUE):
                        $imageManager = new ImageManager($db, $logfile, $serveremail, $adminemail);
                        $imageManager->getImage($value["set"], $id, $ImgLocation, $value["layout"], $two_card_detail_sections);
                    endif;
                elseif($status === 2):
                    $count_update = $count_update + 1;
                    $msg->logMessage('[DEBUG]',"Updated card - no error returned; return code: $status");
                else:
                    $count_other = $count_other + 1;
                    $msg->logMessage('[DEBUG]',"Updated card - no error returned; return code: $status");
                endif;
            endif;
            $stmt->close();
        endif;
    endforeach;
    $msg->logMessage('[NOTICE]',"Bulk update completed: Total $total_count, added: $count_add, skipped $count_skip, included $count_inc, updated: $count_update, other: $count_other");
    $message = "Total: $total_count; total added: $count_add; total skipped: $count_skip; total included: $count_inc; total updated: $count_update";
    return $message;
    // return $message to then use in parent to send email using myPHPMailer
}

function validateTrueDecimal($v) 
{	
    global $logfile;
    $result = floor($v);
    $msg = new Message($logfile);
    
    $msg->logMessage('[DEBUG]',"Checking $v for true decimal, result is $result");
    return(floor($v) != $v);
}
function update_topvalue_card($collection,$scryid)
{
    global $db, $logfile;
    $msg = new Message($logfile);
    
    if($findcards = $db->execute_query("SELECT
                            `$collection`.id AS id,
                            IFNULL(`$collection`.normal,0) AS mynormal,
                            IFNULL(`$collection`.foil, 0) AS myfoil,
                            IFNULL(`$collection`.etched, 0) AS myetch,
                            notes,
                            topvalue,
                            IFNULL(price, 0) AS normalprice,
                            IFNULL(price_foil, 0) AS foilprice,
                            IFNULL(price_etched, 0) AS etchedprice
                            FROM `$collection` LEFT JOIN `cards_scry` 
                            ON `$collection`.id = `cards_scry`.id
                            WHERE IFNULL(`$collection`.normal,0) + IFNULL(`$collection`.foil,0) + IFNULL(`$collection`.etched,0) > 0
                            AND `$collection`.id = ?",[$scryid])):
        $msg->logMessage('[DEBUG]',"SQL query succeeded");
        while($row = $findcards->fetch_array(MYSQLI_ASSOC)):
            $normalqty = $row['mynormal'];
            $normalprice = $row['normalprice'];
            $foilqty = $row['myfoil'];
            $foilprice = $row['foilprice'];
            $etchedqty = $row['myetch'];
            $etchedprice = $row['etchedprice'];
            if($normalqty * $normalprice > 0):
                $normalrate = $normalprice;
            else:
                $normalrate = 0;
            endif;
            if($foilqty * $foilprice > 0):
                $foilrate = $foilprice;
            else:
                $foilrate = 0;
            endif;
            if($etchedqty * $etchedprice > 0):
                $etchedrate = $etchedprice;
            else:
                $etchedrate = 0;
            endif;
            $selectedrate = max($normalrate,$foilrate,$etchedrate);
            $cardid = $db->real_escape_string($row['id']);
            $updatemaxqry = "INSERT INTO `$collection` (topvalue,id)
                VALUES (?,?)
                ON DUPLICATE KEY UPDATE topvalue = ?";
            $params = [$selectedrate,$cardid,$selectedrate];
            if($updatemax = $db->execute_query($updatemaxqry,$params)):
                //succeeded
                $msg->logMessage('[DEBUG]',"SQL update succeeded");
            else:
                trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL: ". $db->error, E_USER_ERROR);
            endif;
        endwhile;
    else: 
        trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL: ". $db->error, E_USER_ERROR);
    endif;
    
}

function cardtypes($finishes)
{
    global $db, $logfile;
    $cardtypes = 'none';
    $card_normal = 0;
    $card_foil = 0;
    $card_etched = 0;
    foreach($finishes as $key => $value):
        if($value == 'nonfoil'):
            $card_normal = 1;
        elseif($value == 'foil'):
            $card_foil = 1;
        elseif($value == 'etched'):
            $card_etched = 1;
        endif;
    endforeach;
    if ($card_normal == 1 AND $card_foil == 1 AND $card_etched == 1):
        $cardtypes = 'normalfoiletched';
    elseif ($card_normal == 1 AND $card_foil == 1 AND $card_etched == 0):
        $cardtypes = 'normalfoil';
    elseif ($card_normal == 1 AND $card_foil == 0 AND $card_etched == 1):
        $cardtypes = 'normaletched';
    elseif ($card_normal == 0 AND $card_foil == 1 AND $card_etched == 1):
        $cardtypes = 'foiletched';
    elseif ($card_normal == 0 AND $card_foil == 0 AND $card_etched == 1):
        $cardtypes = 'etchedonly';
    elseif ($card_normal == 0 AND $card_foil == 1 AND $card_etched == 0):
        $cardtypes = 'foilonly';
    elseif ($card_normal == 1 AND $card_foil == 0 AND $card_etched == 0):
        $cardtypes = 'normalonly';
    endif;
    return $cardtypes;
}

function cardtype_for_id($id)
{
    global $db, $logfile;
    $msg = new Message($logfile);
    
    $msg->logMessage('[DEBUG]',"Looking up card types for card $id");
    $stmt = $db->execute_query("SELECT finishes FROM cards_scry WHERE id = ? LIMIT 1", [$id]);
    if($stmt != TRUE):
        trigger_error("[ERROR] Class " .__METHOD__ . " ".__LINE__," - SQL failure: Error: " . $db->error, E_USER_ERROR);
    else:
        if ($stmt->num_rows > 0):
            $result = $stmt->fetch_assoc();
            if(isset($result['finishes'])):
                $msg->logMessage('[DEBUG]',"Card $id is valid, looking up finishes");
                $finishes = json_decode($result['finishes'], TRUE);
                $cardtypes = cardtypes($finishes);
            else:
                $msg->logMessage('[DEBUG]',"Card $id is valid, but no finishes");
                $cardtypes = 'none';
            endif;
        else:
            $msg->logMessage('[DEBUG]',"Card $id has no match");
            $cardtypes = 'nomatch';
        endif;
    endif;
    $msg->logMessage('[DEBUG]',"Card type for $id is $cardtypes");
    return $cardtypes;
}

function card_legal_db_field($decktype)
{
    global $db, $deck_legality_map, $logfile;
    $msg = new Message($logfile);
    
    $msg->logMessage('[DEBUG]',"Looking up db_field for legality for deck type '$decktype'");
    $index = array_search("$decktype", array_column($deck_legality_map, 'decktype'));
    if ($index !== false):
        $db_field = $deck_legality_map[$index]['db_field'];
    endif;
    $msg->logMessage('[DEBUG]',"Deck type '$decktype' has legality in '$db_field'");
    return $db_field;
}

function promo_lookup($promo_type)
{
    global $promos_to_show, $logfile;
    $msg = new Message($logfile);
    
    $msg->logMessage('[DEBUG]',"Looking up promo description for '$promo_type'");
    $index = array_search($promo_type, array_column($promos_to_show, 'promotype'));
    if ($index !== false):
        $promo_description = $promos_to_show[$index]['display'];
    else:
        $promo_description = 'skip';
    endif;
    $msg->logMessage('[DEBUG]',"Promo description for '$promo_type' is '$promo_description'");
    return $promo_description;
}

function deck_legal_list($decknumber,$deck_type,$db_field)
{
    global $db, $logfile;
    $msg = new Message($logfile);
    
    $msg->logMessage('[DEBUG]',"Getting deck legality list for $deck_type deck '$decknumber' (using db_field '$db_field')");
    $sql = "SELECT cardnumber FROM deckcards WHERE decknumber = ?";
    $msg->logMessage('[DEBUG]',"Looking up SQL: $sql");
    $sqlresult = $db->execute_query($sql,[$decknumber]);
    if($sqlresult === false):
        trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
    else:
        $i = 0;
        $record = array();
        while($row = $sqlresult->fetch_assoc()):
            $record[$i] = $row['cardnumber'];
            $i = $i + 1;
        endwhile;
    endif;
    $list = array();
    $p = 0;
    foreach($record as $value):
        $sql2 = "SELECT $db_field FROM cards_scry WHERE id = ? LIMIT 1";
        $sqlresult2 = $db->execute_query($sql2,[$value]);
        if($sqlresult2 === false):
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
        else:
            $row2 = $sqlresult2->fetch_array(MYSQLI_ASSOC);
            $legal = $row2["$db_field"];
        endif;
        $list[$p]['id'] = $value;
        $list[$p]['legality'] = $legal;
        $p = $p + 1;
    endforeach;
    return $list;
}

function valid_uuid($uuid)
{
    global $db, $logfile;
    $msg = new Message($logfile);
    
    $msg->logMessage('[DEBUG]',"Checking for valid UUID ($uuid)");
    if (preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/', $uuid)):
        $msg->logMessage('[DEBUG]',"Valid UUID ($uuid)");
        $uuid = $db->real_escape_string($uuid);
        return $uuid;
    else:
        $msg->logMessage('[ERROR]',"Invalid UUID ($uuid), returning 'false'");
        return false;
    endif;
}

function valid_tablename($input)
{
    global $db, $logfile;
    $msg = new Message($logfile); 
    
    $msg->logMessage('[DEBUG]',"Checking for valid table name ($input)");
    $pattern = '/^\d+collection$/';
    if (preg_match($pattern, $input)):
        $msg->logMessage('[DEBUG]',"Valid table name");
        return $input;
    else:
        $msg->logMessage('[ERROR]',"Invalid table name");
        return false;
    endif;
}

function isValidSetcode($setcode) 
{
    return preg_match('/^[a-zA-Z0-9]{3,6}$/', $setcode) || empty($setcode);
}

function isValidCardName($name) 
// Cannot be only numbers
{
    return preg_match('/\D/', $name) || empty($name);
}

function isValidLanguageCode($lang) 
// Alpha only
{
    return preg_match('/^[a-zA-Z]*$/', $lang) || empty($lang);
}

function in_array_case_insensitive($needle, $haystack) 
{
    foreach ($haystack as $item):
        if (strtolower($needle) == strtolower($item)):
            return true;
        endif;
    endforeach;
    return false;
}

function input_interpreter($input_string)
// Called by quickAdd in deckmanager class, index.php search inputs and profile.php collection imports

// This function takes an input string, either from deck quick add or search strings, and strips it into components:
// - UUID
// - qty (not applicable for searches)
// - cardname
// - set
// - collector number
{
    global $db, $logfile, $bracketsInNames, $importLinestoIgnore;
    $msg = new Message($logfile); 
    
    $msg->logMessage('[DEBUG]', "Input interpreter called with '$input_string'");
    $sanitised_string = htmlspecialchars($input_string, ENT_NOQUOTES);
    
    // Define is_csv as a closure
    $is_csv = function($string) use ($logfile) {
        $msg = new Message($logfile);
        // Check if the string contains at least 4 commas
        $comma_count = substr_count($string, ',');
        if ($comma_count < 4):
            $msg->logMessage('[DEBUG]', "Input is not CSV");
            return false;
        endif;
        
        // Check if the string can be parsed into fields
        $fields = str_getcsv($string);
        
        // If str_getcsv returns an array with more than one element, it's likely a CSV
        $fieldcount = count($fields);
        $msg->logMessage('[DEBUG]', "Input is CSV, returning field count $fieldcount");
        return $fieldcount > 1;
    };
    
    // Define extract_and_process_csv as a closure
    $extract_and_process_csv = function($line) use ($logfile) {
        $msg = new Message($logfile);
        
        // Parse the CSV row, with basic sanity checking on where things should be and what they should look like
        $fields = str_getcsv($line);
        $qtyFields = count($fields);
        
        if ($qtyFields === 6 || $qtyFields === 8): // Only check if it has 6 or 8 fields, otherwise don't bother
            // Header check
            $headerKeywords  = ['set', 'number', 'name'];
            $isHeader = true;
            foreach ($headerKeywords as $keyword):
                $found = false;
                foreach ($fields as $field):
                    if (stripos($field, $keyword) !== false):
                        $found = true;
                        break;
                    endif;
                endforeach;
                if (!$found):
                    $isHeader = false;
                    break;
                endif;
            endforeach;
            if ($isHeader):
                return 'header';
            endif;
            
            // Validate and determine CSV format
            if ($qtyFields === 6):
                if( !isValidSetcode($fields[0]) ||
                    !isValidCardName($fields[2]) ||
                    !(is_numeric($fields[3]) || empty($fields[3])) ||
                    !(is_numeric($fields[4]) || empty($fields[4])) ||
                    !valid_uuid($fields[5]) 
                    ):
                    $csvFormat = 'invalid';
                else:
                    $csvFormat = 'delver';
                endif;
            elseif ($qtyFields === 8):
                if( !isValidSetcode($fields[0]) ||
                    !isValidCardName($fields[2]) ||
                    !isValidLanguageCode($fields[3]) ||
                    !(is_numeric($fields[4]) || empty($fields[4])) ||
                    !(is_numeric($fields[5]) || empty($fields[5])) ||
                    !(is_numeric($fields[6]) || empty($fields[6])) ||
                    !(valid_uuid($fields[7]) || empty($fields[7])) 
                    ):
                    $csvFormat = 'invalid';
                else:
                    $csvFormat = 'mtgc';
                endif;
            else:
                $csvFormat = 'invalid';
            endif;
            $msg->logMessage('[DEBUG]', "CSV input has $qtyFields fields, format is '$csvFormat'");

            if ($csvFormat === 'invalid'):
                return false;
            endif;

            // Extracting common fields
            $set    = $fields[0];
            $number = $fields[1];
            $name   = $fields[2];

            // Extracting other fields based on format
            if ($csvFormat === 'mtgc'):
                $lang   = $fields[3];
                $param5 = isset($fields[4]) ? (int)$fields[4] : 0;
                $param6 = isset($fields[5]) ? (int)$fields[5] : 0;
                $param7 = isset($fields[6]) ? (int)$fields[6] : 0;
                $uuid   = isset($fields[7]) ? $fields[7] : '';
            elseif ($csvFormat === 'delver'): // No etched in Delver Lens files
                $lang   = 'unspecified';
                $param5 = isset($fields[3]) ? (int)$fields[3] : 0;
                $param6 = isset($fields[4]) ? (int)$fields[4] : 0;
                $param7 = 0;
                $uuid   = isset($fields[5]) ? $fields[5] : '';
            else:
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
        else:
            $msg->logMessage('[ERROR]', "Invalid CSV format: $line");
            return false;
        endif;
    };
    
    // MAIN PROCESSING //
    
    // Is the line CSV with at least 4 fields?
    if ($is_csv($sanitised_string)):
        // The line is in CSV format
        $result = $extract_and_process_csv($sanitised_string);
    
        if ($result === 'header'):
            return 'header';
        elseif ($result !== false):
            if(($result['normal'] + $result['foil'] + $result['etched'] === 0) && $result['qty'] > 0):
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
        else:
            return false;
        endif;
    elseif(trim($sanitised_string) === '' || in_array_case_insensitive(trim($sanitised_string),$importLinestoIgnore)):
        return 'empty line';
    else: 
        // Not a CSV
        // Need to interpret a text line
        // as either a moxfield decklist line or a MTGC quick add text line (MTGC has no info on normal/foil/etched)
        
        // If the string starts with a number < 1000, assume it's a quantity and strip it from the string into a variable, 
        // leaving the rest of the string to be assessed for name / set / number.
        // The only card names that start with numbers are Year cards, e.g. 2001 World Championships Ad etc.
        
        $patternNumber = '/^(\d{1,3})\s+(.*)/'; // Match numbers up to 3 digits, and remove into $qty
        $matches = [];
        if (preg_match($patternNumber, trim($sanitised_string), $matches)):
            $qty = $matches[1];
            $sanitised_string = trim($matches[2]);
        else:
            $qty = '';
            $sanitised_string = trim($sanitised_string);
        endif;
        
        // If string contains an opening ( or [ but no closing ) or ], then terminate the string with %] and submit
        if (strpos($sanitised_string,'(')       !== false && strpos($sanitised_string,']') === false && strpos($sanitised_string,')') === false):
            $sanitised_string = $sanitised_string."%)";
        elseif (strpos($sanitised_string,'[')   !== false && strpos($sanitised_string,']') === false && strpos($sanitised_string,')') === false):
            $sanitised_string = $sanitised_string."%]";
        endif;
        
        // Shortcut matches
        $pattern_shortcut1  = '/^[[(]([^)\]]+)[\])]\s+(\d+\S*?)$/';                                              // e.g. (mh3) 304 or [mh3] 304
        $pattern_shortcut2  = '/^[[(]([^)\]]+)\s+(\d+\S*?)[)\]]$/';                                              // e.g. (mh3 304) or [mh3 304]
        
        // Full matches
        $pattern_full_1     = '/^(.+?)\s+[(\[]([^)\]]+)[)\]]\s+(\d+\S*?)(\s\*F\*)?$/';                              // Plains (mh3) 304 or Plains [mh3] 304   Note - quantity already removed
        $pattern_full_2     = '/^(.+?)\s+[(\[]([^)\]]+)\s+(\d+\S*?)[)\]](\s\*F\*)?$/';                              // Plains (mh3 304) or Plains [mh3 304]   Note - quantity already removed
        
        // Legacy match - catches remaining non-specific cases, e.g. "Plains"
        $pattern_mtgc       = "/^([^()\[\]]+)?(?:[\[\(]\s*([^)\]\s]+)(?:\s*([^)\]\s]+(?:\s+[^)\]\s]+)*)?)?\s*[\)\]])?/";
                
        // Shortcut matches (qty irrelevant)
        if (preg_match($pattern_shortcut1, $sanitised_string, $matches) || preg_match($pattern_shortcut2, $sanitised_string, $matches)):
            $msg->logMessage('[DEBUG]', "Input interpreter result: String '$sanitised_string' is shortcut");
            $format = 'shortcut';
            // Set
            if (isset($matches[1])):
                $set = strtoupper($matches[1]);
            else:
                $set = '';
            endif;
            // Collector number
            if (isset($matches[2])):
                $number = $matches[2];
            else:
                $number = '';
            endif;
            $msg->logMessage('[DEBUG]', "Input interpreter result (Shortcut): Set: [$set] Collector number: [$number]");
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
        elseif (preg_match($pattern_full_1, $sanitised_string, $matches) || preg_match($pattern_full_2, $sanitised_string, $matches)):
            $msg->logMessage('[DEBUG]', "Input interpreter result: String '$sanitised_string' is full string");
            $format = 'full';
            if ($qty === ''):
                $qty = 1;
            endif;
            $isFoil = isset($matches[4]) ? true : false;
            if($isFoil):
                $normal = 0;
                $foil = $qty;
            else:
                 $normal = $qty;
                 $foil = 0;
            endif;
            // Name
            if (isset($matches[1])):
                $name = trim($matches[1]);
            else:
                $name = '';
            endif;
            // Set
            if (isset($matches[2])):
                $set = strtoupper($matches[2]);
            else:
                $set = '';
            endif;
            // Collector number
            if (isset($matches[3])):
                $number = $matches[3];
            else:
                $number = '';
            endif;
            $name = htmlspecialchars_decode($name, ENT_QUOTES);
            $msg->logMessage('[DEBUG]', "Input interpreter result (full): Qty: [$qty (N:$normal / F:$foil)] x Card: [$name] Set: [$set] Collector number: [$number]");
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
        elseif(preg_match($pattern_mtgc, trim($sanitised_string), $matches)):
            $msg->logMessage('[DEBUG]', "Input interpreter result: String '$sanitised_string' is mtgc");
            $format = 'mtgc';
            if ($qty === ''):
                $qty = 1;
            endif;
            
            // Name
            /// Catch fringe cases where name contains brackets ///
            if(isset($matches[1]) && isset($matches[2])):
                if(isset($matches[3])):
                    $teststring = trim($matches[2])." ".trim($matches[3]);
                else:
                    $teststring = trim($matches[2]);
                endif;
            endif;
            if(isset($teststring) && in_array_case_insensitive($teststring, $bracketsInNames)):
                $msg->logMessage('[DEBUG]', "Bracket contents match a card with brackets in name, resetting name, set to match");
                $matches[1] = $matches[1]."(".$teststring.")";
                $matches[2] = $matches[3] = '';
            endif;
            
            if (isset($matches[1])):
                $name = trim($matches[1]);
            else:
                $name = '';
            endif;
            // Set
            if (isset($matches[2])):
                $set = strtoupper($matches[2]);
            else:
                $set = '';
            endif;
            // Collector number
            if (isset($matches[3])):
                $number = $matches[3];
            else:
                $number = '';
            endif;
            $name = htmlspecialchars_decode($name, ENT_QUOTES);
            $msg->logMessage('[DEBUG]', "Input interpreter result (MTGC Quick add): Qty: [$qty] x Card: [$name] Set: [$set] Collector number: [$number]");
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
        endif;
        return $output;
    endif;
}