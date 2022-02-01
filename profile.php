<?php 
/* Version:     4.0
    Date:       11/01/20
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
        if($row = $db->select_one('salt, password, username, usernumber, status, admin','users',"WHERE email='$useremail'")):
            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"SQL query for user details succeeded",$logfile);
        else:
            trigger_error('[ERROR] profile.php: Error: '.$db->error, E_USER_ERROR);
        endif;
        //2. Has a password reset been called? Needs to be in DIV for error display
        if (isSet($_POST['changePass']) AND isSet($_POST['newPass']) AND isSet($_POST['newPass2']) AND isSet($_POST['curPass'])):
            if (!empty($_POST['curPass']) AND !empty($_POST['newPass']) AND !empty($_POST['newPass2'])):
                $new = $_POST['newPass'];
                $new2 = $_POST['newPass2'];
                $old = $_POST['curPass'];
                if ($new == $new2):
                    if (valid_pass($new)):
                        if($new != $old):
                            $old = crypt($old, $Blowfish_Pre . $row['salt'] . $Blowfish_End);
                            if ($old == $row['password']):
                                $new = crypt($new, $Blowfish_Pre . $row['salt'] . $Blowfish_End);
                                $data = array(
                                    'password' => "$new"
                                );
                                $pwdchg = $db->update('users',$data,"WHERE email='$useremail'");
                                $obj = new Message;
                                $obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Password change call for $useremail from {$_SERVER['REMOTE_ADDR']}",$logfile);
                                if ($pwdchg === false):
                                    trigger_error('[ERROR] profile.php: Error: '.$db->error, E_USER_ERROR);
                                endif;
                                $pwdvalidate = $db->select_one('salt, password','users',"WHERE email='$useremail'");
                                if($pwdvalidate === false):
                                    trigger_error('[ERROR] profile.php: Error: '.$db->error, E_USER_ERROR);
                                else:
                                    if ($pwdvalidate['password'] == $new):
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
    //4. Groups in / out change
        if (isset($_POST['group'])):
            $group = $db->escape($_POST['group']);
            if ($group == 'OPT OUT'):
                $obj = new Message;
                $obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Call to opt out of groups",$logfile);
                $updatedata = array (
                    'grpinout' => 0
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
        
        //5. No changes called in POST - run user query
        if($userdatarow=$db->select_one(
                'username, password, email, salt, reg_date, status, admin, groupid, grpinout, groupname',
                'users LEFT JOIN `groups` ON users.groupid = groups.groupnumber',
                "WHERE usernumber=$user")):
            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"SQL query for user with group details succeeded",$logfile);
        else:
            trigger_error('[ERROR] profile.php: Error: '.$db->error, E_USER_ERROR);
        endif;
        //6. Update pricing in case any new cards have been added to collection
        if($findnormal = $db->query("SELECT
                                    `$mytable`.id AS id,
                                    `$mytable`.normal AS mynormal,
                                    `$mytable`.foil AS myfoil,
                                    notes,
                                    topvalue,
                                    price,
                                    price_foil AS foilprice
                                    FROM `$mytable` LEFT JOIN `cards_scry` 
                                    ON `$mytable`.id = `cards_scry`.id 
                                    WHERE `$mytable`.normal / `$mytable`.normal IS TRUE 
                                    AND `$mytable`.foil / `$mytable`.foil IS NOT TRUE")):
            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"SQL query succeeded",$logfile);
            while($row = $findnormal->fetch_array(MYSQLI_ASSOC)):
                if($row['price'] == ''):
                    $normalprice = '0.00';
                else:
                    $normalprice = $row['price'];
                endif;
                $cardid = $db->real_escape_string($row['id']);
                $updatemaxqry = "INSERT INTO `$mytable` (topvalue,id)
                    VALUES ($normalprice,'$cardid')
                    ON DUPLICATE KEY UPDATE topvalue=$normalprice";
                if($updatemax = $db->query($updatemaxqry)):
                    //succeeded
                else:
                    trigger_error('[ERROR] profile.php: Error: '.$db->error, E_USER_ERROR);
                endif;
            endwhile;
        else: 
            trigger_error('[ERROR] profile.php: Error: '.$db->error, E_USER_ERROR);
        endif;
        if($findfoil = $db->query("SELECT
                                    `$mytable`.id AS id,
                                    `$mytable`.normal AS mynormal,
                                    `$mytable`.foil AS myfoil,
                                    notes,
                                    topvalue,
                                    price,
                                    price_foil AS foilprice
                                    FROM `$mytable` LEFT JOIN `cards_scry` 
                                    ON `$mytable`.id = `cards_scry`.id 
                                    WHERE `$mytable`.foil / `$mytable`.foil IS TRUE")):            
            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"SQL query succeeded",$logfile);
            while($foilrow = $findfoil->fetch_array(MYSQLI_ASSOC)):
                if($foilrow['foilprice'] == ''):
                    $foilprice = '0.00';
                else:
                    $foilprice = $foilrow['foilprice'];
                endif;
                $cardid = $db->real_escape_string($foilrow['id']);
                $updatemaxqry = "INSERT INTO `$mytable` (topvalue,id)
                    VALUES ($foilprice,'$cardid')
                    ON DUPLICATE KEY UPDATE topvalue=$foilprice";
                if($updatemax = $db->query($updatemaxqry)):
                    //succeeded
                else:
                    trigger_error('[ERROR] profile.php: Error: '.$db->error, E_USER_ERROR);
                endif;
            endwhile;
        else: 
            trigger_error('[ERROR] profile.php: Error: '.$db->error, E_USER_ERROR);
        endif;
        
        //Get card total and value
        if($totalcount = $db->query("SELECT sum(normal) + sum(foil) as TOTAL from `$mytable`")):
            $rowcount = $totalcount->fetch_array(MYSQLI_ASSOC);
        else:
            trigger_error('[ERROR] profile.php: Error: '.$db->error, E_USER_ERROR);
        endif;

        $sqlvalue = "SELECT (
                        COALESCE(SUM(`$mytable`.normal * price),0)
                        + 
                        COALESCE(SUM(`$mytable`.foil * price_foil),0)
                            ) 
                        as TOTAL FROM `$mytable` LEFT JOIN cards_scry ON `$mytable`.id = cards_scry.id";
        if($totalvalue = $db->query($sqlvalue)):
            $rowvalue = $totalvalue->fetch_assoc();
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
                $collectionmoney = $a->format($rowvalue['TOTAL']); 
                $collectionvalue = "Total value approximately USD".$collectionmoney;
                $rowcounttotal = number_format($rowcount['TOTAL']);
                echo "$collectionvalue over $rowcounttotal cards.<br>";
                echo "This is based on normal and foil pricing where applicable from <a href='http://www.scryfall.com/' target='_blank'>scryfall.com</a>, obtained from tcgplayer.com, in USD.<br>";
            
                $rowcounttotal = number_format($rowcount['TOTAL']);
                echo "Total $rowcounttotal cards.<br>";  
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
        if ((!isset($_SESSION["chgpwd"])) OR ($_SESSION["chgpwd"] != TRUE)): 
        ?>

        <div id='importexport'>
            
            <h2 id='h2'>Groups</h2>
                Groups functionality allows you to see cards owned by others in your 'group' and for them to see your cards.<br> 
                If you Opt Out of Groups then your collection is private. <br>
                <b>Groups active? </b> 
                    <?php 
                if ($userdatarow['grpinout'] == 1):
                    echo "Yes<br>"; 
                    echo "<b>Group:</b> ".$userdatarow['groupname'];
                    ?>
                    <form action='/profile.php' method='POST'>
                        <input class='inline_button stdwidthbutton' id='optout' type='submit' value='OPT OUT' name='group' />
                    </form>
                <?php
                else:
                    echo "No"; ?>
                    <form action='/profile.php' method='POST'>
                        <input class='inline_button stdwidthbutton' id='optout' type='submit' value='OPT IN' name='group' />
                    </form>
                <?php
                endif;
                ?> 
            <h2 id='h2'>Import / Export</h2>
                <b>Import guidelines - read carefully!</b><br>
                <ul>
                    <li>Import will <b>ADD</b> to your existing collection</li>
                    <li>If a card entry already exists, the quantity in the import file will <b>over-write</b> the existing quantity</li>
                    <li>Import file must be a comma-delimited file</li>
                    <li>Delimiters and enclosing should be as the following example (which also shows how to have a name that includes a comma):</li></ul>
        <pre>
        setcode,number,name,normal,foil,id
        M15,2,Ajani's Pridemate,5,0,383181
        M15,3,"Avacyn, Guardian Angel",2,0,383185</pre>
                <ul>
                    <li>The only mandatory fields are setcode and collector number.</li>
                    <li>If id is included and is a valid Scryfall UUID value, the line will be imported as that id without checking anything else, otherwise, name and id are for your reference only</li>
                    <li>Set codes MUST be as per the list <a href='sets.php'> here (Set codes) </a>for successful import</li>
                    <li>Unless you are 100% confident that the cards are in the database, only import normal cards, not promos or specials. There is no consistent naming convention for non-standard sets</li>
                    <li>Check the last line of exported files to make sure that it has been closed properly - it should have terminating quotes and a newline; this can be seen in an app like Notepad++ (don't use Excel)</li>
                    <li>The import process imports a line, then does a follow-up check to see if it has been successfully written to the database</li>
                    <li>A green tick, red cross or warning is shown dependent on each line's result</li>
                    <li>Make a note of any failures for checking, including 'name warnings' where the card has been imported based on setcode and number but the name does not match</li>
                    <li>The process will email you a list of the failures and warnings at the end of the import process</li></ul>
                
                        <span id='importspan'><b>Import</b></span>
                            <?php if (!isset($_POST['import'])): 
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
                        <div id='importdiv'>
                            <form enctype='multipart/form-data' action='?' method='post'>
                                <label class='importlabel'>
                                    <input id='importfile' type='file' name='filename'>
                                    <span>UPLOAD</span>
                                </label>
                                <input class='profilebutton' id='importsubmit' type='submit' name='import' value='IMPORT CSV' disabled>
                            </form> 
                        </div>
                        <?php
                        if (isset($_POST['import'])):
                            $obj = new Message;
                            $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Import called",$logfile);
                            if (is_uploaded_file($_FILES['filename']['tmp_name'])):
                                echo "<br><h4>" . "File ". $_FILES['filename']['name'] ." uploaded successfully. Processing..." . "</h4>";
                                $obj = new Message;
                                $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Import file {$_FILES['filename']['name']} uploaded",$logfile);
                            endif;
                            //Import uploaded file to Database
                            $handle = fopen($_FILES['filename']['tmp_name'], "r");
                            $i = 0;
                            $count = 0;
                            $total = 0;
                            $warningsummary = 'Warning type, Setcode, Number, Import Name, Import Normal, Import Foil, Database Name (if applicable), Database ID (if applicable)'."\n";
                            while (($data = fgetcsv ($handle, 100000, ',')) !== FALSE):
                                $idimport = 0;
                                $row_no = $i + 1;
                                if ($i === 0):
                                    if (       (strpos($data[0],'setcode') === FALSE) 
                                            OR (strpos($data[1],'number') === FALSE) 
                                            OR (strpos($data[2],'name') === FALSE) 
                                            OR (strpos($data[3],'normal') === FALSE) 
                                            OR (strpos($data[4],'foil') === FALSE) 
                                            OR (strpos($data[5],'id') === FALSE)):
                                        echo "<h4>Incorrect file format</h4>";
                                        $obj = new Message;
                                        $obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Import file {$_FILES['filename']['name']} does not contain header row",$logfile);
                                        exit;
                                    endif;
                                elseif(isset($data[0]) AND isset($data[1]) AND isset($data[2])):
                                    $obj = new Message;
                                    $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Row $row_no of import file: setcode({$data[0]}), number({$data[1]}), name ({$data[2]}), normal ({$data[3]}), foil ({$data[4]}), id ({$data[5]})",$logfile);
                                    $data0 = $data[0];
                                    $data1 = $data[1];
                                    $data2 = $data[2];
                                    if (!empty($data[3])): // normal qty
                                        $data3 = $data[3];
                                    else:
                                        $data3 = 0;
                                    endif;
                                    if (!empty($data[4])): // foil qty
                                        $data4 = $data[4];
                                    else:
                                        $data4 = 0;
                                    endif;
                                    $data5 = $data[5]; // id
                                    if (!empty($data5)): // ID has been supplied, run an ID check / import first
                                        echo "Row $row_no: Data has an ID ($data5), checking for a match...<br>";
                                        $obj = new Message;
                                        $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: Data has an ID ($data5), checking for a match",$logfile);
                                        if($getid = $db->select_one('id,foil,nonfoil','cards_scry',"WHERE id = '$data5'")):
                                            $card_normal = $getid['nonfoil'];
                                            $card_foil = $getid['foil'];
                                            if($card_normal != 1 AND $card_foil == 1):
                                                $cardtypes = 'foilonly';
                                                if($data3 > 0 and $data4 === 0):
                                                    echo "Row $row_no: WARNING: This matches to a Foil-only ID, but import contains Normal cards - swapping ($data0, $data1, $data2, $data3, $data4, $db_name, $data5) ";
                                                    echo "<img src='/images/warning.png' alt='Warning'><br>";
                                                    $newwarning = "Foil/Normal warning (swapping), $row_no, $data0, $data1, $data2, $data3, $data4, $db_name, $data5"."\n";
                                                    $warningsummary = $warningsummary.$newwarning;
                                                    // swap $data3 and $data4
                                                    $data3 = $data3 + $data4;
                                                    $data4 = $data3 - $data4;
                                                    $data3 = $data3 - $data4;
                                                elseif($data3 > 0 and $data4 > 0):
                                                    echo "Row $row_no: ERROR: This matches to a Foil-only ID, but import contains Normal AND Foil cards ($data0, $data1, $data2, $data3, $data4, $db_name, $data5) ";
                                                    echo "<img src='/images/error.png' alt='Error'><br>";
                                                    $newwarning = "Foil/Normal error, $row_no, $data0, $data1, $data2, $data3, $data4, $db_name, $data5"."\n";
                                                    $warningsummary = $warningsummary.$newwarning;
                                                    $i = $i + 1;
                                                    continue;
                                                endif;
                                            elseif($card_normal == 1 AND $card_foil != 1):
                                                $cardtypes = 'normalonly';
                                                if($data4 > 0 and $data3 === 0):
                                                    echo "Row $row_no: WARNING: This matches to a Normal-only ID, but import contains Foil cards - swapping ($data0, $data1, $data2, $data3, $data4, $db_name, $data5) ";
                                                    echo "<img src='/images/warning.png' alt='Warning'><br>";
                                                    $newwarning = "Foil/Normal warning, $row_no, $data0, $data1, $data2, $data3, $data4, $db_name, $data5"."\n";
                                                    $warningsummary = $warningsummary.$newwarning;
                                                    // swap $data3 and $data4
                                                    $data3 = $data3 + $data4;
                                                    $data4 = $data3 - $data4;
                                                    $data3 = $data3 - $data4;
                                                elseif($data4 > 0 and $data3 > 0):
                                                    echo "Row $row_no: ERROR: This matches to a Normal-only ID, but import contains Normal AND Foil cards ($data0, $data1, $data2, $data3, $data4, $db_name, $data5) ";
                                                    echo "<img src='/images/error.png' alt='Error'><br>";
                                                    $newwarning = "Foil/Normal error, $row_no, $data0, $data1, $data2, $data3, $data4, $db_name, $data5"."\n";
                                                    $warningsummary = $warningsummary.$newwarning;
                                                    $i = $i + 1;
                                                    continue;
                                                endif;
                                            else:
                                                $cardtypes = 'normalandfoil'; // Keep going
                                            endif;
                                            $obj = new Message;
                                            $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: Match found for ID $data5, will import",$logfile);
                                            echo "Row $row_no: $data0, $data1, $data2, $data5<br>";
                                            $stmt = $db->prepare("  INSERT INTO
                                                                        `$mytable`
                                                                        (id,normal,foil)
                                                                    VALUES
                                                                        (?,?,?)
                                                                    ON DUPLICATE KEY UPDATE
                                                                        id=VALUES(id),normal=VALUES(normal),foil=VALUES(foil)
                                                                ");
                                            if ($stmt === false):
                                                trigger_error('[ERROR] profile.php: Preparing SQL: ' . $db->error, E_USER_ERROR);
                                            endif;
                                            $bind = $stmt->bind_param("sss",
                                                            $data5,
                                                            $data3,
                                                            $data4
                                                        );
                                            if ($bind === false):
                                                trigger_error('[ERROR] profile.php: Binding parameters: ' . $db->error, E_USER_ERROR);
                                            endif;
                                            $exec = $stmt->execute();
                                            if ($exec === false):
                                                trigger_error("[ERROR] profile.php: Importing row $row_no" . $db->error, E_USER_ERROR);
                                            else:
                                                $status = mysqli_affected_rows($db); // 1 = add, 2 = change, 0 = no change
                                                if($status === 1):
                                                    echo "Row $row_no: New, added: $data0, $data1, $data2, $data5<br>";
                                                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: New, imported - no error returned; return code: $status",$logfile);
                                                elseif($status === 2):
                                                    echo "Row $row_no: Updated: $data0, $data1, $data2, $data5<br>";
                                                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: Updated - no error returned; return code: $status",$logfile);
                                                else:
                                                    $obj = new Message;
                                                    $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: No change - no error returned; return code: $status",$logfile);
                                                endif;
                                            endif;
                                            $stmt->close();
                                            if($status === 1 OR $status === 2 OR $status === 0):
                                                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: Import query ran - checking",$logfile);
                                                if($sqlcheck = $db->select_one('normal,foil',$mytable,"WHERE id = '$data5'")):
                                                    $obj = new Message;
                                                    $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: Check result = Normal: {$sqlcheck['normal']}; Foil: {$sqlcheck['foil']}",$logfile);
                                                    echo "Row $row_no: Normal: ",$sqlcheck['normal']," Foil: ",$sqlcheck['foil']."<br>";
                                                    if (($sqlcheck['normal'] == $data3) AND ($sqlcheck['foil'] == $data4)):
                                                        echo "Row $row_no: Normal: ID import OK: <img src='/images/success.png' alt='Success'><br>";
                                                        $total = $total + $sqlcheck['normal'] + $sqlcheck['foil'];
                                                        $count = $count + 1;
                                                        $idimport = 1;?>
                                                    <?php
                                                    else: ?>
                                                        <img src='/images/error.png' alt='Failure'><br>
                                                        <?php
                                                    endif;
                                                else:
                                                    trigger_error("[ERROR]: SQL failure: " . $db->error, E_USER_ERROR);
                                                endif;
                                            endif;
                                        else:
                                            $obj = new Message;
                                            $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: ID $data5 does not map to a card in database, falling back to setcode/number...",$logfile);
                                            echo "Row $row_no: ID $data5 does not map to a card in database, falling back to setcode/number<br>";
                                        endif;
                                    endif;
                                    if (!empty($data0) AND !empty($data1) AND $idimport === 0):
                                        $obj = new Message;
                                        $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: Data place 1 (setcode - $data0) and place 2 (number - $data1) without ID - getting ID",$logfile);
                                        echo "Row $row_no:  Data in Row $row_no has no matched ID, using setcode and number<br>";
                                        if($getid = $db->select_one('id,name,foil,nonfoil','cards_scry',"WHERE setcode = '$data0' AND number_import = '$data1'")):
                                            $data5 = $getid['id'];
                                            $db_name = $getid['name'];
                                            $card_normal = $getid['nonfoil'];
                                            $card_foil = $getid['foil'];
                                            if($card_normal != 1 AND $card_foil == 1):
                                                $cardtypes = 'foilonly';
                                                if($data3 > 0 and $data4 === 0):
                                                    echo "Row $row_no: WARNING: This matches to a Foil-only ID, but import contains Normal cards - swapping ($data0, $data1, $data2, $data3, $data4, $db_name, $data5) ";
                                                    echo "<img src='/images/warning.png' alt='Warning'><br>";
                                                    $newwarning = "Foil/Normal warning (swapping), $row_no, $data0, $data1, $data2, $data3, $data4, $db_name, $data5"."\n";
                                                    $warningsummary = $warningsummary.$newwarning;
                                                    // swap $data3 and $data4
                                                    $data3 = $data3 + $data4;
                                                    $data4 = $data3 - $data4;
                                                    $data3 = $data3 - $data4;
                                                elseif($data3 > 0 and $data4 > 0):
                                                    echo "Row $row_no: ERROR: This matches to a Foil-only ID, but import contains Normal AND Foil cards ($data0, $data1, $data2, $data3, $data4, $db_name, $data5) ";
                                                    echo "<img src='/images/error.png' alt='Error'><br>";
                                                    $newwarning = "Foil/Normal error, $row_no, $data0, $data1, $data2, $data3, $data4, $db_name, $data5"."\n";
                                                    $warningsummary = $warningsummary.$newwarning;
                                                    $i = $i + 1;
                                                    continue;
                                                endif;
                                            elseif($card_normal == 1 AND $card_foil != 1):
                                                $cardtypes = 'normalonly';
                                                if($data4 > 0 and $data3 === 0):
                                                    echo "Row $row_no: WARNING: This matches to a Normal-only ID, but import contains Foil cards - swapping ($data0, $data1, $data2, $data3, $data4, $db_name, $data5) ";
                                                    echo "<img src='/images/warning.png' alt='Warning'><br>";
                                                    $newwarning = "Foil/Normal warning, $row_no, $data0, $data1, $data2, $data3, $data4, $db_name, $data5"."\n";
                                                    $warningsummary = $warningsummary.$newwarning;
                                                    // swap $data3 and $data4
                                                    $data3 = $data3 + $data4;
                                                    $data4 = $data3 - $data4;
                                                    $data3 = $data3 - $data4;
                                                elseif($data4 > 0 and $data3 > 0):
                                                    echo "Row $row_no: ERROR: This matches to a Normal-only ID, but import contains Normal AND Foil cards ($data0, $data1, $data2, $data3, $data4, $db_name, $data5) ";
                                                    echo "<img src='/images/error.png' alt='Error'><br>";
                                                    $newwarning = "Foil/Normal error, $row_no, $data0, $data1, $data2, $data3, $data4, $db_name, $data5"."\n";
                                                    $warningsummary = $warningsummary.$newwarning;
                                                    $i = $i + 1;
                                                    continue;
                                                endif;
                                            else:
                                                $cardtypes = 'normalandfoil'; // Keep going
                                            endif;
                                            $obj = new Message;
                                            $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: ID for row $i place 1 ($data0) and place 2 ($data1) is $data5",$logfile);
                                            if (!empty($data5)):
                                                echo "Row $row_no: $data0, $data1, $data2, ,$data5<br>";
                                                $stmt = $db->prepare("  INSERT INTO
                                                                            `$mytable`
                                                                            (id,normal,foil)
                                                                        VALUES
                                                                            (?,?,?)
                                                                        ON DUPLICATE KEY UPDATE
                                                                            id=VALUES(id),normal=VALUES(normal),foil=VALUES(foil)
                                                                    ");
                                                if ($stmt === false):
                                                    trigger_error('[ERROR] profile.php: Preparing SQL: ' . $db->error, E_USER_ERROR);
                                                endif;
                                                $bind = $stmt->bind_param("sss",
                                                                $data5,
                                                                $data3,
                                                                $data4
                                                            );
                                                if ($bind === false):
                                                    trigger_error('[ERROR] profile.php: Binding parameters: ' . $db->error, E_USER_ERROR);
                                                endif;
                                                $exec = $stmt->execute();
                                                if ($exec === false):
                                                    trigger_error("[ERROR] profile.php: Importing row $row_no" . $db->error, E_USER_ERROR);
                                                else:
                                                    $status = mysqli_affected_rows($db); // 1 = add, 2 = change, 0 = no change
                                                    if($status === 1):
                                                        echo "Row $row_no: New, added: $data0, $data1, $data2, $data5<br>";
                                                        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: New, imported - no error returned; return code: $status",$logfile);
                                                    elseif($status === 2):
                                                        echo "Row $row_no: Updated: $data0, $data1, $data2, $data5<br>";
                                                        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: Updated - no error returned; return code: $status",$logfile);
                                                    else:
                                                        $obj = new Message;
                                                        $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: No change - no error returned; return code: $status",$logfile);
                                                    endif;
                                                endif;
                                                $stmt->close();
                                                if($status === 1 OR $status === 2 OR $status === 0):
                                                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: Import query ran OK - checking...",$logfile);
                                                    if($sqlcheck = $db->select_one('normal,foil',$mytable,"WHERE id = '$data5'")):
                                                        $obj = new Message;
                                                        $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: Check result = Normal: {$sqlcheck['normal']}; Foil: {$sqlcheck['foil']}",$logfile);
                                                        echo "Row $row_no: Normal: ".$sqlcheck['normal']." Foil: ".$sqlcheck['foil']." <br>";
                                                        if (($sqlcheck['normal'] == $data3) AND ($sqlcheck['foil'] == $data4)):
                                                            echo "Row $row_no: Normal: Setcode/number import OK: <img src='/images/success.png' alt='Success'><br>";
                                                            $total = $total + $sqlcheck['normal'] + $sqlcheck['foil'];
                                                            $count = $count + 1;
                                                            if($db_name != $data2):
                                                                if(strpos($db_name,' // ') !== false): // Card is a double name or flip card in the database
                                                                    if(strpos((string)$db_name,(string)$data2) === 0):
                                                                        // Card is a double name or flip card in the database, and the database name starts with the full import name
                                                                    endif;
                                                                else: // Card is NOT a double name or flip card in the database, or otherwise the compare has failed, so a name variation is a concern
                                                                    echo "WARNING: Imported on setcode/number, but name in import file ($data2) does not match database name ($db_name) ";
                                                                    echo "<img src='/images/warning.png' alt='Warning'><br>";
                                                                    $newwarning = "Name warning, $row_no, $data0, $data1, $data2, $data3, $data4, $db_name, $data5"."\n";
                                                                    $warningsummary = $warningsummary.$newwarning;
                                                                endif;
                                                            endif;
                                                        else: ?>
                                                            <img src='/images/error.png' alt='Failure'><br>
                                                            <?php
                                                        endif;
                                                    else:
                                                        trigger_error("[ERROR]: SQL failure: " . $db->error, E_USER_ERROR);
                                                    endif;
                                                endif;
                                            else:
                                                echo "Row ",$i+1,": Setcode $data0 and number $data1 do not map to a card in database <img src='/images/error.png' alt='Failure'><br>";
                                                $newwarning = "Failure, $row_no, $data0, $data1, $data2, $data3, $data4"."\n";
                                                $warningsummary = $warningsummary.$newwarning;
                                            endif;
                                        else:
                                            echo "Row ",$i+1,": Setcode $data0 and number $data1 do not map to a card in database <img src='/images/error.png' alt='Failure'><br>";
                                            $newwarning = "Failure, $row_no, $data0, $data1, $data2, $data3, $data4"."\n";
                                            $warningsummary = $warningsummary.$newwarning;
                                        endif;
                                    elseif($idimport === 1):
                                        // do nothing
                                    else:
                                        echo "Row ",$i+1,": Check row - not enough data to identify card <img src='/images/error.png' alt='Failure'><br>";
                                        $newwarning = "Failure, $row_no, Check row - not enough data to identify card"."\n";
                                        $warningsummary = $warningsummary.$newwarning;
                                    endif;
                                else:
                                    echo "Row ",$i+1,": Row reached without 3 data items, stopping <img src='/images/warning.png' alt='Warning'><br>";
                                endif;
                                $i = $i + 1;
                            endwhile;
                            fclose($handle);
                            print "Import done - $count unique cards, $total in total.";
                            $from = "From: $serveremail\r\nReturn-path: $serveremail"; 
                            $subject = "Import failures / warnings"; 
                            $message = "$warningsummary";
                            mail($useremail, $subject, $message, $from); 
                        else: ?>
                            <div id='exportdiv'>
                                <form action="csv.php"  method="GET">
                                    <input id='exportsubmit' class='profilebutton' type="submit" value="EXPORT CSV">
                                    <?php echo "<input type='hidden' name='table' value='$mytable'>"; ?>
                                </form>
                            </div>
                        <?php
                        endif;
                        ?>
        </div>
        <?php endif; ?>
        <br>&nbsp;<br>
    </div>
</div>

<?php 
require('includes/footer.php'); 
?>
</body>
</html>