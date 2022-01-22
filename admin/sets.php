<?php
/* Version:     5.0
    Date:       11/01/20
    Name:       admin/sets.php
    Purpose:    Set administrative tasks
    Notes:      This page uses a combination of Mysqli prepared statements and straight OOP mysqli connectivity
                using $db (Mysqli_Manager, set in ini.php).
        
    1.0
                Initial version
    2.0         
                Mysqli_Manager
 *  3.0         
 *              Rename to sets.php, stop filters converting apostrophes to & #39 ; etc.
 *  4.0
 *              Added ability to edit settype
 *  5.0     
 *              Use Message Class instead of writelog
 *              Remove unnecessary global references
 *              Tidy up some SQL calls
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

// Basic page purpose triggers
if (isset($_GET['setformanage'])):
    $setformanage = strtolower(filter_input(INPUT_GET, 'setformanage', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES));
endif;
if (isset($_GET['editset'])):
    $editset = 1;
elseif (isset($_GET['setimages'])):
    $setimages = 1;
elseif (isset($_GET['newset'])):
    $newset = 1;
elseif (isset($_GET['linkcards'])):
    $linkcards = 1;
elseif (isset($_POST['ncards'])):
    $ncards = 1;
elseif (isset($_POST['import'])):
    $import = $_POST['import'];
elseif (isset($_POST['routine'])):
    $routine = 1;
endif;

//Page called with Set edit or delete variables
if ((isset($_POST['update'])) AND ( $_POST['update'] == 'UPDATE')):
    $update = 1;
    // Retrieve all the posted variables
    if (isset($_POST['setcodeid'])):
        $setcodeid = filter_input(INPUT_POST, 'setcodeid', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    endif;
    if (isset($_POST['magiccardsid'])):
        $magiccardsid = filter_input(INPUT_POST, 'magiccardsid', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    endif;
    if (isset($_POST['fullsetname'])):
        $fullsetname = filter_input(INPUT_POST, 'fullsetname', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    endif;
    if (isset($_POST['tcgfullname'])):
        $tcgfullname = filter_input(INPUT_POST, 'tcgfullname', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    endif;
    if (isset($_POST['block'])):
        $block = filter_input(INPUT_POST, 'block', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    endif;
    if (isset($_POST['websearch'])):
        $websearch = filter_input(INPUT_POST, 'websearch', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    endif;
    if (isset($_POST['description'])):
        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    endif;
    if (isset($_POST['common'])):
        $common = filter_input(INPUT_POST, 'common', FILTER_VALIDATE_INT);
    endif;
    if (isset($_POST['uncommon'])):
        $uncommon = filter_input(INPUT_POST, 'uncommon', FILTER_VALIDATE_INT);
    endif;
    if (isset($_POST['rare'])):
        $rare = filter_input(INPUT_POST, 'rare', FILTER_VALIDATE_INT);
    endif;
    if (isset($_POST['mythicrare'])):
        $mythicrare = filter_input(INPUT_POST, 'mythicrare', FILTER_VALIDATE_INT);
    endif;
    if (isset($_POST['basicland'])):
        $basicland = filter_input(INPUT_POST, 'basicland', FILTER_VALIDATE_INT);
    endif;
    if (isset($_POST['total'])):
        $total = filter_input(INPUT_POST, 'total', FILTER_VALIDATE_INT);
    endif;
    if (isset($_POST['releasedat'])):
        $releasedat = filter_input(INPUT_POST, 'releasedat', FILTER_SANITIZE_STRING);
    endif;
    if (isset($_POST['settype'])):
        $settype = filter_input(INPUT_POST, 'settype', FILTER_SANITIZE_STRING);
    endif;
    if (isset($_POST['stdlegal'])):
        $stdlegal = filter_input(INPUT_POST, 'stdlegal', FILTER_VALIDATE_INT);
    endif;
    if (isset($_POST['mdnlegal'])):
        $mdnlegal = filter_input(INPUT_POST, 'mdnlegal', FILTER_VALIDATE_INT);
    endif;
    $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Update set called by $useremail from {$_SERVER['REMOTE_ADDR']}",$logfile);
    
    //Get existing values from the Database
    $obj = new Message;$obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Getting existing set details for $setcodeid",$logfile);
    $result = $db->select('*','sets',"WHERE setcodeid = '$setcodeid'");
    if ($result === false):
        trigger_error("[ERROR] sets.php: Retrieving existing set details: Error: " . $db->error, E_USER_ERROR);
    else:
        if ($result->num_rows != 0):
            $row = $result->fetch_assoc();
            $oldsetcodeid = $row['setcodeid'];
            $oldmagiccardsid = $row['magiccardsid'];
            $oldfullsetname = $row['fullsetname'];
            $oldtcgfullname = $row['tcgfullname'];
            $oldblock = $row['block'];
            $oldwebsearch = $row['websearch'];
            $olddescription = $row['description'];
            $oldcommon = $row['common'];
            $olduncommon = $row['uncommon'];
            $oldrare = $row['rare'];
            $oldmythicrare = $row['mythicrare'];
            $oldbasicland = $row['basicland'];
            $oldtotal = $row['total'];
            $oldreleasedat = $row['releasedat'];
            $oldsettype = $row['settype'];
            $oldstdlegal = $row['stdlegal'];
            $oldmdnlegal = $row['mdnlegal'];
            $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Existing set parameters:",$logfile);
            $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"setcodeid: $oldsetcodeid; magiccardsid: $oldmagiccardsid;"
                    . " fullsetname: $oldfullsetname; tcgfullname: $oldtcgfullname;"
                    . " block: $oldblock; websearch: $oldwebsearch; description: $olddescription; common: $oldcommon;"
                    . " uncommon: $olduncommon; rare: $oldrare; mythicrare: $oldmythicrare;"
                    . " basicland: $oldbasicland; total: $oldtotal; releasedat: $oldreleasedat;"
                    . " settype: $oldsettype; stdlegal: $oldstdlegal; mdnlegal: $oldmdnlegal",$logfile);
        else:
            $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"No such set - adding new one!",$logfile);
        endif;
        $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Update set parameters:",$logfile);
        $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"setcodeid: $setcodeid; magiccardsid: $magiccardsid;"
                    . " fullsetname: $fullsetname; tcgfullname: $tcgfullname;"
                    . " block: $block; websearch: $websearch; description: $description; common: $common;"
                    . " uncommon: $uncommon; rare: $rare; mythicrare: $mythicrare;"
                    . " basicland: $basicland; total: $total; releasedat: $releasedat;"
                    . " settype: $settype; stdlegal: $stdlegal; mdnlegal: $mdnlegal",$logfile);
        $stmt = $db->prepare("INSERT INTO sets (setcodeid,magiccardsid,fullsetname,
                    tcgfullname,block,websearch,description,common,uncommon,rare,
                    mythicrare,basicland,total,releasedat,settype,stdlegal,mdnlegal)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE 
                    setcodeid=VALUES(setcodeid),
                    magiccardsid=VALUES(magiccardsid),
                    fullsetname=VALUES(fullsetname),
                    tcgfullname=VALUES(tcgfullname),
                    block=VALUES(block),
                    websearch=VALUES(websearch),
                    description=VALUES(description),
                    common=VALUES(common),
                    uncommon=VALUES(uncommon),
                    rare=VALUES(rare),
                    mythicrare=VALUES(mythicrare),
                    basicland=VALUES(basicland),
                    total=VALUES(total),
                    releasedat=VALUES(releasedat),
                    settype=VALUES(settype),
                    stdlegal=VALUES(stdlegal),
                    mdnlegal=VALUES(mdnlegal)");
        if ($stmt === false):
            trigger_error('[ERROR] sets.php: Preparing SQL: ' . $db->error, E_USER_ERROR);
        endif;
        $stmt->bind_param("sssssssiiiiiissii", $setcodeid, $magiccardsid, $fullsetname, $tcgfullname, $block, $websearch, $description, $common, $uncommon, $rare, $mythicrare, $basicland, $total, $releasedat, $settype, $stdlegal, $mdnlegal);
        if ($stmt === false):
            trigger_error('[ERROR] sets.php: Binding parameters: ' . $db->error, E_USER_ERROR);
        endif;
        $result = $stmt->execute();
        if ($result === false):
            trigger_error("[ERROR] sets.php: Writing new set details: " . $db->error, E_USER_ERROR);
        else:
            $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Update set - no error returned",$logfile);
            $setformanage = strtolower($setcodeid); //Make the set being edited as the active one
        endif;
        $stmt->close();
    endif;
elseif ((isset($_POST['delete'])) AND ( $_POST['delete'] == 'DELETE')):
    $delete = 1;
    if (isset($_POST['setcodeid'])):
        $setcodeid = filter_input(INPUT_POST, 'setcodeid', FILTER_SANITIZE_STRING);
    endif;
    $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Delete set $setcodeid called by $useremail from {$_SERVER['REMOTE_ADDR']}",$logfile);
    $sql = "DELETE FROM sets WHERE setcodeid = '$setcodeid'";
    $result = $db->query($sql);
    if ($result === false):
        trigger_error("[ERROR] sets.php: Deleting set: Wrong SQL: ($sql) Error: " . $db->error, E_USER_ERROR);
    else:
        $sql = "SELECT setcodeid FROM sets WHERE setcodeid = '$setcodeid'";
        $result = $db->query($sql);
        $rowcount = $result->num_rows;
        if ($result === false):
            trigger_error("[ERROR] sets.php: Deleting set: Wrong SQL: ($sql) Error: " . $db->error, E_USER_ERROR);
        elseif ($rowcount === 0):
            $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Delete set $setcodeid successful",$logfile);
            ?>
            <div class="alert-box success" id="setdeletealert2"><span>success: </span>Deleted</div> <?php
        endif;
    endif;
elseif (isset($import)):
    $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Import called by $useremail",$logfile);
    //See if Ncards needs to be deleted
    $result = $db->select('Nid','Ncards');
    if($result != FALSE): //Ncards to be deleted
        $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Deleting Ncards table",$logfile);
        $stmt = $db->prepare("DROP TABLE Ncards");
        if ($stmt === false):
            trigger_error('[ERROR] sets.php: Preparing SQL for dropping old Ncards table: ' . $db->error, E_USER_ERROR);
        endif;
        $result = $stmt->execute();
        if ($result === false):
            $obj = new Message;$obj->MessageTxt('[ERROR]',$_SERVER['PHP_SELF'],"Ncards drop failed",$logfile);
            $validimport = 1;
            $stmt->close();
        else:
            $obj = new Message;$obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Dropping old Ncards table - no error returned",$logfile);
            $validimport = 2;
            $stmt->close();
        endif;
    else:
        $validimport = 3;
    endif;        
    if(isset($validimport) AND ($validimport == 2 OR $validimport == 3)):
        $obj = new Message;$obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Copying NcardsTemplate",$logfile);
        $stmt = $db->prepare("CREATE TABLE Ncards LIKE NcardsTemplate");
        if ($stmt === false):
            trigger_error('[ERROR] sets.php: Preparing SQL for writing Ncards table: ' . $db->error, E_USER_ERROR);
        endif;
        $result = $stmt->execute();
        if ($result === false):
            $obj = new Message;$obj->MessageTxt('[ERROR]',$_SERVER['PHP_SELF'],"Ncards new table failed",$logfile);
            $validimport = 4;
            $stmt->close();
        else:
            $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Writing Ncards table - no error returned",$logfile);
            $validimport = 5;
            $stmt->close();
        endif;    
    endif;        
    if(isset($validimport) AND $validimport == 5):        
        $obj = new Message;$obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Importing to Ncards",$logfile);
        if(substr($import,0,25) == 'INSERT INTO Ncards VALUES'):
            $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Import call for: $import",$logfile);
            $stmt = $db->prepare($import);
            if ($stmt === false):
                trigger_error('[ERROR] sets.php: Preparing SQL for import: ' . $db->error, E_USER_ERROR);
            endif;
            $result = $stmt->execute();
            if ($result === false):
                $obj = new Message;$obj->MessageTxt('[ERROR]',$_SERVER['PHP_SELF'],"Import failed",$logfile);
                $validimport = 6;
                $stmt->close();
            else:
                $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Import succeeded",$logfile);
                $validimport = 7;
                $stmt->close();
            endif;
        else:
            $obj = new Message;$obj->MessageTxt('[ERROR]',$_SERVER['PHP_SELF'],"Check import SQL - incorrect first 25 chars. '".substr($import,0,25)."'",$logfile);
            $validimport = 8;
        endif;
    endif;
    if(isset($validimport) AND $validimport == 7):        
        $obj = new Message;$obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Running NEWSET routine",$logfile);
        $stmt = $db->prepare("CALL `newset`()");
        if ($stmt === false):
            trigger_error('[ERROR] sets.php: Preparing SQL for NEWSETS routine: ' . $db->error, E_USER_ERROR);
        endif;
        $result = $stmt->execute();
        if ($result === false):
            $obj = new Message;$obj->MessageTxt('[ERROR]',$_SERVER['PHP_SELF'],"Running NEWSET routine failed",$logfile);
            $validimport = 9;
            $stmt->close();
        else:
            $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Running NEWSET routine succeeded",$logfile);
            $validimport = 10;
            $stmt->close();
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
    <script src="../js/jquery.js"></script>
    <script>
        $(document).ready(function () {
            $(".success").click(function () {
                $(".success").hide();
            });
        });
    </script>
    <script type="text/javascript">
        jQuery(function ($) {
            $('#seteditform').submit(function () {
                if (($('#setcodeid').val() === '') || ($('#fullsetname').val() === '')) {
                    alert("You need to complete the Setcode ID and Full Setname...");
                    return false;
                }
            });
        });
    </script>
    <!-- Following script is to ensure that numbers entered are valid-->
    <script type="text/javascript">
        function isInteger(x) {
            if (x < 0)
            {
                return false;
            } else
            {
                return x % 1 === 0;
            }
        }
        $(function () {
            $(".carddetailqtyinput").change(function () {
                var ths = this;
                var myqty = $(ths).val();
                if (myqty == '')
                {
                    alert("Enter a number");
                    $(ths).focus();
                } else if (!isInteger(myqty))
                {
                    alert("Enter a valid quantity");
                    $(ths).focus();
                }
            });
        });
        $(function () {
            $(".legalcheck").change(function () {
                var ths = this;
                var myqty = $(ths).val();
                if (myqty > 1)
                {
                    alert("Legal can only be 0 ('No') or 1 ('Yes')");
                    $(ths).focus();
                } else if (!isInteger(myqty))
                {
                    alert("Legal can only be 0 ('No') or 1 ('Yes')");
                    $(ths).focus();
                }
            });
        });
    </script>
</head>
<body id="body" class="body">

<?php
include '../includes/overlays.php';
include '../includes/header.php';
require('../includes/menu.php');
?>
    <div id='page'>
        <div class='staticpagecontent'>
            <div>
                <h3>Add and edit sets</h3>
                <?php
                $sql = "select fullsetname,setcodeid from sets ORDER BY fullsetname ASC";
                $result = $db->query($sql);
                if ($result === false):
                    trigger_error("[ERROR] sets.php: Set image retrieval: Wrong SQL ($sql) Error: " . $db->error, E_USER_ERROR);
                else:
                    ?>
                    <table>
                        <tr>
                            <td>
                                <form>
                                    <select size="1" name="setformanage" onchange='this.form.submit()'>
                                        <?php
                                        if (!isset($setformanage)):
                                            echo "<option selected disabled>Choose set from dropdown list</option>";
                                        endif;
                                        while ($row = $result->fetch_assoc()):
                                            if (isset($setformanage) AND $setformanage == strtolower($row['setcodeid'])):
                                                echo "<option selected='selected' value='{$row['setcodeid']}'>{$row['fullsetname']}</option>";
                                            else:
                                                echo "<option value='{$row['setcodeid']}'>{$row['fullsetname']}</option>";
                                            endif;
                                        endwhile;
                                        ?>
                                    </select>
                                    <input type="hidden"name="editset" value="EDIT SET" />
                                </form>
                            </td>
                            <td>
                                <input type='button' class='inline_button stdwidthbutton updatebutton' value="ADD SET" name='newset' onclick='window.location = "/admin/sets.php?newset=y";'/>
                                <?php 
                                if (isset($setformanage)): ?>
                                    <input type='button' class='inline_button stdwidthbutton updatebutton' value="LINK CARDS" name='linkcards' onclick='window.location = "/admin/sets.php?linkcards=y&setformanage=<?php echo $setformanage; ?>";'/>
                                    <input type='button' class='inline_button stdwidthbutton updatebutton' value="GET IMAGES" name='setimages' onclick='window.location = "/admin/sets.php?setimages=y&setformanage=<?php echo $setformanage; ?>";'/>
                                <?php 
                                endif; ?>
                            </td>
                        </tr>
                    </table>
                <?php
                endif;
                if (isset($setimages) AND $setimages == 1):
                    $obj = new Message;$obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Set image retrieval: Getting images for $setformanage",$logfile);
                    echo "Getting images for " . $setformanage . "<br>";
                    $sql = "SELECT cards.id,number,name,backid,manacost,meld,setcode,jsondata FROM cards "
                            . "LEFT JOIN sets ON cards.setcode = sets.setcodeid "
                            . "LEFT JOIN scryfalljson ON cards.id = scryfalljson.id "
                            . "WHERE sets.setcodeid = '$setformanage' "
                            . "ORDER BY number ASC";
                    $obj = new Message;$obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Set image retrieval: Running $sql",$logfile);
                    $result = $db->query($sql);
                    if ($result === false):
                        trigger_error("[ERROR] sets.php: Set image retrieval: Wrong SQL ($sql) Error: " . $db->error, E_USER_ERROR);
                    else:
                        while ($row = $result->fetch_array(MYSQLI_BOTH)):
                            echo "<br>Getting image for card {$row['setcode']} {$row['name']} {$row['number']}<br>";
                            $setcode = $db->escape(strtolower($row['setcode']),'str');
                            if(!empty($row['jsondata'])):
                                $scry_json = json_decode($row['jsondata'],true);
                                if(isset($scry_json['image_uris']['normal'])):
                                    $scryfallimg = $scry_json['image_uris']['normal'];
                                else:
                                    $scryfallimg = null;
                                endif;
                            endif;
                            $flipcard = fliptype($row['backid'], $row['manacost'], $row['meld']);
                            $imgname = getimgname($setcode, $row['number'], $row[0], $flipcard);
                            $image = getImageNew($setcode, $imgname, $row[0], $ImgLocation, $flipcard, $row['number'],$row['name'],$scryfallimg);
                            echo "<img src='/$image'>";
                        endwhile;
                    endif;
                elseif ((isset($editset) AND $editset == 1) OR ( isset($update) AND $update == 1)):
                    $obj = new Message;$obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Set edit: Getting set details for $setformanage",$logfile);
                    $sql = "SELECT * FROM sets WHERE setcodeid = '$setformanage' LIMIT 1";
                    $result = $db->query($sql);
                    if ($result === false):
                        trigger_error("[ERROR] sets.php: Set details retrieval: Wrong SQL ($sql) Error: " . $db->error, E_USER_ERROR);
                    else:
                        $row = $result->fetch_assoc();
                        ?>
                        <form id='seteditform' action="?" method="POST">
                            <table>
                                <tr class="admintable">
                                    <td>
                                        Set code
                                    </td>
                                    <td>
                                        Magiccards
                                    </td>
                                    <td>
                                        Set name
                                    </td>
                                    <td>
                                        Set name (TCGPlayer)
                                    </td>
                                    <td>
                                        Block
                                    </td>
                                    <td>
                                        Websearch
                                    </td>
                                    <td colspan="4">
                                        Description
                                    </td>
                                </tr>
                                <tr>
                                    <td>
        <?php
        echo "<textarea class='textinput' id='setcodeid' name='setcodeid' rows='4' cols='6'>{$row['setcodeid']}</textarea>";
        ?>
                                    </td>
                                    <td>
                                        <?php
                                        echo "<textarea class='textinput' name='magiccardsid' rows='4' cols='6'>{$row['magiccardsid']}</textarea>";
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        echo "<textarea class='textinput' id='fullsetname' name='fullsetname' rows='4' cols='15'>{$row['fullsetname']}</textarea>";
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        echo "<textarea class='textinput' name='tcgfullname' rows='4' cols='15'>{$row['tcgfullname']}</textarea>";
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        echo "<textarea class='textinput' name='block' rows='4' cols='15'>{$row['block']}</textarea>";
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        echo "<textarea class='textinput' name='websearch' rows='4' cols='15'>{$row['websearch']}</textarea>";
                                        ?>
                                    </td>
                                    <td colspan="4">
                                        <?php
                                        echo "<textarea class='textinput' name='description' rows='4' cols='80'>{$row['description']}</textarea>";
                                        ?>
                                    </td>
                                </tr>
                                <tr class="admintable">
                                    <td>
                                        Common
                                    </td>
                                    <td>
                                        Uncommon
                                    </td>
                                    <td>
                                        Rare
                                    </td>
                                    <td>
                                        Mythic
                                    </td>
                                    <td>
                                        Basic land
                                    </td>
                                    <td>
                                        Total
                                    </td>
                                    <td>
                                        Release date
                                    </td>
                                    <td>
                                        Set Type
                                    </td>
                                    <td>
                                        Standard legal
                                    </td>
                                    <td>
                                        Modern legal
                                    </td>
                                </tr>
                                <tr>
                                    <td>
        <?php
        echo "<input class='carddetailqtyinput textinput' type='number' name='common' min='0' value={$row['common']}>";
        ?>
                                    </td>
                                    <td>
                                        <?php
                                        echo "<input class='carddetailqtyinput textinput' type='number' name='uncommon' min='0' value='{$row['uncommon']}'>";
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        echo "<input class='carddetailqtyinput textinput' type='number' name='rare' min='0' value='{$row['rare']}'>";
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        echo "<input class='carddetailqtyinput textinput' type='number' name='mythicrare' min='0' value='{$row['mythicrare']}'>";
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        echo "<input class='carddetailqtyinput textinput' type='number' name='basicland' min='0' value='{$row['basicland']}'>";
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        echo "<input class='carddetailqtyinput textinput' type='number' name='total' min='0' value='{$row['total']}'>";
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        echo "<input class='textinput' type='date' name='releasedat' min='0' value='{$row['releasedat']}'>";
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        echo "<textarea class='textinput' name='settype' cols='15'>{$row['settype']}</textarea>";
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        echo "<input class='textinput legalcheck' type='number' name='stdlegal' min='0' max='1' value='{$row['stdlegal']}'>";
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        echo "<input class='textinput legalcheck' type='number' name='mdnlegal' min='0' max='1' value='{$row['mdnlegal']}'>";
                                        ?>
                                    </td>
                                </tr>
                            </table>
                            <input class='inline_button stdwidthbutton updatebutton' name='update' type="submit" value="UPDATE">
                            <input class='inline_button stdwidthbutton updatebutton' id='deletebutton' name='delete' type="submit" value="DELETE" 
                                   onclick="return confirm('Do you really want to delete this set? \n\
        Note that deleting the set using this form does not \n\
        delete cards with this set code, only the set description.');">
                        </form>
                        <b>Notes:</b><br>
                        1. Deleting the set using the above form does not delete cards with this set code, only the set description.<br>
                        2. If the "Set Type" field contains "prerelease", it will enable a full set of Mythic and Rare cards to be duplicated into the PTC "Prerelease Events" set,<br>
                        by using the function on the "CARDS" page.
                           <?php
                   endif;
                elseif (isset($newset) AND $newset == 1):
                   ?>
                    <form id='seteditform' action="?" method="POST">
                        <table>
                            <tr class="admintable">
                                <td>
                                    Set code
                                </td>
                                <td>
                                    Magiccards
                                </td>
                                <td>
                                    Set name
                                </td>
                                <td>
                                    Set name (TCGPlayer)
                                </td>
                                <td>
                                    Block
                                </td>
                                <td>
                                    Websearch
                                </td>
                                <td colspan="4">
                                    Description
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <?php
                                    echo "<textarea class='textinput' id='setcodeid' name='setcodeid' rows='4' cols='6'></textarea>";
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    echo "<textarea class='textinput' name='magiccardsid' rows='4' cols='6'></textarea>";
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    echo "<textarea class='textinput' id='fullsetname' name='fullsetname' rows='4' cols='15'></textarea>";
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    echo "<textarea class='textinput' name='tcgfullname' rows='4' cols='15'></textarea>";
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    echo "<textarea class='textinput' name='block' rows='4' cols='15'></textarea>";
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    echo "<textarea class='textinput' name='websearch' rows='4' cols='15'></textarea>";
                                    ?>
                                </td>
                                <td colspan="4">
                                    <?php
                                    echo "<textarea class='textinput' name='description' rows='4' cols='80'></textarea>";
                                    ?>
                                </td>
                            </tr>
                            <tr class="admintable">
                                <td>
                                    Common
                                </td>
                                <td>
                                    Uncommon
                                </td>
                                <td>
                                    Rare
                                </td>
                                <td>
                                    Mythic
                                </td>
                                <td>
                                    Basic land
                                </td>
                                <td>
                                    Total
                                </td>
                                <td>
                                    Release date
                                </td>
                                <td>
                                    Set Type
                                </td>
                                <td>
                                    Standard legal
                                </td>
                                <td>
                                    Modern legal
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <?php
                                    echo "<input class='carddetailqtyinput textinput' type='number' name='common' min='0' value='0'>";
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    echo "<input class='carddetailqtyinput textinput' type='number' name='uncommon' min='0' value='0'>";
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    echo "<input class='carddetailqtyinput textinput' type='number' name='rare' min='0' value='0'>";
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    echo "<input class='carddetailqtyinput textinput' type='number' name='mythicrare' min='0' value='0'>";
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    echo "<input class='carddetailqtyinput textinput' type='number' name='basicland' min='0' value='0'>";
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    echo "<input class='carddetailqtyinput textinput' type='number' name='total' min='0' value='0'>";
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    echo "<input class='textinput' type='date' name='releasedat' min='0' value=''>";
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    echo "<textarea class='textinput' name='settype' cols='15'></textarea>";
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    echo "<input class='textinput legalcheck' type='number' name='stdlegal' min='0' max='1' value='0'>";
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    echo "<input class='textinput legalcheck' type='number' name='mdnlegal' min='0' max='1' value='0'>";
                                    ?>
                                </td>
                            </tr>
                        </table>
                        <input class='inline_button stdwidthbutton updatebutton' name='update' type="submit" value="UPDATE">
                        <input class='inline_button stdwidthbutton updatebutton' name='cancel' type="button" value="CANCEL" onclick="window.location = '/admin/sets.php';">
                    </form>
    <?php
elseif (isset($linkcards) AND $linkcards == 1):
    $sql1 = "SELECT fullsetname FROM sets WHERE setcodeid = '$setformanage' LIMIT 1";
    $result1 = $db->query($sql1);
    if ($result1 === false):
        trigger_error("[ERROR] sets.php: Link cards set name retrieval: Wrong SQL ($sql1) Error: " . $db->error, E_USER_ERROR);
    else:
        $row1 = $result1->fetch_assoc();
        $linksetname = $db->escape($row1['fullsetname']);
        $obj = new Message;$obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Link set - $linksetname, setcode $setformanage, called by $useremail from {$_SERVER['REMOTE_ADDR']}",$logfile);
        $sql2 = "SELECT name FROM cards WHERE (setname <> '$linksetname' OR setname IS NULL) AND setcode='$setformanage'";
        $obj = new Message;$obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Link set - $sql2",$logfile);
        $result2 = $db->query($sql2);
        $rowcount2 = $result2->num_rows;
        if ($result2 === false):
            trigger_error("[ERROR] sets.php: Link set - check: Wrong SQL ($sql2) Error: " . $db->error, E_USER_ERROR);
            ?>
                            <?php
                        else:
                            $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Link set - $rowcount2 cards to be linked for $linksetname",$logfile);
                        endif;
                        if ($rowcount2 !== 0):
                            $sql3 = "UPDATE cards SET setname='$linksetname' WHERE setcode='$setformanage'";
                            $result3 = $db->query($sql3);
                            if ($result3 === false):
                                trigger_error("[ERROR] sets.php: Link set - cards execution: Wrong SQL ($sql3) Error: " . $db->error, E_USER_ERROR);
                            else:
                                $sql4 = "SELECT name FROM cards WHERE setname <> '$linksetname' AND setcode='$setformanage'";
                                $result4 = $db->query($sql4);
                                $rowcount4 = $result4->num_rows;
                                if ($result4 === false):
                                    trigger_error("[ERROR] sets.php: Link set - execution: Wrong SQL ($sql4) Error: " . $db->error, E_USER_ERROR);
                                    ?>
                                    <?php
                                elseif ($rowcount4 === 0):
                                    $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Link set - executed successfully for $setformanage",$logfile);
                                    echo '<div class="alert-box success" id="setlinkalert2"><span>success: </span>' . $rowcount2 . ' card(s) linked</div>';
                                endif;
                            endif;
                        else:
                            $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Link set - skipping write",$logfile);
                            ?>
                            <div class="alert-box notice" id="setlinkalert2"><span>notice: </span>No cards to be linked</div> 
                        <?php
                        endif;
                    endif;
                endif;
                ?>

                <h3>How to add new set</h3>
                <?php
                if ((isset($validimport) AND $validimport == 1)):
                    echo "<div class='alert-box error'><span>error: </span>NCards drop failed</div>"; 
                elseif ((isset($validimport) AND $validimport == 2)):
                    echo "<div class='alert-box notice'><span>notice: </span>NCards drop completed</div>";
                elseif ((isset($validimport) AND $validimport == 3)):
                    echo "<div class='alert-box notice'><span>notice: </span>NCards drop not needed</div>"; 
                elseif ((isset($validimport) AND $validimport == 4)):
                    echo "<div class='alert-box error'><span>error: </span>NcardsTemplate copy failed</div>"; 
                elseif ((isset($validimport) AND $validimport == 5)):
                    echo "<div class='alert-box notice'><span>notice: </span>NcardsTemplate copied</div>"; 
                elseif ((isset($validimport) AND $validimport == 6)):
                    echo "<div class='alert-box error'><span>error: </span>Import failed (6)</div>"; 
                elseif ((isset($validimport) AND $validimport == 7)):
                    echo "<div class='alert-box notice'><span>notice: </span>Import succeeded</div>"; 
                elseif ((isset($validimport) AND $validimport == 8)):
                    echo "<div class='alert-box error'><span>error: </span>Import failed (8)</div>"; 
                elseif ((isset($validimport) AND $validimport == 9)):
                    echo "<div class='alert-box error'><span>error: </span>NEWSET failed</div>"; 
                elseif ((isset($validimport) AND $validimport == 10)):
                    echo "<div class='alert-box success'><span>success: </span>Import succeeded</div>"; 
                endif;
                ?>
                <h4>Import new cards to database</h4>
                <ol>
                    <li>Export sql from Gatherer Extractor</li>
                    <li>Copy ONLY the "INSERT INTO Ncards VALUES" section of the exported sql into the following form field, then press import</li>
                    <li><form id='import' action="?" method="POST">
                            <textarea class='textinput' name='import' rows='4' cols='100'></textarea><br>
                            <input class='inline_button stdwidthbutton updatebutton' type="submit" value="IMPORT">
                        </form></li>
                </ol>
                <h4>Complete Set Management for new set</h4>
                <ol>
                    <li>Add new Set using "Add and edit sets" above (ensure Block is correct for search page entry)</li>
                    <li>If the set is a standard set with pre-release promos, set the SetType as "prerelease" - this will allow promos to be generated on the Cards admin page</li>
                    <li>Link new set to cards using "Add and edit sets" above</li>
                </ol>
                <h4>In the database</h4>
                <ol>
                    <li>If it's a single type set (i.e. no foils) add to setsPromo table</li>
                    <li>If the set has flip cards, edit the cards table to add 'backid' for each fip card, and 
                        map the import in importlookup table where reverse side maps to casting side</li>
                    <li>For meld cards, ensure backid of components points to meld; backid of meld points to components (xxxxxx/xxxxxx) and meld column is completed 
                        (with 'main' for card that shares card number with the meld, 'add' for the extra, and 'combo' for the meld.</li>
                    <li>If it's a new block, add to blockSequence table</li>
                </ol>
                After adding any cards, and after checking Standard set, etc., run <a href='/admin/legality.php'>legality check</a> to reset legalities. 
                <br>
                <h3>Block management</h3>
                - Edit blockSequence table from site<br>
                
            </div>
        </div>
    </div>

<?php require('../includes/footer.php'); ?>
</body>
</html>