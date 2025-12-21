<?php

/*
Version:     14.5
Date:        21/12/25
Name:        profile.php
Purpose:     User profile page.
Notes:       This page must not run the forcePasswordChange function - this is the page that a user goes to TO change
             password.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

if (file_exists('includes/sessionname.local.php')) :
    require 'includes/sessionname.local.php';
else :
    require 'includes/sessionname_template.php';
endif;
startCustomSession();
require 'includes/ini.php';               // Initialise and load ini file
require 'includes/error_handling.php';
require 'includes/functions.php';         // Includes basic functions for non-secure pages
require 'includes/secpagesetup.php';      // Setup page variables

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\LabelAlignment;
use Endroid\QrCode\Label\Font\OpenSans;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use OTPHP\TOTP;

$msg = new \MTG\Core\Message($logfile);
$userId = isset($_SESSION['user']) ? $_SESSION['user'] : 0;
$msg->logMessage('[DEBUG]', "Page load");
$emailEnabled = (($iniArray['email']['Email'] ?? 'enabled') === 'enabled');
$siteTitleEsc = htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8');
?>

<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
        <title><?php echo $siteTitleEsc;?> - profile</title>
        <link rel="manifest" href="/manifest.json" />
        <link rel="stylesheet" type="text/css" href="css/style<?php echo $cssver?>.css">
        <?php include('includes/googlefonts.php');?>
        <script src="/js/jquery.js"></script>
        <script>
            function toggleQRBox() {
                var qrBox = document.getElementById("qrBox");
                var currentlyHidden = (qrBox.style.display === "none" || qrBox.style.display === "");
                if (currentlyHidden) {
                    qrBox.style.display = "block";
                } else {
                    qrBox.style.display = "none";
                    // After user dismisses the backup/QR box, reload so UI updates (e.g., password change section)
                    setTimeout(function() {
                        window.location.href = "profile.php";
                    }, 200);
                }
            }

            function copySecretKey() {
                let hiddenInput = document.getElementById("hiddenSecretKey");
                hiddenInput.select();
                hiddenInput.setSelectionRange(0, 99999); // For mobile compatibility
                document.execCommand("copy");
                alert("Secret key copied to clipboard");
            };
        </script>
    </head>

    <body> <?php
        include_once 'includes/analyticstracking.php';
        require 'includes/overlays.php';
        require 'includes/header.php';
        require 'includes/menu.php';
    if (empty($_SESSION["chgpwd"])) :
        require 'includes/profilemenus.php';
    endif; ?>

        <!-- QR / 2FA box -->
        <div class="qr-box" id="qrBox" style="display:none">
            <div class="qr-box-inner">
            </div>
        </div>
        <?php

        // Does the user have a collection table?
        $tableExistsQuery = "SHOW TABLES LIKE '$mytable'";
        $msg->logMessage('[DEBUG]', "Checking if user has a collection table...");

        $result = $db->query($tableExistsQuery);
        if ($result->num_rows == 0) :
            $msg->logMessage('[NOTICE]', "No existing collection table...");
            $query2 = "CREATE TABLE `$mytable` LIKE collectionTemplate";
            $msg->logMessage('[DEBUG]', "Copying collection template...: $query2");

            if ($db->query($query2) === true) :
                $msg->logMessage('[NOTICE]', "Collection template copy successful");
            else :
                $msg->logMessage('[NOTICE]', "Collection template copy failed: " . $db->error);
            endif;
        else :
            $msg->logMessage('[DEBUG]', "Collection table exists");
        endif;

        //1. Get user details for current user
        if (
            $rowqry = $db->execute_query("SELECT username, password, email, reg_date, status, admin,
                                                groupid, grpinout, groupname, collection_view,
                                                currency, weeklyexport
                                            FROM users
                                            LEFT JOIN `groups`
                                            ON users.groupid = groups.groupnumber
                                            WHERE usernumber = ? LIMIT 1", [$userId])
        ) :
            $msg->logMessage('[DEBUG]', "SQL query for user details succeeded");
            $row = $rowqry->fetch_assoc();
            $tfaManager = new \MTG\Auth\TwoFactorManager($db, $smtpParameters, $serverEmail, $logfile);
            $userHas2fa = $tfaManager->isEnabled($userId);
            $userTwofaMethod = $userHas2fa ? $tfaManager->getMethod($userId) : '';
        else :
            throw new Exception('[ERROR] profile.php: Error: ' . $db->error);
        endif;  ?>

        <div id='page'>
            <div class='staticpagecontent'>
                <?php
                $disableTwofaNotice = '';
                if (
                    isset($_POST['send_twofa_code'])
                    && !empty($userHas2fa)
                    && $userTwofaMethod === 'email'
                ) :
                    $tfaManager->startVerification($userId, $userEmail);
                    echo "<div class='alert-box notice' id='pwdchange'>"
                         . "<span>notice: </span>"
                         . "Verification code sent to your email."
                         . "</div>";
                endif;
                if (
                    isset($_POST['send_disable_twofa_code'])
                    && !empty($tfa_enabled)
                    && $tfaManager->getMethod($userId) === 'email'
                ) :
                    $tfaManager->startVerification($userId, $userEmail);
                    $disableTwofaNotice = "<div class='alert-box notice' id='tfa_message'><span>notice: </span>"
                        . "Verification code sent to your email to disable two-factor authentication.</div>";
                endif;

                //Page PHP processing

                //2. Has a password reset been called? Needs to be in DIV for error display
                if (
                    isset($_POST['changePass'])
                    and isset($_POST['newPass'])
                    and isset($_POST['newPass2'])
                    and isset($_POST['curPass'])
                ) :
                    if (!empty($_POST['curPass']) and !empty($_POST['newPass']) and !empty($_POST['newPass2'])) :
                        $new_password = $_POST['newPass'];
                        $new_password_2 = $_POST['newPass2'];
                        $old_password = $_POST['curPass'];
                        $db_password = $row['password'];
                        if ($new_password == $new_password_2) :
                            $msg->logMessage('[DEBUG]', "New passwords double type = match");
                            if (validPass($new_password)) :
                                $msg->logMessage('[DEBUG]', "New password is a valid password");
                                $twofaVerified = ($userHas2fa !== true);
                                if ($userHas2fa) :
                                    $twofaCode = trim($_POST['twofa_code'] ?? '');
                                    if ($userTwofaMethod === 'email' && $twofaCode === '') :
                                        $tfaManager->startVerification($userId, $userEmail);
                                        echo "<div class='alert-box error' id='pwdchange'>"
                                             . "<span>error: </span>"
                                             . "Enter the verification code emailed to you to change your password."
                                             . "</div>";
                                    elseif ($twofaCode === '') :
                                        echo "<div class='alert-box error' id='pwdchange'>"
                                             . "<span>error: </span>"
                                             . "Enter your two-factor code to change your password."
                                             . "</div>";
                                    elseif (!$tfaManager->verify($userId, $twofaCode)) :
                                        echo "<div class='alert-box error' id='pwdchange'>"
                                             . "<span>error: </span>"
                                             . "Invalid two-factor code. Please try again."
                                             . "</div>";
                                    else :
                                        $twofaVerified = true;
                                    endif;
                                endif;
                                if ($twofaVerified && $new_password != $old_password) :
                                    $msg->logMessage('[DEBUG]', "New password is different to old password");
                                    if (password_verify($old_password, $db_password)) :
                                        $msg->logMessage('[DEBUG]', "Old password is correct");
                                        $new_password = password_hash("$new_password", PASSWORD_DEFAULT);
                                        $data = array(
                                            'password' => "$new_password"
                                        );
                                        $pwdchg = $db->execute_query(
                                            "UPDATE users SET password = ?, status = 'active', badlogins = 0 "
                                                . "WHERE email = ?",
                                            [$new_password, $userEmail]
                                        );
                                        $msg->logMessage(
                                            '[NOTICE]',
                                            "Password change call for $userEmail from {$_SERVER['REMOTE_ADDR']}"
                                        );
                                        if ($pwdchg === false) :
                                            throw new Exception('[ERROR] profile.php: Error: ' . $db->error);
                                        endif;
                                        $pwdvalidateqry = $db->execute_query(
                                            "SELECT password FROM users WHERE email = ?",
                                            [$userEmail]
                                        );
                                        if ($pwdvalidateqry === false) :
                                            throw new Exception('[ERROR] profile.php: Error: ' . $db->error);
                                        else :
                                            $pwdvalidate = $pwdvalidateqry->fetch_assoc();
                                            if ($pwdvalidate['password'] == $new_password) :
                                                $msg->logMessage(
                                                    '[NOTICE]',
                                                    "Confirmed new password written to database for "
                                                    . "$userEmail from {$_SERVER['REMOTE_ADDR']}"
                                                );
                                                // Removing all trusted devices
                                                    (new TrustedDeviceManager($db, $logfile))
                                                        ->removeAllUserDevices($userId);
                                                echo "<div class='alert-box success' id='pwdchange'>"
                                                    . "<span>success: </span>"
                                                    . "Password changed and trusted devices cleared - log in again"
                                                    . "</div>";
                                                if (!class_exists(\MTG\Auth\PasswordCheck::class)) :
                                                    require_once('classes/passwordcheck.class.php');
                                                endif;
                                                $passwordCheck = new \MTG\Auth\PasswordCheck($db, $logfile, $siteTitle);
                                                $passwordCheck->clearResetForEmail($userEmail);
                                                $passwordCheck->sendPasswordChangeNotification($userEmail);
                                                $_SESSION['chgpwd'] = false;
                                                session_destroy();
                                                echo "<meta http-equiv='refresh' content='4;url=login.php'>";
                                                exit();
                                            else :
                                                echo "<div class='alert-box error' id='pwdchange'>"
                                                    . "<span>error: </span>Password change failed... contact support"
                                                    . "</div>";
                                                $msg->logMessage(
                                                    '[NOTICE]',
                                                    "New password not verified from database for "
                                                    . "$userEmail from {$_SERVER['REMOTE_ADDR']}"
                                                );
                                            endif;
                                        endif;
                                    else :
                                        echo "<div class='alert-box error' id='pwdchange'><span>error: </span>"
                                            . "Your entered current password was not correct. Please try again."
                                            . "</div>";
                                    endif;
                                else :
                                    echo "<div class='alert-box error' id='pwdchange'><span>error: </span>"
                                        . "Your new password is the same as the old one. Please try again.</div>";
                                endif;
                            else :
                                echo "<div class='alert-box error' id='pwdchange'><span>error: </span>"
                                    . "The new password does not meet requirements.</div>";
                            endif;
                        else :
                            echo "<div class='alert-box error' id='pwdchange'><span>error: </span>"
                                . "The two new passwords did not match. Please ensure they match then try again.</div>";
                        endif;
                    else :
                        echo "<div class='alert-box error' id='pwdchange'><span>error: </span>"
                            . "Fill in all fields.</div>";
                    endif;
                endif;
            //3. User needs to change password (status = chgpwd). Needs to be in DIV for error display
                if ((isset($_SESSION["chgpwd"])) and ($_SESSION["chgpwd"] == true)) :
                    echo "<div class='alert-box notice' id='pwdchange'><span>notice: </span>"
                        . "You must set a new password.</div>";
                    $msg->logMessage(
                        '[NOTICE]',
                        "Enforcing password change for $userEmail from {$_SERVER['REMOTE_ADDR']}"
                    );
                endif;
            //4. Collection view
                $current_coll_view = $row['collection_view'];
                $msg->logMessage('[DEBUG]', "Collection view is '$current_coll_view'");

            //5. Groups
                $current_group_status = $row['grpinout'];
                $current_group = $row['groupid'];
                $msg->logMessage('[DEBUG]', "Groups are '$current_group_status', group id '$current_group'");

            //6. Currency
                $current_currency = $row['currency'];
                $msg->logMessage('[DEBUG]', "Current currency is '$current_currency'");
                $currencySelectEnabled = ($fx === true);
                $msg->logMessage(
                    '[DEBUG]',
                    "Currency selector available: " . ($currencySelectEnabled ? 'enabled' : 'disabled')
                );

            //7. 2FA Section
                // Get 2FA status for this user
                $tfaManager = new \MTG\Auth\TwoFactorManager($db, $smtpParameters, $serverEmail, $logfile);
                $tfa_enabled = $tfaManager->isEnabled($userId);

                // Check if we should enable or disable 2FA
                if (isset($_POST['enable_2fa'])) :
                    $tfa_method = $_POST['tfa_method'] ?? 'email';
                    $enabled = $tfaManager->enable($userId, $tfa_method);
                    if ($enabled) :
                        $tfa_enabled = true;
                        // Set backup codes
                        $backup_codes = $tfaManager->getBackupCodes($userId);
                        // Build the backup codes HTML outside the JavaScript block:
                        $backupHtml = "<span style='font-family: monospace; margin-left: 20px;'><br>";
                        if (!empty($backup_codes)) :
                            foreach ($backup_codes as $code) :
                                $backupHtml .= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . "<br>";
                            endforeach;
                            $backupHtml .= "</span><br><strong>Keep these codes safe and private!</strong></br>";
                        else :
                            $backupHtml .= "Error generating backup codes<br>";
                        endif;
                        if ($tfa_method === "app" && isset($_SESSION['tfa_provisioning_uri'])) :
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
                            // <wbr> allows line breaks without adding spaces
                            $formattedSecretKey = implode('<wbr>', str_split($encodedSecretKey, 16));

                            // Inject JavaScript to update the div dynamically
                            echo "<script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    let qrBox = document.getElementById('qrBox');
                                    let qrInner = qrBox.querySelector('.qr-box-inner');

                                    // Inject QR Code and secret key into the div
                                    qrInner.innerHTML = `
                                        <h2>Two-factor authentication enabled successfully</h2>
                                        <h3>Scan QR Code using your authentication app</h3>
                                        <img src=\"{$qrDataUri}\" alt=\"Scan QR Code to set up 2FA\">
                                        <h3>...or manually enter this code:</h3>
                                        <p class=\"secret-key\" onclick=\"copySecretKey()\">{$formattedSecretKey}</p>
                                        <input
                                            type=\"text\"
                                            id=\"hiddenSecretKey\"
                                            value=\"{$encodedSecretKey}\"
                                            style=\"position:absolute; left:-9999px;\"
                                        >
                                        <h3>Verify your 6-digit code:</h3>
                                        <form id='verify2FAForm' method='post' action='profile.php'>
                                            <input
                                                type='text'
                                                name='tfa_code'
                                                id='tfa_code'
                                                maxlength='6'
                                                pattern='[0-9]{6}'
                                                required
                                                placeholder='Enter 6-digit code'
                                                style='font-size: 18px; text-align: center; width: 120px;'
                                            >
                                            <input type='hidden' name='tfa_secret' value='{$encodedSecretKey}'>
                                            <button
                                                type='submit'
                                                name='verify_2fa'
                                                class='ok-button profilebutton'
                                            >VERIFY</button>
                                        </form>
                                        <br>
                                        <b>Important:</b> The backup codes below can be used if you lose access to your
                                        authentication method. Save them, as you will not get access to them again.<br>
                                        " . $backupHtml . "
                                        <br>
                                        <form method='post' action='profile.php' onsubmit='return'>
                                            <input type='hidden' name='disable_2fa' value='1'>
                                            <input type='hidden' name='setup_cancel' value='1'>
                                            <button type='submit' class='ok-button profilebutton'>CANCEL</button>
                                        </form>
                                    `;

                                    // Show the div
                                    qrBox.style.display = 'block';
                                });
                            </script>";
                        elseif ($tfa_method === "email") :
                            // Inject JavaScript to update the div dynamically
                            echo "<script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    let qrBox = document.getElementById('qrBox');
                                    let qrInner = qrBox.querySelector('.qr-box-inner');

                                    // Inject content into the div
                                    qrInner.innerHTML = `
                                        <h2>Two-factor authentication enabled successfully</h2>
                                        <b>Important:</b> The backup codes below can be used if you lose access to your
                                        authentication method. Save them, as you will not get access to them again.<br>
                                        " . $backupHtml . "
                                        <br>
                                        <button onclick=\"toggleQRBox()\" class=\"ok-button, profilebutton\">OK</button>
                                    `;
                                    // Show the div
                                    qrBox.style.display = 'block';
                                });
                            </script>";
                        endif;
                    else :
                        echo "<div class='alert-box error' id='tfa_message'><span>error: </span>"
                            . "Failed to enable two-factor authentication.</div>";
                    endif;
                elseif (isset($_POST['disable_2fa'])) :
                    $setupCancel = isset($_POST['setup_cancel']) && $_POST['setup_cancel'] === '1';
                    $twofaDisableCode = trim($_POST['twofa_code_disable'] ?? '');
                    $tfa_method = $tfaManager->getMethod($userId);
                    $verifiedForDisable = false;
                    if ($setupCancel || isset($_POST['cancel_disable_2fa'])) :
                        $verifiedForDisable = true; // allow immediate rollback of in-progress setup
                    elseif ($tfa_method === 'email' && $twofaDisableCode === '') :
                        $tfaManager->startVerification($userId, $userEmail);
                        $disableTwofaNotice = "<div class='alert-box notice' id='tfa_message'><span>notice: </span>"
                            . "Enter the verification code emailed to you to disable two-factor authentication.</div>";
                    elseif ($twofaDisableCode === '') :
                        echo "<div class='alert-box notice' id='tfa_message'><span>notice: </span>"
                            . "Enter your authenticator or backup code to disable two-factor authentication.</div>";
                    elseif (!$tfaManager->verify($userId, $twofaDisableCode)) :
                        echo "<div class='alert-box error' id='tfa_message'><span>error: </span>"
                            . "Invalid two-factor code. Please try again.</div>";
                    else :
                        $verifiedForDisable = true;
                    endif;

                    if ($verifiedForDisable) :
                        $disabled = $tfaManager->disable($userId);
                        if ($disabled) :
                            $tfa_enabled = false;
                            echo "<script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    var codeInputs = document.querySelectorAll(
                                        'input[name=\"twofa_code\"], input[name=\"twofa_code_disable\"]'
                                    );
                                    codeInputs.forEach(function(inp) {
                                        inp.style.display = 'none';
                                        var star = inp.parentElement.querySelector('.error2');
                                        if (star) {
                                            star.style.display = 'none';
                                        }
                                    });
                                    var sendButtons = document.querySelectorAll(
                                        'button[name=\"send_twofa_code\"], button[name=\"send_disable_twofa_code\"]'
                                    );
                                    sendButtons.forEach(function(btn) { btn.style.display = 'none'; });
                                });
                            </script>";
                            echo "<div class='alert-box success' id='tfa_message'><span>success: </span>"
                                . "Two-factor authentication disabled successfully.</div>";
                        else :
                            echo "<div class='alert-box error' id='tfa_message'><span>error: </span>"
                                . "Failed to disable two-factor authentication.</div>";
                        endif;
                    endif;
                elseif (isset($_POST['regenerate_backup_codes'])) :
                    $new_codes = $tfaManager->regenerateBackupCodes($userId);
                    $newCodesHtml = "<span style='font-family: monospace; margin-left: 20px;'><br>";
                    if (!empty($new_codes)) :
                        // Build the backup codes HTML outside the JavaScript block:
                        foreach ($new_codes as $new_code) :
                            $newCodesHtml .= htmlspecialchars($new_code, ENT_QUOTES, 'UTF-8') . "<br>";
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
                                    <b>Important:</b>
                                    The codes below can be used if you lose access to your authentication method.
                                    Save them, as you will not get access to them again.<br>
                                    " . $newCodesHtml . "
                                    <br>
                                    <button onclick=\"toggleQRBox()\" class=\"ok-button, profilebutton\">OK</button>
                                `;
                                // Show the div
                                qrBox.style.display = 'block';
                            });
                        </script>";
                    else :
                        echo "<div class='alert-box error' id='tfa_message'><span>error: </span>"
                            . "Failed to regenerate backup codes.</div>";
                    endif;
                elseif (isset($_POST['verify_2fa'])) :
                    $userCode = $_POST['tfa_code'] ?? '';
                    $userSecret = $_POST['tfa_secret'] ?? '';

                    // Verify TOTP code
                    $totp = TOTP::create($userSecret);
                    if ($totp->verify($userCode)) :
                        // Store that 2FA is fully verified
                        $_SESSION['2fa_verified'] = true;
                        echo "<div class='alert-box success'><span>success: </span>"
                            . "Two-factor authentication successfully enabled and verified.</div>";
                    else :
                        // Disable 2FA since the verification failed
                        $tfaManager->disable($userId);
                        echo "<div class='alert-box error'><span>error: </span>"
                            . "Invalid 6-digit code. Two-factor authentication was not enabled.</div>";
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
                                            <input
                                                style="font-size: 16px;"
                                                class="profilepassword textinput"
                                                tabindex="1"
                                                type="password"
                                                name="curPass"
                                                placeholder="CURRENT"
                                            >
                                            <span class="error2">*</span>
                                        </td>
                                        <td rowspan="3">
                                            <input
                                                class="inline_button stdwidthbutton"
                                                tabindex="4"
                                                id="chgpwdsubmit"
                                                type="submit"
                                                value="UPDATE"
                                                name="changePass"
                                            />
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <input
                                                style="font-size: 16px;"
                                                class="profilepassword textinput"
                                                tabindex="2"
                                                type="password"
                                                name="newPass"
                                                placeholder="NEW"
                                            >
                                            <span class="error2">*</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <input
                                                style="font-size: 16px;"
                                                class="profilepassword textinput"
                                                tabindex="3"
                                                type="password"
                                                name="newPass2"
                                                placeholder="REPEAT NEW"
                                            >
                                            <span class="error2">*</span>
                                        </td>
                                    </tr>
                                    <?php if (!empty($userHas2fa)) : ?>
                                    <tr>
                                        <td>
                                            <input
                                                style="font-size: 16px;"
                                                class="profilepassword textinput"
                                                tabindex="4"
                                                type="text"
                                                name="twofa_code"
                                                placeholder="<?php
                                                    echo ($userTwofaMethod === 'app')
                                                        ? 'APP OR BACKUP CODE'
                                                        : 'EMAIL OR BACKUP CODE';
                                                ?>"
                                                autocomplete="one-time-code"
                                            >
                                            <span class="error2">*</span>
                                        </td>
                                        <?php if (!empty($userHas2fa) && $userTwofaMethod === 'email') : ?>
                                        <td>
                                            <button
                                                class="inline_button stdwidthbutton"
                                                type="submit"
                                                name="send_twofa_code"
                                                value="send"
                                            >
                                                SEND
                                            </button>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endif; ?>
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
                if (isset($_COOKIE[$deviceManager->getCookieName()])) :
                    $token = $_COOKIE[$deviceManager->getCookieName()];
                    $currentDeviceHash = $deviceManager->getTokenHash($token);
                endif;
                // Check if we should remove a device
                if (isset($_GET['remove_device']) && is_numeric($_GET['remove_device'])) :
                    $device_id = intval($_GET['remove_device']);
                    $removed = $deviceManager->removeDeviceById($device_id, $userId);
                    if ($removed) :
                        echo "<div class='alert-box success' id='device_message'><span>success: </span>"
                            . "Device removed successfully.</div>";
                    else :
                        echo "<div class='alert-box error' id='device_message'><span>error: </span>"
                            . "Failed to remove device or device not found.</div>";
                    endif;
                elseif (isset($_GET['remove_all_devices']) && $_GET['remove_all_devices'] == 1) :
                    $removed = $deviceManager->removeAllUserDevices($userId);
                    if ($removed) :
                        echo "<div class='alert-box success' id='device_message'><span>success: </span>"
                            . "All trusted devices removed successfully.</div>";
                    else :
                        echo "<div class='alert-box error' id='device_message'><span>error: </span>"
                            . "Failed to remove devices.</div>";
                    endif;
                endif; ?>
                <div id='profilebuttons'>
                    <table class="profile_options"><?php

                    // Display trusted devices
                    $devices = $deviceManager->getUserDevices($userId);
                    if (count($devices) > 0) : ?>
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
                        foreach ($devices as $device) : ?>
                        <tr class="hoverhighlight">
                            <td><?php echo htmlspecialchars($device['device_name'], ENT_QUOTES, 'UTF-8');
                            // If the current device hash matches the device token hash, flag it.
                            if ($currentDeviceHash !== null && $currentDeviceHash === $device['token_hash']) :
                                echo " <strong>(This device)</strong>";
                            endif;?></td>
                            <td>
                                <?php echo $device['last_used']
                                    ? date('Y-m-d H:i', strtotime($device['last_used']))
                                    : 'Never'; ?>
                            </td>
                            <td><?php echo date('Y-m-d H:i', strtotime($device['expires'])); ?></td>
                            <td style="text-align: center;">
                                <a
                                   href="profile.php?remove_device=<?php echo $device['id']; ?>"
                                   onclick="return confirm('Are you sure you want to remove this device?');"
                                   class="profilebutton"
                                   style="padding: 0px 0px; display: inline-block;"
                                >REMOVE</a>
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
                                   onclick="return confirm('Are you sure you want to remove ALL trusted devices? '
                                        + 'You will need to log in again on all devices.');"
                                   class="profilebutton"
                                   style="padding: 0px 0px; display: inline-block;">CLEAR
                                </a>
                                </p>
                            </td>
                        </tr><?php
                    else : ?>
                        <tr>
                            <td colspan="4">
                                <p>You don't have any trusted devices. When you log in, you can choose to trust a device
                                    to stay logged in for up to <?php echo $trustDuration; ?> days.
                                </p>
                            </td>
                        </tr> <?php
                    endif; ?>
                </div> <?php

                if ((!isset($_SESSION["chgpwd"])) or ($_SESSION["chgpwd"] != true)) : ?>
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
                                    var currencySelect = $('#currencySelect');
                                    if (currencySelect.length && !currencySelect.prop('disabled')) {
                                        currencySelect.on('change', function () {
                                            var selectedCurrency = $(this).val();
                                            $.ajax({
                                                url: "/ajax/ajaxcurrency.php",
                                                method: "GET",
                                                data: { "currency": selectedCurrency },
                                                success: function (data) {
                                                    var response = JSON.parse(data);
                                                    console.log(response);
                                                    currencySelect.addClass('flash-success');
                                                    setTimeout(function () {
                                                        currencySelect.removeClass('flash-success');
                                                    }, 1000);
                                                },
                                                error: function (jqXHR, textStatus, errorThrown) {
                                                    console.log("AJAX error: " + textStatus + ' : ' + errorThrown);
                                                }
                                            });
                                        });
                                    }
                                });
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
                                if ($tfa_enabled) :
                                    $tfa_method = $tfaManager->getMethod($userId);?>
                    Two-factor authentication is currently <strong>enabled</strong> using
                    <strong><?php echo htmlspecialchars(ucfirst($tfa_method), ENT_QUOTES, 'UTF-8'); ?></strong>.
                                        <br>Click "CODES" to generate new backup codes.<?php
                                else : ?>
                                        Require a verification code when you log in<?php
                                endif; ?>
                                </td>
                                <td class="options_right"><?php
                                    // Show 2FA status and options
                                if ($tfa_enabled) : ?>
                                        <?php if (!empty($disableTwofaNotice)) :
                                            echo $disableTwofaNotice;
                                        endif; ?>
                                        <?php if (!isset($_POST['disable_2fa'])) : ?>
                                            <form action="profile.php" method="post">
                                                <input
                                                    type="submit"
                                                    name="disable_2fa"
                                                    class="profilebutton"
                                                    value="DISABLE"
                                                    onclick="
                                                        return confirm(
                    'Disabling two-factor authentication will make your account less secure - are you sure?'
                                                        );
                                                    "
                                                />
                                            </form>
                                        <?php else : ?>
                                            <form action="profile.php" method="post" style="margin-top: 8px;">
                                                <input
                                                    style="font-size: 16px; width: 150px; margin-bottom: 6px;"
                                                    class="profilepassword textinput"
                                                    type="text"
                                                    name="twofa_code_disable"
                                                    placeholder="<?php
                                                        echo ($tfaManager->getMethod($userId) === 'app')
                                                            ? 'APP / BACKUP CODE'
                                                            : 'EMAIL / BACKUP CODE';
                                                    ?>"
                                                    autocomplete="one-time-code"
                                                ><br>
                                                <button
                                                    class="profilebutton"
                                                    type="submit"
                                                    name="disable_2fa"
                                                    value="1"
                                                >
                                                    CONFIRM
                                                </button>
                                                <button
                                                    class="profilebutton"
                                                    type="submit"
                                                    name="cancel_disable_2fa"
                                                    value="1"
                                                >
                                                    CANCEL
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <br>
                                        <form action="profile.php" method="post">
                                            <input
                                                type="submit"
                                                name="regenerate_backup_codes"
                                                class="profilebutton"
                                                value="CODES"
                                                onclick="
                                                    return confirm(
            'Are you sure you want to regenerate backup codes? This will invalidate all existing backup codes.'
                                                    );
                                                "
                                            />
                                        </form> <?php
                                else : ?>
                                        <form method="post" action="profile.php">
                                            <select
                                                class="dropdown"
                                                name="tfa_method"
                                                id="tfa_method"
                                                onchange="this.form.submit()"
                                                style="width: 85px;"
                                            >
                                                <option value="disabled" selected>Disabled</option>
                                                <option value="email" <?php if (!$emailEnabled) :
                                                    ?>disabled<?php
                                                                      endif; ?>>
                                                    Email
                                                </option>
                                                <option value="app">App</option>
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
                                    Show cards you do not own in B&W in grid view
                                </td>
                                <td class="options_right"> <?php
                                if ($current_coll_view == 1) : ?>
                                        <label class="switch">
                                            <input
                                                type="checkbox"
                                                id="cview_toggle"
                                                class="option_toggle"
                                                checked="true"
                                                value="on"
                                            />
                                        <div class="slider round"></div>
                                        </label> <?php
                                else : ?>
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
                                    if ($current_group_status == 1) :
                                        echo "<span id='grpname'><b>Group:</b> {$row['groupname']} <br>";
                                        echo "<a href='help.php'>Send me a request</a> "
                                            . "to create a new group</span>&nbsp;";
                                    else :
                                        $hiddenGroup = <<<HTML
<span id='grpname' style='display:none'><b>Group:</b> {$row['groupname']} (
<a href='help.php'>Send me a request</a> to create a new group)</span>&nbsp;
HTML;
                                        echo $hiddenGroup;
                                    endif; ?>
                                </td>
                                <td class="options_right"> <?php
                                if ($current_group_status == 1) : ?>
                                        <label class="switch">
                                            <input
                                                type="checkbox"
                                                id="group_toggle"
                                                class="option_toggle"
                                                checked="true"
                                                value="on"
                                            />
                                        <div class="slider round"></div>
                                        </label> <?php
                                else : ?>
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
                                    Currency to use for localised pricing<?php
                                    if (!$currencySelectEnabled) :
                                        echo " (FX disabled)";
                                    endif; ?>
                                </td>
                                <td class="options_right">
                                    <select
                                        class="dropdown"
                                        name='currency'
                                        id='currencySelect'
                                        style="width: 85px;"
                                        <?php if (!$currencySelectEnabled) :
                                            ?>disabled<?php
                                        endif; ?>
                                    >
                                        <?php foreach ($currencies as $currency) : ?>
                                            <option value='<?php echo $currency['code']; ?>'
                                                <?php if ($current_currency === $currency['db']) :
                                                    ?>selected<?php
                                                endif; ?>>
                                                <?php echo $currency['pretty']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        </table>

                        <?php
                        // Collection management now handled on collection tab
                        ?>
                    </div>
                    <br>&nbsp; <?php
                endif; ?>
            </div>
        </div> <?php
        require('includes/footer.php');
        $msg->logMessage('[DEBUG]', "Finished");?>
    </body>
</html>
