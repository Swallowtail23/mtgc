<?php 
/* Version:     2.0
    Date:       05/09/17
    Name:       reset.php
    Purpose:    Password reset page, called from login.php
    Notes:      Does not run secpagesetup - not a secure page!
    To do:      -
 *     
    1.0
                Initial version
 *  2.0 
 *              Removed hard-coded email address, now uses ini.php
*/
ini_set('session.name', '5VDSjp7k-n-_yS-_');
session_start();
require ('includes/ini.php');               //Initialise and load ini file
require ('includes/error_handling.php');
require ('includes/functions.php');     //Includes basic functions for non-secure pages
// Find CSS Version
$cssver = cssver();

?>
<!DOCTYPE html>
<head>
<title> MTG collection </title>
<link rel="stylesheet" type="text/css" href="css/style<?php echo $cssver?>.css">
<?php include('includes/googlefonts.php');?>
<meta name="viewport" content="initial-scale=1.1, maximum-scale=1.1, minimum-scale=1.1, user-scalable=no">
</head>
<body id="loginbody" class="body">
<div id="loginheader">    
    <h2 id='h2'> MtG collection</h2>
<?php 
    if(isset($_REQUEST['action'])):
        $action=$_REQUEST['action']; 
        if(isset($_POST['email'])):
            $email = spamcheck($_POST['email']);
        else:
            $email = FALSE;
        endif;
        if ($email == FALSE):
            echo "Valid email is required, please fill <a href=\"\">the form</a> again."; 
        elseif ($email == 'No match'):
            // Not a valid email, don't send an email
            echo "If the email address exists, your request will be actioned"; 
            echo "<meta http-equiv='refresh' content='3;url=login.php'>";
        else:
            $from = "From: $email\r\nReturn-path: $email"; 
            $subject = "Password reset request for $email"; 
            $message = "Password reset request for $email\r\n$from";
            mail($adminemail, $subject, $message, $from); 
            echo "If the email address exists, your request will be actioned"; 
            echo "<meta http-equiv='refresh' content='3;url=login.php'>";
        endif;
    else:
        ?> 
        <form  action="?" method="POST" enctype="multipart/form-data"> 
            <input type="hidden" name="action" value="submit"> 
            <br>Request password reset:<br><br>
            <?php echo "<input class='textinput loginfield' name='email' type='email' placeholder='EMAIL' size='30'/><br>"; ?> 
            <input class='sendreset' type="submit" value="SEND"/> 
        </form>
        <?php
    endif;
    ?>
</div>
</body>
</html>
