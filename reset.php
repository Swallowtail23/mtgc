<?php

/*
Version:     2.1
Date:        25/11/25
Name:        reset.php
Purpose:     Password reset page, called from login.php.
Notes:       Does not run secpagesetup - not a secure page!
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -

History:
    1.0         Initial version
    2.0 05/09/17 Removed hard-coded email address, now uses ini.php
    2.1 25/11/25 Standard tidy-up
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

$cssver = cssver();

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="initial-scale=1.1, maximum-scale=1.1, minimum-scale=1.1, user-scalable=no"
    >
    <title><?php echo htmlspecialchars($siteTitle);?> - reset</title>
    <link rel="manifest" href="manifest.json" />
    <link rel="stylesheet" type="text/css" href="css/style<?php echo htmlspecialchars($cssver);?>.css">
    <?php include 'includes/googlefonts.php';?>
</head>
<body id="loginbody" class="body">
<div id="loginheader">
    <h2 id="h2"><?php echo htmlspecialchars($siteTitle);?></h2>
<?php
    if (isset($_REQUEST['action'])) :
        $action = $_REQUEST['action'];
        if (isset($_POST['email'])) :
            $email = spamcheck($_POST['email']);
        else :
            $email = false;
        endif;
        if ($email == false) :
            echo "Valid email is required, please fill <a href=\"\">the form</a> again.";
        elseif ($email == 'No match') :
            echo "If the email address exists, your request will be actioned";
            echo "<meta http-equiv='refresh' content='3;url=login.php'>";
        else :
            $from = "From: $email\r\nReturn-path: $email";
            $subject = "Password reset request for $email";
            $message = "Password reset request for $email\r\n$from";
            mail($adminemail, $subject, $message, $from);
            echo "If the email address exists, your request will be actioned";
            echo "<meta http-equiv='refresh' content='3;url=login.php'>";
        endif;
    else :
        ?>
        <form  action="?" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="submit">
            <br>Request password reset:<br><br>
            <?php echo "<input class='textinput loginfield' name='email' type='email' "
                       . "placeholder='EMAIL' size='30'/><br>"; ?>
            <input class='sendreset' type="submit" value="SEND"/>
        </form>
        <?php
    endif;
    ?>
</div>
</body>
</html>
