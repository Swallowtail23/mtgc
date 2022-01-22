<?php 
/* Version:     2.1
    Date:       11/01/20
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
*/

session_start();
require ('includes/ini.php');
require ('includes/error_handling.php');
require ('includes/functions_new.php');
include 'includes/colour.php';

if (!$_SESSION["logged"] == TRUE): ?>
    <table class='ajaxshow'>
        <tr>
            <td class="name">Your session is expired, or</td>
        </tr>
        <tr>
            <td class="name">you have been logged out.</td>
        </tr>
        <tr>
            <td class="name"><a href=login.php>Click here to log in again.</a></td>
        </tr>
    </table>
    <?php 
else: 
    //Need to run these as secpagesetup not run (see page notes)
    $user = check_logged();
    $mytable = $user."collection"; 
    //
    if($_POST):
        $r = $_POST['search'];
        $q =  $db->escape($r);
        $sql = "SELECT setcode,name FROM cards JOIN sets ON cards.setcode = sets.setcodeid WHERE name like '%$q%' ORDER BY sets.releasedat DESC, cards.name ASC LIMIT 20"; 
        $obj = new Message;
        $obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Running partial string query: $sql",$logfile);
        $sql_res = $db->query($sql);
        if ($sql_res === false):
            trigger_error("[ERROR]".basename(__FILE__)." ".__LINE__.": SQL failure: " . $db->error, E_USER_ERROR);
        else:?>
            <table class='ajaxshow'>
            <?php while($row = $sql_res->fetch_assoc()):
                $name = $row['name'];
                $ajaxname = $db->escape($name);
                $setcode = $row['setcode'];
                $sqlsetcode = $db->escape($row['setcode']);
                $getajax = $db->select_one('id,number','cards',"WHERE name like '$ajaxname' AND setcode like '$sqlsetcode'");
                if ($getajax === false):
                    trigger_error("[ERROR]".basename(__FILE__)." ".__LINE__.": SQL failure: " . $db->error, E_USER_ERROR);
                else:
                    $ajaxid = $getajax['id'];
                    $ajaxnumber = $getajax['number'];
                    $b_name = '<strong>'.$r.'</strong>';
                    $final_name = str_ireplace($r, $b_name, $name);
                endif;
                ?>
                <tr>
                    <td class="name"><?php echo "<a href='carddetail.php?setabbrv=$setcode&number=$ajaxnumber&id=$ajaxid'>$setcode - $final_name</a></td>"; ?>
                </tr>
                <?php
            endwhile; ?>
            </table> <?php
        endif;
    endif;
endif;
?>