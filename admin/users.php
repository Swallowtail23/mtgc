<?php

/*
Version:     6.13
Date:        11/01/26
Name:        users.php
Purpose:     User administrative tasks
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Auth\PasswordCheck;

// Bootstrap
$appContext = require '../bootstrap_secure.php';

// Content
$msg->logMessage('[DEBUG]', 'users.php loaded; initialising admin user management page');
function shouldRequirePasswordForNewUser($emailEnabled)
{
    return $emailEnabled === false;
}

//Check if user is logged in, if not redirect to login.php
$msg->logMessage('[ERROR]', "Admin page called by user $userName ($userEmail)");
// Is admin running the page
$msg->logMessage('[ERROR]', "Admin is $admin");
$msg->logMessage('[DEBUG]', 'Validating admin access for user session');
if ($admin !== 1) :
    $msg->logMessage('[DEBUG]', 'User is not admin; redirecting to reject.php');
    require('reject.php');
endif;

$requirePassword = shouldRequirePasswordForNewUser($emailEnabled);
$msg->logMessage(
    '[DEBUG]',
    'New user creation requires password: ' . ($requirePassword === true ? 'yes' : 'no')
);


if (isset($_POST['newuser'])) :
    $msg->logMessage('[DEBUG]', 'New user form submission detected');
    $newuser = ($_POST['newuser'] == 'yes') ? 'yes' : '';
    if (isset($_POST['password'])) :
        $msg->logMessage('[DEBUG]', 'New user password supplied in request');
        $password = $_POST['password'];
    endif;
    if (isset($_POST['email'])) :
        $postemail_raw = $_POST['email'];
        $postemail = htmlspecialchars($postemail_raw, ENT_QUOTES, 'UTF-8');
        $msg->logMessage('[DEBUG]', "New user email captured for $postemail");
    endif;
    if (isset($_POST['username'])) :
        $username_raw = $_POST['username'];
        $userName = htmlspecialchars($username_raw, ENT_QUOTES, 'UTF-8');
        $msg->logMessage('[DEBUG]', "New user username captured for $userName");
    endif;
endif;
if (isset($_POST['updateusers'])) :
    $msg->logMessage('[DEBUG]', 'User table update request detected');
    $updateusers = ($_POST['updateusers'] == 'yes') ? 'yes' : '';
    $updatearray[] = filter_input_array(INPUT_POST);
endif;
$siteTitleEsc = htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8');
?>

<!DOCTYPE html>
<head>
    <title><?php echo $siteTitleEsc;?> - admin (users)</title>
    <link rel="manifest" href="/manifest.json" />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="/css/style<?php echo $cssver?>.css">
    <?php include('../includes/googlefonts.php');?>
    <script src="../js/jquery.js"></script>
    <script type="text/javascript">
    jQuery(function($) {
        const requirePassword = <?php echo $requirePassword ? 'true' : 'false'; ?>;
        $('#newuserform').on('submit', function(event) {
            const username = $('#username').val().trim();
            const email    = $('#email').val().trim();
            const password = $('#pword').length ? $('#pword').val().trim() : '';
            // Determine which field is missing
            let missingField = null;
            if (username === '') {
                missingField = '#username';
            } else if (email === '') {
                missingField = '#email';
            } else if (requirePassword && password === '') {
                missingField = '#pword';
            }
            if (missingField !== null) {
                event.preventDefault();
                alert("You need to complete all required fields");
                $(missingField).focus();
                return false;
            }
            // Allow form to submit normally
            return true;
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
        <?php
        // Generate new account or do password reset
        if ((isset($newuser)) and ($newuser === "yes")) :
            $msg->logMessage('[DEBUG]', 'Entering new user creation flow');
            $password = isset($password) ? trim($password) : '';
            if ($requirePassword && $password === '') :
                $msg->logMessage('[DEBUG]', 'Password required for new user but not supplied');
                echo "<div class='alert-box error'><span>error: </span>Email is disabled; you must supply a "
                     . "temporary password.</div>";
            else :
                $obj = new PasswordCheck($db, $appConfig);
                $msg->logMessage(
                    '[DEBUG]',
                    "Attempting to create user $username_raw with email $postemail_raw"
                );
                $newuserstatus = $obj->newUser(
                    $username_raw,
                    $postemail_raw,
                    $password,
                    $dbname
                ); // Use "_raw" variables as newuser() uses parameterised query, so no need to quote
                if ($newuserstatus === 2) :
                    $msg->logMessage('[DEBUG]', 'New user created with collection table initialised');
                    echo "<div class='alert-box success'><span>success: </span>User $userName / $postemail created, "
                         . "password successfully recorded and checked.</div>";
                    echo "<div class='alert-box success'><span>success: </span>Writing table successful.</div>";
                elseif ($newuserstatus === 1) :
                    $msg->logMessage('[DEBUG]', 'New user created; collection table already existed');
                    echo "<div class='alert-box success'><span>success: </span>User $userName / $postemail password "
                     . "successfully recorded and checked.</div>";
                    echo "<div class='alert-box notice'><span>notice: </span>No new collection table created, "
                     . "already exists for this user.</div>";
                elseif ($newuserstatus === 6) :
                    $msg->logMessage('[DEBUG]', 'New user creation failed: email validation error');
                    echo "<div class='alert-box error'><span>error: </span>Email address validation failed.</div>";
                else :
                    $msg->logMessage('[DEBUG]', 'New user creation failed with unknown status');
                    echo "<div class='alert-box error'><span>error: </span>Something went wrong. Check logs.</div>";
                endif;
            endif;
        endif;

        // Multiple user form update
        if ((isset($updateusers)) and ($updateusers === "yes")) :
            $msg->logMessage('[DEBUG]', 'Entering user update flow');
            $resetResults = [];
            foreach ($updatearray[0]['id'] as $i => $id) :
                $msg->logMessage('[DEBUG]', "Processing update for user id $id");
                $sql_id = (int)$updatearray[0]['id'][$i];
                ${'sqlid' . $id} = $sql_id;
                $sql_eml = trim($updatearray[0]['eml'][$i]);
                ${'sqleml' . $id} = $sql_eml;
                $sql_name = trim($updatearray[0]['name'][$i]);
                ${'sqlname' . $id} = $sql_name;
                $sql_status = trim($updatearray[0]['status'][$i]);
                ${'sqlstatus' . $id} = $sql_status;
                $sql_fx = trim($updatearray[0]['currency'][$i]);
                if ($sql_fx === 'zzz') :
                    $msg->logMessage('[DEBUG]', "User $id currency set to default via placeholder");
                    $sql_fx = null;
                elseif (!in_array($sql_fx, array_column($currencies, 'code'))) :
                    $msg->logMessage('[DEBUG]', "User $id currency not recognised; clearing to default");
                    $sql_fx = null;
                endif;
                ${'sqlfx' . $id} = $sql_fx;
                $sql_adm = (int)$updatearray[0]['adm'][$i];
                ${'sqladm' . $id} = $sql_adm;
                //Simple update of fields
                $msg->logMessage('[DEBUG]', "Updating user record for $sql_name ($sql_id)");
                $query = "UPDATE users
                          SET username = ?, email = ?, status = ?, admin = ?, currency = ?
                          WHERE usernumber = ?";
                $params = [$sql_name, $sql_eml, $sql_status, $sql_adm, $sql_fx, $sql_id];
                if ($result = $db->execute_query($query, $params)) :
                    $affected_rows = $db->affected_rows;
                    $msg->logMessage(
                        '[ERROR]',
                        "Update user query by $userEmail from {$_SERVER['REMOTE_ADDR']} affected $affected_rows rows"
                    );
                else :
                    $msg->logMessage('[ERROR]', "Update user query unsuccessful");
                endif;
                $usertable = $sql_id . "collection";
                // More complex updates
                // - delete card collection for a user
                if (($updatearray[0]['actions'][$i]) == 'deletecards') :
                    $msg->logMessage('[DEBUG]', "Clearing collection for $sql_name from {$_SERVER['REMOTE_ADDR']}");
                    if ($db->execute_query("DELETE FROM $usertable")) :
                        if ($deletecards = $db->execute_query("SELECT * FROM $usertable")) :
                            if ($deletecards->num_rows == 0) :
                                echo "<div class='alert-box success'>"
                                     . "<span>success: </span>Cards cleared for $sql_name</div>";
                                $msg->logMessage('[ERROR]', "Table empty successful");
                            else :
                                echo "<div class='alert-box error'>"
                                     . "<span>error: </span>Cards not cleared for $sql_name</div>";
                                $msg->logMessage('[ERROR]', "Table empty failed");
                            endif;
                        endif;
                    endif;
                // - delete user and collection
                elseif (($updatearray[0]['actions'][$i]) == 'deleteuser') :
                    $msg->logMessage('[DEBUG]', "Nuking $sql_name from {$_SERVER['REMOTE_ADDR']}");
                    if ($db->execute_query("DELETE FROM users WHERE usernumber = ?", [$sql_id])) :
                        if (
                            $nukeuser = $db->execute_query(
                                "SELECT username FROM users WHERE usernumber = ?",
                                [$sql_id]
                            )
                        ) :
                            if ($nukeuser->num_rows == 0) :
                                echo "<div class='alert-box success'><span>success: "
                                     . "</span>User $sql_name removed</div>";
                                $msg->logMessage('[ERROR]', "User deletion successful");
                            else :
                                echo "<div class='alert-box error'><span>error: "
                                     . "</span>User $sql_name not removed</div>";
                                $msg->logMessage('[ERROR]', "User deletion failed");
                            endif;
                        endif;
                    endif;
                            $sqldrop = "DROP TABLE $usertable";
                            $msg->logMessage('[ERROR]', "Running $sqldrop");
                            $db->query($sqldrop);
                            $queryexists = "SHOW TABLES LIKE '$usertable'";
                            $stmt = $db->prepare($queryexists);
                            $msg->logMessage('[ERROR]', "Checking if collection table still exists: $queryexists");
                            $exec = $stmt->execute();
                    if ($exec === false) :
                        $msg->logMessage('[ERROR]', "Collection table check failed");
                    else :
                                $stmt->store_result();
                                $collection_exists = $stmt->num_rows;
                                   //$collection_exists now has qty of tables with collection name
                                $stmt->close();
                                $msg->logMessage('[ERROR]', "Collection table check returned $collection_exists rows");
                        if ($collection_exists === 0) : //No existing collection table
                            echo "<div class='alert-box success'><span>success: "
                                 . "</span>Table dropped for $sql_name</div>";
                            $msg->logMessage('[ERROR]', "Collection table check shows 0");
                        elseif ($collection_exists == -1) :
                                    $msg->logMessage('[ERROR]', "Shouldn't be here...");
                        else : // There is still a table with this name
                                    echo "<div class='alert-box error'><span>error: "
                                         . "</span>Table not dropped for $sql_name</div>";
                                $msg->logMessage('[ERROR]', "Table still exists");
                        endif;
                    endif;
                elseif (($updatearray[0]['actions'][$i]) == 'resetpassword') :
                    $msg->logMessage('[DEBUG]', "Password reset requested for $sql_name ($sql_id)");
                    $msg->logMessage(
                        '[ERROR]',
                        "Reset password call for $sql_id/$sql_name/$sql_eml from {$_SERVER['REMOTE_ADDR']}"
                    );
                    if ($emailEnabled) :
                        $obj = new PasswordCheck($db, $appConfig);
                        $sent = $obj->requestResetToken($sql_eml, true);
                        if ($sent) :
                            echo "<div class='alert-box success'><span>success: </span>Password reset link sent"
                                 . "</div>";
                            $resetResults[$sql_id] = true;
                            $msg->logMessage('[DEBUG]', "Password reset email sent for $sql_name ($sql_id)");
                        else :
                            echo "<div class='alert-box error'><span>error: </span>Failed to send reset link.</div>";
                            $resetResults[$sql_id] = false;
                            $msg->logMessage('[DEBUG]', "Password reset email failed for $sql_name ($sql_id)");
                        endif;
                    else :
                                echo "<div class='alert-box notice'>"
                                     . "<span>notice: </span>Email is disabled; reset links cannot "
                                     . "be sent.</div>";
                                $resetResults[$sql_id] = false;
                                $msg->logMessage('[DEBUG]', "Email disabled; cannot send reset link to $sql_name");
                    endif;
                elseif (($updatearray[0]['actions'][$i]) == 'disable2fa') :
                    $msg->logMessage('[DEBUG]', "Disabling 2FA for $sql_name ($sql_id)");
                    $disable2fa = $db->execute_query(
                        "UPDATE users SET tfa_enabled = 0, tfa_method = NULL, tfa_backup_codes = NULL, "
                        . "tfa_app_secret = NULL, status = 'chgpwd' WHERE usernumber = ?",
                        [$sql_id]
                    );
                    if ($disable2fa) :
                        echo "<div class='alert-box notice'><span>notice: </span>2FA disabled for $sql_name. "
                             . "Password change required on next login.</div>";
                        $msg->logMessage('[NOTICE]', "2FA disabled for $sql_name ($sql_id)");
                        $resetResults[$sql_id] = '2fa_disabled';
                    else :
                        echo "<div class='alert-box error'><span>error: </span>Failed to disable 2FA for "
                             . "$sql_name.</div>";
                        $msg->logMessage('[ERROR]', "Disable 2FA failed for $sql_name ($sql_id)");
                    endif;
                else :
                    $msg->logMessage('[DEBUG]', "No complex action selected for $sql_name ($sql_id)");
                endif;
            endforeach;
        else :
            $updateusers = '';
        endif;?>
        <form id='newuserform' name="newuser" action="users.php" method="post" autocomplete="user-form">
            <h3> New user </h3>
            <?php if ($emailEnabled) : ?>
                Email is enabled. New users will receive a reset link and set their own passwords via email.<br>
            <?php else : ?>
                <b>Email is disabled.</b> Enter a temporary password below for new or existing users. They will be
                forced to change it on next login.<br>
            <?php endif; ?>
            <input type='hidden' name="newuser" value="yes">
            <input
                class="textinput"
                title="Please enter username"
                placeholder="Username"
                id="username"
                autocomplete="off"
                name="username"
                type="text"
                size="12" maxlength="12"
            >
            <br>
            <input
                class="textinput"
                title="Email address"
                placeholder="Email"
                id="email"
                autocomplete="user-email-for-form"
                name="email"
                type="email"
                size="64"
                maxlength="64"
            >
            <br>
            <?php if (!$emailEnabled) : ?>
            <input
                class="textinput"
                type="password"
                id='pword'
                title="Enter a temporary password"
                placeholder="Temporary password"
                size="20"
                autocomplete="user-password-for-form"
                name="password"
                maxlength="64"
            >
            <br><br>
            <?php endif; ?>
            <input class="profilebutton" type="submit" value="ADD USER" />
        </form>

        <div>
            <h3>User table</h3>
            Note, default currency is set in ini file ([fx], TargetCurrency)
            <?php
            $allusertable = $db->execute_query(
                "SELECT username, usernumber, email, badlogins, reg_date, lastlogin_date, status, admin, currency, "
                . "tfa_enabled, tfa_method "
                . "FROM users"
            );
            ?>
            <form name="updateusers" action="users.php" method="post">
                <table>
                    <tr>
                        <th style="padding: 5px;">User #</th>
                        <th style="padding: 5px;">Registered</th>
                        <th style="padding: 5px;">Last login</th>
                        <th style="padding: 5px;">Username</th>
                        <th style="padding: 5px;">Email</th>
                        <th style="padding: 5px;">Status</th>
                        <th style="padding: 5px;">Bad logins</th>
                        <th style="padding: 5px;">Local FX</th>
                        <th style="padding: 5px;">Admin</th>
                        <th style="padding: 5px;">2FA</th>
                        <?php if ($updateusers === 'yes') : ?>
                        <th style="padding: 5px;"></th>
                        <?php endif; ?>
                        <th style="padding: 5px;">Actions</th>
                    </tr>
                    <?php
                    while ($alluserresults = $allusertable->fetch_assoc()) :
                        $usertable = $alluserresults['usernumber'] . "collection";
                        ?>
                        <tr>
                            <td style="padding: 5px;">
                                <?php echo $alluserresults['usernumber']; ?>
                                <input type='hidden' name=id[] value='<?php echo $alluserresults['usernumber']; ?>'>
                            </td>
                            <td style="padding: 5px;">
                                <?php echo $alluserresults['reg_date']; ?>
                            </td>
                            <td style="padding: 5px;">
                                <?php echo $alluserresults['lastlogin_date']; ?>
                            </td>
                            <td style="padding: 5px;">
                                <input
                                    class="textinput"
                                    type='text'
                                    size='10'
                                    name=name[]
                                    value='<?php echo $alluserresults['username']; ?>'
                                >
                            </td>
                            <td style="padding: 5px;">
                                <input
                                    class="textinput"
                                    type='email'
                                    size='30'
                                    name=eml[]
                                    value='<?php echo $alluserresults['email']; ?>'
                                >
                            </td>
                            <td style="padding: 5px;">
                                <select class="dropdown" name='status[]'>
                                    <option value='active' <?php if ($alluserresults['status'] === 'active') :
                                        echo "selected";
                                                           endif; ?> >active</option>
                                    <option value='disabled'  <?php if ($alluserresults['status'] === 'disabled') :
                                        echo "selected";
                                                              endif; ?> >disabled</option>
                                    <option value='locked' <?php if ($alluserresults['status'] === 'locked') :
                                        echo "selected";
                                                           endif; ?> >locked</option>
                                    <option value='chgpwd' <?php if ($alluserresults['status'] === 'chgpwd') :
                                        echo "selected";
                                                           endif; ?> >password change required</option>
                                    <option value='mtce' <?php if ($alluserresults['status'] === 'mtce') :
                                        echo "selected";
                                                         endif; ?> >site maintenance</option>
                                </select>
                            </td>
                            <td style="padding: 5px;">
                                <?php echo $alluserresults['badlogins']; ?>
                            </td>
                            <td style="padding: 5px;">
                                <select class="dropdown" name='currency[]'>
                                    <?php foreach ($currencies as $currency) : ?>
                                        <option value='<?php echo $currency['code']; ?>'
                                            <?php if ($alluserresults['currency'] === $currency['db']) :
                                                ?>selected<?php
                                            endif; ?>>
                                            <?php echo $currency['pretty']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td style="padding: 5px;">
                                <select class="dropdown" name='adm[]'>
                                    <option value=1 <?php if ($alluserresults['admin'] == 1) :
                                        echo "selected";
                                                    endif; ?> >Yes</option>
                                    <option value=0  <?php if ($alluserresults['admin'] == 0) :
                                        echo "selected";
                                                     endif; ?> >No</option>
                                </select>
                            </td>
                            <td style="padding: 5px;">
                                <?php
                                if ((int)$alluserresults['tfa_enabled'] !== 1) :
                                    echo "Off";
                                elseif ($alluserresults['tfa_method'] === 'app') :
                                    echo "App";
                                else :
                                    echo "Email";
                                endif;
                                ?>
                            </td>

                            <?php if ($updateusers === 'yes') : ?>
                            <td style="padding: 5px;">
                                <?php
                                $aur_usernumber = $alluserresults['usernumber'];
                                $updatesql = $db->execute_query(
                                    "SELECT username, email, status, admin
                                     FROM users
                                     WHERE usernumber = ?
                                     LIMIT 1",
                                    [$aur_usernumber]
                                );
                                $updateoutcome = $updatesql->fetch_assoc();
                                $updatesMatched =
                                    ((string)$updateoutcome['username']
                                        === (string)${'sqlname' . $alluserresults['usernumber']})
                                    &&
                                    ((string)$updateoutcome['email']
                                        === (string)${'sqleml' . $alluserresults['usernumber']})
                                    &&
                                    ((string)$updateoutcome['status']
                                        === (string)${'sqlstatus' . $alluserresults['usernumber']})
                                    &&
                                    ((string)$updateoutcome['admin']
                                        === (string)${'sqladm' . $alluserresults['usernumber']});
                                $resetSuccess = isset($resetResults[$aur_usernumber])
                                    && $resetResults[$aur_usernumber] === true;
                                $twofaDisabled = isset($resetResults[$aur_usernumber])
                                    && $resetResults[$aur_usernumber] === '2fa_disabled';
                                if ($updatesMatched || $resetSuccess || $twofaDisabled) : ?>
                                    <img src='/images/success.png' alt='Success'>
                                <?php else : ?>
                                    <img src='/images/error.png' alt='Failure'>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                            <td style="padding: 5px;">
                                <select class="dropdown" name='actions[]'>
                                    <option value='' selected></option>
                                    <option value=deletecards>Delete collection</option>
                                    <option value=deleteuser>Delete user & cards</option>
                                    <option value="resetpassword"
                                        <?php
                                        if (!$emailEnabled) :
                                            echo 'disabled';
                                        endif;
                                        ?>
                                    >Send reset link</option>
                                    <option value="disable2fa">Disable 2FA</option>
                                </select>
                            </td>
                        </tr>
                        <?php
                    endwhile; ?>
                </table>
                <input type='hidden' name="updateusers" value="yes">
                <br>
                <input class="profilebutton" type="submit" value="UPDATE" />
            </form>
            <form id='exportcsv' action="/csv.php"  method="GET">
            </form>
            <h4>Export</h4>
            Export specific user's collection to a .csv file.
            <form action="/csv.php"  method="GET">
                <select class="dropdown" name='table'>
                <?php
                $exportlist = $db->execute_query("SELECT usernumber,username FROM users");
                while ($listuser = $exportlist->fetch_assoc()) :
                    $userno = $listuser['usernumber'];
                    $userid = $listuser['username'];
                    echo "<option value='{$userno}collection'>$userid</option>";
                endwhile;
                ?>
                </select>
                <br><br>
                <input type='hidden' name='type' value='echo'>
                <input class="profilebutton" type="submit" value="EXPORT">
            </form>
        </div>
    </div>
</div>

<?php require('../includes/footer.php'); ?>
</body>
</html>
