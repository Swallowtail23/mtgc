<?php 
/* Version:     4.1
    Date:       25/03/2023
    Name:       admin/admin.php
    Purpose:    Site control panel
    Notes:      
        
    1.0
                Initial version
    2.0         
                Mysqli_Manager
 *  3.0
 *              Moved from writelog to Message class
 *  4.0
 *              PHP 8.1 compatibility
 *  4.1
 *              Fixed error on unminifying CSS
*/
ini_set('session.name', '5VDSjp7k-n-_yS-_');
session_start();
require ('../includes/ini.php');               //Initialise and load ini file
require ('../includes/error_handling.php');
require ('../includes/functions_new.php');     //Includes basic functions for non-secure pages
require ('adminfunctions.php');
require ('../includes/secpagesetup.php');      //Setup page variables
forcechgpwd();                              //Check if user is disabled or needs to change password

//Check if user is logged in, if not redirect to login.php
$obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Admin page called by user $username ($useremail) Admin result: ".$admin,$logfile);
if ($admin !== 1):
    require('reject.php');
endif;

//Get date for update form
$dateobject = new DateYMD;
$date = $dateobject->getToday();

$clearscryfalljson = isset($_GET['clearscryfalljson']) ? 'y' : '';
$togglecss = isset($_GET['togglecss']) ? 'y' : '';
$publishcss = isset($_GET['publishcss']) ? 'y' : '';
if((isset($_POST['update'])) AND ($_POST['update'] == 'ADD')):
    $update = 1;
    // Retrieve all the posted variables
    if(isset($_POST['date'])):
        $date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_NUMBER_INT);
    endif;
    if(isset($_POST['name'])):
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);
    endif;
    if(isset($_POST['updatetext'])):
        $updatetext = filter_input(INPUT_POST, 'updatetext', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);
    endif;
    $date = $db->escape($date);
    $name = strtolower ($db->escape($name));
    // $updatetext = $db->escape($updatetext);
    $data = array(
                '`date`' => $date,
                '`author`' => $name,
                '`update`' => $updatetext
        );
    if ($db->insert('updatenotices', $data) === TRUE):
        $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Adding update notice: Insert ID: ".$db->insert_id,$logfile);
    else:
        trigger_error("[ERROR] admin.php: Adding update notice: failed " . $db->error, E_USER_ERROR);
    endif;
endif;
if(isset($_GET['loglevel'])):
    $newloglevel = filter_input(INPUT_GET, 'loglevel', FILTER_SANITIZE_NUMBER_INT);
    $ini->data['general']['Loglevel'] = "$newloglevel";
    $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Log level change by user $username to $newloglevel",$logfile);
    $ini->write();
    //re-read ini file
    $ini = new INI("/opt/mtg/mtg_new.ini");
    $ini_array = $ini->data;
    $loglevelini = $ini_array['general']['Loglevel'];
    if($loglevelini == $newloglevel):
        $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Log level change success to $newloglevel",$logfile);
    endif;
endif;
?>

<!DOCTYPE html>
<head>
    <title>MtG collection administration - site</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" type="text/css" href="/css/style<?php echo $cssver?>.css">
<?php include('../includes/googlefonts.php');?>
<script src="../js/jquery.js"></script>
<script type="text/javascript">   
    jQuery( function($) {
        $('#newinfoupdate').submit(function() {
            if(($('#updatetext').val() === '') || ($('#updatedate').val() === '')){
                alert("You need to complete the date and update text fields");
                return false;
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
            <h3>Add Info update</h3>
            <form id='newinfoupdate' action="?" method="POST">
                <table>
                    <tr>
                        <td colspan='2'>
                            Date
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <input class='textinput' id='updatedate' type='date' name='date' value='<?php echo $date ?>' >
                        </td>
                    </tr>
                    <tr>
                        <td colspan='2'>
                            Update notes
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <textarea class='textinput' id='updatetext' name='updatetext' rows='8'></textarea>
                        </td>
                        <td>
                            <input class='inline_button stdwidthbutton updatebutton' name='update' type="submit" value="ADD">
                        </td>
                    </tr>
                </table>
                <input name='name' type='hidden' value='<?php echo ucfirst($username) ?>'/>
            </form>
            <h3>Logging </h3>
            <h4>Log file path</h4> <?php
            $filepath = "$logfile";
            $file = file($filepath);
            echo 'Log file location: '.$filepath.'<p>';
            echo '<h4>Log file - recent</h4>';
            if(count($file) < 9):
                $lines = count($file);
            else:
                $lines = 8;
            endif;
            for ($i = count($file)-$lines; $i < count($file); $i++) {
              echo $file[$i] . "\n", "<br>";
            }
            echo '<h4>Log level</h4>'; ?>
            <form action="/admin/admin.php">
                <label class="radio"><input type="radio" name="loglevel" value="1" <?php if($loglevelini === '1'): echo 'checked="checked"';endif;?>><span class="outer"><span class="inner"></span></span>1 - Error;</label><br>
                <label class="radio"><input type="radio" name="loglevel" value="2" <?php if($loglevelini === '2'): echo 'checked="checked"';endif;?>><span class="outer"><span class="inner"></span></span>2 - Notice;</label><br>
                <label class="radio"><input type="radio" name="loglevel" value="3" <?php if($loglevelini === '3'): echo 'checked="checked"';endif;?>><span class="outer"><span class="inner"></span></span>3 - Debug;</label><br>
                <input class='inline_button stdwidthbutton' type="submit" value="SET" />
            </form>
            If log level set fails, check permissions of web server to the ini file. <?php
            if((isset($togglecss)) AND ($togglecss == "y")):
                $obj = new Message;$obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Turning off minimised CSS...",$logfile);
                $cssquery = 0;
                $query = 'UPDATE admin SET usemin=?';
                if ($db->execute_query($query, [$cssquery]) === TRUE):
                    $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Turned off minimised CSS",$logfile);
                else:
                    trigger_error("[ERROR] admin.php: Turning off minimised CSS: Failed: " . $db->error, E_USER_ERROR);
                endif;
                $cssver = cssver(); //run again
            endif;
            if((isset($publishcss)) AND ($publishcss == "y")):
                $obj = new Message;$obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Turning on minimised CSS...",$logfile);
                $cssquery = 1;
                $query = 'UPDATE admin SET usemin=?';
                if ($db->execute_query($query, [$cssquery]) === TRUE):
                    $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Turned on minimised CSS",$logfile);
                else:
                    trigger_error("[ERROR] admin.php: Turning on minimised CSS: Failed: " . $db->error, E_USER_ERROR);
                endif;
                $cssver = cssver(); //run again
            endif;
            if((isset($clearscryfalljson)) AND ($clearscryfalljson == "y")):
                if ($db->query('TRUNCATE TABLE scryfalljson') === TRUE):
                    $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"JSON data removed",$logfile);
                else:
                    trigger_error("[ERROR] admin.php: JSON removal failed: " . $db->error, E_USER_ERROR);
                endif;
                $cssver = cssver(); //run again
            endif;
                ?>
            <h4>CSS</h4> <?php 
            if (strpos($cssver,"min") == true): 
                echo "Current CSS status: Minified <p>";
                echo "Un-minify to see results of editing CSS!!"; ?>
                <form action="/admin/admin.php">
                    <input type="submit" value="Use non-minified CSS for editing" />
                    <input type="hidden" name="togglecss" value="y"/>
                </form> <?php
            else: 
                echo "Current CSS status: Not minified <p> Make required edits to CSS file style$cssver.css, save it, minify it in NetBeans to 'css/style-min.css', then come back here and 'publish' it."; ?>
                <form action="/admin/admin.php">
                    <input type="submit" value="Use minified CSS" />
                    <input type="hidden" name="publishcss" value="y"/>
                </form> <?php
            endif;?> 
            <h4>Scryfall JSON</h4> <?php 
                echo "Clear all Scryfall data from JSON table"; ?>
                <form action="/admin/admin.php">
                    <input type="submit" value="Clear JSON" />
                    <input type="hidden" name="clearscryfalljson" value="y"/>
                </form>
            <h4>Maintenance Mode</h4>
            Current Maintenance mode status: <?php 
            if ((isset($_GET['mtce'])) AND ($_GET['mtce'] == 'MTCE ON')):
                setmtcemode('on');
            elseif ((isset($_GET['mtce'])) AND ($_GET['mtce'] == 'MTCE OFF')):
                setmtcemode('off');    
            endif;
            $mtcestatus = mtcemode($user); 
            if (($mtcestatus == 1) OR ($mtcestatus == 2)):
                echo "On"; ?>
                <form action='admin.php' method='GET'>
                    <input class='inline_button stdwidthbutton' id='mtce' type='submit' value='MTCE OFF' name='mtce' />
                </form> <?php
            else:
                echo "Off"; ?>
                <form action='admin.php' method='GET'>
                    <input class='inline_button stdwidthbutton' id='mtce' type='submit' value='MTCE ON' name='mtce' />
                </form> <?php
            endif; ?>
            
            <h4>Migration cards (Scryfall corrections)</h4> <?php
            $stmt = $db->execute_query('SELECT old_scryfall_id,object,performed_at,migration_strategy,note,metadata_name,metadata_set_code,metadata_collector_number,new_scryfall_id FROM migrations WHERE db_match = 1');
            if($stmt != TRUE):
                trigger_error("[ERROR] Class " .__METHOD__ . " ".__LINE__," - SQL failure: Error: " . $db->error, E_USER_ERROR);
            else:
                if ($stmt->num_rows > 0): ?>
                <table>
                    <tr>
                        <td>Row</td>
                        <td>Old Scryfall ID</td>
                        <td>Object</td>
                        <td>Migration Strategy</td>
                        <td>Name</td>
                        <td>Set code</td>
                        <td>Card number</td>
                        <td>Note</td>
                        <td>Merge new Scryfall ID</td>
                    </tr>
                    <tr>
                    <?php
                    $row_no = 1;
                    while($row = $stmt->fetch_assoc()): 
                        $row_no = $row_no + 1; ?>
                        <tr>
                            <td><?php echo($row_no);?></td>
                            <td><?php echo($row['old_scryfall_id']);?></td>
                            <td><?php echo($row['object']);?></td>
                            <td><?php echo($row['migration_strategy']);?></td>
                            <td><?php echo($row['metadata_name']);?></td>
                            <td><?php echo($row['metadata_set_code']);?></td>
                            <td><?php echo($row['metadata_collector_number']);?></td>
                            <td><?php echo($row['note']);?></td>
                            <td><?php echo($row['new_scryfall_id']);?></td>
                        </tr>
                        <?php
                    endwhile; ?>
                    </tr>
                </table>
            
                <?php
                else:
                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"No rows",$logfile);
                    echo "No cards needing action <br>";
                    echo "&nbsp;<br>";
                endif;
            endif;
            ?>
        </div>
    </div>
</div>
    
<?php require('../includes/footer.php'); ?>
</body>
</html>