<?php

/* Version:     2.0
    Date:       11/01/20
    Name:       legality.php
    Purpose:    Legality check
    Notes:      Ensure SQL table 'banlist' is up to date when legalities are
                changed by Wizards. 
 *              **This file runs when called**

    To do:      
 *              Add ability to alter ban cards from admin page
 *  
    1.0
                Initial version
 *  2.0
 *              Moved from writelog to Message class
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

?>

<!DOCTYPE html>
<head>
    <title>MtG collection administration - cards</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="/css/style<?php echo $cssver ?>.css">
    <?php include('../includes/googlefonts.php'); ?>
</head>
<?php
$obj = new Message;$obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Starting legality update",$logfile);

//Clear legalities from DB:
$data = array(
    'legalitystandard' => '',
    'legalitymodern' => '',
    'legalitylegacy' => '',
    'legalityvintage' => ''
);
if($db->update('cards',$data)):
    $obj = new Message;$obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Cleared legalities",$logfile);
else:
    $obj = new Message;$obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Error clearing legalities",$logfile);
    exit;
endif;


// Build set lists - standard legal first, then modern
$stdlegalsets = $db->select('setcodeid','sets','WHERE stdlegal = 1');
if(($stdlegalsets === false) OR ($stdlegalsets === null)):
    trigger_error('[ERROR] legality.php: Error: '.$db->error, E_USER_ERROR);
else:
    $stdlglsets = array();
    while ($row = $stdlegalsets->fetch_assoc()):
        $value = "'".$row['setcodeid']."'";
        $stdlglsets[] = $value;
    endwhile;
    $stdlglsets_s = array();
    foreach ($stdlglsets as $s) $stdlglsets_s[] = $s;
    $stdlglsets_f = implode(",",$stdlglsets_s);
endif;

$mdnlegalsets = $db->select('setcodeid','sets','WHERE mdnlegal = 1');
if(($mdnlegalsets === false) OR ($mdnlegalsets === null)):
    trigger_error('[ERROR] legality.php: Error: '.$db->error, E_USER_ERROR);
else:
    $mdnlglsets = array();
    while ($row = $mdnlegalsets->fetch_assoc()):
        $value = "'".$row['setcodeid']."'";
        $mdnlglsets[] = $value;
    endwhile;
    $mdnlglsets_s = array();
    foreach ($mdnlglsets as $s) $mdnlglsets_s[] = $s;
    $mdnlglsets_f = implode(",",$mdnlglsets_s);
endif;

//Get arrays of cardnames from std and mdn legal sets
$stdlegalcardsqry = $db->select('name','cards',"WHERE setcode IN ($stdlglsets_f)");
if(($stdlegalcardsqry === false) OR ($stdlegalcardsqry === null)):
    trigger_error('[ERROR] legality.php: Error: '.$db->error, E_USER_ERROR);
else:
    $stdlglcards = array();
    while ($row = $stdlegalcardsqry->fetch_assoc()):
        $value = "`".$row['name']."`";
        if (!in_array($value,$stdlglcards)):
            $stdlglcards[] = $value;
        endif;
    endwhile;
    $stdlglcards_s = array();
    //Next 2 lines:
    //  $stdlglcards_s is array of names
    //  $stdlglcards_f is escaped string of array
    foreach ($stdlglcards as $s) $stdlglcards_s[] = $s;
    $stdlglcards_f = str_replace("`","'",$db->escape(implode(",",$stdlglcards_s))); 
endif;

$mdnlegalcardsqry = $db->select('name','cards',"WHERE setcode IN ($mdnlglsets_f)");
if(($mdnlegalcardsqry === false) OR ($mdnlegalcardsqry === null)):
    trigger_error('[ERROR] legality.php: Error: '.$db->error, E_USER_ERROR);
else:
    $mdnlglcards = array();
    while ($row = $mdnlegalcardsqry->fetch_assoc()):
        $value = "`".$row['name']."`";
        if (!in_array($value,$mdnlglcards)):
            $mdnlglcards[] = $value;
        endif;
    endwhile;
    $mdnlglcards_s = array();
    //Next 2 lines:
    //  $mdnlglcards_s is array of names
    //  $mdnlglcards_f is escaped string of array
    foreach ($mdnlglcards as $s) $mdnlglcards_s[] = $s;
    $mdnlglcards_f = str_replace("`","'",$db->escape(implode(",",$mdnlglcards_s)));
endif;

//Write legalities to each card record, std and modern
$fullstdlist = $db->select('name, id, setcode','cards',"WHERE name IN ($stdlglcards_f)");
if(($fullstdlist === false) OR ($fullstdlist === null)):
    trigger_error('[ERROR] legality.php: Error: '.$db->error, E_USER_ERROR);
else:
    while ($row = $fullstdlist->fetch_assoc()):
        $data = array(
            'legalitystandard' => '1'
        );
        if($db->update('cards',$data,"WHERE id='{$row['id']}'")):
            //do something?
        else:
            $obj = new Message;$obj->MessageTxt('[ERROR]',$_SERVER['PHP_SELF'],"Error updating std legality on card ".$row['id'],$logfile);
            exit;
        endif;
    endwhile;
endif;

//Everythng is legal by default in legacy and vintage
$data = array(
    'legalityvintage' => '1',
    'legalitylegacy' => '1'
);
if($db->update('cards',$data)):
    //do something?
else:
    $obj = new Message;$obj->MessageTxt('[ERROR]',$_SERVER['PHP_SELF'],"Error updating legacy/vintage legality on card ".$row['id'],$logfile);
    exit;
endif;

$fullmdnlist = $db->select('name, id, setcode','cards',"WHERE name IN ($mdnlglcards_f)");
if(($fullmdnlist === false) OR ($fullmdnlist === null)):
    trigger_error('[ERROR] legality.php: Error: '.$db->error, E_USER_ERROR);
else:
    while ($row = $fullmdnlist->fetch_assoc()):
        $data = array(
            'legalitymodern' => '1'
        );
        if($db->update('cards',$data,"WHERE id='{$row['id']}'")):
            //do something?
        else:
            $obj = new Message;$obj->MessageTxt('[ERROR]',$_SERVER['PHP_SELF'],"Error updating mdn legality on card ".$row['id'],$logfile);
            exit;
        endif;
    endwhile;
endif;

//process banlists
$banlistraw = $db->select('cardname, standard, modern, vintageban, vintagerestrict, legacy','banlist');
if(($banlistraw === false) OR ($banlistraw === null)):
    trigger_error('[ERROR] legality.php: Error: '.$db->error, E_USER_ERROR);
else:
    $banlist = array();
    while ($row = $banlistraw->fetch_assoc()):
        $banlist[] = $row;
    endwhile;
    //var_dump($banlist);
endif;

$banentries = count($banlist);
$stdleg = $mdnleg = $vintleg = $legacy = '';
$data = array();
for($x = 0; $x < $banentries; $x++):
    $bancardname = $db->escape($banlist[$x]['cardname']);
    if ($banlist[$x]['standard'] == 1):
        $stdleg = 0;
        $data['legalitystandard'] = $stdleg;
    endif;
    if ($banlist[$x]['modern'] == 1):
        $mdnleg = 0;
        $data['legalitymodern'] = $mdnleg;
    endif;
    if ($banlist[$x]['vintageban'] == 1):
        $vintleg = 0;
        $data['legalityvintage'] = $vintleg;
    endif;
    if ($banlist[$x]['vintagerestrict'] == 1):
        $vintleg = 2;
        $data['legalityvintage'] = $vintleg;
    endif;
    if ($banlist[$x]['legacy'] == 1):
        $legacy = 0;
        $data['legalitylegacy'] = $legacy;
    endif;
    if($db->update('cards',$data,"WHERE name='$bancardname'")):
        $stdleg = $mdnleg = $vintleg = $legacy = $data = null;
    else:
        $obj = new Message;$obj->MessageTxt('[ERROR]',$_SERVER['PHP_SELF'],"Error updating ban legality on card $bancardname",$logfile);
        exit;
    endif;
endfor;
$obj = new Message;$obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Finishing legality update",$logfile);

include '../includes/overlays.php';
include '../includes/header.php';
require('../includes/menu.php');
?>
    <div id='page'>
        <div class='staticpagecontent'>
            <div>
                <h3>Completed</h3>    
            </div>
        </div>
    </div>
<?php
require('../includes/footer.php'); ?>
</body>
</html>
<?php
echo "<meta http-equiv='refresh' content='3;url=cards.php'>";
?>