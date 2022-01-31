<?php
/* Version:     9.0
    Date:       28/01/22
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
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;

function check_logged(){ 
    global $_SESSION, $db, $logfile;
    
    if (isset($_SESSION['user'])):
        $user = $_SESSION['user'];
    else:
        $user = '';
        header("Location: /login.php");
        exit();
    endif;
    
    // Check user status
    $row = $db->select_one('status', 'users',"WHERE usernumber='$user'");
    if($row === false):
        header("Location: /login.php");
    else:
        if (!$_SESSION["logged"] == TRUE):
            header("Location: /login.php");
            exit();
        elseif (($row['status'] === 'disabled') OR ($row['status'] === 'locked')):
            session_destroy();
            header("Location: /login.php");
            exit();
        endif;
    endif;
    return $user;
}

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
        $value = "'" . mysqli_real_escape_string($db,$value) . "'";
    endif;

    return $value;
}

function cssver()
{
    global $db;
    if($row = $db->select_one('usemin', 'admin')):
        if($row['usemin'] == 1):
            $cssver = "-min";
        else:
            $cssver = "";
        endif;
        return $cssver;
    else:
        trigger_error('[ERROR]',basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
    endif; 
}

function spamcheck($field) 
{
    global $db, $logfile;
    // Sanitize e-mail address
    $obj = new Message;
    $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Checking email address <$field>",$logfile);
    $field=filter_var($field, FILTER_SANITIZE_EMAIL);
    if(!filter_var($field, FILTER_VALIDATE_EMAIL)):
        $obj = new Message;$obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Invalid email address <$field> passed",$logfile);
        return FALSE;
    else:
        if($row = $db->select_one('usernumber,username', 'users',"WHERE email = '$field'")):
            if(empty($row)):
                return FALSE;
            elseif(filter_var($field, FILTER_VALIDATE_EMAIL)):
                $obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Email address validated for reset request",$logfile);
                return $field;
            else:
                return FALSE;
            endif;
        else:
            trigger_error('[ERROR]',basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
        endif;
    endif;
}

function mtcemode($user)
{
    global $db;
    if($row = $db->select_one('mtce', 'admin')):
        if ($row['mtce'] == 1):
            if($row2 = $db->select_one('admin', 'users',"WHERE usernumber='$user'")):
                if ($row2['admin'] == 1):   //admin user logged on
                    return 2;
                else:
                    return 1;               //non-admin user logged on
                endif;
            else:
                trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
            endif;
        else:
            return 0;                           // maintenance mode not set
        endif;
    else:
        trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
    endif; 
}

function username($user)
{
    global $db;
    if($row = $db->select_one('username','users',"WHERE usernumber='$user'")):
        $username = $row['username'];
        return $username;
    else:
        trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
    endif;
}

function deckownercheck($deck,$user)
{
    global $db, $logfile;
    $sql = "SELECT * FROM decks WHERE decknumber = $deck LIMIT 1";
    $result = $db->query($sql);
    if($result === false):
        trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
    else:
        while($row = $result->fetch_assoc()):
            $deckname = $row['deckname'];
            if ($row['owner'] !== $user):
                $obj = new Message;$obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Deck {$row['deckname']} does not belong to user $user, returning to deck page",$logfile);
                return false;
            else:
                return $deckname;
            endif;
        endwhile;
    endif;    
}

function adddeckcard($deck,$card,$section,$quantity)
{
    global $db, $logfile;
    if($section == "side"):
        $check = $db->select_one('sideqty','deckcards',"WHERE decknumber = $deck AND cardnumber = '$card' AND sideqty IS NOT NULL");
        if ($check !== null):
            if($check['sideqty'] > 0):
                $cardquery = "UPDATE deckcards SET sideqty = sideqty + 1 WHERE decknumber = $deck AND cardnumber = '$card'";
                $status = "+1side";
            endif;
        else:
            $cardquery = "INSERT into deckcards (decknumber, cardnumber, sideqty) VALUES ($deck, '$card', $quantity)";
            $status = "+newside";
        endif;
    elseif($section == "main"):
        $check = $db->select_one('cardqty','deckcards',"WHERE decknumber = $deck AND cardnumber = '$card' AND cardqty IS NOT NULL");
        if ($check !== null):
            if($check['cardqty'] > 0):
                $cardquery = "UPDATE deckcards SET cardqty = cardqty + $quantity WHERE decknumber = $deck AND cardnumber = '$card'";
                $status = "+1main";
            endif;
        else:
            $cardquery = "INSERT into deckcards (decknumber, cardnumber, cardqty) VALUES ($deck, '$card', $quantity)";
            $status = "+newmain";
        endif;
    endif;
    $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Add card called: $cardquery, status is $status",$logfile);
    if($runquery = $db->query($cardquery)):
        return $status;
    else:
        trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
    endif;    
}

function subtractdeckcard($deck,$card,$section,$quantity)
{
    global $db, $logfile;
    if($quantity == "all"):
        if($section == "side"):
            $cardquery = "DELETE FROM deckcards WHERE decknumber = $deck AND sideqty IS NOT NULL AND cardnumber = '$card'";
            $status = "allside";
        elseif($section == "main"):
            $cardquery = "DELETE FROM deckcards WHERE decknumber = $deck AND cardqty IS NOT NULL AND cardnumber = '$card'";
            $status = "allmain";
        endif;
    else:
        if($section == "side"):
            $check = $db->select_one('sideqty','deckcards',"WHERE decknumber = $deck AND cardnumber = '$card' AND sideqty IS NOT NULL");
            if ($check !== null):
                if($check['sideqty'] > 1):
                    $cardquery = "UPDATE deckcards SET sideqty = sideqty - 1 WHERE decknumber = $deck AND cardnumber = '$card'";
                    $status = "-1side";
                elseif($check['sideqty'] == 1):
                    $cardquery = "DELETE FROM deckcards WHERE decknumber = $deck AND cardnumber = '$card' AND sideqty = 1";
                    $status = "lastside";
                endif;
            else:
                $status = "-error";
            endif;
        elseif($section == "main"):
            $check = $db->select_one('cardqty','deckcards',"WHERE decknumber = $deck AND cardnumber = '$card' AND cardqty IS NOT NULL");
            if ($check !== null):
                if($check['cardqty'] > 1):
                    $cardquery = "UPDATE deckcards SET cardqty = cardqty - 1 WHERE decknumber = $deck AND cardnumber = '$card'";
                    $status = "-1main";
                elseif($check['cardqty'] == 1):
                    $cardquery = "DELETE FROM deckcards WHERE decknumber = $deck AND cardnumber = '$card' AND cardqty = 1";
                    $status = "lastmain";
                endif;
            else:
                $status = "-error";
            endif;
        endif;
    endif;
    $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Delete deck card query called: $cardquery, status is $status",$logfile);
    if($status != '-error'):
        if ($runquery = $db->query($cardquery)):
            //ran ok
        else:
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
        endif;
    else:
        trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
    endif;
    return $status;
}

function addcommander($deck,$card)
{
    global $db, $logfile;
    $check = $db->select('commander','deckcards',"WHERE decknumber = $deck AND commander = '1'");
    if ($check->num_rows > 0): //Commander already there
        $cardquery = "UPDATE deckcards SET commander = '0' WHERE decknumber = $deck";
        if($runquery = $db->query($cardquery)):
            $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Old Commander removed",$logfile);
        else:
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
        endif; 
    endif;
    $status = "+cdr";
    $cardquery = "UPDATE deckcards SET commander = '1' WHERE decknumber = $deck AND cardnumber = '$card'";
    if($runquery = $db->query($cardquery)):
        $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Add Commander run: $cardquery, status is $status",$logfile);
        return $status;
    else:
        trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
    endif; 
}

function delcommander($deck,$card)
{
    global $db, $logfile;
    $cardquery = "UPDATE deckcards SET commander = '0' WHERE decknumber = $deck AND cardnumber = '$card'";
    $check = $db->select('commander','deckcards',"WHERE decknumber = $deck AND cardnumber = '$card' AND commander = '1'");
    if ($check->num_rows > 0): 
        $status = "-cdr";
        if($runquery = $db->query($cardquery)):
            $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Remove Commander called: $cardquery, status is $status",$logfile);
            return $status;
        else:
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
        endif;    
    else:
        $status = "notcdr";
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
    $str = str_replace('{WB}','<img src="images/wb.png" alt="{WB}" class="manaimg">',$str);
    $str = str_replace('{UB}','<img src="images/ub.png" alt="{UB}" class="manaimg">',$str);
    $str = str_replace('{UR}','<img src="images/ur.png" alt="{UR}" class="manaimg">',$str);
    $str = str_replace('{BR}','<img src="images/br.png" alt="{BR}" class="manaimg">',$str);
    $str = str_replace('{BG}','<img src="images/bg.png" alt="{BG}" class="manaimg">',$str);
    $str = str_replace('{RW}','<img src="images/rw.png" alt="{RW}" class="manaimg">',$str);
    $str = str_replace('{RG}','<img src="images/rg.png" alt="{RG}" class="manaimg">',$str);
    $str = str_replace('{GW}','<img src="images/gw.png" alt="{GW}" class="manaimg">',$str);
    $str = str_replace('{GU}','<img src="images/gu.png" alt="{GU}" class="manaimg">',$str);
    
    $str = str_replace('{2W}','<img src="images/2w.png" alt="{2W}" class="manaimg">',$str);
    $str = str_replace('{2U}','<img src="images/2u.png" alt="{2U}" class="manaimg">',$str);
    $str = str_replace('{2B}','<img src="images/2b.png" alt="{2B}" class="manaimg">',$str);
    $str = str_replace('{2R}','<img src="images/2r.png" alt="{2R}" class="manaimg">',$str);
    $str = str_replace('{2G}','<img src="images/2g.png" alt="{2G}" class="manaimg">',$str);
    
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
    $str = str_replace('{PU}','<img src="images/pu.png" alt="{PU}" class="manaimg">',$str);
    $str = str_replace('{PB}','<img src="images/pb.png" alt="{PB}" class="manaimg">',$str);
    $str = str_replace('{PR}','<img src="images/pr.png" alt="{PR}" class="manaimg">',$str);
    $str = str_replace('{PG}','<img src="images/pg.png" alt="{PG}" class="manaimg">',$str);
    $str = str_replace('{CHAOS}','<img src="images/chaos.png" alt="{PG}" class="manaimg">',$str);
    $str = str_replace('?','-',$str);
    $str = str_replace('£','<br>',$str);
    $str = str_replace('#','',$str);
    $str = str_replace('{PWk}','Planeswalk',$str);
    $str = str_replace('{Ch}','Chaos',$str);
    $str = str_replace("\n","<br>",$str);
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
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,$url);
    curl_setopt($ch, CURLOPT_NOBODY, 1);
    curl_setopt($ch, CURLOPT_VERBOSE, 1);
    curl_setopt($ch, CURLOPT_STDERR, fopen('php://stderr', 'w'));
    curl_setopt($ch, CURLOPT_FAILONERROR, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch,CURLOPT_USERAGENT,'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.17 (KHTML, like Gecko) Chrome/24.0.1312.52 Safari/537.17');
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    $curlresult = curl_exec($ch);
    $curldetail = curl_getinfo($ch);
    curl_close($ch);
    if($curlresult === FALSE or $curldetail['http_code'] != 200 or $curldetail['download_content_length'] < 1):
        $obj = new Message;$obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": {$curldetail['url']} DOES NOT exist, HTTP code is: ".
                $curldetail['http_code'].", file size is: ".$curldetail['download_content_length']." bytes",$logfile);
        return false;
    else: 
        $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": {$curldetail['url']} exists, HTTP code is: ".
                $curldetail['http_code'].", file size is: ".$curldetail['download_content_length']." bytes",$logfile);
        return true;
    endif;
}

function getimgname($cardid)
{
    global $logfile;
    $obj = new Message;
    $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Getting image name - cardid: $cardid",$logfile);
    $imgname = $cardid.'.jpg';
    $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": card image name is $imgname",$logfile);
    return $imgname;
}

function getImageNew($setcode,$cardid,$ImgLocation,$layout)
{
    global $db, $logfile, $serveremail, $adminemail;
    $obj = new Message;
    $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": called for $setcode, $cardid, $ImgLocation, $layout",$logfile);
    $flip_types = ['transform','art_series','modal_dfc','reversible_card'];
    $localfile = $ImgLocation.$setcode.'/'.$cardid.'.jpg';
    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": File should be at $localfile",$logfile);
    if(in_array($layout,$flip_types)):
        $localfile_b = $ImgLocation.$setcode.'/'.$cardid.'_b.jpg';
        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Back file should be at $localfile_b",$logfile);
    endif;
    // Front face
    if (!file_exists($localfile)):
        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": $localfile missing, running get image function",$logfile);
        $sql = "SELECT image_uri,layout,f1_image_uri FROM cards_scry WHERE id like '$cardid' LIMIT 1";
        $result = $db->query($sql);
        if($result === false):
             trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL error: ".$db->error, E_USER_ERROR);
        else:
            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Query $sql successful",$logfile);
            $coderow = $result->fetch_array(MYSQLI_ASSOC);
            $imageurl = '';
            if(isset($coderow['image_uri']) AND !is_null($coderow['image_uri'])):
                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Standard card, {$coderow['image_uri']}",$logfile);
                $imageurl = strtolower($coderow['image_uri']);
                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Looking on scryfall.com ($cardid) for image to use as $localfile",$logfile);
            elseif(isset($coderow['f1_image_uri']) AND !is_null($coderow['f1_image_uri'])):
                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Flip card, {$coderow['f1_image_uri']}",$logfile);
                $imageurl = strtolower($coderow['f1_image_uri']);
                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Looking on scryfall.com ($cardid) for images to use as $localfile",$logfile);
            endif;
            if ((checkRemoteFile($imageurl) == false) OR ($imageurl === '')):
                $imageurl = '';
                $from = "From: $serveremail\r\nReturn-path: $serveremail"; 
                $subject = "Invalid image from Scryfall API"; 
                $message = "$imageurl for card $cardid does not exist - check database entry against API, has it been deleted?";
                mail($adminemail, $subject, $message, $from); 
                $frontimg = 'error';
            else:
                $options  = array('http' => array('user_agent' => 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.17 (KHTML, like Gecko) Chrome/24.0.1312.52 Safari/537.17'));
                $context  = stream_context_create($options);
                $image = file_get_contents($imageurl, false, $context);
                if (!file_exists($ImgLocation.$setcode)):
                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Creating new directory $setcode",$logfile);
                    mkdir($ImgLocation.$setcode);
                endif;
                file_put_contents($localfile, $image);
                $relativepath = strpos($localfile,'cardimg');
                $frontimg = substr($localfile,$relativepath);
            endif;
        endif;
    else:
        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": File exists already at $localfile",$logfile);
        $relativepath = strpos($localfile,'cardimg');
        $frontimg = substr($localfile,$relativepath);
    endif;
    $imageurl = array('front' => $frontimg,
                      'back' => '');
    //Back face
    if (isset($localfile_b)):
        if(!file_exists($localfile_b)):
            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": $localfile_b missing, running get image function",$logfile);
            $sql = "SELECT layout,f2_image_uri FROM cards_scry WHERE id like '$cardid' LIMIT 1";
            $result2 = $db->query($sql);
            if($result2 === false):
                 trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL error: ".$db->error, E_USER_ERROR);
            else:
                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Query $sql successful",$logfile);
                $coderow2 = $result2->fetch_array(MYSQLI_ASSOC);
                $imageurl_2 = '';
                if(isset($coderow2['f2_image_uri']) AND !is_null($coderow2['f2_image_uri'])):
                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Flip card back, {$coderow2['f2_image_uri']}",$logfile);
                    $imageurl = strtolower($coderow2['f2_image_uri']);
                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Looking on scryfall.com ($cardid) for images to use as $localfile_b",$logfile);
                    $imageurl_2 = strtolower($coderow2['f2_image_uri']);
                endif;
                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Flip card back image, {$coderow2['f2_image_uri']}",$logfile);
                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Looking on scryfall.com ($cardid) for image to use as $localfile_b",$logfile);
                if ($imageurl_2 === ''):
                    $backimg = 'empty';
                elseif(checkRemoteFile($imageurl_2) == false):
                    $backimg = 'error';
                else:
                    $options  = array('http' => array('user_agent' => 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.17 (KHTML, like Gecko) Chrome/24.0.1312.52 Safari/537.17'));
                    $context  = stream_context_create($options);
                    $image2 = file_get_contents($imageurl_2, false, $context);
                    if (!file_exists($ImgLocation.$setcode)):
                        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Creating new directory $setcode",$logfile);
                        mkdir($ImgLocation.$setcode);
                    endif;
                    file_put_contents($localfile_b, $image2);
                    $relativepath_2 = strpos($localfile_b,'cardimg');
                    $backimg = substr($localfile_b,$relativepath_2);
                endif;
            endif;
        elseif (file_exists($localfile_b)):
            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": File exists already at $localfile_b",$logfile);
            $relativepath_2 = strpos($localfile_b,'cardimg');
            $backimg = substr($localfile_b,$relativepath_2);
        endif;
        $imageurl = array('front' => $frontimg,
                          'back' => $backimg);
    endif;
    return $imageurl;
}

function getStringParameters($input,$ignore1,$ignore2='')
// This function takes a parsed GET string and passes it back with SET sub-arrays included, and a specified KEY excluded
{
    $output="";
    foreach($input as $key => $value):
        if ((isset($input['set'])) AND (is_array($input['set']))):
            $sets = filter_var_array($input['set'], FILTER_SANITIZE_STRING);
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

function exportMysqlToCsv($table,$filename = 'export.csv')
{
        global $db, $logfile;
        $csv_terminated = "\n";
	$csv_separator = ",";
	$csv_enclosed = '"';
	$csv_escaped = "\\";
	$table = $db->escape($table);
        $sql = "SELECT setcode,number_import,name,normal,$table.foil,$table.id as scryfall_id FROM $table JOIN cards_scry ON $table.id = cards_scry.id WHERE (($table.normal > 0) OR ($table.foil > 0))";
        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Running Export Collection SQL: $sql",$logfile);
        
	// Gets the data from the database
	$result = $db->query($sql);
        if($result === false):
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
        else:
            $fields_cnt = $result->field_count;
            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Number of fields: $fields_cnt",$logfile);
            $schema_insert = '';
            for ($i = 0; $i < $fields_cnt; $i++):
                $fieldinfo = mysqli_fetch_field_direct($result, $i);
                $l = $csv_enclosed.str_replace($csv_enclosed, $csv_escaped.$csv_enclosed, stripslashes($fieldinfo->name)).$csv_enclosed;
                $schema_insert .= $l;
                $schema_insert .= $csv_separator;
            endfor;

            $out = trim(substr($schema_insert, 0, -1));
            $out .= $csv_terminated;

            // Format the data
            while($row = $result->fetch_row()):
                $schema_insert = '';
                for ($j = 0; $j < $fields_cnt; $j++):
                    if ($row[$j] == '0' || $row[$j] != ''):
                        if ($csv_enclosed == ''):
                            $schema_insert .= $row[$j];
                        else:
                            $schema_insert .= $csv_enclosed .
                            str_replace($csv_enclosed, $csv_escaped . $csv_enclosed, $row[$j]) . $csv_enclosed;
                        endif;
                    else:
                        $schema_insert .= '';
                    endif;
                    if ($j < $fields_cnt - 1):
                        $schema_insert .= $csv_separator;
                    endif;
                endfor;
                $out .= $schema_insert;
                $out .= $csv_terminated;
            endwhile;
                $out .= $csv_enclosed;
                $out .= $csv_terminated;
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
            header("Content-Length: " . strlen($out));
            // Output to browser with appropriate mime type, you choose ;)
            header("Content-type: text/x-csv; charset=UTF-8");
            //header("Content-type: text/csv");
            //header("Content-type: application/csv");
            header("Content-Disposition: attachment; filename=$filename");
            echo "\xEF\xBB\xBF"; // UTF-8 BOM
            echo $out;
            exit;
        endif;
}

function importmapcheck($import_id)
{
    global $db;
    $sql = "SELECT mapped_id FROM importlookup WHERE original_id = '$import_id' LIMIT 1";
    $result = $db->query($sql);
    if($result === false):
        trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
    else:
        $row = $result->fetch_assoc();
        if (!empty($row[0])):
            $import_id = $row[0];
        endif;
        return $import_id;
    endif;
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

function check_admin_control($adminip)
{ 
    // Check for Session variable for admin access (set on login)
    if ((isset($_SESSION['admin'])) AND ($_SESSION['admin'] === TRUE)):
        if (($adminip === 1) OR ($adminip === $_SERVER['REMOTE_ADDR'])):
            //Admin and secure location or Admin IP set to ''
            $admin = 1;
        else:
            //Admin but not a secure location
            $admin = 2;
        endif;
    else:
        //Not an admin
        $admin = 3;
    endif;
    return $admin;
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

function scryfall($cardid)
// Fetch TCG buy URI from scryfall.com
{
    //Set up the function
    global $db,$logfile,$useremail;
    $obj = new Message;
    $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail for $cardid",$logfile);
    if(!isset($cardid)):
        $obj = new Message;
        $obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail without required card id",$logfile);
        exit;
    endif;
    $baseurl = "https://api.scryfall.com/";
    $cardid = $db->escape($cardid);
    $time = time();
    //Set the URL
    $url = $baseurl."cards/".$cardid;
    $obj = new Message;
    $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail URL for $cardid is $url",$logfile);
        
    if($row = $db->select('id','cards_scry',"WHERE id='$cardid'")):
        if ($row->num_rows === 0):
            $obj = new Message;
            $obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail, no card with this id - exiting (2)",$logfile);
            exit;
        elseif ($row->num_rows === 1):
            $scrymethod = 'id';
        endif;
    else:
        $obj = new Message;
        $obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API error",$logfile);
        $this->status = 0;
        trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
    endif;
    
    // Check for existing data, not too old, and set required action
    $row = $db->select_one('jsonupdatetime, tcg_buy_uri','scryfalljson',"WHERE id='$cardid'");
    if ($row !== null):
        $obj = new Message;
        $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail with result: Data exists for $cardid",$logfile);
        $lastjsontime = $row['jsonupdatetime'];
        if ((time() - $lastjsontime) > 43200):
            //Old data, fetch and update:
            $scryaction = 'update';
            $obj = new Message;
            $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail with result: Data stale or has error for $cardid, running '$scryaction'",$logfile);
        else:
            //data is there and is current:
            $scryaction = 'read';
            $obj = new Message;
            $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail with result: Data already current for $cardid, running '$scryaction'",$logfile);
        endif;
    else:
        //No data, fetch and insert:
        $scryaction = 'get';
        $obj = new Message;
        $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail with result: No data exists for $cardid, running '$scryaction'",$logfile);
    endif;
            
    // Actions:

    // UPDATE
    if($scryaction === 'update'):
        $obj = new Message;
        $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail with 'update' result: fetching $url",$logfile);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $curlresult = curl_exec($ch);
        $obj = new Message;
        $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail with update: $curlresult",$logfile);
        curl_close($ch);
        $scryfall_result = json_decode($curlresult,true);
        $tcg_buy_uri = $scryfall_result["purchase_uris"]["tcgplayer"];
        $price = $scryfall_result["prices"]["usd"];
        $price_foil = $scryfall_result["prices"]["usd_foil"];
        $data = array(
            'tcg_buy_uri' => $tcg_buy_uri
            );
        if ($db->update('scryfalljson', $data, "WHERE id='$cardid'")):
            $obj = new Message;
            $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail, tcg uri updated",$logfile);
        else:
            $obj = new Message;
            $obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail, tcg uri update failed",$logfile);
        endif;
        $data = array(
            'price' => $price,
            'price_foil' => $price_foil,
            );
        if ($db->update('cards_scry', $data, "WHERE id='$cardid'")):
            $obj = new Message;
            $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail, price data updated",$logfile);
        else:
            $obj = new Message;
            $obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail, price data update failed",$logfile);
        endif;

    // READ
    elseif($scryaction === 'read'):
        $tcg_buy_uri = $row['tcg_buy_uri'];
        $obj = new Message;
        $obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail, returning {$row['tcg_buy_uri']}",$logfile);
    
    // GET and INSERT
    elseif($scryaction === 'get'):
        $obj = new Message;
        $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail with 'get' result: fetching $url",$logfile);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $curlresult = curl_exec($ch);
        $obj = new Message;
        $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail with get: $curlresult",$logfile);
        curl_close($ch);
        $scryfall_result = json_decode($curlresult,true);
        if(isset($scryfall_result["purchase_uris"]["tcgplayer"])):
            $tcg_buy_uri = $scryfall_result["purchase_uris"]["tcgplayer"];
        else:
            $obj = new Message;
            $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail, result does not contain a tcg link",$logfile);
            $tcg_buy_uri = 0;
            return '';
        endif;
        if(isset($scryfall_result["prices"])):
            $price = $scryfall_result["prices"]["usd"];
            $price_foil = $scryfall_result["prices"]["usd_foil"];
        else:
            $obj = new Message;
            $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail, result does not contain a prices section",$logfile);
            $prices = 0;
        endif;
        $query = 'INSERT INTO scryfalljson (id, jsonupdatetime, tcg_buy_uri) VALUES (?,?,?)';
        $stmt = $db->prepare($query);
        if ($stmt === false):
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": Preparing SQL: ". $db->error, E_USER_ERROR);
        endif;
        $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": $query",$logfile);
        $stmt->bind_param('sss', $cardid, $time, $tcg_buy_uri);
        if ($stmt === false):
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": Binding SQL: ". $db->error, E_USER_ERROR);
        endif;
        $exec = $stmt->execute();
        if ($exec === false):
            $obj = new Message;
            $obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Adding update notice: failed ".$db->error, E_USER_ERROR,$logfile);
        else:
            $obj = new Message;
            $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail, new data written for $cardid: Insert ID: ".$stmt->insert_id,$logfile);
        endif;
        if($prices !== 0):
            $data = array(
                'price' => $price,
                'price_foil' => $price_foil,
                );
            if ($db->update('cards_scry', $data, "WHERE id='$cardid'")):
                $obj = new Message;
                $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail, price data updated",$logfile);
            else:
                $obj = new Message;
                $obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail, price data update failed",$logfile);
            endif;
        endif;
    endif;
    return $tcg_buy_uri;
}

function loginstamp($useremail)
{
    global $db, $logfile;
        $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Writing user login",$logfile);
        $logindate = date("Y-m-d");
        $query = "UPDATE users SET lastlogin_date = '$logindate' WHERE email = '$useremail'";
    if($db->query($query) === TRUE):
        $obj = new Message;
        $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Writing user login successful",$logfile);
        return 1;
    else:
        $obj = new Message;
        $obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Writing user login failed",$logfile);
        return 0;
    endif;
}

if(!function_exists('hash_equals')):
    function hash_equals($str1, $str2)
    {
        if(strlen($str1) != strlen($str2)):
            return false;
        else:
            $res = $str1 ^ $str2;
            $ret = 0;
            for($i = strlen($res) - 1; $i >= 0; $i--)
            {
                $ret |= ord($res[$i]);
            }
            return !$ret;
        endif;
    }
endif;

function downloadbulk($url, $dest)
  {
    $options = array(
      CURLOPT_FILE => is_resource($dest) ? $dest : fopen($dest, 'w'),
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_URL => $url,
      CURLOPT_FAILONERROR => true, // HTTP code > 400 will throw curl error
    );

    $ch = curl_init();
    curl_setopt_array($ch, $options);
    $return = curl_exec($ch);
    curl_close($ch);

    if ($return === false):
        return curl_error($ch);
    else:
        return true;
    endif;
  }
  
  function validateTrueDecimal($v) 
{	return(floor($v) != $v);		}