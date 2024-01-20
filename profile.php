<?php 
/* Version:     11.1
    Date:       20/01/24
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
 * 
 * 10.0         10/01/24
 *              Rewrite of import display and code
 *              Add Delver Lens import
 * 
 * 11.0         13/01/24
 *              Add email export for CSV
 * 
 * 11.1         20/01/24
 *              Move to logMessage
 */

if (file_exists('includes/sessionname.php')):
    require('includes/sessionname.php');
else:
    require('includes/sessionname_template.php');
endif;
startCustomSession();
require ('includes/ini.php');               //Initialise and load ini file
require ('includes/error_handling.php');
require ('includes/functions.php');     //Includes basic functions for non-secure pages
require ('includes/secpagesetup.php');      //Setup page variables
$msg = new Message($logfile);

// Has DELETE collection been called? 
$deletecollection = (isset($_GET['deletecollection']) && $_GET['deletecollection'] === 'DELETE') ? 'DELETE' : '';
$delcollresult = ''; // Variable to hold error message

// If redirected back to this page after a successful CSV export
if(isset($_GET['csvsuccess']) && $_GET['csvsuccess'] === 'true'):
    $csvsuccess = 'true';
elseif(isset($_GET['csvsuccess']) && $_GET['csvsuccess'] === 'false'):
    $csvsuccess = 'true';
else:
    $csvsuccess = '';
endif;

if ($deletecollection === 'DELETE'):    
    $msg->logMessage('[DEBUG]',"Called to delete collection '$mytable'");
    $obj = new ImportExport($db,$logfile,$useremail,$serveremail);
    $msg->logMessage('[DEBUG]',"Exporting collection to email...");
    $obj->exportCollectionToCsv($mytable, $myURL, $smtpParameters, 'email');
    $msg->logMessage('[DEBUG]',"Truncating collection table...");
    if(!$db->execute_query("TRUNCATE TABLE `$mytable`")):
        $msg->logMessage('[ERROR]',"Truncate table failed");
        $delcollresult = "Error: Failed to delete collection";
    else:
        $msg->logMessage('[DEBUG]',"PRG with success parameter...");
        $delcollresult = "Success: Deleted collection";
    endif;
else:
    $msg->logMessage('[DEBUG]',"Normal page load...");
endif; 

?> 

<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="initial-scale=1">
        <title>MtG collection - profile</title>
        <link rel="manifest" href="manifest.json" />
        <link rel="stylesheet" type="text/css" href="css/style<?php echo $cssver?>.css"> 
        <?php include('includes/googlefonts.php');?>
        <script src="/js/jquery.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var csvSuccess = "<?php echo $csvsuccess; ?>";
                if (csvSuccess === 'true') {
                    alert("CSV email send was successful.");
                    window.location.href = "<?php echo $myURL; ?>/profile.php";
                }
                else if (csvSuccess === 'false') {
                    alert("ERROR: CSV email send was NOT successful.");
                    window.location.href = "<?php echo $myURL; ?>/profile.php";
                }
            });
            // Function to toggle the visibility of the info box
            function toggleInfoBox() {
                var infoBox = document.getElementById("infoBox");
                infoBox.style.display = (infoBox.style.display === "none" || infoBox.style.display === "") ? "block" : "none";
            }
        </script>
    </head>

    <body> <?php 
        include_once("includes/analyticstracking.php");
        require('includes/overlays.php');  
        require('includes/header.php');
        require('includes/menu.php'); ?>

        <!-- Info box -->
        <div class="info-box" id="infoBox" style="display:none">
            <span class="close-button-profile material-symbols-outlined" onclick="toggleInfoBox()">close</span>
            <div class="info-box-inner">
                <h2 class="h2-no-top-margin">Import help</h2>
                <ul>
                    <li>Select 'Add to existing cards' or 'Replace existing cards' to either add imports to existing quantities or  overwrite existing quantities</li>
                    <li>Import file must be a comma-delimited file (csv); e.g.:</li>
                </ul>
                <pre>
          setcode,number,name,normal,foil,etched,id
          LTR,3,Bill the Pony,5,0,0,9ac68519-ed7f-4f38-9549-c02975f88eed
          LTR,4,"Card, name",2,0,0,f6bc3720-2892-4dda-8f30-079a1ac8e1e2</pre>
                <ul>
                    <li>Delver Lens lists can be imported in the CSV export format of</li>
                </ul>
                <pre>
          'Edition code','Collector's number','Name','Non-foil quantity'
          'Foil quantity','Scryfall ID'</pre>
                <ul>
                    <li><u>Do not import etched files with Delver Lens</u>, it flags etched foils as separate cards instead of variations of a card</li>
                    <li>If "id" is a valid Scryfall UUID value, the line will be imported as that id <i>without checking anything else</i></li>
                    <li>If a Scryfall UUID cannot be matched, import will try and match on setcode, name and collector number. If this fails the row will be skipped</li>
                    <li>Set codes and collector numbers MUST be as on this site (see <a href='sets.php'> for Set codes </a>) for successful import</li>
                    <li>For an example of the right format export first and use that file as a template</li>
                    <li>Edit CSVs in an app like Notepad++ (<b>don't use Excel</b>)</li>
                    <li>You will be sent a list of the failures and warnings at the end of the import</li>
                </ul>
            </div>
        </div> <?php

        $import = isset($_POST['import']) ? 'yes' : '';

        $valid_importType = ['add','replace'];
        $importType = isset($_POST['importscope']) ? "{$_POST['importscope']}" : '';
        if (!in_array($importType,$valid_importType)):
            $importType = '';
        endif;

        $valid_format = ['mtgc','delverlens'];
        $importFormat = isset($_POST['format']) ? $_POST['format'] : '';
        if (!in_array($importFormat,$valid_format)):
            $importFormat = '';
        endif;

        // Does the user have a collection table?
        $tableExistsQuery = "SHOW TABLES LIKE '$mytable'";
        $msg->logMessage('[DEBUG]', "Checking if user has a collection table...");

        $result = $db->query($tableExistsQuery);
        if ($result->num_rows == 0):
            $msg->logMessage('[NOTICE]', "No existing collection table...");
            $query2 = "CREATE TABLE `$mytable` LIKE collectionTemplate";
            $msg->logMessage('[DEBUG]', "Copying collection template...: $query2");

            if ($db->query($query2) === TRUE):
                $msg->logMessage('[NOTICE]', "Collection template copy successful");
            else:
                $msg->logMessage('[NOTICE]', "Collection template copy failed: " . $db->error);
            endif;
        else:
            $msg->logMessage('[DEBUG]', "Collection table exists");
        endif;
        
        //1. Get user details for current user
        if($rowqry = $db->execute_query("SELECT username, password, email, reg_date, status, admin,
                                                groupid, grpinout, groupname, collection_view, 
                                                currency, weeklyexport
                                            FROM users 
                                            LEFT JOIN `groups` 
                                            ON users.groupid = groups.groupnumber 
                                            WHERE usernumber = ? LIMIT 1",[$user])):
            $msg->logMessage('[DEBUG]',"SQL query for user details succeeded");
            $row = $rowqry->fetch_assoc();
        else:
            trigger_error('[ERROR] profile.php: Error: '.$db->error, E_USER_ERROR);
        endif;  ?>

        <div id='page'>
            <div class='staticpagecontent'>
                <?php 
                $msg->logMessage('[ERROR]',"delcollresult is '$delcollresult'");
                if (!empty($delcollresult)):
                    echo "<script>alert('$delcollresult');</script>";
                    echo "<meta http-equiv='refresh' content='0; url=profile.php'>";
                endif;
                //Page PHP processing

                //2. Has a password reset been called? Needs to be in DIV for error display
                if (isSet($_POST['changePass']) AND isSet($_POST['newPass']) AND isSet($_POST['newPass2']) AND isSet($_POST['curPass'])):
                    if (!empty($_POST['curPass']) AND !empty($_POST['newPass']) AND !empty($_POST['newPass2'])):
                        $new_password = $_POST['newPass'];
                        $new_password_2 = $_POST['newPass2'];
                        $old_password = $_POST['curPass'];
                        $db_password = $row['password'];
                        if ($new_password == $new_password_2):
                            $msg->logMessage('[DEBUG]',"New passwords double type = match");
                            if (valid_pass($new_password)):
                                $msg->logMessage('[DEBUG]',"New password is a valid password");
                                if($new_password != $old_password):
                                    $msg->logMessage('[DEBUG]',"New password is different to old password");
                                    if (password_verify($old_password, $db_password)):
                                        $msg->logMessage('[DEBUG]',"Old password is correct");
                                        $new_password = password_hash("$new_password", PASSWORD_DEFAULT);
                                        $data = array(
                                            'password' => "$new_password"
                                        );
                                        $pwdchg = $db->execute_query("UPDATE users SET password = ? WHERE email = ?",[$new_password,$useremail]);
                                        $msg->logMessage('[NOTICE]',"Password change call for $useremail from {$_SERVER['REMOTE_ADDR']}");
                                        if ($pwdchg === false):
                                            trigger_error('[ERROR] profile.php: Error: '.$db->error, E_USER_ERROR);
                                        endif;
                                        $pwdvalidateqry = $db->execute_query("SELECT password FROM users WHERE email = ?",[$useremail]);
                                        if($pwdvalidateqry === false):
                                            trigger_error('[ERROR] profile.php: Error: '.$db->error, E_USER_ERROR);
                                        else:
                                            $pwdvalidate = $pwdvalidateqry->fetch_assoc();
                                            if ($pwdvalidate['password'] == $new_password):
                                                $msg->logMessage('[NOTICE]',"Confirmed new password written to database for $useremail from {$_SERVER['REMOTE_ADDR']}");
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
                                                $msg->logMessage('[NOTICE]',"New password not verified from database for $useremail from {$_SERVER['REMOTE_ADDR']}");
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
                    $msg->logMessage('[NOTICE]',"Enforcing password change for $useremail from {$_SERVER['REMOTE_ADDR']}");
                endif;
            //4. Collection view
                $current_coll_view = $row['collection_view'];
                $msg->logMessage('[DEBUG]',"Collection view is '$current_coll_view'");

            //5. Groups
                $current_group_status = $row['grpinout'];
                $current_group = $row['groupid'];
                $msg->logMessage('[DEBUG]',"Groups are '$current_group_status', group id '$current_group'");

            //6. Currency
                $current_currency = $row['currency'];
                $msg->logMessage('[DEBUG]',"Current currency is '$current_currency'");
            
            //7. Weekly exports
                $current_weekly = $row['weeklyexport'];
                $msg->logMessage('[DEBUG]',"Weekly exports are set to '$current_weekly'");

            //8. Update pricing in case any new cards have been added to collection
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
                $msg->logMessage('[DEBUG]',"Total card count = $totalcardcount");
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
                $msg->logMessage('[DEBUG]',"Total mythics and rares count = $totalmrcardcount");

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
                    $msg->logMessage('[DEBUG]',"Unformatted value = $unformatted_value");
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
                        $msg->logMessage('[DEBUG]',"Formatted value = $collectionmoney");
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

                                // Toggle weekly export
                                $('#weekly_toggle').on('change', function () {
                                    var weekly = this.checked ? "TURN ON" : "TURN OFF";
                                    $.ajax({
                                        url: "/ajax/ajaxweekly.php",
                                        method: "POST",
                                        data: { "weekly": weekly },
                                        error: function (jqXHR, textStatus, errorThrown) {
                                            console.error("AJAX error: " + textStatus + " - " + errorThrown);
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

                                $("#importfile").change(function() {
                                    if ($(this).val()){
                                        $("#submitfile").attr("style", "display: inline");
                                        $("#importsubmit").attr("style", "box-shadow: none");
                                    }
                                    else {
                                        $("#submitfile").attr("style", "display: none");
                                    }
                                });
                            });

                            function ImportPrep() {
                                alert('Import can take several minutes, please be patient...');
                                document.body.style.cursor='wait';
                            };
                            function confirmDelete() {
                                var firstConfirm = confirm("Delete all cards from your collection? Selecting OK will send a CSV collection export to your registered email address and then delete all cards.");
    
                                if (firstConfirm) {
                                    var secondConfirm = confirm("This action is irreversible. Are you absolutely sure you want to delete all cards from your collection?");
                                    return secondConfirm;
                                }
    
                                return false;
                            };
                        </script> 

                        <table class="profile_options">
                            <tr>
                                <td colspan="3">
                                    <h2 class='h2pad'>Options</h2>
                                </td>
                            </tr>
                            <tr>
                                <td class="options_left">
                                    <b>Collection view: &nbsp;</b>
                                </td>
                                <td class="options_centre">
                                    Cards you do not own show in B&W in grid view
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
                                    <b>Group cards: &nbsp;</b> 
                                </td>
                                <td class="options_centre">
                                    Shows cards in your 'group'. If you 'Opt out' your collection is private<br>
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
                                    <b>Local currency: &nbsp;</b>
                                </td>
                                <td class="options_centre">
                                    Currency to use for localised pricing
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
                            <tr>
                                <td colspan="3">
                                    <h2 class='h2pad'>Collection management</h2>
                                </td>
                            </tr>
                            <tr>
                                <td class="options_left">
                                    <b>Delete: &nbsp;</b>
                                </td>
                                <td class="options_centre">
                                     Email CSV and delete all cards in your collection
                                </td>
                                <td class="options_right">
                                    <form action="?"  method="GET" onsubmit="return confirmDelete()">
                                        <input id='delCollection' name='deletecollection' class='profilebutton' type="submit" value="DELETE">
                                        <?php echo "<input type='hidden' name='table' value='$mytable'>"; ?>
                                    </form>
                                </td>
                            </tr>
                            <tr>
                                <td class="options_left">
                                    <b>Import: </b> 
                                </td>
                                <td class="options_centre">
                                    Import cards to your collection<br>
                                    <span id="help-button" class="material-symbols-outlined" onclick="toggleInfoBox()">
                                        help
                                    </span>
                                </td>
                                <td class="options_right">
                                    <form enctype='multipart/form-data' action='?' method='post'>
                                        <label class='profilelabel'>
                                            <input id='importfile' type='file' name='filename'>
                                            <span>SELECT CSV</span>
                                        </label><br>
                                        <span id='submitfile' style="display: none;">
                                            <label class='profilelabel'>
                                                <input id='importsubmit' class='importlabel' type='submit' name='import' value='IMPORT CSV' onclick='ImportPrep()';>
                                            </label>
                                            <br>
                                        </span>
                                        <b>Add/replace:</b><br>
                                        <select class="dropdown" name='importscope' id='importScopeSelect'>
                                            <option value='add'>Add to existing</option>
                                            <option value='replace'>Replace existing</option>
                                        </select>
                                        <br>
                                        <b>CSV format:</b><br>
                                        <select class="dropdown" name='format' id='formatSelect'>
                                            <option value='mtgc'>MtG Collection &nbsp;&nbsp;</option>
                                            <option value='delverlens'>Delver Lens</option>
                                        </select>
                                    </form>
                                </td>
                            </tr>
                            <tr>
                                <td class="options_left">
                                    <b>Export: </b> 
                                </td>
                                <td class="options_centre">
                                     Download a CSV file with all cards in your collection
                                </td>
                                <td class="options_right">
                                    <form action="csv.php"  method="GET">
                                        <input id='exportsubmit' class='profilebutton' type="submit" value="EXPORT CSV">
                                        <input type='hidden' name='type' value='echo'>
                                        <?php echo "<input type='hidden' name='table' value='$mytable'>"; ?>
                                    </form>
                                </td>
                            </tr>
                            <tr>
                                <td class="options_left">
                                    &nbsp;
                                </td>
                                <td class="options_centre">
                                    Email a CSV file with all cards in your collection to your email address
                                </td>
                                <td class="options_right">
                                    <form action="csv.php"  method="GET">
                                        <input id='exportsubmit' class='profilebutton' type="submit" value="EMAIL CSV">
                                        <input type='hidden' name='type' value='email'>
                                        <?php echo "<input type='hidden' name='table' value='$mytable'>"; ?>
                                    </form>
                                </td>
                            </tr>
                            <tr>
                                <td class="options_left">
                                    <b>&nbsp;</b>
                                </td>
                                <td class="options_centre">
                                    Weekly email to you with a CSV file of your collection
                                </td>
                                <td class="options_right"> <?php 
                                    if($current_weekly == 1): ?>
                                        <label class="switch"> 
                                            <input type="checkbox" id="weekly_toggle" class="option_toggle" checked="true" value="on"/>
                                        <div class="slider round"></div>
                                        </label> <?php
                                    else: ?>
                                        <label class="switch"> 
                                            <input type="checkbox" id="weekly_toggle" class="option_toggle" value="off"/>
                                        <div class="slider round"></div>
                                        </label> <?php
                                    endif; ?>
                                </td>
                            </tr>
                        </table>

                        <?php
                        if ($import === 'yes' && $importType !== '' && $importFormat !== ''):
                            $msg->logMessage('[DEBUG]',"Import called, checking file uploaded...");
                            if (is_uploaded_file($_FILES['filename']['tmp_name'])):
                                echo "<br><h4>" . "File ". $_FILES['filename']['name'] ." uploaded successfully. Processing..." . "</h4>";
                                $msg->logMessage('[DEBUG]',"Import file {$_FILES['filename']['name']} uploaded");
                            else:
                                echo "<br><h4>" . "File ". $_FILES['filename']['name'] ." did not upload successfully. Exiting..." . "</h4>";
                                $msg->logMessage('[DEBUG]',"Import file {$_FILES['filename']['name']} failed");
                                exit;
                            endif;
                            $importfile = $_FILES['filename']['tmp_name'];
                            $obj = new ImportExport($db,$logfile,$useremail,$serveremail);
                            $importcards = $obj->importCollection($importfile, $mytable, $importType, $useremail, $serveremail, $importFormat);
                            if ($importcards === 'incorrect format'):
                                echo "<h4>Incorrect file format</h4>";
                                exit;
                            else:
                                echo "<meta http-equiv='refresh' content='0;url=profile.php'>";
                            endif;
                        elseif ($import === 'yes' && ($importType === '' OR $importFormat === '')):
                            $msg->logMessage('[ERROR]',"Import called without valid importType");
                            echo "<h4>Invalid parameters</h4>";
                            echo "<meta http-equiv='refresh' content='2;url=profile.php'>";
                        else: 

                        endif; ?>
                    </div> 
                    <br>&nbsp; <?php
                endif; ?>
            </div>
        </div> <?php 
        require('includes/footer.php');
        $msg->logMessage('[DEBUG]',"Finished");?>
    </body>
</html>