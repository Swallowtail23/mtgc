<?php 
/* Version:     9.0
    Date:       17/12/23
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
 *  8.0
 *              Toggles for options (calling ajax updates)
 * 
 *  9.0         17/12/23
 *              Add currency selection
*/
ini_set('session.name', '5VDSjp7k-n-_yS-_');
session_start();
require ('includes/ini.php');               //Initialise and load ini file
require ('includes/error_handling.php');
require ('includes/functions.php');     //Includes basic functions for non-secure pages
require ('includes/secpagesetup.php');      //Setup page variables
$msg = new Message;

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
$msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Checking if user has a collection table...",$logfile);
if($db->query($tablecheck) === FALSE):
    $msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"No existing collection table...",$logfile);
    $query2 = "CREATE TABLE `$mytable` LIKE collectionTemplate";
    $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"...copying collection template...: $query2",$logfile);
    if($db->query($query2) === TRUE):
        $msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Collection template copy successful",$logfile);
    else:
        $msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Collection template copy failed",$logfile);
    endif;
endif;

//1. Get user details for current user
if($rowqry = $db->execute_query("SELECT username, password, email, reg_date, status, admin,
                                        groupid, grpinout, groupname, collection_view, currency 
                                    FROM users 
                                    LEFT JOIN `groups` 
                                    ON users.groupid = groups.groupnumber 
                                    WHERE usernumber = ? LIMIT 1",[$user])):
    $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"SQL query for user details succeeded",$logfile);
    $row = $rowqry->fetch_assoc();
else:
    trigger_error('[ERROR] profile.php: Error: '.$db->error, E_USER_ERROR);
endif;  ?>
    
<div id='page'>
    <div class='staticpagecontent'>
        <?php 
        //Page PHP processing

        //2. Has a password reset been called? Needs to be in DIV for error display
        if (isSet($_POST['changePass']) AND isSet($_POST['newPass']) AND isSet($_POST['newPass2']) AND isSet($_POST['curPass'])):
            if (!empty($_POST['curPass']) AND !empty($_POST['newPass']) AND !empty($_POST['newPass2'])):
                $new_password = $_POST['newPass'];
                $new_password_2 = $_POST['newPass2'];
                $old_password = $_POST['curPass'];
                $db_password = $row['password'];
                if ($new_password == $new_password_2):
                    $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"New passwords double type = match",$logfile);
                    if (valid_pass($new_password)):
                        $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"New password is a valid password",$logfile);
                        if($new_password != $old_password):
                            $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"New password is different to old password",$logfile);
                            if (password_verify($old_password, $db_password)):
                                $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Old password is correct",$logfile);
                                $new_password = password_hash("$new_password", PASSWORD_DEFAULT);
                                $data = array(
                                    'password' => "$new_password"
                                );
                                $pwdchg = $db->execute_query("UPDATE users SET password = ? WHERE email = ?",[$new_password,$useremail]);
                                $msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Password change call for $useremail from {$_SERVER['REMOTE_ADDR']}",$logfile);
                                if ($pwdchg === false):
                                    trigger_error('[ERROR] profile.php: Error: '.$db->error, E_USER_ERROR);
                                endif;
                                $pwdvalidateqry = $db->execute_query("SELECT password FROM users WHERE email = ?",[$useremail]);
                                if($pwdvalidateqry === false):
                                    trigger_error('[ERROR] profile.php: Error: '.$db->error, E_USER_ERROR);
                                else:
                                    $pwdvalidate = $pwdvalidateqry->fetch_assoc();
                                    if ($pwdvalidate['password'] == $new_password):
                                        $msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Confirmed new password written to database for $useremail from {$_SERVER['REMOTE_ADDR']}",$logfile);
                                        echo "<div class='alert-box success' id='pwdchange'><span>success: </span>Password successfully changed, please log in again</div>";
                                        // Clear the force password flag and session variable
                                        $chgflagclear = $db->execute_query("UPDATE users SET status = 'active' WHERE email = ?",[$useremail]);
                                        if ($chgflagclear === false):
                                            trigger_error('[ERROR] profile.php: Error: '.$db->error, E_USER_ERROR);
                                        else:
                                            $_SESSION['chgpwd'] = '';
                                        endif;
                                        session_destroy();
                                        echo "<meta http-equiv='refresh' content='4;url=login.php'>";
                                        exit();
                                    else:
                                        echo "<div class='alert-box error' id='pwdchange'><span>error: </span>Password change failed... contact support</div>";
                                        $msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"New password not verified from database for $useremail from {$_SERVER['REMOTE_ADDR']}",$logfile);
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
            $msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Enforcing password change for $useremail from {$_SERVER['REMOTE_ADDR']}",$logfile);
        endif;
    //4. Collection view
        $current_coll_view = $row['collection_view'];
        $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Collection view is '$current_coll_view'",$logfile);

    //5. Groups
        $current_group_status = $row['grpinout'];
        $current_group = $row['groupid'];
        $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Groups are '$current_group_status', group id '$current_group'",$logfile);

    //6. Currency
        $current_currency = $row['currency'];
        $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Current currency is '$current_currency'",$logfile);
                
    //7. Update pricing in case any new cards have been added to collection
        //Make sure only number+collection is passed as table name
        if (valid_tablename($mytable) !== false):
            $obj = new PriceManager($db,$logfile,$useremail);
            $obj->updateCollectionValues($mytable);
        else:
            trigger_error("[ERROR] valueupdate.php: Invalid table format", E_USER_ERROR);
        endif;
        
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
        $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Total card count = $totalcardcount",$logfile);
        if($totalmrcount = $db->query("SELECT (SUM(IFNULL(`$mytable`.normal, 0)) + SUM(IFNULL(`$mytable`.foil, 0)) + SUM(IFNULL(`$mytable`.etched, 0))) 
                                        as TOTALMR FROM `$mytable` LEFT JOIN cards_scry ON `$mytable`.id = cards_scry.id
                                        WHERE rarity IN ('mythic', 'rare');")):
            $rowmrcount = $totalmrcount->fetch_array(MYSQLI_ASSOC);
        else:
            trigger_error('[ERROR] profile.php: Error: '.$db->error, E_USER_ERROR);
        endif;
        if(is_null($rowmrcount['TOTALMR'])):
            $totalmrcardcount = 0;
        else:
            $totalmrcardcount = $rowmrcount['TOTALMR'];
        endif;
        $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Total mythics and rares count = $totalmrcardcount",$logfile);
        
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
            $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Unformatted value = $unformatted_value",$logfile);
        else:
            trigger_error('[ERROR] profile.php: Error: '.$db->error, E_USER_ERROR);
        endif;

        //Page display content ?>
        <div id="userdetails">
            <h2 class='h2pad'>User details</h2>
            <b>Email: </b><?php echo $row['email']; ?> <br>
            <b>Account status: </b> <?php echo $row['status']; ?> <br>
            <b>Registered date: </b> <?php echo $row['reg_date']; ?>
        </div>
        <div id="mycollection">
            <h2 class='h2pad'>My Collection</h2>
            <?php
                $a = new \NumberFormatter("en-US", \NumberFormatter::CURRENCY);
                $collectionmoney = $a->format($unformatted_value);
                $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Formatted value = $collectionmoney",$logfile);
                $collectionvalue = "Total value approximately <br>US ".$collectionmoney;
                $rowcounttotal = number_format($totalcardcount);
                $totalmrcardcount = number_format($totalmrcardcount);
                if(isset($rate) AND $rate > 0):
                    $b = new \NumberFormatter("en-US", \NumberFormatter::CURRENCY);
                    $b->setTextAttribute(\NumberFormatter::CURRENCY_CODE, $targetCurrency);
                    $currencySymbol = $b->getSymbol(\NumberFormatter::CURRENCY_SYMBOL);
                    $localvalue = $b->format($unformatted_value * $rate);
                    echo "$collectionvalue ($localvalue) <br>over $rowcounttotal cards ($totalmrcardcount M/R).<br>";
                else:
                    echo "$collectionvalue over $rowcounttotal cards.<br>";
                endif;
                
                
                echo "This is based on pricing from <a href='https://www.scryfall.com/' target='_blank'>scryfall.com</a>, obtained from tcgplayer.com 'market price'.<br>";
                $rowcounttotal = number_format($totalcardcount);
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
            <div id='profilebuttons'>
                <script type="text/javascript">
                    $(document).ready(function () {
                        document.body.style.cursor='normal';
                        
                        // Toggle collection view
                        $('#cview_toggle').on('change', function () {
                            var cview = this.checked ? "TURN ON" : "TURN OFF";
                            $.ajax({
                                url: "/ajax/ajaxcview.php",
                                method: "POST",
                                data: { "collection_view": cview },
                                error: function (jqXHR, textStatus, errorThrown) {
                                    console.error("AJAX error: " + textStatus + " - " + errorThrown);
                                }
                            });
                        });

                        // Toggle group
                        $('#group_toggle').on('change', function () {
                            var group = this.checked ? "OPT IN" : "OPT OUT";
                            var display = this.checked ? "" : "none";
                            $.ajax({
                                url: "/ajax/ajaxgroup.php",
                                method: "POST",
                                data: { "group": group },
                                success: function () {
                                    document.getElementById("grpname").style.display = display;
                                }
                            });
                        });

                        // Flash effect for currency select
                        $('#currencySelect').on('change', function () {
                            var selectedCurrency = $(this).val();
                            $.ajax({
                                url: "/ajax/ajaxcurrency.php",
                                method: "GET",
                                data: { "currency": selectedCurrency },
                                success: function (data) {
                                    var response = JSON.parse(data);
                                    console.log(response);
                                    $('#currencySelect').addClass('flash-success');
                                    setTimeout(function () {
                                        $('#currencySelect').removeClass('flash-success');
                                    }, 1000);
                                },
                                error: function (jqXHR, textStatus, errorThrown) {
                                    console.log("AJAX error: " + textStatus + ' : ' + errorThrown);
                                }
                            });
                        });
                        
                        $("#importsubmit").attr('disabled',true);
                        $("#importfile").change(function() {
                            if ($(this).val()){
                                $("#importsubmit").removeAttr('disabled'); 
                            }
                            else {
                                $("#importsubmit").attr('disabled',true);
                            }
                        });
                    });
                    
                    function ImportPrep() {
                        alert('Import can take several minutes, please be patient...');
                        document.body.style.cursor='wait';
                    };
                </script> 
                <h2 class='h2pad'>Options</h2>
                <table>
                    <tr>
                        <td class="options_left">
                            <b>Collection view:</b> Show black and white images in the grid view for cards you do not own
                        </td>
                        <td class="options_right"> <?php 
                            if($current_coll_view == 1): ?>
                                <label class="switch"> 
                                    <input type="checkbox" id="cview_toggle" class="option_toggle" checked="true" value="on"/>
                                <div class="slider round"></div>
                                </label> <?php
                            else: ?>
                                <label class="switch"> 
                                    <input type="checkbox" id="cview_toggle" class="option_toggle" value="off"/>
                                <div class="slider round"></div>
                                </label> <?php
                            endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="options_left">
                            <b>Group cards:</b> Shows you cards owned by others in your 'group', and them yours. If you Opt Out of Groups then your collection is private<br>
                            <?php 
                            if($current_group_status == 1):
                                echo "<span id='grpname'><b>Group:</b> {$row['groupname']} (<a href='help.php'>Send me a request</a> to create a new group)</span>&nbsp;"; 
                            else:
                                echo "<span id='grpname' style='display:none'><b>Group:</b> {$row['groupname']} (<a href='help.php'>Send me a request</a> to create a new group)</span>&nbsp;"; 
                            endif; ?>
                        </td>
                        <td class="options_right"> <?php 
                            if($current_group_status == 1): ?>
                                <label class="switch"> 
                                    <input type="checkbox" id="group_toggle" class="option_toggle" checked="true" value="on"/>
                                <div class="slider round"></div>
                                </label> <?php
                            else: ?>
                                <label class="switch"> 
                                    <input type="checkbox" id="group_toggle" class="option_toggle" value="off"/>
                                <div class="slider round"></div>
                                </label> <?php
                            endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="options_left">
                            <b>Local currency:</b> Set the currency you want to use for localised pricing<br>
                        </td>
                        <td class="options_right">  
                            <select class="dropdown" name='currency' id='currencySelect'>
                                <?php foreach($currencies as $currency): ?>
                                    <option value='<?php echo $currency['code']; ?>' 
                                        <?php if($current_currency === $currency['db']): ?>selected<?php endif; ?>>
                                        <?php echo $currency['pretty']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>
            <div id='importexport'>
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
                    $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Import called, checking file uploaded...",$logfile);
                    if (is_uploaded_file($_FILES['filename']['tmp_name'])):
                        echo "<br><h4>" . "File ". $_FILES['filename']['name'] ." uploaded successfully. Processing..." . "</h4>";
                        $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Import file {$_FILES['filename']['name']} uploaded",$logfile);
                    else:
                        echo "<br><h4>" . "File ". $_FILES['filename']['name'] ." did not upload successfully. Exiting..." . "</h4>";
                        $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Import file {$_FILES['filename']['name']} failed",$logfile);
                        exit;
                    endif;
                    $importfile = $_FILES['filename']['tmp_name'];
                    $obj = new ImportExport($db,$logfile);
                    $importcards = $obj->importCollection($importfile, $mytable, $useremail, $serveremail);
                    echo "<meta http-equiv='refresh' content='0;url=profile.php'>";
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