<?php
/* Version:     3.0
    Date:       11/01/20
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
if (isset($_GET['cardtoedit'])): //ID of card to be edited
    $cardtoedit = filter_input(INPUT_GET, 'cardtoedit', FILTER_SANITIZE_STRING);
    if (!isset($_GET['editcard'])): //cardtoedit submitted with enter, not the button
    $editcard = 1;
endif;
endif;
if (isset($_GET['editcard'])): //Edit Card button has been submitted with a card ID
    $editcard = 1;
endif;
if (isset($_GET['newcard'])): //Show new card form
    $newcard = 1;
endif;
if (isset($_POST['addcard'])): //call for a new card
    $addcard = 1;
elseif ((isset($_POST['update'])) AND ( $_POST['update'] == 'UPDATE')): //Call for update
    $update = 1;
else:
    $update = null;
    $addcard = null;
endif;
if (isset($_GET['setforpromos'])): //call for a new card
    $setforpromos = filter_input(INPUT_GET, 'setforpromos', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
endif;
if((isset($update)) AND ( $update == 1) OR (isset($addcard)) AND ( $addcard == 1)):
    // Retrieve all the posted variables
    if (isset($_POST['id'])):
        $id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_STRING);
    endif;
    if (isset($_POST['name'])):
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    endif;
    if (isset($_POST['setname'])):
        $setname = filter_input(INPUT_POST, 'setname', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    endif;
    if (isset($_POST['setcode'])):
        $setcode = filter_input(INPUT_POST, 'setcode', FILTER_SANITIZE_STRING);
    endif;
    if (isset($_POST['type'])):
        $type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
    endif;
    if (isset($_POST['power'])):
        $power = filter_input(INPUT_POST, 'power', FILTER_VALIDATE_INT);
    endif;
    if (isset($_POST['toughness'])):
        $toughness = filter_input(INPUT_POST, 'toughness', FILTER_VALIDATE_INT);
    endif;
    if (isset($_POST['loyalty'])):
        $loyalty = filter_input(INPUT_POST, 'loyalty', FILTER_VALIDATE_INT);
    endif;
    if (isset($_POST['manacost'])):
        $manacost = filter_input(INPUT_POST, 'manacost', FILTER_SANITIZE_STRING);
    endif;
    if (isset($_POST['cmc'])):
        $cmc = filter_input(INPUT_POST, 'cmc', FILTER_VALIDATE_INT);
    endif;
    if (isset($_POST['artist'])):
        $artist = filter_input(INPUT_POST, 'artist', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    endif;
    if (isset($_POST['flavor'])):
        $flavor = filter_input(INPUT_POST, 'flavor', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    endif;
    if (isset($_POST['color'])):
        $color = filter_input(INPUT_POST, 'color', FILTER_SANITIZE_STRING);
    endif;
    if (isset($_POST['generatedmana'])):
        $generatedmana = filter_input(INPUT_POST, 'generatedmana', FILTER_SANITIZE_STRING);
    endif;
    if (isset($_POST['number'])):
        $number = filter_input(INPUT_POST, 'number', FILTER_VALIDATE_INT);
    endif;
    if (isset($_POST['rarity'])):
        $rarity = filter_input(INPUT_POST, 'rarity', FILTER_SANITIZE_STRING);
    endif;
    if (isset($_POST['ruling'])):
        $ruling = filter_input(INPUT_POST, 'ruling', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    endif;
    if (isset($_POST['ability'])):
        $ability = filter_input(INPUT_POST, 'ability', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    endif;
    if (isset($_POST['backid'])):
        $backid = filter_input(INPUT_POST, 'backid', FILTER_SANITIZE_STRING);
    endif;
    if (isset($_POST['meld'])):
        $meld = filter_input(INPUT_POST, 'meld', FILTER_SANITIZE_STRING);
    endif;
    if (isset($_POST['comment'])):
        $comment = filter_input(INPUT_POST, 'comment', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    endif;
    if (isset($_POST['tcgsetoverride'])):
        $tcgsetoverride = filter_input(INPUT_POST, 'tcgsetoverride', FILTER_SANITIZE_STRING);
    endif;
    if (isset($_POST['tcgnameoverride'])):
        $tcgnameoverride = filter_input(INPUT_POST, 'tcgnameoverride', FILTER_SANITIZE_STRING);
    endif;
    if (isset($_POST['scrynameoverride'])):
        $scrynameoverride = filter_input(INPUT_POST, 'scrynameoverride', FILTER_SANITIZE_STRING);
    endif;    
    $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Update card called by $useremail from {$_SERVER['REMOTE_ADDR']}",$logfile);
    
    //Get existing values from the Database
    $obj = new Message;$obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Getting existing card details for $id",$logfile);
    $result = $db->select('*','cards_scry',"WHERE id = '$id'");
    if ($result === false):
        trigger_error("[ERROR] cards.php: Retrieving existing card details: Error: " . $db->error, E_USER_ERROR);
    else:
        if ($result->num_rows != 0):
            $row = $result->fetch_assoc();
            $oldid = $row['id'];
            $oldname = $row['name'];
            $oldsetname = $row['set_name'];
            $oldsetcode = $row['setcode'];
            $oldtype = $row['type'];
            $oldpower = $row['power'];
            $oldtoughness = $row['toughness'];
            $oldloyalty = $row['loyalty'];
            $oldmanacost = $row['manacost'];
            $oldcmc = $row['cmc'];
            $oldartist = $row['artist'];
            $oldflavor = $row['flavor'];
            $oldcolor = $row['color'];
            $oldgeneratedmana = $row['generatedmana'];
            $oldnumber = $row['number'];
            $oldnumber_import = $row['number_import'];
            $oldrarity = $row['rarity'];
            $oldability = $row['ability'];
            $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Existing card parameters:",$logfile);
            $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"id: $oldid; name: $oldname;"
                    . " setname: $oldsetname; setcode: $oldsetcode;"
                    . " type: $oldtype; power: $oldpower; toughness: $oldtoughness; loyalty: $oldloyalty;"
                    . " manacost: $oldmanacost; cmc: $oldcmc; artist: $oldartist;"
                    . " flavor: $oldflavor; color: $oldcolor; generatedmana: $oldgeneratedmana;"
                    . " number: $oldnumber; number(import): $oldnumber_import; rarity: $oldrarity;"
                    . " ability: $oldability",$logfile);
        else:
            $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"No such card - adding new one!",$logfile);
        endif;
        $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Update card parameters:",$logfile);
        $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"id: $id; name: $name;"
                    . " setname: $setname; setcode: $setcode;"
                    . " type: $type; power: $power; toughness: $toughness; loyalty: $loyalty;"
                    . " manacost: $manacost; cmc: $cmc; artist: $artist;"
                    . " flavor: $flavor; color: $color; generatedmana: $generatedmana;"
                    . " number: $number; number(import): $number_import; rarity: $rarity;"
                    . " ability: $ability",$logfile);

        $stmt = $db->prepare("INSERT INTO cards (id, name, set_name, setcode,
                    type, power, toughness, loyalty, manacost, cmc, artist, flavor,
                    color, generatedmana, number, number_import, rarity, ability)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE 
                    id=VALUES(id),
                    name=VALUES(name),
                    set_name=VALUES(set_name),
                    setcode=VALUES(setcode),
                    type=VALUES(type),
                    power=VALUES(power),
                    toughness=VALUES(toughness),
                    loyalty=VALUES(loyalty),
                    manacost=VALUES(manacost),
                    cmc=VALUES(cmc),
                    artist=VALUES(artist),
                    flavor=VALUES(flavor),
                    color=VALUES(color),
                    generatedmana=VALUES(generatedmana),
                    number=VALUES(number),
                    number_import=VALUES(number_import),
                    rarity=VALUES(rarity),
                    ability=VALUES(ability)");
        if ($stmt === false):
            trigger_error('[ERROR] cards.php: Preparing SQL: ' . $db->error, E_USER_ERROR);
        endif;
        $stmt->bind_param("ssssssssssssssssss", $id, $name, $setname, $setcode, 
                $type, $power, $toughness, $loyalty, $manacost, $cmc, $artist, $flavor,
                $color, $generatedmana, $number, $number_import, $rarity, $ability);
        if ($stmt === false):
            trigger_error('[ERROR] cards.php: Binding parameters: ' . $db->error, E_USER_ERROR);
        endif;
        $result = $stmt->execute();
        if ($result === false):
            trigger_error("[ERROR] cards.php: Writing new card details: " . $db->error, E_USER_ERROR);
        else:
            $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Update card - no error returned",$logfile);
            $cardtoedit = $id; //Make the card being edited as the active one
        endif;
        $stmt->close();
    endif;
elseif ((isset($_POST['delete'])) AND ( $_POST['delete'] == 'DELETE')):
    $delete = 1;
    if (isset($_POST['id'])):
        $id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_STRING);
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
            $(".alert-box").click(function () {
                $(".alert-box").hide();
            });
        });
    </script>
    <script type="text/javascript">
        jQuery(function ($) {
            $('#cardtoeditform').submit(function () {
                if ($('#cardtoeditvalue').val() === '') {
                    alert("You need to complete the Card ID...");
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
                <h3>Edit, add and copy cards</h3>
                    <?php
                    if(!isset($newcard)):
                        ?>
                        <table>
                            <tr>
                                <td>
                                    <form action="?" id='cardtoeditform' method="GET">
                                        <textarea class='textinput' id='cardtoeditvalue' name='cardtoedit' rows='1' cols='10' onkeydown="if (event.keyCode == 13) { this.form.submit(); return false; }"></textarea>
                                        <input type='submit' class='inline_button stdwidthbutton updatebutton' name="editcard" value="EDIT CARD" />
                                    </form>
                                </td>
                                <td>
                                    <input type='button' class='inline_button stdwidthbutton updatebutton' value="ADD CARD" name='newcard' onclick='window.location = "/admin/cards.php?newcard=y";'/>
                                </td>
                            </tr>
                        </table>
                    <?php
                    endif;
                if ((isset($editcard) AND $editcard == 1) OR ( isset($update) AND $update == 1) OR ( isset($addcard) AND $addcard == 1)):
                    $obj = new Message;$obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Card edit: Getting card details for $cardtoedit",$logfile);
                    $sql = "SELECT * FROM cards_scry WHERE id = '$cardtoedit' LIMIT 1";
                    $result = $db->query($sql);
                    if ($result === false):
                        trigger_error("[ERROR] cards.php: Card details retrieval: Wrong SQL ($sql) Error: " . $db->error, E_USER_ERROR);
                    else:
                        $rowcount = $result->num_rows;
                        if ($rowcount == 0):
                            echo "<div class='alert-box error'><span>error: </span>Card does not exist</div>";
                        else:
                            $row = $result->fetch_assoc();
                            ?>
    <script type="text/javascript">
        jQuery(function ($) {
            $('#cardeditform').submit(function () {
                if ($('#editid').val() === '') {
                    alert("You need to complete the card ID");
                    return false;
                }
                if ($('#editsetcode').val() === '') {
                    alert("You need to complete the set code");
                    return false;
                }
                if ($('#editsetname').val() === '') {
                    alert("You need to complete the set name");
                    return false;
                }
                if ($('#editnumber').val() === '') {
                    alert("You need to complete the Card's set number");
                    return false;
                }
                if ($('#editname').val() === '') {
                    alert("You need to complete the card name");
                    return false;
                }
                if ($('#edittype').val() === '') {
                    alert("You need to complete the card type");
                    return false;
                }
                if ($('#editartist').val() === '') {
                    alert("You need to complete the card artist");
                    return false;
                }
                if ($('#editrarity').val() === '') {
                    alert("You need to complete the card rarity");
                    return false;
                }
            });
        });
    </script>                
                            <form id='cardeditform' action="?" method="POST">
                                <table>
                                    <tr class="admintable">
                                        <td class='required'>
                                            Card ID
                                        </td>
                                        <td class='required'>
                                            Set code
                                        </td>
                                        <td colspan='3' class='required'>
                                            Set name
                                        </td>
                                        <td class='required'>
                                            Number
                                        </td>
                                        <td colspan='2' class='required'>
                                            Name
                                        </td>
                                        <td colspan='2' class='required'>
                                            Type
                                        </td>
                                        <td class='required'>
                                            Artist
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <?php
                                            echo "<textarea class='textinput requiredvalue' id='editid' name='id' rows='2' cols='8'>{$row['id']}</textarea>";
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            echo "<textarea class='textinput requiredvalue' id='editsetcode' name='setcode' rows='2' cols='8'>{$row['setcode']}</textarea>";
                                            ?>
                                        </td>
                                        <td colspan='3'>
                                            <?php
                                            echo "<textarea class='textinput requiredvalue' id='editsetname' name='setname' rows='2' cols='20'>{$row['set_name']}</textarea>";
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            echo "<textarea class='textinput requiredvalue' id='editnumber' name='number' rows='2' cols='5'>{$row['number']}</textarea>";
                                            ?>
                                        </td>
                                        <td colspan='2'>
                                            <?php
                                            echo "<textarea class='textinput requiredvalue' id='editname' name='name' rows='2' cols='24'>{$row['name']}</textarea>";
                                            ?>
                                        </td>
                                        <td colspan='2'>
                                            <?php
                                            echo "<textarea class='textinput requiredvalue' id='edittype' name='type' rows='2' cols='24'>{$row['type']}</textarea>";
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            echo "<textarea class='textinput requiredvalue' id='editartist' name='artist' rows='2' cols='15'>{$row['artist']}</textarea>";
                                            ?>
                                        </td>
                                    </tr>
                                    <tr class="admintable">
                                        <td>
                                            Power
                                        </td>
                                        <td>
                                            Toughness
                                        </td>
                                        <td>
                                            Loyalty
                                        </td>
                                        <td>
                                            Manacost
                                        </td>
                                        <td>
                                            CMC
                                        </td>
                                        <td>
                                            Color
                                        </td>
                                        <td class='required'>
                                            Rarity
                                        </td>
                                        <td>
                                            Generated mana
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <?php
                                            echo "<textarea class='textinput' name='power' rows='2' cols='8'>{$row['power']}</textarea>";
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            echo "<textarea class='textinput' name='toughness' rows='2' cols='8'>{$row['toughness']}</textarea>";
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            echo "<textarea class='textinput' name='loyalty' rows='2' cols='3'>{$row['loyalty']}</textarea>";
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            echo "<textarea class='textinput' name='manacost' rows='2' cols='5'>{$row['manacost']}</textarea>";
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            echo "<textarea class='textinput' name='cmc' rows='2' cols='4'>{$row['cmc']}</textarea>";
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            echo "<textarea class='textinput' name='color' rows='2' cols='5'>{$row['color']}</textarea>";
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            echo "<textarea class='textinput requiredvalue' id='editrarity' name='rarity' rows='2' cols='6'>{$row['rarity']}</textarea>";
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            echo "<textarea class='textinput' name='generatedmana' rows='2' cols='14'>{$row['generatedmana']}</textarea>";
                                            ?>
                                        </td>
                                    </tr>
                                    <tr class="admintable">
                                        <td colspan='2'>
                                            Ability
                                        </td>
                                        <td colspan='3'>
                                            Flavor
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan='2'>
                                            <?php
                                            echo "<textarea class='textinput' name='ability' rows='8' cols='20'>{$row['ability']}</textarea>";
                                            ?>
                                        </td>
                                        <td colspan='3'>
                                            <?php
                                            echo "<textarea class='textinput' name='flavor' rows='8' cols='20'>{$row['flavor']}</textarea>";
                                            ?>
                                        </td>
                                    </tr>
                                </table>
                                <input class='inline_button stdwidthbutton updatebutton' name='update' type="submit" value="UPDATE">
                                <input class='inline_button stdwidthbutton updatebutton' id='deletebutton' name='delete' type="submit" value="DELETE" 
                                       onclick="return confirm('Do you really want to delete this card?');">
                            </form>
                        <?php   
                        endif;
                    endif;
                elseif (isset($newcard) AND $newcard == 1): ?>
    <script type="text/javascript">
        jQuery(function ($) {
            $('#newcardform').submit(function () {
                if ($('#newid').val() === '') {
                    alert("You need to complete the card ID");
                    return false;
                }
                if ($('#newsetcode').val() === '') {
                    alert("You need to complete the set code");
                    return false;
                }
                if ($('#newsetname').val() === '') {
                    alert("You need to complete the set name");
                    return false;
                }
                if ($('#newnumber').val() === '') {
                    alert("You need to complete the Card's set number");
                    return false;
                }
                if ($('#newname').val() === '') {
                    alert("You need to complete the card name");
                    return false;
                }
                if ($('#newtype').val() === '') {
                    alert("You need to complete the card type");
                    return false;
                }
                if ($('#newartist').val() === '') {
                    alert("You need to complete the card artist");
                    return false;
                }
                if ($('#newrarity').val() === '') {
                    alert("You need to complete the card rarity");
                    return false;
                }
            });
        });
    </script>
                    <form id='newcardform' action="?" method="POST">
                        <table>
                            <tr class="admintable">
                                <td class='required'>
                                    Card ID
                                </td>
                                <td class='required'>
                                    Set code
                                </td>
                                <td colspan='3' class='required'>
                                    Set name
                                </td>
                                <td class='required'>
                                    Number
                                </td>
                                <td colspan='2' class='required'>
                                    Name
                                </td>
                                <td colspan='2' class='required'>
                                    Type
                                </td>
                                <td class='required'>
                                    Artist
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <?php
                                    echo "<textarea class='textinput' id='newid' name='id' rows='2' cols='7'></textarea>";
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    echo "<textarea class='textinput' id='newsetcode' name='setcode' rows='2' cols='7'></textarea>";
                                    ?>
                                </td>
                                <td colspan='3'>
                                    <?php
                                    echo "<textarea class='textinput' id='newsetname' name='setname' rows='2' cols='25'></textarea>";
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    echo "<textarea class='textinput' id='newnumber' name='number' rows='2' cols='5'></textarea>";
                                    ?>
                                </td>
                                <td colspan='2'>
                                    <?php
                                    echo "<textarea class='textinput' id='newname' name='name' rows='2' cols='24'></textarea>";
                                    ?>
                                </td>
                                <td colspan='2'>
                                    <?php
                                    echo "<textarea class='textinput' id='newtype' name='type' rows='2' cols='19'></textarea>";
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    echo "<textarea class='textinput' id='newartist' name='artist' rows='2' cols='15'></textarea>";
                                    ?>
                                </td>
                            </tr>
                            <tr class="admintable">
                                <td>
                                    Power
                                </td>
                                <td>
                                    Toughness
                                </td>
                                <td>
                                    Loyalty
                                </td>
                                <td>
                                    Manacost
                                </td>
                                <td>
                                    CMC
                                </td>
                                <td>
                                    Color
                                </td>
                                <td class='required'>
                                    Rarity
                                </td>
                                <td>
                                    Generated mana
                                </td>
                                <td>
                                    Back ID
                                </td>
                                <td>
                                    Meld
                                </td>
                                <td>
                                    Comment
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <?php
                                    echo "<textarea class='textinput' name='power' rows='2' cols='7'></textarea>";
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    echo "<textarea class='textinput' name='toughness' rows='2' cols='7'></textarea>";
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    echo "<textarea class='textinput' name='loyalty' rows='2' cols='7'></textarea>";
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    echo "<textarea class='textinput' name='manacost' rows='2' cols='9'></textarea>";
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    echo "<textarea class='textinput' name='cmc' rows='2' cols='4'></textarea>";
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    echo "<textarea class='textinput' name='color' rows='2' cols='5'></textarea>";
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    echo "<textarea class='textinput' id='newrarity' name='rarity' rows='2' cols='6'></textarea>";
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    echo "<textarea class='textinput' name='generatedmana' rows='2' cols='15'></textarea>";
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    echo "<textarea class='textinput' name='backid' rows='2' cols='8'></textarea>";
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    echo "<textarea class='textinput' name='meld' rows='2' cols='8'></textarea>";
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    echo "<textarea class='textinput' name='comment' rows='2' cols='15'></textarea>";
                                    ?>
                                </td>
                            </tr>
                            <tr class="admintable">
                                <td colspan='3'>
                                    Ability
                                </td>
                                <td colspan='3'>
                                    Flavor
                                </td>
                                <td colspan='3'>
                                    Ruling
                                </td>
                                <td>
                                    TCG set
                                </td>
                                <td>
                                    TCG name
                                </td>
                                <td>
                                    Scryfall ID
                                </td> 
                            </tr>
                            <tr>
                                <td colspan='3'>
                                    <?php
                                    echo "<textarea class='textinput' name='ability' rows='8' cols='26'></textarea>";
                                    ?>
                                </td>
                                <td colspan='3'>
                                    <?php
                                    echo "<textarea class='textinput' name='flavor' rows='8' cols='24'></textarea>";
                                    ?>
                                </td>
                                <td colspan='3'>
                                    <?php
                                    echo "<textarea class='textinput' name='ruling' rows='8' cols='35'></textarea>";
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    echo "<textarea class='textinput' name='tcgsetoverride' rows='8' cols='8'></textarea>";
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    echo "<textarea class='textinput' name='tcgnameoverride' rows='8' cols='15'></textarea>";
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    echo "<textarea class='textinput' name='tcgnameoverride' rows='8' cols='15'></textarea>";
                                    ?>
                                </td>                                
                            </tr>
                        </table>
                        <input class='inline_button stdwidthbutton updatebutton' name='addcard' type="submit" value="ADD CARD">
                        <input class='inline_button stdwidthbutton updatebutton' name='cancel' type="button" value="CANCEL" onclick="window.location = '/admin/cards.php';">
                    </form>
                <?php
                endif;
                ?>
                
            </div>
        </div>
    </div>
<?php
require('../includes/footer.php'); ?>
</body>
</html>