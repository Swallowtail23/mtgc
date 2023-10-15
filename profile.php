<?php 
/* Version:     7.0
    Date:       06/07/23
    Name:       profile.php
    Purpose:    User profile page
    Notes:      This page must not run the forcechgpwd function - this is the page
 *              that a user goes to TO change password. 
    To do:      
 * 
    1.0
                Initial version
    2.0         
                Mysqli_Manager migration completed down to Line 350
 *  3.0 
 *              Moved from writelog to Message class
 *              Migrated to mysqli
 *  4.0
 *              Errors running under php7.4 resolved (primarily null variable
 *                  issues)
 *  5.0
 *              Refactoring of import for new database structure, and adding emailed report
 *  6.0
 *              Changed to password_verify / password_hash
 *  7.0
 *              Added handling for etched cards
*/

session_start();
require ('includes/ini.php');               //Initialise and load ini file
require ('includes/error_handling.php');
require ('includes/functions_new.php');     //Includes basic functions for non-secure pages
require ('includes/secpagesetup.php');      //Setup page variables
?> 
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="initial-scale=1">
    <title>MtG collection profile</title>
<link rel="stylesheet" type="text/css" href="css/style<?php echo $cssver?>.css"> 
<?php include('includes/googlefonts.php');?>
<script src="/js/jquery.js"></script>
</head>

<body class="body">
<?php 
include_once("includes/analyticstracking.php");
require('includes/overlays.php');  
require('includes/header.php');
require('includes/menu.php');

// Does the user have a collection table?
$tablecheck = "SELECT * FROM $mytable";
$obj = new Message;$obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": Checking if user has a collection table...",$logfile);
if($db->query($tablecheck) === FALSE):
    $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": No existing collection table...",$logfile);
    $query2 = "CREATE TABLE `$mytable` LIKE collectionTemplate";
    $obj = new Message;$obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": ...copying collection template...: $query2",$logfile);
    if($db->query($query2) === TRUE):
        $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": Collection template copy successful",$logfile);
    else:
        $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": Collection template copy failed",$logfile);
    endif;
endif; ?>

<div id='page'>
    <div class='staticpagecontent'>
        <?php 
        //Page PHP processing
        //1. Get user details for current user
        if($row = $db->select_one('password, username, usernumber, status, admin','users',"WHERE email='$useremail'")):
            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"SQL query for user details succeeded",$logfile);
        else:
            trigger_error('[ERROR] profile.php: Error: '.$db->error, E_USER_ERROR);
        endif;
        //2. Has a password reset been called? Needs to be in DIV for error display
        if (isSet($_POST['changePass']) AND isSet($_POST['newPass']) AND isSet($_POST['newPass2']) AND isSet($_POST['curPass'])):
            if (!empty($_POST['curPass']) AND !empty($_POST['newPass']) AND !empty($_POST['newPass2'])):
                $new_password = $_POST['newPass'];
                $new_password_2 = $_POST['newPass2'];
                $old_password = $_POST['curPass'];
                $db_password = $row['password'];
                if ($new_password == $new_password_2):
                    if (valid_pass($new_password)):
                        if($new_password != $old_password):
                            if (password_verify($old_password, $db_password)):
                                $new_password = password_hash("$new_password", PASSWORD_DEFAULT);
                                $data = array(
                                    'password' => "$new_password"
                                );
                                $pwdchg = $db->update('users',$data,"WHERE email='$useremail'");
                                $obj = new Message;
                                $obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Password change call for $useremail from {$_SERVER['REMOTE_ADDR']}",$logfile);
                                if ($pwdchg === false):
                                    trigger_error('[ERROR] profile.php: Error: '.$db->error, E_USER_ERROR);
                                endif;
                                $pwdvalidate = $db->select_one('password','users',"WHERE email='$useremail'");
                                if($pwdvalidate === false):
                                    trigger_error('[ERROR] profile.php: Error: '.$db->error, E_USER_ERROR);
                                else:
                                    if ($pwdvalidate['password'] == $new_password):
                                        $obj = new Message;
                                        $obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Confirmed new password written to database for $useremail from {$_SERVER['REMOTE_ADDR']}",$logfile);
                                        echo "<div class='alert-box success' id='pwdchange'><span>success: </span>Password successfully changed.</div>";
                                        // Clear the force password flag and session variable
                                        $statusdata = array(
                                            'status' => 'active'
                                        );
                                        $chgflagclear = $db->update('users',$statusdata,"WHERE email='$useremail'");
                                        if ($chgflagclear === false):
                                            trigger_error('[ERROR] profile.php: Error: '.$db->error, E_USER_ERROR);
                                        else:
                                            $_SESSION['chgpwd'] = '';
                                        endif;
                                    else:
                                        echo "<div class='alert-box error' id='pwdchange'><span>error: </span>Password change failed... not sure why!</div>";
                                        $obj = new Message;
                                        $obj->MessageTxt('[ERROR]',$_SERVER['PHP_SELF'],"New password not verified from database for $useremail from {$_SERVER['REMOTE_ADDR']}",$logfile);
                                    endif;
                                endif;
                            else:
                                echo "<div class='alert-box error' id='pwdchange'><span>error: </span>Your entered current password was not correct. Please try again.</div>";
                            endif;
                        else:
                            echo "<div class='alert-box error' id='pwdchange'><span>error: </span>Your new password is the same as the old one. Please try again.</div>";
                        endif;
                    else:
                        echo "<div class='alert-box error' id='pwdchange'><span>error: </span>The new password does not meet requirements.</div>";
                    endif;
                else:
                    echo "<div class='alert-box error' id='pwdchange'><span>error: </span>The two new passwords did not match. Please ensure they match then try again.</div>";
                endif;    
            else:
                echo "<div class='alert-box error' id='pwdchange'><span>error: </span>Fill in all fields.</div>";
            endif;
        endif;
    //3. User needs to change password (status = chgpwd). Needs to be in DIV for error display
        if ((isset($_SESSION["chgpwd"])) AND ($_SESSION["chgpwd"] == TRUE)):
            echo "<div class='alert-box notice' id='pwdchange'><span>notice: </span>You must set a new password.</div>";
            $obj = new Message;
            $obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Enforcing password change for $useremail from {$_SERVER['REMOTE_ADDR']}",$logfile);
        endif;
    //4. Collection view
        if (isset($_POST['collection_view'])):
            $coll_view = $db->escape($_POST['collection_view']);
            if ($coll_view == 'TURN OFF'):
                $obj = new Message;
                $obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Call to turn off collection view",$logfile);
                $updatedata = array (
                    'collection_view' => '0'
                );
                $cviewquery = $db->update('users',$updatedata,"WHERE usernumber='$user'");
                if($cviewquery === false):
                    trigger_error('[ERROR] profile.php: Error: '.$db->error, E_USER_ERROR);
                else:    
                    $obj = new Message;
                    $obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Call to turn off collection view run for $useremail",$logfile);
                endif;
            elseif ($coll_view == 'TURN ON'):
                $obj = new Message;
                $obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Call to turn on collection view",$logfile);
                $updatedata = array (
                    'collection_view' => 1
                );
                $cviewquery = $db->update('users',$updatedata,"WHERE usernumber='$user'");
                if($cviewquery === false):
                    trigger_error('[ERROR] profile.php: Error: '.$db->error, E_USER_ERROR);
                else:    
                    $obj = new Message;
                    $obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Call to turn off collection view run for $useremail",$logfile);
                endif;
            endif;
        endif;
    //5. Groups in / out change
        if (isset($_POST['group'])):
            $group = $db->escape($_POST['group']);
            if ($group == 'OPT OUT'):
                $obj = new Message;
                $obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Call to opt out of groups",$logfile);
                $updatedata = array (
                    'grpinout' => '0'
                );
                $optinquery = $db->update('users',$updatedata,"WHERE usernumber='$user'");
                if($optinquery === false):
                    trigger_error('[ERROR] profile.php: Error: '.$db->error, E_USER_ERROR);
                else:    
                    $obj = new Message;
                    $obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Group opt-out run for $useremail",$logfile);
                endif;
            elseif ($group == 'OPT IN'):
                $obj = new Message;
                $obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Call to opt in to groups",$logfile);
                $updatedata = array (
                    'grpinout' => 1
                );
                $optoutquery = $db->update('users',$updatedata,"WHERE usernumber='$user'");
                if($optoutquery === false):
                    trigger_error('[ERROR] profile.php: Error: '.$db->error, E_USER_ERROR);
                else:    
                    $obj = new Message;
                    $obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Group opt-in run for $useremail",$logfile);
                endif;
            endif;
        endif;
        
        //6. No changes called in POST - run user query
        if($userdatarow=$db->select_one('username, password, email, reg_date, status, admin, groupid, grpinout, groupname, collection_view','users LEFT JOIN `groups` ON users.groupid = groups.groupnumber',"WHERE usernumber=$user")):
            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"SQL query for user with group details succeeded",$logfile);
        else:
            trigger_error('[ERROR] profile.php: Error: '.$db->error, E_USER_ERROR);
        endif;
        //6. Update pricing in case any new cards have been added to collection
        update_collection_values($mytable);
        
        //Get card total
        if($totalcount = $db->query("SELECT sum(IFNULL(normal, 0)) + sum(IFNULL(foil, 0)) + sum(IFNULL(etched, 0)) as TOTAL from `$mytable`")):
            $rowcount = $totalcount->fetch_array(MYSQLI_ASSOC);
        else:
            trigger_error('[ERROR] profile.php: Error: '.$db->error, E_USER_ERROR);
        endif;
        if(is_null($rowcount['TOTAL'])):
            $totalcardcount = 0;
        else:
            $totalcardcount = $rowcount['TOTAL'];
        endif;
        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Total card count = $totalcardcount",$logfile);
        
        // Get total values
        $sqlvalue = "SELECT (
                        COALESCE(SUM(`$mytable`.normal * price),0)
                        + 
                        COALESCE(SUM(`$mytable`.foil * price_foil),0)
                        +
                        COALESCE(SUM(`$mytable`.etched * price_etched),0)
                            ) 
                        as TOTAL FROM `$mytable` LEFT JOIN cards_scry ON `$mytable`.id = cards_scry.id";
        if($totalvalue = $db->query($sqlvalue)):
            $rowvalue = $totalvalue->fetch_assoc();
            $unformatted_value = $rowvalue['TOTAL'];
            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Unformatted value = $unformatted_value",$logfile);
        else:
            trigger_error('[ERROR] profile.php: Error: '.$db->error, E_USER_ERROR);
        endif;

        //Page display content ?>
        <div id="userdetails">
            <h2 class='h2pad'>User details</h2>
            <b>Email: </b><?php echo $userdatarow['email']; ?> <br>
            <b>Account status: </b> <?php echo $userdatarow['status']; ?> <br>
            <b>Registered date: </b> <?php echo $userdatarow['reg_date']; ?> <br>
        </div>
        <div id="mycollection">
            <h2 class='h2pad'>My Collection</h2>
            <?php
                $a = new \NumberFormatter("en-US", \NumberFormatter::CURRENCY);
                $collectionmoney = $a->format($unformatted_value);
                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Formatted value = $collectionmoney",$logfile);
                $collectionvalue = "Total value approximately USD".$collectionmoney;
                $rowcounttotal = number_format($totalcardcount);
                echo "$collectionvalue over $rowcounttotal cards.<br>";
                echo "This is based on normal and foil pricing where applicable from <a href='https://www.scryfall.com/' target='_blank'>scryfall.com</a>, obtained from tcgplayer.com, in USD.<br>";
                $rowcounttotal = number_format($totalcardcount);
                echo "<br>";  
            ?>
        </div>
        <div id="changepassword">
            <h2 class='h2pad'>Change my password</h2>
            Minimum 8 characters with uppercase, lowercase, and a number.
            <br>
            <form action='/profile.php' method='POST'>
                <table>
                    <tbody>
                        <tr>
                            <td style="min-width:190px">
                                <input class='profilepassword textinput' tabindex='1' type='password' name='curPass' placeholder="CURRENT">
                                <span class="error2">*</span>
                            </td>
                            <td rowspan='3'>
                                <input class='inline_button stdwidthbutton' tabindex='4' id='chgpwdsubmit' type='submit' value='UPDATE' name='changePass' />
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <input class='profilepassword textinput' tabindex='2' type='password' name='newPass' placeholder="NEW">
                                <span class="error2">*</span>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <input class='profilepassword textinput' tabindex='3' type='password' name='newPass2' placeholder="REPEAT NEW">
                                <span class="error2">*</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </form>
        </div>
        <?php 
        if ((!isset($_SESSION["chgpwd"])) OR ($_SESSION["chgpwd"] != TRUE)): ?>
            <div id='importexport'>
                <h2 id='h2'>Collection View</h2>
                With Collection View on, cards you do not own will show as black and white in the grid view.<br> 
                <b>Collection View active? </b> <?php 
                if ($userdatarow['collection_view'] == 1):
                    echo "Yes<br>"; ?>
                    <form action='/profile.php' method='POST'>
                        <input class='inline_button stdwidthbutton' id='cvoff' type='submit' value='TURN OFF' name='collection_view' />
                    </form> <?php
                else:
                    echo "No"; ?>
                    <form action='/profile.php' method='POST'>
                        <input class='inline_button stdwidthbutton' id='cvoff' type='submit' value='TURN ON' name='collection_view' />
                    </form> <?php
                endif;
                ?> 
                <h2 id='h2'>Groups</h2>
                Groups functionality allows you to see cards owned by others in your 'group' and for them to see your cards.<br> 
                If you Opt Out of Groups then your collection is private. <br>
                <b>Groups active? </b> <?php 
                if ($userdatarow['grpinout'] == 1):
                    echo "Yes<br>"; 
                    echo "<b>Group:</b> ".$userdatarow['groupname']; ?>
                    <form action='/profile.php' method='POST'>
                        <input class='inline_button stdwidthbutton' id='optout' type='submit' value='OPT OUT' name='group' />
                    </form> <?php
                else:
                    echo "No"; ?>
                    <form action='/profile.php' method='POST'>
                        <input class='inline_button stdwidthbutton' id='optout' type='submit' value='OPT IN' name='group' />
                    </form> <?php
                endif;
                ?> 
                <h2 id='h2'>Import / Export</h2>
                <b>Deleting your collection</b><br>
                <ul>
                    <li><a href='help.php'>Send me a request</a>, or export your collection, set the qties all to 0 and re-import the file below</li>
                </ul>
                <b>Import guidelines - read carefully!</b><br>
                <ul>
                    <li>It is <b>strongly recommended</b> that you export (backup) your collection before doing an import; save that file, and if it goes wrong, reset your database and rollback by importing the original collection</li>
                    <li>Ideally only do a full import to an empty collection</li>
                    <li>If a card is not in your collection Import will <b>add</b> it with imported quantities; if a card is in your collection Import will <b>over-write</b> the existing quantity with imported quantities;</li>
                    <li>Import file must be a comma-delimited file; delimiters and enclosing should be as the following example (which also shows how to have a name that includes a comma):</li>
                </ul>
                <pre>
                setcode,number,name,normal,foil,etched,id
                LTR,3,Bill the Pony,5,0,0,9ac68519-ed7f-4f38-9549-c02975f88eed
                LTR,4,"Boromir, Warden of the Tower",2,0,0,f6bc3720-2892-4dda-8f30-079a1ac8e1e2</pre>
                <ul>
                    <li>If "id" is a valid Scryfall UUID value, the line will be imported as that id <i>without checking anything else</i></li>
                    <li>If a Scryfall UUID cannot be matched, an attempt will be made to match based on setcode, name and collector number. If this fails the row will generate an error and be skipped</li>
                    <li>Set codes and collector numbers MUST be as on this site (see <a href='sets.php'> for Set codes </a>) for successful import</li>
                    <li>For an example of the right format export first and use that file as a template</li>
                    <li>Be cautious importing promos or specials, unless you have validated the Scryfall UUID</li>
                    <li>The import routine will validate if a foil or etched version is actually available, but the results will depend on the quality of the data being imported</li>
                    <li>Check the last line of exported files before importing to make sure that it has been closed properly - it should have terminating quotes and a newline; this can be seen on a sample export in an app like Notepad++ (<b>don't use Excel</b>)</li>
                    <li>The import process imports a line, then does a follow-up check to see if it has been successfully written to the database</li>
                    <li>Warnings are shown for lines which you should check and fix - database write failures, name matches, etc.</li>
                    <li>You will be sent a list of the failures and warnings at the end of the import</li>
                </ul>
                <span id='importspan'>
                    <b>Import</b>
                </span> <?php
                if (!isset($_POST['import'])): 
                    echo "<b>Export</b><br>"; 
                else:
                    echo "<br>";
                endif;?>
                <script type="text/javascript">
                    $(document).ready(
                    function(){
                        $("#importsubmit").attr('disabled',true);
                        $("#importfile").change(
                            function(){
                                if ($(this).val()){
                                    $("#importsubmit").removeAttr('disabled'); 
                                }
                                else {
                                    $("#importsubmit").attr('disabled',true);
                                }
                            });
                    });
                </script>
                <script type="text/javascript"> 
                    function ImportPrep()
                        {
                            alert('Import can take several minutes, please be patient...');
                            document.body.style.cursor='wait';
                        }
                </script> 
                <div id='importdiv'>
                    <form enctype='multipart/form-data' action='?' method='post'>
                        <label class='importlabel'>
                            <input id='importfile' type='file' name='filename'>
                            <span>UPLOAD</span>
                        </label>
                        <input class='profilebutton' id='importsubmit' type='submit' name='import' value='IMPORT CSV' disabled onclick='ImportPrep()';>
                    </form> 
                </div>
                <?php
                if (isset($_POST['import'])):
                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Import called, checking file uploaded...",$logfile);
                    if (is_uploaded_file($_FILES['filename']['tmp_name'])):
                        echo "<br><h4>" . "File ". $_FILES['filename']['name'] ." uploaded successfully. Processing..." . "</h4>";
                        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Import file {$_FILES['filename']['name']} uploaded",$logfile);
                    else:
                        echo "<br><h4>" . "File ". $_FILES['filename']['name'] ." did not upload successfully. Exiting..." . "</h4>";
                        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Import file {$_FILES['filename']['name']} failed",$logfile);
                        exit;
                    endif;
                    $importfile = $_FILES['filename']['tmp_name'];
                    $importcards = import($importfile);
                else: ?>
                    <div id='exportdiv'>
                        <form action="csv.php"  method="GET">
                            <input id='exportsubmit' class='profilebutton' type="submit" value="EXPORT CSV">
                            <?php echo "<input type='hidden' name='table' value='$mytable'>"; ?>
                        </form>
                    </div>
                <?php
                endif; ?>
            </div> <?php
        endif; ?>
        <br>&nbsp;<br>
    </div>
</div>
<?php 
require('includes/footer.php'); 
?>
</body>
</html>