<?php
/* Version:     4.0
    Date:       01/02/22
    Name:       admin/cards.php
    Purpose:    Card administrative tasks
    Notes:      This page uses a combination of Mysqli prepared statements and straight OOP mysqli connectivity
                using $db (Mysqli_Manager).
        
    1.0
                Initial version - no function yet
 *  2.0         
 *              Functions added for add, edit, copy cards; run legality check; add pre-release promos
 *  3.0
 *              Move from writelog to Message class
 *  4.0     
 *              Much simpler form, all data from Scryfall, so no editing here - just delete or delete image
*/

session_start();
require ('../includes/ini.php');                //Initialise and load ini file
require ('../includes/error_handling.php');
require ('../includes/functions_new.php');      //Includes basic functions for non-secure pages
require ('adminfunctions.php');
require ('../includes/secpagesetup.php');       //Setup page variables
forcechgpwd();                                  //Check if user is disabled or needs to change password

//Check if user is logged in, if not redirect to login.php
$obj = new Message;$obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Admin page called by user $username ($useremail)",$logfile);
//Admin user?
$admin = check_admin_control($adminip);
$obj = new Message;$obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Admin check result: ".$admin,$logfile);
if ($admin !== 1):
    require('reject.php');
endif;
if ((isset($_GET['delete'])) AND ( $_GET['delete'] == 'DELETE')):
    if (isset($_GET['id'])):
        $id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_STRING);
    endif;
    $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Delete card $id called by $useremail from {$_SERVER['REMOTE_ADDR']}",$logfile);
    $sql = "DELETE FROM cards_scry WHERE id = '$id'";
    $result = $db->query($sql);
    if ($result === false):
        trigger_error("[ERROR] cards.php: Deleting card: Wrong SQL: ($sql) Error: " . $db->error, E_USER_ERROR);
    else:
        $sql = "SELECT id FROM cards_scry WHERE id = '$id'";
        $result = $db->query($sql);
        $rowcount = $result->num_rows;
        if ($result === false):
            trigger_error("[ERROR] cards.php: Deleting card: Wrong SQL: ($sql) Error: " . $db->error, E_USER_ERROR);
        elseif ($rowcount === 0):
            $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Delete card $id successful",$logfile);
            ?>
            <div class="alert-box success" id="setdeletealert2"><span>success: </span>Deleted</div> <?php
        endif;
    endif;
elseif ((isset($_GET['deleteimg'])) AND ( $_GET['deleteimg'] == 'DELETEIMG')):
    if (isset($_GET['id'])):
        $id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_STRING);
    endif;
    $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Delete card image for $id called by $useremail from {$_SERVER['REMOTE_ADDR']}",$logfile);
    $sql = "SELECT id,setcode,layout FROM cards_scry WHERE id = '$id' LIMIT 1";
    $result = $db->query($sql);
    if ($result === false):
        trigger_error("[ERROR] cards.php: Deleting card image: Wrong SQL: ($sql) Error: " . $db->error, E_USER_ERROR);
    else:
        $row = $result->fetch_assoc();
        $imagefunction = getImageNew($row['setcode'],$id,$ImgLocation,$row['layout']);
        if($imagefunction['front'] != 'error'):
            $imagename = substr($imagefunction['front'], strrpos($imagefunction['front'], '/') + 1);
            $imageurl = $ImgLocation.$row['setcode']."/".$imagename;
            if (!unlink($imageurl)): 
                $imagedelete = 'failure'; 
            else:
                $imagedelete = 'success'; 
            endif;
        endif;
        if($imagefunction['back'] != '' AND $imagefunction['back'] != 'error'):
            $imagebackname = substr($imagefunction['back'], strrpos($imagefunction['back'], '/') + 1);
            $imagebackurl = $ImgLocation.$row['setcode']."/".$imagebackname;
            if (!unlink($imagebackurl)): 
                $imagebackdelete = 'failure'; 
            else:
                $imagebackdelete = 'success'; 
            endif;
        endif;
    endif;
endif;
?>

<!DOCTYPE html>
<head>
    <title>MtG collection administration - cards</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="/css/style<?php echo $cssver ?>.css">
    <?php include('../includes/googlefonts.php'); ?>
</head>
<body id="body" class="body">    
   
<?php
include '../includes/overlays.php';
include '../includes/header.php';
require('../includes/menu.php');
?>
    <div id='page'>
        <div class='staticpagecontent'>
            <div> <?php
                if(isset($_GET['cardtoedit'])):
                    $id = filter_input(INPUT_GET, 'cardtoedit', FILTER_SANITIZE_STRING); ?>
                    <h3>Delete cards / images</h3>
                    <?php echo "Card id loaded: $id"; ?>
                        <form id='carddeleteform' action="?" method="GET">
                            <input type='hidden' name='id' value='<?php echo "$id";?>' >
                            <input class='inline_button stdwidthbutton updatebutton' id='deletebutton' name='delete' type="submit" value="DELETE" 
                                   onclick="return confirm('Do you really want to delete this card?');">
                        </form>
                        <form id='cardimgdeleteform' action="?" method="GET">
                            <input type='hidden' name='id' value='<?php echo "$id";?>' >
                            <button class='inline_button stdwidthbutton updatebutton' id='deleteimgbutton' name='deleteimg' type="submit" value="DELETEIMG" 
                                   onclick="return confirm('Do you really want to delete this card image?');">DELETE IMAGE</button>
                        </form>  <?php
                elseif(isset($imageurl) AND $imageurl !== '' AND $imagedelete === 'success'):
                    echo "<h3>Image delete processed</h3>";
                    echo "$imageurl deleted";
                    if(isset($imagebackdelete)):
                        echo "$imagebackurl deleted";
                    endif;
                    echo "<meta http-equiv='refresh' content='2;url=cards.php'>";
                elseif(isset($imageurl) AND $imageurl !== '' AND $imagedelete === 'failure'):
                    echo "<h3>Image delete NOT processed</h3>";
                    echo "$imagedelete $imageurl NOT deleted";
                else:
                    echo "<h3>Load this page from a card details page to delete a card or its image</h3>";
                endif; ?>
            </div>
        </div>
    </div>
<?php
require('../includes/footer.php'); ?>
</body>
</html>