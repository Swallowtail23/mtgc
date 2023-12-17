<?php
/* Version:     18.1
    Date:       11/12/23
    Name:       functions_new.php
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
    $msg = new Message;
    
    // Sanitize e-mail address
    $msg->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": Checking email address <$field>", $logfile);
    $field = filter_var($field, FILTER_SANITIZE_EMAIL);
    if (!filter_var($field, FILTER_VALIDATE_EMAIL)):
        $msg->MessageTxt('[ERROR]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": Invalid email address <$field> passed", $logfile);
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
                $msg->MessageTxt('[NOTICE]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": Email address validated for reset request", $logfile);
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
    $msg = new Message;
    if ($toggle == 'off'):
        $msg->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": Setting maintenance mode off",$logfile);
        $mtcequery = 0;
    elseif ($toggle == 'on'):
        $msg->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": Setting maintenance mode on",$logfile);
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
            $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Setting mtce mode to $mtcequery failed",$logfile);
        else:
            $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Set mtce mode to $mtcequery",$logfile);
        endif;
}

function mtcemode($user)
{
    global $db,$logfile;
    $msg = new Message;
    
    $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ". __LINE__,"Function ".__FUNCTION__.": Checking maintenance mode, user $user", $logfile);
    $sql1 = "SELECT mtce FROM admin LIMIT 1";
    $result1 = $db->execute_query($sql1);
    if ($result1 === false):
        trigger_error('[ERROR]', basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__ . ": SQL failure: " . $db->error, E_USER_ERROR);
    else:
        $row1 = $result1->fetch_assoc();
        if (!empty($row1) AND $row1['mtce'] == 1):
            $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ". __LINE__,"Function ".__FUNCTION__.": Maintenance mode on, running admin check", $logfile);
            $sql2 = "SELECT admin FROM users WHERE usernumber = ?";
            $result2 = $db->execute_query($sql2, [$user]);
            if ($result2 === false):
                trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ".$db->error, E_USER_ERROR);
            else:
                $row2 = $result2->fetch_assoc();
                if (!empty($row2)):
                    if ($row2['admin'] == 1):
                        $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ". __LINE__,"Function ".__FUNCTION__.": Maintenance mode on, user is admin, ignoring (return 2)", $logfile);
                        return 2;
                    else:
                        $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ". __LINE__,"Function ".__FUNCTION__.": Maintenance mode on, user is not admin (return 1, destroy session)", $logfile);
                        return 1;
                    endif;
                else:
                    trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
                endif;
            endif;
        else:
            $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ". __LINE__,"Function ".__FUNCTION__.": Maintenance mode not set", $logfile);
            return 0; // maintenance mode not set
        endif;
    endif;
}

function symbolreplace($str)
{
    $str = str_replace('{E}','<img src="images/e.png" alt="{E}" class="manaimg">',$str);
    $str = str_replace('{T}','<img src="images/t.png" alt="{T}" class="manaimg">',$str);
    $str = str_replace('{Q}','<img src="images/q.png" alt="{Q}" class="manaimg">',$str);
    
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
    $str = str_replace("ja","Japanese",$str);
    return $str;
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
    $msg = new Message;
    
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
        $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": {$curldetail['url']} DOES NOT exist, HTTP code is: ".
                $curldetail['http_code'].", file size is: ".$curldetail['download_content_length']." bytes",$logfile);
        return false;
    else: 
        $msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": {$curldetail['url']} exists, HTTP code is: ".
                $curldetail['http_code'].", file size is: ".$curldetail['download_content_length']." bytes",$logfile);
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
    $msg = new Message;
    
    $msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Writing user login",$logfile);
    $logindate = date("Y-m-d");
    $query = "UPDATE users SET lastlogin_date = ? WHERE email = ?";
    if($db->execute_query($query,[$logindate,$useremail]) === TRUE):
        $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Writing user login successful",$logfile);
        return 1;
    else:
        $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Writing user login failed",$logfile);
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
  
function validateTrueDecimal($v) 
{	
    global $logfile;
    $result = floor($v);
    $msg = new Message;
    
    $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Checking $v for true decimal, result is $result",$logfile);
    return(floor($v) != $v);
}
function update_topvalue_card($collection,$scryid)
{
    global $db, $logfile;
    $msg = new Message;
    
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
        $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"SQL query succeeded",$logfile);
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
    $msg = new Message;
    
    $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Looking up card types for card $id",$logfile);
    $stmt = $db->execute_query("SELECT finishes FROM cards_scry WHERE id = ? LIMIT 1", [$id]);
    if($stmt != TRUE):
        trigger_error("[ERROR] Class " .__METHOD__ . " ".__LINE__," - SQL failure: Error: " . $db->error, E_USER_ERROR);
    else:
        if ($stmt->num_rows > 0):
            $result = $stmt->fetch_assoc();
            if(isset($result['finishes'])):
                $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Card $id is valid, looking up finishes",$logfile);
                $finishes = json_decode($result['finishes'], TRUE);
                $cardtypes = cardtypes($finishes);
            else:
                $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Card $id is valid, but no finishes",$logfile);
                $cardtypes = 'none';
            endif;
        else:
            $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Card $id has no match",$logfile);
            $cardtypes = 'nomatch';
        endif;
    endif;
    $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Card type for $id is $cardtypes",$logfile);
    return $cardtypes;
}

function card_legal_db_field($decktype)
{
    global $db, $deck_legality_map, $logfile;
    $msg = new Message;
    
    $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Looking up db_field for legality for deck type '$decktype'",$logfile);
    $index = array_search("$decktype", array_column($deck_legality_map, 'decktype'));
    if ($index !== false):
        $db_field = $deck_legality_map[$index]['db_field'];
    endif;
    $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Deck type '$decktype' has legality in '$db_field'",$logfile);
    return $db_field;
}

function promo_lookup($promo_type)
{
    global $promos_to_show, $logfile;
    $msg = new Message;
    
    $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Looking up promo description for '$promo_type'",$logfile);
    $index = array_search($promo_type, array_column($promos_to_show, 'promotype'));
    if ($index !== false):
        $promo_description = $promos_to_show[$index]['display'];
    else:
        $promo_description = 'skip';
    endif;
    $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Promo description for '$promo_type' is '$promo_description'",$logfile);
    return $promo_description;
}

function deck_legal_list($decknumber,$deck_type,$db_field)
{
    global $db, $logfile;
    $msg = new Message;
    
    $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Getting deck legality list for $deck_type deck '$decknumber' (using db_field '$db_field')",$logfile);
    $sql = "SELECT cardnumber FROM deckcards WHERE decknumber = ?";
    $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Looking up SQL: $sql",$logfile);
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
    $msg = new Message;
    
    $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Checking for valid UUID ($uuid)",$logfile);
    if (preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/', $uuid)):
        $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Valid UUID ($uuid)",$logfile);
        $uuid = $db->real_escape_string($uuid);
        return $uuid;
    else:
        $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Invalid UUID ($uuid), returning 'false'",$logfile);
        return false;
    endif;
}

function valid_tablename($input)
{
    global $db, $logfile;
    $msg = new Message; 
    
    $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Checking for valid table name ($input)",$logfile);
    $pattern = '/^\d+collection$/';
    if (preg_match($pattern, $input)):
        $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Valid table name",$logfile);
        return $input;
    else:
        $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Invalid table name",$logfile);
        return false;
    endif;
}