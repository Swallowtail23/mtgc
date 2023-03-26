<?php 
/* Version:     3.0
    Date:       27/01/22
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
        $sql = "SELECT id,setcode,name,printed_name,flavor_name,f1_name,f1_printed_name,f1_flavor_name,f2_name,f2_printed_name,f2_flavor_name,release_date
                FROM cards_scry 
                WHERE 
                printed_name like '%$q%'
                OR flavor_name like '%$q%'
                OR name like '%$q%'
                OR f1_printed_name like '%$q%'
                OR f1_flavor_name like '%$q%'
                OR f1_name like '%$q%'
                OR f2_printed_name like '%$q%'
                OR f2_flavor_name like '%$q%'
                OR f2_name like '%$q%'
                ORDER BY release_date DESC, name ASC LIMIT 20"; 
        $obj = new Message;
        $obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Running partial string query: $sql",$logfile);
        $sql_res = $db->query($sql);
        if ($sql_res === false):
            trigger_error("[ERROR]".basename(__FILE__)." ".__LINE__.": SQL failure: " . $db->error, E_USER_ERROR);
        else:?>
            <table class='ajaxshow'>
            <?php while($row = $sql_res->fetch_assoc()):
                if(strpos(strtolower($row['name']),strtolower($r)) !== false):
                    $name = $row['name'];
                    $z = 1;
                elseif($row['printed_name'] !== null AND strpos(strtolower($row['printed_name']),strtolower($r)) !== false):
                    $name = $row['printed_name'];
                    $z = 2;
                elseif($row['flavor_name'] !== null AND strpos(strtolower($row['flavor_name']),strtolower($r)) !== false):
                    $name = $row['flavor_name'];
                    $z = 3;
                elseif($row['f1_name'] !== null AND strpos(strtolower($row['f1_name']),strtolower($r)) !== false):
                    $name = $row['f1_name'];
                    $z = 4;
                elseif($row['f1_printed_name'] !== null AND strpos(strtolower($row['f1_printed_name']),strtolower($r)) !== false):
                    $name = $row['f1_printed_name'];
                    $z = 5;
                elseif($row['f1_flavor_name'] !== null AND strpos(strtolower($row['f1_flavor_name']),strtolower($r)) !== false):
                    $name = $row['f1_flavor_name'];
                    $z = 6;
                elseif($row['f2_name'] !== null AND strpos(strtolower($row['f2_name']),strtolower($r)) !== false):
                    $name = $row['f2_name'];
                    $z = 7;
                elseif($row['f2_printed_name'] !== null AND strpos(strtolower($row['f2_printed_name']),strtolower($r)) !== false):
                    $name = $row['f2_printed_name'];
                    $z = 8;
                elseif($row['f2_flavor_name'] !== null AND strpos(strtolower($row['f2_flavor_name']),strtolower($r)) !== false):
                    $name = $row['f2_flavor_name'];
                    $z = 9;
                else:
                    $name = $row['name'];
                    $z = 10;
                endif;
                $id = $row['id'];
                $ajaxname = $db->escape($name);
                $setcode = $row['setcode'];
                $sqlsetcode = $db->escape($row['setcode']);
                $displaysetcode = strtoupper($setcode);
                $getajax = $db->select_one('id,number_import','cards_scry',"WHERE id LIKE '$id'");
                if ($getajax === false):
                    trigger_error("[ERROR]".basename(__FILE__)." ".__LINE__.": SQL failure: " . $db->error, E_USER_ERROR);
                else:
                    $ajaxid = $getajax['id'];
                    $ajaxnumber = $getajax['number_import'];
                    $b_name = '<strong>'.$r.'</strong>';
                    $final_name = str_ireplace($r, $b_name, $name);
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