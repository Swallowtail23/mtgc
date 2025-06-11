<?php 
/* Version:     13.1
    Date:       02/03/25
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
 * 
 * 11.2         09/06/24
 *              Update help wording for export and import with languages
 *              MTGC-87 and MTGC-89
 * 
 * 12.0         05/07/24
 *              Major import rewrite
 *              MTGC-100
 * 
 * 12.1         06/07/24
 *              Tweaks for new import rewrite
 * 
 * 12.2         23/08/24
 *              MTGC-123 - Use normal price for total value if foil or etched prices 
 *              are not available but normal price is (and we have foil or etched)
 * 
 * 13.0         01/03/25
 *              Alterations for display of additional security options
 * 
 * 13.1         02/03/25
 *              Display fixes, including MTGC-145
 */

if (file_exists('includes/sessionname.local.php')):
    require('includes/sessionname.local.php');
else:
    require('includes/sessionname_template.php');
endif;
startCustomSession();
require ('includes/ini.php');               //Initialise and load ini file
require ('includes/error_handling.php');
require ('includes/functions.php');     //Includes basic functions for non-secure pages
require ('includes/secpagesetup.php');      //Setup page variables

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\LabelAlignment;
use Endroid\QrCode\Label\Font\OpenSans;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use OTPHP\TOTP;

$msg = new Message($logfile);
$userId = isset($_SESSION['user']) ? $_SESSION['user'] : 0;
$msg->logMessage('[DEBUG]',"Page load");

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

if(isset($_GET['deckcreated'])):
    $newdecksuccess = htmlspecialchars($_GET['deckcreated'], ENT_QUOTES, 'UTF-8');
    $msg->logMessage('[DEBUG]',"New deck name $newdecksuccess");
else:
    $newdecksuccess = '';
endif;
if(isset($_GET['decknumber'])):
    $newdecknumber = filter_input(INPUT_GET, 'decknumber', FILTER_VALIDATE_INT);
    $msg->logMessage('[DEBUG]',"New deck number $newdecknumber");
    if ($newdecknumber === false):
        $newdecknumber = ''; // If not a valid integer, reset to empty string
    endif;
else:
    $newdecknumber = '';
endif;
if($newdecksuccess === '' OR $newdecknumber === ''):
    $newdecksuccess = $newdecknumber = '';
endif;

if ($deletecollection === 'DELETE'):    
    $msg->logMessage('[DEBUG]',"Called to delete collection '$mytable'");
    $obj = new ImportExport($db,$logfile,$useremail,$serveremail,$siteTitle);
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
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
        <title><?php echo $siteTitle;?> - profile</title>
        <link rel="manifest" href="manifest.json" />
        <link rel="stylesheet" type="text/css" href="css/style<?php echo $cssver?>.css"> 
        <?php include('includes/googlefonts.php');?>
        <script src="/js/jquery.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var csvSuccess = "<?php echo $csvsuccess; ?>";
                if (csvSuccess === 'true') {
                    document.getElementById('csvsuccess').style.display = 'block';
                }
                else if (csvSuccess === 'false') {
                    document.getElementById('csvfailure').style.display = 'block';
                }
            });
            document.addEventListener('DOMContentLoaded', function() {
                var delcollresult = "<?php echo isset($delcollresult) ? $delcollresult : ''; ?>";
                if (delcollresult !== '') {
                    document.getElementById('delcollresult').style.display = 'block';
                }
            });
            document.addEventListener('DOMContentLoaded', function() {
                var newdecksuccess = "<?php echo isset($newdecksuccess) ? $newdecksuccess : ''; ?>";
                if (newdecksuccess !== '') {
                    document.getElementById('newdecksuccess').style.display = 'block';
                }
            });
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('importScopeSelect').addEventListener('change', function() {
                    var addDeckRow = document.getElementById('addDeckRow');
                    if (this.value === 'replace' || this.value === 'subtract') {
                        addDeckRow.style.display = 'none';
                    } else {
                        addDeckRow.style.display = '';
                    }
                });
            });
            // Function to toggle the visibility of the info box
            function toggleInfoBox() {
                var infoBox = document.getElementById("infoBox");
                infoBox.style.display = (infoBox.style.display === "none" || infoBox.style.display === "") ? "block" : "none";
            };
            function toggleQRBox() {
                var infoBox = document.getElementById("qrBox");
                infoBox.style.display = (qrBox.style.display === "none" || qrBox.style.display === "") ? "block" : "none";
            };

            function copySecretKey() {
                let hiddenInput = document.getElementById("hiddenSecretKey");
                hiddenInput.select();
                hiddenInput.setSelectionRange(0, 99999); // For mobile compatibility
                document.execCommand("copy");
                alert("Secret key copied to clipboard");
            };
            
            function CloseMe( obj )
            {
                obj.style.display = 'none';
                window.location.href = "<?php echo $myURL; ?>/profile.php";
            }
        </script>
    </head>

    <body> <?php 
        include_once("includes/analyticstracking.php");
        require('includes/overlays.php');  
        require('includes/header.php');
        require('includes/menu.php'); ?>

        <!-- Info box -->
        <div id="csvsuccess" class="msg-new" onclick='CloseMe(this)' style="display: none;"><span>CSV email send was successful</span>
            <br>
            <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
        </div>
        <div id="csvfailure" class="msg-new error-new" onclick='CloseMe(this)' style="display: none;"><span>CSV email send was NOT successful</span>
            <br>
            <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
        </div>
        <div id="delcollresult" class="msg-new" onclick='CloseMe(this)' style="display: none;"><span><?php echo $delcollresult; ?></span>
            <br>
            <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
        </div>
        <div id="newdecksuccess" class="msg-new" onclick='CloseMe(this)' style="display: none;"><span>Deck <i><a href='deckdetail.php?deck=<?php echo $newdecknumber ?>'>"<?php echo $newdecksuccess; ?>"</a></i> created</span>
            <br>
            <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
        </div>
        <div class="info-box" id="infoBox" style="display:none">
            <span class="close-button-profile material-symbols-outlined" onclick="toggleInfoBox()">close</span>
            <div class="info-box-inner">
                <h2 class="h2-no-top-margin">Import help</h2>
                <ul>
                    <li>Select 'Add a deck' to create a deck with cards in this import</li>
                    <li>Select Import type 'Add', 'Replace' or 'Remove' to add to existing, replace existing, or remove cards</li>
                    <li>Import file can be a MTGC CSV, e.g.:</li>
                </ul>
                <pre>
          setcode,number,name,lang,normal,foil,etched,id
          LTR,3,Bill the Pony,en,5,0,0,{Scryfall id}</pre>
                <ul>
                    <li>Delver Lens lists can be imported in the CSV export format of</li>
                </ul>
                <pre>
          'Edition code','Collector's number','Name',
          'Non-foil quantity','Foil quantity','Scryfall ID'</pre>
                <ul>
                    <li><u>Do not import etched cards with Delver Lens</u>, it flags etched foils as separate cards instead of variations of a card</li>
                    <li><u>Do not import stamped cards with Delver Lens</u>, it tends to misallocate (e.g. Planeswalker-stamped promos, The List, etc.</li>
                    <li>Files can also be decklists (MTGC or Moxfield)</li>
                    <li>If "id" is a valid Scryfall UUID value, the line will be imported as that id <i>without checking anything else</i></li>
                    <li>If a Scryfall UUID cannot be matched, import will try a setcode/name/collector number/language match or skip the row</li>
                    <li>If language is unspecified, the primary version is imported (usually English)</li>
                    <li>Set codes and collector numbers must be as <a href='sets.php'> here </a>for success</li>
                    <li>For a format example: export first, use that file as a template</li>
                    <li>Edit CSVs in an app like Notepad++ (<b>don't use Excel</b>)</li>
                    <li>You will be emailed a list of import failures/warnings</li>
                </ul>
            </div>
        </div>
        <!-- QR / 2FA box -->
        <div class="qr-box" id="qrBox" style="display:none">
            <div class="qr-box-inner">
            </div>
        </div> 
        <?php

        $import = isset($_POST['import']) ? 'yes' : '';
        $adddeck = isset($_POST['adddeck']) ? 'yes' : '';
        
        $valid_importType = ['add','replace','subtract'];
        $importType = isset($_POST['importscope']) ? "{$_POST['importscope']}" : '';
        if (!in_array($importType,$valid_importType)):
            $importType = '';
        endif;

        $valid_format = ['mtgc','delverlens','regex'];
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
                                            WHERE usernumber = ? LIMIT 1",[$userId])):
            $msg->logMessage('[DEBUG]',"SQL query for user details succeeded");
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
                                                // Removing all trusted devices
                                                (new TrustedDeviceManager($db, $logfile))->removeAllUserDevices($userId);
                                                echo "<div class='alert-box success' id='pwdchange'><span>success: </span>Password changed and trusted devices cleared - log in again</div>";
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
                                COALESCE(SUM(`$mytable`.foil * 
                                    CASE 
                                        WHEN price_foil IS NOT NULL AND price_foil > 0 THEN price_foil
                                        WHEN price IS NOT NULL AND price > 0 THEN price
                                        ELSE 0
                                    END), 0)
                                +
                                COALESCE(SUM(`$mytable`.etched * 
                                    CASE 
                                        WHEN price_etched IS NOT NULL AND price_etched > 0 THEN price_etched
                                        WHEN price IS NOT NULL AND price > 0 THEN price
                                        ELSE 0
                                    END), 0)
                                ) 
                                as TOTAL FROM `$mytable` LEFT JOIN cards_scry ON `$mytable`.id = cards_scry.id";
                if($totalvalue = $db->query($sqlvalue)):
                    $rowvalue = $totalvalue->fetch_assoc();
                    $unformatted_value = $rowvalue['TOTAL'];
                    $msg->logMessage('[DEBUG]',"Unformatted value = $unformatted_value");
                else:
                    trigger_error('[ERROR] profile.php: Error: '.$db->error, E_USER_ERROR);
                endif;

            //9. 2FA Section
                // Get 2FA status for this user
                $tfaManager = new TwoFactorManager($db, $smtpParameters, $serveremail, $logfile);
                $tfa_enabled = $tfaManager->isEnabled($userId);

                // Check if we should enable or disable 2FA
                if (isset($_POST['enable_2fa'])):
                    $tfa_method = $_POST['tfa_method'] ?? 'email';
                    $enabled = $tfaManager->enable($userId, $tfa_method);
                    if ($enabled):
                        $tfa_enabled = true;
                        // Set backup codes
                        $backup_codes = $tfaManager->getBackupCodes($userId);
                        // Build the backup codes HTML outside the JavaScript block:
                        $backupHtml = "<span style='font-family: monospace; margin-left: 20px;'><br>";
                        if (!empty($backup_codes)):
                            foreach ($backup_codes as $code):
                                $backupHtml .= htmlspecialchars($code) . "<br>";
                            endforeach;
                            $backupHtml .= "</span><br><strong>Keep these codes safe and private!</strong></br>";
                        else:
                            $backupHtml .= "Error generating backup codes<br>";
                        endif;
                        if ($tfa_method === "app" && isset($_SESSION['tfa_provisioning_uri'])):
                            $provisioningUri = $_SESSION['tfa_provisioning_uri'];

                            // Extract the secret key from provisioning URI
                            parse_str(parse_url($provisioningUri, PHP_URL_QUERY), $queryParams);
                            $secretKey = $queryParams['secret'] ?? 'N/A';

                            // Generate QR Code
                            $builder = new Builder(
                                writer: new PngWriter(),
                                writerOptions: [],
                                validateResult: false,
                                data: $provisioningUri,
                                encoding: new Encoding('UTF-8'),
                                errorCorrectionLevel: ErrorCorrectionLevel::High,
                                size: 200,
                                margin: 10,
                                roundBlockSizeMode: RoundBlockSizeMode::Margin
                            );

                            // Build the QR Code
                            $result = $builder->build();

                            // Convert QR Code to Data URI
                            $qrDataUri = $result->getDataUri();
                            $encodedSecretKey = htmlspecialchars($secretKey, ENT_QUOTES, 'UTF-8');

                            // Format for display (line break every 16 characters)
                            $formattedSecretKey = implode('<wbr>', str_split($encodedSecretKey, 16)); // <wbr> allows line breaks without adding spaces

                            // Inject JavaScript to update the div dynamically
                            echo "<script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    let qrBox = document.getElementById('qrBox');
                                    let qrInner = qrBox.querySelector('.qr-box-inner');

                                    // Inject QR Code and secret key into the div
                                    qrInner.innerHTML = `
                                        <h2>Two-factor authentication enabled successfully</h2>
                                        <h3>Scan QR Code using your authentication app</h3>
                                        <img src=\"${qrDataUri}\" alt=\"Scan QR Code to set up 2FA\">
                                        <h3>...or manually enter this code:</h3>
                                        <p class=\"secret-key\" onclick=\"copySecretKey()\">${formattedSecretKey}</p>
                                        <input type=\"text\" id=\"hiddenSecretKey\" value=\"${encodedSecretKey}\" style=\"position:absolute; left:-9999px;\"> 
                                        <h3>Verify your 6-digit code:</h3>
                                        <form id='verify2FAForm' method='post' action='profile.php'>
                                            <input type='text' name='tfa_code' id='tfa_code' maxlength='6' pattern='[0-9]{6}' required placeholder='Enter 6-digit code' style='font-size: 18px; text-align: center; width: 120px;'>
                                            <input type='hidden' name='tfa_secret' value='${encodedSecretKey}'>
                                            <button type='submit' name='verify_2fa' class='ok-button profilebutton'>VERIFY</button>
                                        </form>
                                        <br>
                                        <b>Important:</b> The backup codes below can be used if you lose access to your authentication method. Save them, as you will not get access to them again.<br>
                                        " . $backupHtml . "
                                        <br>
                                        <form method='post' action='profile.php' onsubmit='return'>
                                            <input type='hidden' name='disable_2fa' value='1'>
                                            <button type='submit' class='ok-button profilebutton'>CANCEL</button>
                                        </form>
                                    `;

                                    // Show the div
                                    qrBox.style.display = 'block';
                                });
                            </script>";
                        elseif ($tfa_method === "email"):
                            // Inject JavaScript to update the div dynamically
                            echo "<script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    let qrBox = document.getElementById('qrBox');
                                    let qrInner = qrBox.querySelector('.qr-box-inner');

                                    // Inject content into the div
                                    qrInner.innerHTML = `
                                        <h2>Two-factor authentication enabled successfully</h2>
                                        <b>Important:</b> The backup codes below can be used if you lose access to your authentication method. Save them, as you will not get access to them again.<br>
                                        " . $backupHtml . "
                                        <br>
                                        <button onclick=\"toggleQRBox()\" class=\"ok-button, profilebutton\">OK</button>
                                    `;
                                    // Show the div
                                    qrBox.style.display = 'block';
                                });
                            </script>";
                        endif;
                    else:
                        echo "<div class='alert-box error' id='tfa_message'><span>error: </span>Failed to enable two-factor authentication.</div>";
                    endif;
                elseif (isset($_POST['disable_2fa'])):
                    $disabled = $tfaManager->disable($userId);
                    if ($disabled):
                        $tfa_enabled = false;
                        echo "<div class='alert-box success' id='tfa_message'><span>success: </span>Two-factor authentication disabled successfully.</div>";
                    else:
                        echo "<div class='alert-box error' id='tfa_message'><span>error: </span>Failed to disable two-factor authentication.</div>";
                    endif;
                elseif (isset($_POST['regenerate_backup_codes'])):
                    $new_codes = $tfaManager->regenerateBackupCodes($userId);
                    $newCodesHtml = "<span style='font-family: monospace; margin-left: 20px;'><br>";
                    if (!empty($new_codes)):
                        // Build the backup codes HTML outside the JavaScript block:
                        foreach ($new_codes as $new_code):
                            $newCodesHtml .= htmlspecialchars($new_code) . "<br>";
                        endforeach;
                        $newCodesHtml .= "</span><br><strong>Keep these codes safe and private!</strong></br>";
                        // Inject JavaScript to update the div dynamically
                        echo "<script>
                            document.addEventListener('DOMContentLoaded', function() {
                                let qrBox = document.getElementById('qrBox');
                                let qrInner = qrBox.querySelector('.qr-box-inner');

                                // Inject content into the div
                                qrInner.innerHTML = `
                                    <h2>New backup codes generated successfully</h2>
                                    <b>Important:</b> The codes below can be used if you lose access to your authentication method. Save them, as you will not get access to them again.<br>
                                    " . $newCodesHtml . "
                                    <br>
                                    <button onclick=\"toggleQRBox()\" class=\"ok-button, profilebutton\">OK</button>
                                `;
                                // Show the div
                                qrBox.style.display = 'block';
                            });
                        </script>";
                    else:
                        echo "<div class='alert-box error' id='tfa_message'><span>error: </span>Failed to regenerate backup codes.</div>";
                    endif;
                elseif (isset($_POST['verify_2fa'])):
                    $userCode = $_POST['tfa_code'] ?? '';
                    $userSecret = $_POST['tfa_secret'] ?? '';

                    // Verify TOTP code
                    $totp = TOTP::create($userSecret);
                    if ($totp->verify($userCode)):
                        // Store that 2FA is fully verified
                        $_SESSION['2fa_verified'] = true;
                        echo "<div class='alert-box success'><span>success: </span>Two-factor authentication successfully enabled and verified.</div>";
                    else:
                        // Disable 2FA since the verification failed
                        $tfaManager->disable($userId);
                        echo "<div class='alert-box error'><span>error: </span>Invalid 6-digit code. Two-factor authentication was not enabled.</div>";
                    endif;
                endif;
                //Page display content ?>
                <div class="profile-container">
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
                            $collectionvalue = "Collection tcgplayer market value <br>US ".$collectionmoney;
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

                            echo "(Pricing via <a href='https://www.scryfall.com/' target='_blank'>scryfall.com</a>.)<br>";
                            $rowcounttotal = number_format($totalcardcount);
                        ?>
                    </div>
                    <div id="changepassword">
                        <h2 class="h2pad">
                          Change my password
                          <span class="tooltip-icon" tabindex="0">
                          ?
                          <span class="tooltip-text">
                            Minimum 8 characters, including uppercase, lowercase, and at least one number.
                          </span>
                        </span>
                        </h2>

                        <form action="/profile.php" method="POST">
                            <table>
                                <tbody>
                                    <tr>
                                        <td style="min-width:190px">
                                            <input style="font-size: 16px;" class="profilepassword textinput" tabindex="1" type="password" name="curPass" placeholder="CURRENT">
                                            <span class="error2">*</span>
                                        </td>
                                        <td rowspan="3">
                                            <input class="inline_button stdwidthbutton" tabindex="4" id="chgpwdsubmit" type="submit" value="UPDATE" name="changePass" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <input style="font-size: 16px;" class="profilepassword textinput" tabindex="2" type="password" name="newPass" placeholder="NEW">
                                            <span class="error2">*</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <input style="font-size: 16px;" class="profilepassword textinput" tabindex="3" type="password" name="newPass2" placeholder="REPEAT NEW">
                                            <span class="error2">*</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </form>
                    </div>                
                </div> <?php
                
                // Get trusted devices for this user
                require_once('classes/trusteddevicemanager.class.php');
                $deviceManager = new TrustedDeviceManager($db, $logfile);
                // Get the current device's token hash, if the cookie is set.
                $currentDeviceHash = null;
                if (isset($_COOKIE[$deviceManager->getCookieName()])):
                    $token = $_COOKIE[$deviceManager->getCookieName()];
                    $currentDeviceHash = $deviceManager->getTokenHash($token);
                endif;
                // Check if we should remove a device
                if (isset($_GET['remove_device']) && is_numeric($_GET['remove_device'])):
                    $device_id = intval($_GET['remove_device']);
                    $removed = $deviceManager->removeDeviceById($device_id, $userId);
                    if ($removed):
                        echo "<div class='alert-box success' id='device_message'><span>success: </span>Device removed successfully.</div>";
                    else:
                        echo "<div class='alert-box error' id='device_message'><span>error: </span>Failed to remove device or device not found.</div>";
                    endif;
                elseif (isset($_GET['remove_all_devices']) && $_GET['remove_all_devices'] == 1):
                    $removed = $deviceManager->removeAllUserDevices($userId);
                    if ($removed):
                        echo "<div class='alert-box success' id='device_message'><span>success: </span>All trusted devices removed successfully.</div>";
                    else:
                        echo "<div class='alert-box error' id='device_message'><span>error: </span>Failed to remove devices.</div>";
                    endif;
                endif; ?>
                <div id='profilebuttons'>
                    <table class="profile_options"><?php                    

                    // Display trusted devices
                    $devices = $deviceManager->getUserDevices($userId);
                    if (count($devices) > 0): ?>
                        <tr>
                            <td colspan="4" style="border-width: 0px 0px 1px;">
                                <h2 class='h2pad'>Trusted Devices</h2>
                            </td>
                        </tr>
                        <tr>
                            <th>Device</th>
                            <th>Last Used</th>
                            <th>Expires</th>
                            <th>Actions</th>
                        </tr> <?php 
                        foreach ($devices as $device): ?>
                        <tr class="hoverhighlight">
                            <td><?php echo htmlspecialchars($device['device_name']);
                            // If the current device hash matches the device token hash, flag it.
                                if ($currentDeviceHash !== null && $currentDeviceHash === $device['token_hash']):
                                    echo " <strong>(This device)</strong>";
                                endif;?></td>
                            <td><?php echo $device['last_used'] ? date('Y-m-d H:i', strtotime($device['last_used'])) : 'Never'; ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($device['expires'])); ?></td>
                            <td style="text-align: center;">
                                <a href="profile.php?remove_device=<?php echo $device['id']; ?>" 
                                   onclick="return confirm('Are you sure you want to remove this device?');"
                                   class="profilebutton" style="padding: 3px 8px; width: 56px; display: inline-block;">REMOVE</a>
                            </td>
                        </tr> <?php 
                        endforeach; ?>
                        <tr class="hoverhighlight">
                            <td colspan="3">
                                Clear all trusted device authorisations and force new logins
                            </td>
                            <td style="text-align: center;">
                                <p style="margin-top: 10px;">
                                <a href="profile.php?remove_all_devices=1" 
                               onclick="return confirm('Are you sure you want to remove ALL trusted devices? You will need to log in again on all devices.');"
                               class="profilebutton" style="padding: 3px 8px; width: 56px; display: inline-block;">CLEAR</a>
                                </p> 
                            </td>
                        </tr><?php 
                    else: ?>
                        <tr>
                            <td colspan="4">
                                <p>You don't have any trusted devices. When you log in, you can choose to trust a device to stay logged in for up to <?php echo $trustDuration; ?> days.
                                </p>
                            </td> 
                        </tr> <?php 
                    endif; ?>
                </div> <?php 
                
                if ((!isset($_SESSION["chgpwd"])) OR ($_SESSION["chgpwd"] != TRUE)): ?>
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

                                    $("#importfileProfile").change(function() {
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
                                    // alert('Import can take several minutes, please be patient...');
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
                            <tr>
                                <td colspan="4" style="border-width: 1px 0px;">
                                    <h2 class='h2pad'>Options</h2>
                                </td>
                            </tr>
                            <tr class="hoverhighlight">
                                <td class="options_left">
                                    <b>Two-Factor<br>Authentication</b>
                                </td>
                                <td class="options_centre" colspan="2"> <?php
                                    // Show 2FA status and options
                                    if ($tfa_enabled):
                                        $tfa_method = $tfaManager->getMethod($userId);?>
                                        Two-factor authentication is currently <strong>enabled</strong> using <strong><?php echo htmlspecialchars(ucfirst($tfa_method)); ?></strong>.
                                        <br>Click "NEW CODES" to generate new backup codes.<?php 
                                    else: ?>
                                        Require a verification code when you log in<?php 
                                    endif; ?>
                                </td>
                                <td class="options_right"><?php
                                    // Show 2FA status and options
                                    if ($tfa_enabled): ?>
                                        <form action="profile.php" method="post">
                                            <input type="submit" name="disable_2fa" class="profilebutton" value="DISABLE 2FA" 
                                                onclick="return confirm('Are you sure you want to disable two-factor authentication? This will make your account less secure.');" />
                                        </form>
                                    <br>
                                        <form action="profile.php" method="post">
                                            <input type="submit" name="regenerate_backup_codes" class="profilebutton" value="NEW CODES" 
                                                onclick="return confirm('Are you sure you want to regenerate backup codes? This will invalidate all existing backup codes.');" />
                                        </form> <?php
                                    else: ?>
                                        <form method="post" action="profile.php">
                                            <select class="dropdown" name="tfa_method" id="tfa_method" onchange="this.form.submit()" style="width: 85px;">
                                                <option value="disabled" selected>Disabled</option>
                                                <option value="email">Enable: Email</option>
                                                <option value="app">Enable: App</option>
                                            </select>
                                            <input type="hidden" name="enable_2fa" value="1">
                                        </form><?php
                                    endif; ?>
                                </td>
                            </tr>
                            <tr class="hoverhighlight">
                                <td class="options_left">
                                    <b>Collection view</b>
                                </td>
                                <td class="options_centre" colspan="2">
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
                            <tr class="hoverhighlight">
                                <td class="options_left">
                                    <b>Group cards</b> 
                                </td>
                                <td class="options_centre" colspan="2">
                                    Shows cards in your 'group'. If you 'Opt out' your collection is private<br>
                                    <?php 
                                    if($current_group_status == 1):
                                        echo "<span id='grpname'><b>Group:</b> {$row['groupname']} <br><a href='help.php'>Send me a request</a> to create a new group</span>&nbsp;"; 
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
                            <tr class="hoverhighlight">
                                <td class="options_left">
                                    <b>Local currency</b>
                                </td>
                                <td class="options_centre" colspan="2">
                                    Currency to use for localised pricing
                                </td>
                                <td class="options_right">  
                                    <select class="dropdown" name='currency' id='currencySelect' style="width: 85px;">
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
                                <td colspan="4" style="border-width: 1px 0px;">
                                    <h2 class='h2pad'>Collection management</h2>
                                </td>
                            </tr>
                            <tr class="hoverhighlight">
                                <td class="options_left">
                                    <b>Delete</b>
                                </td>
                                <td class="options_centre" colspan="2">
                                     Email CSV and delete all cards in your collection
                                </td>
                                <td class="options_right">
                                    <form action="?"  method="GET" onsubmit="return confirmDelete()">
                                        <input id='delCollection' name='deletecollection' class='profilebutton' type="submit" value="DELETE">
                                        <?php echo "<input type='hidden' name='table' value='$mytable'>"; ?>
                                    </form>
                                </td>
                            </tr>
                            <script>
                                function displayFileName() {
                                    var input = document.getElementById('importfileProfile');
                                    var fileNameSpan = document.getElementById('fileNameSpan');
                                    if (input.files.length > 0) {
                                        var fileName = input.files[0].name;
                                        fileNameSpan.textContent = fileName;
                                        document.getElementById('submitfile').style.display = 'block';
                                    } else {
                                        fileNameSpan.textContent = '';
                                        document.getElementById('submitfile').style.display = 'none';
                                    }
                                }
                            </script>
                            <tr class="hoverhighlight">
                                <td class="options_left" style="padding-top: 10px;">
                                    <b>Import</b> 
                                </td>
                                <td class="options_centre" colspan="2">
                                    Import cards to your collection&nbsp;
                                    <span id="help-button" class="material-symbols-outlined" onclick="toggleInfoBox()">
                                        help
                                    </span>
                                </td>
                                <td class="options_right">
                                    <form enctype='multipart/form-data' action='?' method='post'>
                                        <label class='profilelabel'>
                                            <input id='importfileProfile' type='file' name='filename' onchange='displayFileName()'>
                                            <span>SELECT</span>
                                        </label><br>
                                        <div id='submitfile' style="display: none;">
                                            <label id='profilefilelabel'>
                                                <input id='importsubmit' class='importlabel' type='submit' name='import' value='IMPORT' onclick='ImportPrep()';>
                                                <input type="hidden" name="format" value="regex">
                                            </label>
                                            <table>
                                                <tr title='Selected file name'>
                                                    <td style='text-align: left'>
                                                        <b>Selected:&nbsp;</b>
                                                    </td>
                                                    <td>
                                                        <span id='fileNameSpan'></span>
                                                    </td>
                                                </tr>
                                                <tr title='Add cards, replace card quantities, or remove these cards from your collection' >
                                                    <td style='text-align: left'>
                                                        <b>Action:</b>
                                                    </td>
                                                    <td>
                                                        <select class="dropdown" name='importscope' id='importScopeSelect'>
                                                            <option value='add'>Add</option>
                                                            <option value='replace'>Replace</option>
                                                            <option value='subtract'>Remove</option>
                                                        </select>
                                                    </td>
                                                </tr>
                                                <tr title='Add a new deck with these imported cards' id='addDeckRow'>
                                                    <td style='text-align: left'>
                                                        <b>Deck:</b>
                                                    </td>
                                                    <td>
                                                        <span class="checkbox-group">
                                                            <input id = "adddeck" type="checkbox" class="checkbox" name="adddeck" value="yes">
                                                            <label for='adddeck'>
                                                                <span class="check"></span>
                                                                <span class="box"></span>
                                                            </label>
                                                        </span>
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                            <tr class="hoverhighlight">
                                <td class="options_left">
                                    <b>Export</b> 
                                </td>
                                <td class="options_centre" colspan="2">
                                     Download a CSV file with all cards in your collection
                                </td>
                                <td class="options_right">
                                    <form action="csv.php"  method="GET">
                                        <input id='exportsubmit' class='profilebutton' type="submit" value="EXPORT">
                                        <input type='hidden' name='type' value='echo'>
                                        <?php echo "<input type='hidden' name='table' value='$mytable'>"; ?>
                                    </form>
                                </td>
                            </tr>
                            <tr class="hoverhighlight">
                                <td class="options_left">
                                    &nbsp;
                                </td>
                                <td class="options_centre" colspan="2">
                                    Email a CSV file with all cards in your collection to your email address
                                </td>
                                <td class="options_right">
                                    <form action="csv.php"  method="GET">
                                        <input id='emailsubmit' class='profilebutton' type="submit" value="EMAIL">
                                        <input type='hidden' name='type' value='email'>
                                        <?php echo "<input type='hidden' name='table' value='$mytable'>"; ?>
                                    </form>
                                </td>
                            </tr>
                            <tr class="hoverhighlight">
                                <td class="options_left">
                                    <b>&nbsp;</b>
                                </td>
                                <td class="options_centre" colspan="2">
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
                            $obj = new ImportExport($db,$logfile,$useremail,$serveremail,$siteTitle);
                            $importcards = $obj->importCollectionRegex($importfile, $mytable, $importType, $useremail, $serveremail);
                            if ($importcards === 'emptyfile'):
                                echo "<h4>File contains no card data</h4>";
                                exit;
                            else:
                                if ($adddeck === 'yes'):
                                    $currentDateTime = date("j F Y, g:i:sa");
                                    $tmpdeckname = $currentDateTime;
                                    $obj = new DeckManager($db, $logfile, $useremail, $serveremail, $importLinestoIgnore, $nonPreferredSetCodes);
                                    $msg->logMessage('[DEBUG]',"Import called with 'add deck' option, $tmpdeckname to be created...");
                                    $decksuccess = $obj->addDeck($userId,$tmpdeckname); //returns array with success flag, and if success flag is 1, the deck number (otherwise NULL)
                                    if($decksuccess['flag'] === 1):
                                        $decknumber = $decksuccess['decknumber'];
                                        $msg->logMessage('[DEBUG]',"Deck created, $tmpdeckname created, deck number is $decknumber");
                                        echo "<script>var deckNumber = '$decknumber'; var deckName = '$tmpdeckname'; var deckCreated = true;</script>";
                                        $file = fopen($_FILES['filename']['tmp_name'], 'r');
                                        $deckManager = new DeckManager($db, $logfile, $useremail, $serveremail, $importLinestoIgnore, $nonPreferredSetCodes);
                                        // Read the entire file content into a variable
                                        $fileContent = fread($file, filesize($_FILES['filename']['tmp_name']));
                                        fclose($file);

                                        // Call the processInput method with the decknumber and file content
                                        $deckManager->processInput($decknumber, $fileContent);
                                    else:
                                        $msg->logMessage('[ERROR]',"Deck NOT created");
                                    endif;
                                    $msg->logMessage('[DEBUG]',"redirecting to profile.php?deckcreated=$tmpdeckname&decknumber=$decknumber");
                                    echo "<meta http-equiv='refresh' content='0;url=profile.php?deckcreated=$tmpdeckname&decknumber=$decknumber'>";
                                else:
                                    $msg->logMessage('[DEBUG]',"adddeck is not 'yes', skipping deck creation.");
                                    echo "<meta http-equiv='refresh' content='0;url=profile.php'>";
                                endif;
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
