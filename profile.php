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
?>

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

        $sqlvalue = "SELECT (SUM(`$mytable`.normal * price) + SUM(`$mytable`.foil * price_foil)) as TOTAL FROM `$mytable` LEFT JOIN cards_scry ON `$mytable`.id = cards_scry.id";
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
                        Import will ADD to your existing collection. If a card entry already exists, the quantity in the import file will over-write the existing quantity.<br>
                        Import file must be a comma-delimited file.<br>
                        Delimiters and enclosing should be as the following example (which also shows how to have a name that includes a comma):<br><br>
                        <pre>
        setcode,number,name,normal,foil,id
        M15,2,Ajani's Pridemate,5,0,383181
        M15,3,"Avacyn, Guardian Angel",2,0,383185
                        </pre>
                        The only mandatory fields are setcode and collector number. 
                        Name and id are for your reference only. <br>
                        Set codes MUST be as per the following list for successful import:<br>
                        <a href='sets.php'>Set codes</a><br>

                        Unless you are 100% confident that the cards are in the database, only import normal cards, not promos or specials. There<br>
                        is no consistent naming convention for non-standard sets.<br>
                        Check the last line of exported files to make sure that it has been closed properly - it should have terminating quotes and a newline.<br>
                        This can be seen in an app like Notepad++ (don't use Excel).<br>
                        <br>
                        The import process imports a line, then does a follow-up check to see if it has been successfully written to the database. <br>
                        A green tick or a red cross is then shown dependent on the result.
                        Make a note of any failures for checking.<br>
                
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
                            while (($data = fgetcsv ($handle, 100000, ',')) !== FALSE):
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
                                    $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Row $i of import file contains minimum data - escaping data",$logfile);
                                    $data0 = $db->real_escape_string($data[0]);
                                    $data1 = $db->real_escape_string($data[1]);
                                    $data2 = $db->real_escape_string($data[2]);
                                    if (!empty($data[3])):
                                        $data3 = $db->real_escape_string($data[3]);
                                    else:
                                        $data3 = 0;
                                    endif;
                                    if (!empty($data[4])):
                                        $data4 = $db->real_escape_string($data[4]);
                                    else:
                                        $data4 = 0;
                                    endif;
                                    $data5 = $db->real_escape_string($data[5]);
                                    if (!empty($data0) AND !empty($data1)):
                                        $obj = new Message;
                                        $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Data in Row $i place 1 ($data0) and place 2 ($data1) - getting ID",$logfile);
                                        if($getid = $db->select_one('id','cards',"WHERE setcode = '$data0' AND number = '$data1'")):
                                            $data5 = $getid['id'];
                                            $obj = new Message;
                                            $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"ID for row $i place 1 ($data0) and place 2 ($data1) is $data5",$logfile);
                                            // If the sets contain flip cards, check that we are not importing the backs, and assign the correct ID
                                            $data5 = importmapcheck($data5);
                                            $obj = new Message;
                                            $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Post flip-map ID for row $i place 1 ($data0) and place 2 ($data1) is $data5",$logfile);
                                            if (!empty($data5)):
                                                echo "Row ",$i+1,": ",$data0," ",$data1," ",$data[2]," ",$data5;
                                                $import="INSERT into `$mytable` (id,normal,foil) values('$data5','$data3','$data4') ON DUPLICATE KEY UPDATE normal='$data3', foil='$data4'";
                                                $obj = new Message;
                                                $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Import query is $import",$logfile);
                                                if($runimport = $db->query($import)):
                                                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Import query ran OK - checking...",$logfile);
                                                    if($sqlcheck = $db->select_one('normal,foil',$mytable,"WHERE id = '$data5'")):
                                                        $obj = new Message;
                                                        $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Check result = Normal: {$sqlcheck['normal']}; Foil: {$sqlcheck['foil']}",$logfile);
                                                        echo " Normal: ",$sqlcheck['normal']," Foil: ",$sqlcheck['foil'];
                                                        if (($sqlcheck['normal'] == $data3) AND ($sqlcheck['foil'] == $data4)): ?>
                                                            <img src='/images/success.png' alt='Success'><br>
                                                            <?php $total = $total + $sqlcheck['normal'] + $sqlcheck['foil']?>
                                                            <?php $count = $count + 1?>
                                                        <?php
                                                        else: ?>
                                                            <img src='/images/error.png' alt='Failure'><br>
                                                            <?php
                                                        endif;
                                                    else:
                                                        trigger_error("[ERROR]: SQL failure: " . $db->error, E_USER_ERROR);
                                                    endif;
                                                else:    
                                                    trigger_error("[ERROR]: SQL failure: " . $db->error, E_USER_ERROR);
                                                endif;
                                            else:
                                                echo "Row ",$i+1,": Setcode $data0 and number $data1 do not map to a card in database <img src='/images/error.png' alt='Failure'><br>";
                                            endif;
                                        else:
                                            trigger_error("[ERROR]: SQL failure: " . $db->error, E_USER_ERROR);
                                        endif;
                                    else:
                                        echo "Row ",$i+1,": Check row - not enough data to identify card <img src='/images/error.png' alt='Failure'><br>";
                                    endif;
                                else:
                                    echo "Row ",$i+1,": Row reached without 3 data items, stopping <img src='/images/error.png' alt='Failure'><br>";
                                endif;
                                $i = $i + 1;
                            endwhile;
                            fclose($handle);
                            print "Import done - $count unique cards, $total in total.";
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