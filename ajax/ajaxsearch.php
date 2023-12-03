<?php 
/* Version:     5.0
    Date:       01/12/23
    Name:       ajaxsearch.php
    Purpose:    PHP script to run ajax search from header
    Notes:      The page does not run standard secpagesetup as it breaks 
                the ajax login catch.
    To do:      -

    1.0
                Initial version
 *  2.0
 *              Migrated to Mysqli_Manager
 *  2.1
 *              Moved from writelog to Message class
 *  3.0
 *              Refactor for cards_scry
 *  4.0
 *              Move to prepared statements
 *  5.0
 *              Add [set] search interpretation
*/

session_start();
require ('../includes/ini.php');
require ('../includes/error_handling.php');
require ('../includes/functions_new.php');
include '../includes/colour.php';

if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== TRUE): 
    echo "<table class='ajaxshow'><tr><td class='name'>You are not logged in.</td></tr></table>";
    echo "<meta http-equiv='refresh' content='2;url=/login.php'>";               // check if user is logged in; else redirect to login.php
    exit(); 
else: 
    //Need to run these as secpagesetup not run (see page notes)
    $sessionManager = new SessionManager($db,$adminip,$_SESSION, $fxAPI, $fxLocal, $logfile);
    $userArray = $sessionManager->getUserInfo();
    $user = $userArray['usernumber'];
    $mytable = $userArray['table'];
    //
    if($_POST):
        $r = $_POST['search'];
        // Test for the existence of a string enclosed in parentheses
        if (strpos($r, '[') !== false):
            $insideBrackets = $closingBracket = false;
            $u = $q = $s = $t = '';

            foreach (str_split($r) as $char):
                if ($char === '['):
                    $insideBrackets = true;
                elseif ($insideBrackets && $char !== ']'):
                    $s .= $char;
                elseif ($char === ']' && $insideBrackets):
                    $closingBracket = TRUE;
                    break;
                elseif (!$insideBrackets):
                    $q .= $char;
                endif;
            endforeach;
            if($closingBracket === TRUE):
                $u = $db->escape($s);
            endif;
            $t = trim($q);
            $q = '%'.trim($db->escape($q)).'%';
        else:
            $t = trim($r);
            $q = '%'.trim($db->escape($r)).'%';
            $u = ''; // No brackets in this case
        endif;
        
        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Seaarch string (q) is '$q', match string (t) is '$t', setcode is '$u'",$logfile);
        $stmt = $db->prepare("SELECT id, setcode, name, printed_name, flavor_name, f1_name, f1_printed_name, f1_flavor_name, f2_name, f2_printed_name, f2_flavor_name, release_date
                      FROM cards_scry
                      WHERE
                      (printed_name LIKE ? 
                      OR flavor_name LIKE ? 
                      OR name LIKE ? 
                      OR f1_printed_name LIKE ? 
                      OR f1_flavor_name LIKE ? 
                      OR f1_name LIKE ?
                      OR f2_printed_name LIKE ? 
                      OR f2_flavor_name LIKE ? 
                      OR f2_name LIKE ?)
                      AND
                      (setcode LIKE ? OR ? = '')
                      ORDER BY release_date DESC, name ASC LIMIT 20");
        $stmt->bind_param("sssssssssss", $q, $q, $q, $q, $q, $q, $q, $q, $q, $u, $u);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($id, $setcode, $name, $printed_name, $flavor_name, $f1_name, $f1_printed_name, $f1_flavor_name, $f2_name, $f2_printed_name, $f2_flavor_name, $release_date);

        if ($stmt->error):
            trigger_error("[ERROR]".basename(__FILE__)." ".__LINE__.": SQL failure: " . $stmt->error, E_USER_ERROR);
        else: ?>
            <table class='ajaxshow'>
            <?php 
            while($row = $stmt->fetch()):
                if($printed_name !== null AND strpos(strtolower($printed_name),strtolower($t)) !== false):
                    $name = $printed_name;
                elseif($flavor_name !== null AND strpos(strtolower($flavor_name),strtolower($t)) !== false):
                    $name = $flavor_name;
                elseif($f1_name !== null AND strpos(strtolower($f1_name),strtolower($t)) !== false):
                    $name = $f1_name;
                elseif($f1_printed_name !== null AND strpos(strtolower($f1_printed_name),strtolower($t)) !== false):
                    $name = $f1_printed_name;
                elseif($f1_flavor_name !== null AND strpos(strtolower($f1_flavor_name),strtolower($t)) !== false):
                    $name = $f1_flavor_name;
                elseif($f2_name !== null AND strpos(strtolower($f2_name),strtolower($t)) !== false):
                    $name = $f2_name;
                elseif($f2_printed_name !== null AND strpos(strtolower($f2_printed_name),strtolower($t)) !== false):
                    $name = $f2_printed_name;
                elseif($f2_flavor_name !== null AND strpos(strtolower($f2_flavor_name),strtolower($t)) !== false):
                    $name = $f2_flavor_name;
                endif;
                $ajaxname = $db->escape($name);
                $sqlsetcode = $db->escape($setcode);
                $displaysetcode = strtoupper($setcode);
                $getajax = $db->select_one('id,number_import','cards_scry',"WHERE id LIKE '$id'");
                if ($getajax === false):
                    trigger_error("[ERROR]".basename(__FILE__)." ".__LINE__.": SQL failure: " . $db->error, E_USER_ERROR);
                else:
                    $ajaxid = $getajax['id'];
                    $ajaxnumber = $getajax['number_import'];
                    $b_name = '<strong>'.$t.'</strong>';
                    $final_name = str_ireplace($t, $b_name, $name);
                endif;
                ?>
                <tr>
                    <td class="name"><?php echo "<a href='carddetail.php?id=$ajaxid'>$displaysetcode - $final_name</a></td>"; ?>
                </tr>
                <?php
            endwhile; ?>
            </table> <?php
        endif;
    endif;
endif;
?>